<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ActionCategory;
use App\Models\ActionType;
use App\Models\Assessment;
use App\Models\Branch;
use App\Models\ExamAbsence;
use App\Models\Grade;
use App\Models\GradeEntry;
use App\Models\MedicalRecord;
use App\Models\ResponsiblePerson;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentIncident;
use App\Models\StudentRegistration;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class StudentDossierDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = app(BranchContext::class)->id() ?? Branch::query()->value('id');
        $user = User::query()->orderBy('id')->first();
        if (! $branchId || ! $user) {
            $this->command?->warn('Demo student dossier data was not added because SBM needs a branch and at least one user.');

            return;
        }

        $year = AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->first()
            ?? AcademicYear::firstOrCreate(['name' => '2026-2027'], ['starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'created_by' => $user->id]);
        $level = SchoolLevel::firstOrCreate(['name' => 'SBM Demo'], ['sort_order' => 999]);
        $grade = Grade::firstOrCreate(['school_level_id' => $level->id, 'name' => 'Demo Grade'], ['sort_order' => 999]);
        $sectionA = Section::firstOrCreate(['grade_id' => $grade->id, 'name' => 'A']);
        $sectionB = Section::firstOrCreate(['grade_id' => $grade->id, 'name' => 'B']);

        $teacher = Teacher::firstOrCreate(['employee_number' => 'DEMO-TEACH-001'], [
            'first_name' => 'Nadia', 'last_name' => 'Example', 'email' => 'nadia.example@sbm.demo', 'phone' => '+961 70 000 001', 'is_active' => true,
        ]);
        $subject = Subject::firstOrCreate(['branch_id' => $branchId, 'name' => 'Demo English'], ['code' => 'DEMO-ENG', 'description' => 'Synthetic data for SBM student dossier checks.', 'is_active' => true]);
        $term = AcademicTerm::firstOrCreate(['branch_id' => $branchId, 'academic_year_id' => $year->id, 'name' => 'Demo Term 1'], [
            'type' => 'term', 'starts_on' => $year->starts_on, 'ends_on' => $year->ends_on, 'status' => 'open', 'sort_order' => 1,
        ]);
        $assignment = TeacherAssignment::firstOrCreate([
            'branch_id' => $branchId, 'academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $sectionA->id,
        ], ['required_periods_per_week' => 4, 'weekly_period_limit' => 18, 'is_active' => true, 'notes' => 'Demo class assignment']);
        $assessment = Assessment::firstOrCreate([
            'branch_id' => $branchId, 'academic_year_id' => $year->id, 'name' => 'Demo English Reading Check',
        ], ['academic_term_id' => $term->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $sectionA->id, 'type' => 'quiz', 'maximum_score' => 20, 'assessment_date' => today()->subDays(7), 'status' => 'open']);
        $category = ActionCategory::firstOrCreate(['branch_id' => $branchId, 'name' => 'Demo wellbeing'], ['kind' => 'wellbeing', 'is_active' => true]);
        $actionType = ActionType::firstOrCreate(['action_category_id' => $category->id, 'name' => 'Demo follow-up'], ['branch_id' => $branchId, 'is_active' => true]);

        $students = [
            ['number' => 'DEMO-STU-001', 'first' => 'Maya', 'last' => 'Demo', 'gender' => 'female', 'section' => $sectionA, 'status' => 'active'],
            ['number' => 'DEMO-STU-002', 'first' => 'Omar', 'last' => 'Demo', 'gender' => 'male', 'section' => $sectionA, 'status' => 'active'],
            ['number' => 'DEMO-STU-003', 'first' => 'Lina', 'last' => 'Demo', 'gender' => 'female', 'section' => $sectionA, 'status' => 'active'],
            ['number' => 'DEMO-STU-004', 'first' => 'Karim', 'last' => 'Demo', 'gender' => 'male', 'section' => $sectionA, 'status' => 'active'],
            ['number' => 'DEMO-STU-005', 'first' => 'Sara', 'last' => 'Demo', 'gender' => 'female', 'section' => $sectionA, 'status' => 'active'],
            ['number' => 'DEMO-STU-006', 'first' => 'Rami', 'last' => 'Demo', 'gender' => 'male', 'section' => $sectionB, 'status' => 'active'],
            ['number' => 'DEMO-STU-007', 'first' => 'Yara', 'last' => 'Demo', 'gender' => 'female', 'section' => $sectionB, 'status' => 'active'],
            ['number' => 'DEMO-STU-008', 'first' => 'Adam', 'last' => 'Demo', 'gender' => 'male', 'section' => $sectionB, 'status' => 'active'],
            ['number' => 'DEMO-STU-009', 'first' => 'Tala', 'last' => 'Archive', 'gender' => 'female', 'section' => $sectionA, 'status' => 'withdrawn'],
            ['number' => 'DEMO-STU-010', 'first' => 'Sam', 'last' => 'Graduate', 'gender' => 'male', 'section' => $sectionB, 'status' => 'completed'],
        ];

        foreach ($students as $index => $row) {
            $student = Student::withoutGlobalScopes()->updateOrCreate(['student_number' => $row['number']], [
                'branch_id' => $branchId,
                'registration_number' => 'DEMO-REG-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'first_name' => $row['first'], 'last_name' => $row['last'], 'gender' => $row['gender'],
                'date_of_birth' => today()->subYears(10)->subDays($index * 23), 'phone' => '+961 70 100 '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'email' => strtolower($row['first']).'.'.strtolower($row['last']).'@demo.sbm.test', 'address' => 'Demo Street, Beirut',
                'enrollment_date' => $year->starts_on, 'previous_school' => 'SBM Demo School', 'notes' => 'Synthetic demo record — safe to inspect or remove.',
            ]);
            if ($index % 2 === 0) {
                $student->update(['photo_path' => $this->storeDemoPhoto($student)]);
            }

            $registration = StudentRegistration::withoutGlobalScopes()->updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $year->id], [
                'branch_id' => $branchId, 'grade_id' => $grade->id, 'section_id' => $row['section']->id, 'teacher_id' => $teacher->id,
                'status' => $row['status'], 'registered_on' => $year->starts_on, 'withdrawn_on' => $row['status'] === 'withdrawn' ? today()->subMonth() : null,
                'withdrawal_reason' => $row['status'] === 'withdrawn' ? 'Demo transfer record for former-student archive.' : null,
                'notes' => 'Synthetic demo registration.', 'created_by' => $user->id,
            ]);

            $person = ResponsiblePerson::withTrashed()->firstOrNew(['parent_code' => 'DEMO-FAM-'.str_pad((string) (intdiv($index, 2) + 1), 3, '0', STR_PAD_LEFT)]);
            $person->fill([
                'full_name' => 'Demo Family '.(intdiv($index, 2) + 1), 'first_name' => $index % 2 ? 'Fadi' : 'Rana', 'last_name' => 'Demo',
                'type' => $index % 2 ? 'father' : 'mother', 'phone' => '+961 71 200 '.str_pad((string) (intdiv($index, 2) + 1), 3, '0', STR_PAD_LEFT),
                'email' => 'family'.(intdiv($index, 2) + 1).'@demo.sbm.test', 'religion' => 'Demo', 'family_type' => 'Ordinary',
                'occupation' => 'Demo occupation', 'register_number' => 'REG-DEMO-'.($index + 1), 'district' => 'Beirut', 'town' => 'Beirut',
                'nationality' => 'Lebanese', 'employment_status' => 'Employed', 'employer' => 'SBM Demo Company', 'position' => 'Demo role',
                'family_status' => 'Married', 'address' => 'Demo Street, Beirut', 'work_address' => 'Demo Business District, Beirut',
                'notes' => 'Synthetic family contact for route checks.',
            ]);
            $person->save();
            if ($person->trashed()) {
                $person->restore();
            }
            $person->students()->syncWithoutDetaching([$student->id => ['relationship' => str($person->type)->headline()->toString(), 'is_primary' => true]]);

            if ($row['status'] !== 'active') {
                continue;
            }

            StudentAttendance::updateOrCreate(['student_id' => $student->id, 'attendance_date' => today()->subDays(2), 'period_number' => 0], [
                'branch_id' => $branchId, 'student_registration_id' => $registration->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $row['section']->id,
                'status' => $index % 3 === 0 ? 'late' : 'present', 'notes' => 'Synthetic attendance record.', 'recorded_by' => $user->id,
            ]);
        }

        $featured = Student::withoutGlobalScopes()->where('student_number', 'DEMO-STU-001')->firstOrFail();
        $featuredRegistration = StudentRegistration::withoutGlobalScopes()->where('student_id', $featured->id)->where('academic_year_id', $year->id)->firstOrFail();
        MedicalRecord::updateOrCreate(['student_id' => $featured->id, 'title' => 'Demo health note'], [
            'branch_id' => $branchId, 'type' => 'health_note', 'recorded_on' => today()->subDays(10), 'description' => 'Synthetic medical archive entry for interface testing.', 'restrictions' => 'No restriction — demo record.', 'status' => 'active', 'created_by' => $user->id,
        ]);
        StudentIncident::updateOrCreate(['student_id' => $featured->id, 'description' => 'Synthetic wellbeing follow-up.'], [
            'branch_id' => $branchId, 'student_registration_id' => $featuredRegistration->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $sectionA->id,
            'action_category_id' => $category->id, 'action_type_id' => $actionType->id, 'incident_date' => today()->subDays(5), 'location' => 'Demo classroom',
            'action_taken' => 'Checked in with student.', 'parent_notified' => true, 'status' => 'resolved', 'created_by' => $user->id,
        ]);
        GradeEntry::updateOrCreate(['student_id' => $featured->id, 'assessment_id' => $assessment->id], [
            'branch_id' => $branchId, 'student_registration_id' => $featuredRegistration->id, 'teacher_assignment_id' => $assignment->id, 'teacher_id' => $teacher->id,
            'score' => 17.5, 'notes' => 'Synthetic grade entry.', 'status' => 'published', 'recorded_by' => $user->id, 'published_at' => now(), 'published_by' => $user->id,
        ]);
        ExamAbsence::updateOrCreate(['student_id' => $featured->id, 'assessment_id' => $assessment->id], [
            'branch_id' => $branchId, 'student_registration_id' => $featuredRegistration->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
            'grade_id' => $grade->id, 'section_id' => $sectionA->id, 'absence_date' => today()->subDays(7), 'reason' => 'Synthetic absence record.',
            'is_excused' => true, 'makeup_required' => true, 'makeup_exam_date' => today()->addDays(3), 'notes' => 'Synthetic demo absence.', 'status' => 'makeup_scheduled', 'created_by' => $user->id,
        ]);

        foreach ([1, 2] as $number) {
            ResponsiblePerson::updateOrCreate(['parent_code' => 'DEMO-UNLINK-00'.$number], [
                'full_name' => 'Unlinked Demo Family '.$number, 'type' => 'guardian', 'phone' => '+961 76 300 00'.$number,
                'email' => 'unlinked'.$number.'@demo.sbm.test', 'notes' => 'Synthetic unlinked family for dossier review.',
            ]);
        }

        $this->command?->info('SBM student dossier demo data is ready. All generated records use the DEMO- prefix.');
    }

    private function storeDemoPhoto(Student $student): string
    {
        $path = 'student-photos/'.$student->public_id.'/demo-avatar.svg';
        $initials = strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1));
        $colors = ['#1f7de0', '#2d8b89', '#6354d7', '#b45309', '#be185d'];
        $color = $colors[crc32($student->student_number) % count($colors)];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320"><rect width="320" height="320" fill="'.$color.'"/><circle cx="160" cy="120" r="58" fill="#fff" fill-opacity=".22"/><path d="M48 306c14-78 70-112 112-112s98 34 112 112" fill="#fff" fill-opacity=".22"/><text x="160" y="183" text-anchor="middle" font-family="Arial,sans-serif" font-size="86" font-weight="700" fill="#fff">'.$initials.'</text></svg>';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
