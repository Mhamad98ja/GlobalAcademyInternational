<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Grade;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ImportStudentList extends Command
{
    protected $signature = 'students:import-list
        {path : Absolute or relative path to the .xlsx student list}
        {--year=2024-2025 : Academic year for the imported registrations}
        {--registered-on=2024-09-01 : Registration date to record}
        {--branch=MAIN : Branch code that owns the imported students}
        {--status=completed : Registration status to assign}
        {--dry-run : Validate the workbook without changing the database}';

    protected $description = 'Import student names, grades, and divisions from an Excel roster without using G.R. numbers.';

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('The PHP zip extension is required to read .xlsx files.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("Workbook not found: {$path}");

            return self::FAILURE;
        }

        $registrationStatus = (string) $this->option('status');
        if (! in_array($registrationStatus, ['active', 'inactive', 'withdrawn', 'completed'], true)) {
            $this->error('The --status option must be active, inactive, withdrawn, or completed.');

            return self::FAILURE;
        }

        try {
            $roster = $this->readRoster($path);
            $this->assertAcademicStructureExists($roster['rows']);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Read %d worksheets: %d student rows, %d duplicate rows skipped.',
            $roster['sheet_count'],
            count($roster['rows']),
            $roster['duplicate_count'],
        ));
        $this->table(['Grade', 'Section', 'Students'], $this->summaryRows($roster['rows']));

        if ($this->option('dry-run')) {
            $this->comment('Dry run complete. No academic year, student, or registration was changed.');

            return self::SUCCESS;
        }

        $branch = Branch::query()->where('code', (string) $this->option('branch'))->first();
        if ($branch === null) {
            $this->error("Branch '{$this->option('branch')}' was not found.");

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($roster, $branch, $registrationStatus): array {
                $year = $this->findOrCreateAcademicYear((string) $this->option('year'));
                $createdBy = User::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_controller_administrator')
                    ->orderBy('id')
                    ->value('id');
                $structure = $this->academicStructure();
                $createdStudents = 0;
                $createdRegistrations = 0;
                $existingRegistrations = 0;

                foreach ($roster['rows'] as $position => $row) {
                    [$firstName, $middleName, $lastName] = $this->splitName($row['name']);
                    $studentNumber = sprintf(
                        'IMP-%s-%04d',
                        $this->importYearCode((string) $this->option('year')),
                        $position + 1,
                    );
                    $student = Student::withoutGlobalScopes()
                        ->where('student_number', $studentNumber)
                        ->first();

                    if ($student === null) {
                        $student = Student::withoutGlobalScopes()->create([
                            'branch_id' => $branch->id,
                            'student_number' => $studentNumber,
                            'first_name' => $firstName,
                            'middle_name' => $middleName,
                            'last_name' => $lastName,
                            'enrollment_date' => (string) $this->option('registered-on'),
                            'notes' => 'Imported from student roster. G.R. number intentionally not imported.',
                        ]);
                        $createdStudents++;
                    }

                    $registration = StudentRegistration::withoutGlobalScopes()
                        ->where('student_id', $student->id)
                        ->where('academic_year_id', $year->id)
                        ->first();

                    if ($registration !== null) {
                        $existingRegistrations++;

                        continue;
                    }

                    StudentRegistration::withoutGlobalScopes()->create([
                        'branch_id' => $branch->id,
                        'student_id' => $student->id,
                        'academic_year_id' => $year->id,
                        'grade_id' => $structure[$row['grade']]['grade_id'],
                        'section_id' => $structure[$row['grade']]['sections'][$row['section']],
                        'status' => $registrationStatus,
                        'registered_on' => (string) $this->option('registered-on'),
                        'created_by' => $createdBy,
                        'notes' => 'Imported from student roster. G.R. number intentionally not imported.',
                    ]);
                    $createdRegistrations++;
                }

                return compact('year', 'createdStudents', 'createdRegistrations', 'existingRegistrations');
            });
        } catch (\Throwable $exception) {
            report($exception);
            $this->error("Import failed and no rows were saved: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Imported into {$result['year']->name}: {$result['createdStudents']} students and {$result['createdRegistrations']} registrations.");
        if ($result['existingRegistrations'] > 0) {
            $this->comment("Skipped {$result['existingRegistrations']} registrations that were already imported.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{sheet_count: int, rows: list<array{name: string, grade: string, section: string}>, duplicate_count: int}
     */
    private function readRoster(string $path): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The selected file is not a readable Excel .xlsx workbook.');
        }

        try {
            $sharedStrings = $this->sharedStrings($archive);
            $relationships = $this->workbookRelationships($archive);
            $workbook = $this->xml($archive, 'xl/workbook.xml');
            $namespace = $this->namespace($workbook);
            $workbook->registerXPathNamespace('x', $namespace);
            $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheets = $workbook->xpath('//x:sheets/x:sheet') ?: [];

            $rows = [];
            $seen = [];
            $duplicates = 0;
            foreach ($sheets as $sheet) {
                $relationId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $relationships[$relationId] ?? null;
                if ($target === null) {
                    throw new RuntimeException("Could not locate worksheet '{$sheet['name']}'.");
                }

                foreach ($this->worksheetRows($archive, $this->worksheetPath($target), $sharedStrings) as $row) {
                    if ($row['name'] === '') {
                        continue;
                    }

                    $grade = $this->normaliseGrade($row['grade']);
                    $section = strtoupper($this->normalise($row['section']));
                    if ($grade === null || ! preg_match('/^[A-Z]$/', $section)) {
                        throw new RuntimeException("Invalid grade or division in worksheet '{$sheet['name']}', row {$row['row']}.");
                    }

                    $key = mb_strtoupper($row['name'])."|{$grade}|{$section}";
                    if (isset($seen[$key])) {
                        $duplicates++;

                        continue;
                    }

                    $seen[$key] = true;
                    $rows[] = ['name' => $row['name'], 'grade' => $grade, 'section' => $section];
                }
            }
        } finally {
            $archive->close();
        }

        if ($rows === []) {
            throw new RuntimeException('No student rows were found in the workbook.');
        }

        return ['sheet_count' => count($sheets), 'rows' => $rows, 'duplicate_count' => $duplicates];
    }

    /** @return list<array{name: string, grade: string, section: string, row: int}> */
    private function worksheetRows(ZipArchive $archive, string $path, array $sharedStrings): array
    {
        $worksheet = $this->xml($archive, $path);
        $namespace = $this->namespace($worksheet);
        $worksheet->registerXPathNamespace('x', $namespace);
        $header = [];
        $rows = [];

        foreach ($worksheet->xpath('//x:sheetData/x:row') ?: [] as $sheetRow) {
            $values = [];
            foreach ($sheetRow->children($namespace) as $cell) {
                if ($cell->getName() !== 'c') {
                    continue;
                }
                $reference = (string) ($cell->attributes()['r'] ?? '');
                $values[$this->columnIndex($reference)] = $this->cellValue($cell, $namespace, $sharedStrings);
            }

            if ($header === []) {
                foreach ($values as $column => $value) {
                    $header[$this->normaliseHeader($value)] = $column;
                }

                if (isset($header['STUDENTFULLNAME'], $header['GRADE'], $header['DIVISION'])) {
                    continue;
                }
            }

            if (! isset($header['STUDENTFULLNAME'], $header['GRADE'], $header['DIVISION'])) {
                throw new RuntimeException("Worksheet '{$path}' is missing Student Full Name, Grade, or Division headings.");
            }

            $rows[] = [
                'name' => $this->normalise($values[$header['STUDENTFULLNAME']] ?? ''),
                'grade' => $this->normalise($values[$header['GRADE']] ?? ''),
                'section' => $this->normalise($values[$header['DIVISION']] ?? ''),
                'row' => (int) $sheetRow['r'],
            ];
        }

        return $rows;
    }

    /** @return array<string, string> */
    private function workbookRelationships(ZipArchive $archive): array
    {
        $relationships = $this->xml($archive, 'xl/_rels/workbook.xml.rels');
        $namespace = $this->namespace($relationships);
        $relationships->registerXPathNamespace('x', $namespace);
        $result = [];

        foreach ($relationships->xpath('//x:Relationship') ?: [] as $relationship) {
            $result[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        return $result;
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $archive): array
    {
        if ($archive->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $sharedStrings = $this->xml($archive, 'xl/sharedStrings.xml');
        $namespace = $this->namespace($sharedStrings);
        $sharedStrings->registerXPathNamespace('x', $namespace);
        $strings = [];

        foreach ($sharedStrings->xpath('//x:si') ?: [] as $item) {
            $strings[] = html_entity_decode(strip_tags($item->asXML() ?: ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $strings;
    }

    private function cellValue(\SimpleXMLElement $cell, string $namespace, array $sharedStrings): string
    {
        $type = (string) ($cell->attributes()['t'] ?? '');
        $children = $cell->children($namespace);

        if ($type === 's') {
            return $sharedStrings[(int) $children->v] ?? '';
        }

        if ($type === 'inlineStr') {
            return html_entity_decode(strip_tags($children->is?->asXML() ?: ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return (string) $children->v;
    }

    private function xml(ZipArchive $archive, string $path): \SimpleXMLElement
    {
        $contents = $archive->getFromName($path);
        if ($contents === false) {
            throw new RuntimeException("Excel workbook is missing {$path}.");
        }

        $xml = simplexml_load_string($contents);
        if ($xml === false) {
            throw new RuntimeException("Excel workbook contains invalid XML at {$path}.");
        }

        return $xml;
    }

    private function namespace(\SimpleXMLElement $xml): string
    {
        $namespaces = $xml->getNamespaces(true);

        return $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    }

    private function worksheetPath(string $target): string
    {
        return str_starts_with($target, '/')
            ? ltrim($target, '/')
            : 'xl/'.ltrim($target, '/');
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/[A-Z]+/', $reference, $matches);
        $index = 0;

        foreach (str_split($matches[0] ?? '') as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index;
    }

    private function normaliseHeader(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($this->normalise($value))) ?? '';
    }

    private function normalise(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', trim($value));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function normaliseGrade(string $grade): ?string
    {
        $grade = strtoupper($this->normalise($grade));
        if ($grade === 'PRESCHOOL') {
            return 'Preschool';
        }
        if (preg_match('/^KG\s*([12])$/', $grade, $matches)) {
            return "KG{$matches[1]}";
        }
        if (preg_match('/^(?:GRADE\s*)?([1-9]|1[0-2])$/', $grade, $matches)) {
            return "Grade {$matches[1]}";
        }

        return null;
    }

    /** @param list<array{name: string, grade: string, section: string}> $rows */
    private function assertAcademicStructureExists(array $rows): void
    {
        $structure = $this->academicStructure();

        foreach ($rows as $row) {
            if (! isset($structure[$row['grade']]['sections'][$row['section']])) {
                throw new RuntimeException("Academic structure is missing {$row['grade']} / {$row['section']}.");
            }
        }
    }

    /** @return array<string, array{grade_id: int, sections: array<string, int>}> */
    private function academicStructure(): array
    {
        $levels = SchoolLevel::query()->whereIn('name', ['Preschool', 'School'])->get()->keyBy('name');
        if (! isset($levels['Preschool'], $levels['School'])) {
            throw new RuntimeException('The Preschool and School academic levels must exist before importing.');
        }

        $gradeNames = ['Preschool', 'KG1', 'KG2'];
        for ($number = 1; $number <= 12; $number++) {
            $gradeNames[] = "Grade {$number}";
        }

        $grades = Grade::query()
            ->with('sections')
            ->whereIn('name', $gradeNames)
            ->get()
            ->keyBy('name');
        $structure = [];
        foreach ($gradeNames as $gradeName) {
            $grade = $grades->get($gradeName);
            if ($grade === null) {
                throw new RuntimeException("Academic structure is missing {$gradeName}.");
            }

            $structure[$gradeName] = [
                'grade_id' => $grade->id,
                'sections' => $grade->sections->mapWithKeys(fn (Section $section) => [$section->name => $section->id])->all(),
            ];
        }

        return $structure;
    }

    private function findOrCreateAcademicYear(string $name): AcademicYear
    {
        $existing = AcademicYear::query()->where('name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (! preg_match('/^(\d{4})-(\d{4})$/', $name, $matches)) {
            throw new RuntimeException('Use an existing academic year or provide --year in YYYY-YYYY format.');
        }

        return AcademicYear::create([
            'name' => $name,
            'starts_on' => "{$matches[1]}-09-01",
            'ends_on' => "{$matches[2]}-06-30",
            'status' => 'closed',
            'created_by' => User::query()->where('is_active', true)->orderBy('id')->value('id'),
        ]);
    }

    private function importYearCode(string $year): string
    {
        if (preg_match('/^(\d{4})-\d{2}(\d{2})$/', $year, $matches)) {
            return "{$matches[1]}-{$matches[2]}";
        }

        return preg_replace('/[^A-Za-z0-9]+/', '-', $year) ?: 'import';
    }

    /** @return array{0: string, 1: string|null, 2: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            throw new RuntimeException("Student name '{$name}' needs at least a first and last name.");
        }

        $firstName = array_shift($parts);
        $lastName = array_pop($parts);

        return [$firstName, $parts === [] ? null : implode(' ', $parts), $lastName];
    }

    /** @param list<array{name: string, grade: string, section: string}> $rows
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function summaryRows(array $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $key = "{$row['grade']}|{$row['section']}";
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        $result = [];
        foreach ($summary as $key => $count) {
            [$grade, $section] = explode('|', $key, 2);
            $result[] = [$grade, $section, $count];
        }

        return $result;
    }
}
