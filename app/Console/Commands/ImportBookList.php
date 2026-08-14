<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookGradeUsage;
use App\Models\Branch;
use App\Models\ClassBookList;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ImportBookList extends Command
{
    protected $signature = 'books:import-list
        {path : Absolute or relative path to the .xlsx book list}
        {--year=2024-2025 : Academic year for the student book packages}
        {--branch=MAIN : Branch code that owns the packages}
        {--dry-run : Validate the workbook without changing the database}';

    protected $description = 'Import a school book list, grade usage, and student book packages while ignoring source quantities.';

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

        try {
            $workbook = $this->readWorkbook($path);
            $grades = $this->gradesByName($workbook['rows']);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Read %d worksheet(s): %d book rows, %d blank row(s) skipped, %d duplicate row(s) skipped.',
            $workbook['sheet_count'],
            count($workbook['rows']),
            $workbook['blank_rows'],
            $workbook['duplicate_rows'],
        ));
        $this->table(['Grade', 'Student resources', 'Teacher resources'], $this->summaryRows($workbook['rows']));
        $this->comment('Quantity is deliberately ignored. Every imported package item has quantity 1.');

        if ($this->option('dry-run')) {
            $this->comment('Dry run complete. No books, grade usages, or packages were changed.');

            return self::SUCCESS;
        }

        $branch = Branch::query()->where('code', (string) $this->option('branch'))->first();
        if ($branch === null) {
            $this->error("Branch '{$this->option('branch')}' was not found.");

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($workbook, $grades, $branch): array {
                $year = $this->findOrCreateAcademicYear((string) $this->option('year'));
                $studentPackages = [];
                $createdBooks = 0;
                $updatedBooks = 0;
                $createdUsages = 0;
                $existingUsages = 0;

                foreach ($workbook['rows'] as $row) {
                    $book = $this->storeBook($row, $createdBooks, $updatedBooks);

                    foreach ($row['grades'] as $gradeName) {
                        $grade = $grades[$gradeName];
                        $usage = BookGradeUsage::query()->firstOrCreate([
                            'book_id' => $book->id,
                            'grade_id' => $grade->id,
                            'audience' => $row['audience'],
                        ]);
                        if ($usage->wasRecentlyCreated) {
                            $createdUsages++;
                        } else {
                            $existingUsages++;
                        }

                        if ($row['audience'] === 'student') {
                            $studentPackages[$grade->id][$book->id] = true;
                        }
                    }
                }

                $createdPackages = 0;
                $createdPackageItems = 0;
                $existingPackageItems = 0;
                foreach ($studentPackages as $gradeId => $books) {
                    $list = ClassBookList::withoutGlobalScopes()
                        ->where('branch_id', $branch->id)
                        ->where('academic_year_id', $year->id)
                        ->where('grade_id', $gradeId)
                        ->whereNull('section_id')
                        ->where('name', 'Imported student book list')
                        ->first();

                    if ($list === null) {
                        $list = new ClassBookList([
                            'academic_year_id' => $year->id,
                            'grade_id' => $gradeId,
                            'name' => 'Imported student book list',
                            'is_active' => true,
                        ]);
                        $list->branch_id = $branch->id;
                        $list->save();
                        $createdPackages++;
                    }

                    foreach (array_keys($books) as $bookId) {
                        $item = $list->items()->firstOrCreate(
                            ['book_id' => $bookId],
                            [
                                'quantity' => 1,
                                'is_mandatory' => true,
                                'price_type' => 'paid',
                                'unit_price' => '0.00',
                                'teacher_child_discount' => '0.00',
                            ],
                        );
                        if ($item->wasRecentlyCreated) {
                            $createdPackageItems++;
                        } else {
                            $existingPackageItems++;
                        }
                    }
                }

                return compact(
                    'year',
                    'createdBooks',
                    'updatedBooks',
                    'createdUsages',
                    'existingUsages',
                    'createdPackages',
                    'createdPackageItems',
                    'existingPackageItems',
                );
            });
        } catch (\Throwable $exception) {
            report($exception);
            $this->error("Import failed and no rows were saved: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Imported %d new books and updated %d existing books. Created %d grade usages and %d student packages.',
            $result['createdBooks'],
            $result['updatedBooks'],
            $result['createdUsages'],
            $result['createdPackages'],
        ));
        $this->comment(sprintf(
            'Student package items: %d created, %d already present. Grade usages already present: %d.',
            $result['createdPackageItems'],
            $result['existingPackageItems'],
            $result['existingUsages'],
        ));

        return self::SUCCESS;
    }

    /** @return array{sheet_count: int, rows: list<array{grades: list<string>, audience: string, media_format: string, subject: string, title: string, publisher: string|null, isbn: string|null}>, blank_rows: int, duplicate_rows: int} */
    private function readWorkbook(string $path): array
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
            $blankRows = 0;
            $duplicateRows = 0;

            foreach ($sheets as $sheet) {
                $relationId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $relationships[$relationId] ?? null;
                if ($target === null) {
                    throw new RuntimeException("Could not locate worksheet '{$sheet['name']}'.");
                }

                foreach ($this->worksheetRows($archive, $this->worksheetPath($target), $sharedStrings, (string) $sheet['name']) as $row) {
                    if ($row === null) {
                        $blankRows++;

                        continue;
                    }

                    $key = implode('|', [
                        implode(',', $row['grades']), $row['audience'], $row['media_format'],
                        mb_strtoupper($row['subject']), mb_strtoupper($row['title']),
                        mb_strtoupper((string) $row['publisher']), mb_strtoupper((string) $row['isbn']),
                    ]);
                    if (isset($seen[$key])) {
                        $duplicateRows++;

                        continue;
                    }

                    $seen[$key] = true;
                    $rows[] = $row;
                }
            }
        } finally {
            $archive->close();
        }

        if ($rows === []) {
            throw new RuntimeException('No book rows were found in the workbook.');
        }

        return [
            'sheet_count' => count($sheets),
            'rows' => $rows,
            'blank_rows' => $blankRows,
            'duplicate_rows' => $duplicateRows,
        ];
    }

    /** @return list<array{grades: list<string>, audience: string, media_format: string, subject: string, title: string, publisher: string|null, isbn: string|null}|null> */
    private function worksheetRows(ZipArchive $archive, string $path, array $sharedStrings, string $sheetName): array
    {
        $worksheet = $this->xml($archive, $path);
        $namespace = $this->namespace($worksheet);
        $worksheet->registerXPathNamespace('x', $namespace);
        $headers = [];
        $rows = [];

        foreach ($worksheet->xpath('//x:sheetData/x:row') ?: [] as $sheetRow) {
            $values = [];
            foreach ($sheetRow->children($namespace) as $cell) {
                if ($cell->getName() === 'c') {
                    $values[$this->columnIndex((string) ($cell->attributes()['r'] ?? ''))] = $this->cellValue($cell, $namespace, $sharedStrings);
                }
            }

            if ($headers === []) {
                foreach ($values as $column => $value) {
                    $headers[$this->normaliseHeader($value)] = $column;
                }
                if ($this->hasRequiredHeaders($headers)) {
                    continue;
                }
            }

            if (! $this->hasRequiredHeaders($headers)) {
                throw new RuntimeException("Worksheet '{$sheetName}' is missing Grade, User, Format, Subject, Program, Publisher, or ISBN headings.");
            }

            $row = $this->normaliseRow($values, $headers, $sheetName, (int) $sheetRow['r']);
            $rows[] = $row;
        }

        return $rows;
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        foreach (['GRADE', 'USER', 'FORMAT', 'SUBJECT', 'PROGRAM', 'PUBLISHER', 'ISBN'] as $header) {
            if (! isset($headers[$header])) {
                return false;
            }
        }

        return true;
    }

    /** @return array{grades: list<string>, audience: string, media_format: string, subject: string, title: string, publisher: string|null, isbn: string|null}|null */
    private function normaliseRow(array $values, array $headers, string $sheetName, int $rowNumber): ?array
    {
        $raw = [];
        foreach (['GRADE', 'USER', 'FORMAT', 'SUBJECT', 'PROGRAM', 'PUBLISHER', 'ISBN'] as $header) {
            $raw[$header] = $this->normalise($values[$headers[$header]] ?? '');
        }

        if (collect($raw)->filter()->isEmpty()) {
            return null;
        }

        if ($raw['GRADE'] === '' || $raw['USER'] === '' || $raw['FORMAT'] === '' || $raw['SUBJECT'] === '' || $raw['PROGRAM'] === '') {
            throw new RuntimeException("Worksheet '{$sheetName}', row {$rowNumber} is missing a required book value.");
        }

        return [
            'grades' => $this->normaliseGrades($raw['GRADE'], $sheetName, $rowNumber),
            'audience' => $this->normaliseAudience($raw['USER'], $sheetName, $rowNumber),
            'media_format' => $this->normaliseFormat($raw['FORMAT'], $sheetName, $rowNumber),
            'subject' => $raw['SUBJECT'],
            'title' => $raw['PROGRAM'],
            'publisher' => $raw['PUBLISHER'] !== '' ? $raw['PUBLISHER'] : null,
            'isbn' => $raw['ISBN'] !== '' ? $raw['ISBN'] : null,
        ];
    }

    /** @return list<string> */
    private function normaliseGrades(string $value, string $sheetName, int $rowNumber): array
    {
        $normalised = strtoupper($this->normalise($value));
        $compact = preg_replace('/[^A-Z0-9]+/', '', $normalised) ?? '';
        if (in_array($compact, ['PREK', 'PRESCHOOL'], true)) {
            return ['Preschool'];
        }
        if (preg_match('/^(?:GRADE)?K([12])$/', $compact, $matches)) {
            return ["KG{$matches[1]}"];
        }
        if (preg_match('/^(?:GRADE|GR|G)?\s*(\d{1,2})\s*(?:TO|-)\s*(?:GRADE|GR|G)?\s*(\d{1,2})$/', $normalised, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            if ($start >= 1 && $end <= 12 && $start <= $end) {
                return array_map(fn (int $number): string => "Grade {$number}", range($start, $end));
            }
        }
        if (preg_match('/^(?:GRADE|GR|G)?\s*([1-9]|1[0-2])$/', $normalised, $matches)) {
            return ["Grade {$matches[1]}"];
        }

        throw new RuntimeException("Worksheet '{$sheetName}', row {$rowNumber} has an unsupported grade '{$value}'.");
    }

    private function normaliseAudience(string $value, string $sheetName, int $rowNumber): string
    {
        return match (strtolower($value)) {
            'student' => 'student',
            'teacher' => 'teacher',
            default => throw new RuntimeException("Worksheet '{$sheetName}', row {$rowNumber} has an unsupported user '{$value}'."),
        };
    }

    private function normaliseFormat(string $value, string $sheetName, int $rowNumber): string
    {
        $format = preg_replace('/[^A-Z]+/', '', strtoupper($value)) ?? '';

        return match ($format) {
            'PRINT' => 'print',
            'DIGITAL', 'DIGIAL' => 'digital',
            'PRINTDIGITAL', 'PRINTANDDIGITAL' => 'print_and_digital',
            default => throw new RuntimeException("Worksheet '{$sheetName}', row {$rowNumber} has an unsupported format '{$value}'."),
        };
    }

    /** @param list<array{grades: list<string>}> $rows @return array<string, Grade> */
    private function gradesByName(array $rows): array
    {
        $names = collect($rows)->flatMap(fn (array $row) => $row['grades'])->unique()->values();
        $grades = Grade::query()->whereIn('name', $names)->get()->keyBy('name');
        foreach ($names as $name) {
            if (! $grades->has($name)) {
                throw new RuntimeException("Academic structure is missing {$name}.");
            }
        }

        return $grades->all();
    }

    private function storeBook(array $row, int &$createdBooks, int &$updatedBooks): Book
    {
        $signature = implode('|', [
            mb_strtoupper((string) ($row['isbn'] ?? 'NO-ISBN')),
            mb_strtoupper($row['title']),
            mb_strtoupper((string) $row['publisher']),
            mb_strtoupper($row['subject']),
            $row['media_format'],
        ]);
        $token = strtoupper(substr(hash('sha256', $signature), 0, 20));
        $sku = "IMP-BK-{$token}";
        $book = Book::withTrashed()->where('sku', $sku)->first();
        $attributes = [
            'sku' => $sku,
            'isbn' => $row['isbn'],
            'barcode' => "IMPBK-{$token}",
            'title' => $row['title'],
            'publisher' => $row['publisher'],
            'subject' => $row['subject'],
            'category' => 'School book',
            'media_format' => $row['media_format'],
            'default_audience' => $this->combinedAudience($book?->default_audience, $row['audience']),
            'is_active' => true,
            'notes' => 'Imported from the Lebanon book list. Source quantity intentionally not imported.',
        ];

        if ($book === null) {
            $createdBooks++;

            return Book::create($attributes);
        }

        if ($book->trashed()) {
            $book->restore();
        }
        $book->fill($attributes)->save();
        $updatedBooks++;

        return $book;
    }

    private function combinedAudience(?string $current, string $incoming): string
    {
        if ($current === null || $current === $incoming) {
            return $incoming;
        }

        return 'both';
    }

    private function findOrCreateAcademicYear(string $name): AcademicYear
    {
        $year = AcademicYear::query()->where('name', $name)->first();
        if ($year !== null) {
            return $year;
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
        return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
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

    /** @param list<array{grades: list<string>, audience: string}> $rows @return list<array{0: string, 1: int, 2: int}> */
    private function summaryRows(array $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            foreach ($row['grades'] as $grade) {
                $summary[$grade][$row['audience']] = ($summary[$grade][$row['audience']] ?? 0) + 1;
            }
        }
        $order = array_merge(['Preschool', 'KG1', 'KG2'], array_map(fn (int $number): string => "Grade {$number}", range(1, 12)));

        return collect($order)
            ->filter(fn (string $grade) => isset($summary[$grade]))
            ->map(fn (string $grade) => [$grade, $summary[$grade]['student'] ?? 0, $summary[$grade]['teacher'] ?? 0])
            ->values()
            ->all();
    }
}
