<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ActionCategory;
use App\Models\ActionType;
use App\Models\Assessment;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\BookDistributionItem;
use App\Models\Branch;
use App\Models\BranchBookStock;
use App\Models\ClassBookList;
use App\Models\ClassBookListItem;
use App\Models\CourseContent;
use App\Models\ExamAbsence;
use App\Models\Grade;
use App\Models\GradeEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Models\ResponsiblePerson;
use App\Models\Role;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolEvent;
use App\Models\SchoolMessage;
use App\Models\SchoolMessageRecipient;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentIncident;
use App\Models\StudentPerformanceEvaluation;
use App\Models\StudentRegistration;
use App\Models\Subject;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\SupplierPurchaseItem;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherAttendance;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GlobalAcademySampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $administrator = User::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $branch = Branch::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $year = AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->firstOrFail();

        $this->removeLegacyDemoRecords();

        $termOne = AcademicTerm::updateOrCreate(
            ['branch_id' => $branch->id, 'academic_year_id' => $year->id, 'name' => 'Term 1'],
            ['type' => 'term', 'starts_on' => $year->starts_on, 'ends_on' => $year->starts_on->copy()->addMonths(3)->subDay(), 'status' => 'open', 'sort_order' => 1],
        );
        AcademicTerm::updateOrCreate(
            ['branch_id' => $branch->id, 'academic_year_id' => $year->id, 'name' => 'Term 2'],
            ['type' => 'term', 'starts_on' => $termOne->ends_on->copy()->addDay(), 'ends_on' => $year->ends_on, 'status' => 'planned', 'sort_order' => 2],
        );

        $classes = [
            ['Grade 1', 'A'], ['Grade 1', 'B'], ['Grade 2', 'A'], ['Grade 2', 'B'], ['Grade 3', 'A'],
            ['Grade 3', 'B'], ['Grade 4', 'B'], ['Grade 4', 'C'], ['Grade 5', 'A'], ['Grade 5', 'B'],
        ];
        $sections = collect($classes)->mapWithKeys(function (array $class): array {
            $grade = Grade::query()->where('name', $class[0])->firstOrFail();
            $section = $grade->sections()->where('name', $class[1])->firstOrFail();

            return [$grade->id.'-'.$section->id => compact('grade', 'section')];
        });

        $subjects = collect([
            ['ENG', 'English Language Arts'], ['MAT', 'Mathematics'], ['SCI', 'Science'],
            ['ARA', 'Arabic Language'], ['FRE', 'French Language'], ['SOC', 'Social Studies'],
        ])->mapWithKeys(fn (array $subject) => [$subject[0] => Subject::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => $subject[0]],
            ['name' => $subject[1], 'description' => 'Global Academy International curriculum subject.', 'is_active' => true],
        )]);

        $teacherProfiles = [
            ['GAI-STAFF-001', 'Layla', 'Haddad', 'ENG'], ['GAI-STAFF-002', 'Omar', 'Saab', 'MAT'],
            ['GAI-STAFF-003', 'Mira', 'Khoury', 'SCI'], ['GAI-STAFF-004', 'Nabil', 'Rizk', 'ARA'],
            ['GAI-STAFF-005', 'Clara', 'Mansour', 'FRE'], ['GAI-STAFF-006', 'Tarek', 'Farhat', 'SOC'],
        ];
        $teacherRoleId = Role::query()->where('name', 'teacher')->value('id');
        $studentRoleId = Role::query()->where('name', 'student')->value('id');
        $teachers = collect();
        $teacherUsers = collect();
        foreach ($teacherProfiles as [$number, $first, $last, $subjectCode]) {
            $teacher = Teacher::withTrashed()->updateOrCreate(['employee_number' => $number], [
                'first_name' => $first, 'last_name' => $last, 'email' => strtolower($first.'.'.$last).'@globalacademy.example.test',
                'phone' => '+961 70 '.str_pad((string) (400000 + $teachers->count() + 1), 6, '0', STR_PAD_LEFT),
                'address' => 'Beirut, Lebanon', 'is_active' => true,
            ]);
            if ($teacher->trashed()) {
                $teacher->restore();
            }
            $teachers->put($subjectCode, $teacher);

            $user = User::withTrashed()->updateOrCreate(['email' => 'staff.'.strtolower($subjectCode).'@globalacademy.example.test'], [
                'name' => $first.' '.$last, 'teacher_id' => $teacher->id, 'password' => Hash::make('GlobalAcademy2026!'), 'is_active' => true,
            ]);
            if ($user->trashed()) {
                $user->restore();
            }
            if ($teacherRoleId) {
                $user->roles()->syncWithoutDetaching([$teacherRoleId]);
            }
            $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => false, 'is_branch_administrator' => false]]);
            $teacherUsers->put($subjectCode, $user);
        }

        $assignments = collect();
        foreach ($sections as $class) {
            foreach ($subjects as $code => $subject) {
                $assignment = TeacherAssignment::updateOrCreate([
                    'branch_id' => $branch->id, 'academic_year_id' => $year->id, 'teacher_id' => $teachers[$code]->id,
                    'subject_id' => $subject->id, 'grade_id' => $class['grade']->id, 'section_id' => $class['section']->id,
                ], ['required_periods_per_week' => $code === 'ENG' || $code === 'MAT' ? 5 : 3, 'weekly_period_limit' => 24, 'is_active' => true, 'notes' => 'Academic year teaching assignment.']);
                $assignments->put($class['grade']->id.'-'.$class['section']->id.'-'.$code, $assignment);
            }
        }

        $firstNames = ['Adam', 'Aya', 'Daniel', 'Elena', 'Farah', 'Gabriel', 'Hana', 'Ibrahim', 'Jana', 'Karim', 'Lara', 'Maya', 'Nadim', 'Nour', 'Oliver', 'Rania', 'Samir', 'Tala', 'Yara', 'Ziad'];
        $lastNames = ['Abboud', 'Barakat', 'Chehab', 'Daher', 'Elias', 'Fakhoury', 'Ghosn', 'Haddad', 'Issa', 'Jaber'];
        $students = collect();
        for ($index = 0; $index < 50; $index++) {
            $class = $sections->values()->get(intdiv($index, 5));
            $number = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $first = $firstNames[$index % count($firstNames)];
            $last = $lastNames[intdiv($index, 5) % count($lastNames)];
            $student = Student::withTrashed()->updateOrCreate(['student_number' => 'GAI-2026-'.$number], [
                'branch_id' => $branch->id, 'registration_number' => 'GAI-REG-2026-'.$number, 'first_name' => $first, 'last_name' => $last,
                'gender' => $index % 2 === 0 ? 'female' : 'male', 'date_of_birth' => $year->starts_on->copy()->subYears(6 + intdiv($index, 10))->subDays($index * 11),
                'phone' => '+961 3 '.str_pad((string) (500000 + $index), 6, '0', STR_PAD_LEFT), 'email' => strtolower($first.'.'.$last.'.'.$number).'@students.globalacademy.example.test',
                'address' => ['Achrafieh', 'Hamra', 'Badaro', 'Verdun', 'Hazmieh'][$index % 5].', Beirut', 'enrollment_date' => $year->starts_on,
                'previous_school' => 'Global Academy International enrolment', 'notes' => 'Sample academic record for local product review.',
            ]);
            if ($student->trashed()) {
                $student->restore();
            }
            // Every student record has a matching portal account. Keep an
            // existing password when the seeder is re-run, while creating a
            // predictable local-demo password for new sample accounts.
            $studentUser = User::withTrashed()->firstOrNew(['student_id' => $student->id]);
            $studentUser->fill([
                'name' => $student->full_name,
                'email' => $student->email,
                'is_active' => true,
            ]);
            if (! $studentUser->exists) {
                $studentUser->password = 'GlobalAcademy2026!';
            }
            $studentUser->save();
            if ($studentUser->trashed()) {
                $studentUser->restore();
            }
            if ($studentRoleId) {
                $studentUser->roles()->sync([$studentRoleId]);
            }
            $studentUser->branches()->syncWithoutDetaching([
                $branch->id => ['is_default' => true, 'is_branch_administrator' => false],
            ]);
            $registration = StudentRegistration::withoutGlobalScopes()->updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $year->id], [
                'branch_id' => $branch->id, 'grade_id' => $class['grade']->id, 'section_id' => $class['section']->id, 'teacher_id' => $teachers['ENG']->id,
                'status' => 'active', 'registered_on' => $year->starts_on, 'notes' => 'Current academic year registration.', 'created_by' => $administrator->id,
            ]);
            $guardian = ResponsiblePerson::withTrashed()->updateOrCreate(['parent_code' => 'GAI-GDN-'.$number], [
                'first_name' => $index % 2 ? 'Rami' : 'Dina', 'last_name' => $last, 'full_name' => ($index % 2 ? 'Rami' : 'Dina').' '.$last,
                'type' => $index % 2 ? 'father' : 'mother', 'phone' => '+961 71 '.str_pad((string) (600000 + $index), 6, '0', STR_PAD_LEFT),
                'email' => 'guardian.'.$number.'@globalacademy.example.test', 'family_type' => 'Family', 'occupation' => ['Engineer', 'Teacher', 'Designer', 'Accountant', 'Pharmacist'][$index % 5],
                'district' => 'Beirut', 'town' => 'Beirut', 'nationality' => 'Lebanese', 'family_status' => 'Married', 'address' => 'Beirut, Lebanon',
            ]);
            if ($guardian->trashed()) {
                $guardian->restore();
            }
            $guardian->students()->syncWithoutDetaching([$student->id => ['relationship' => str($guardian->type)->headline()->toString(), 'is_primary' => true]]);
            $students->push(compact('student', 'registration', 'class'));
        }

        $assessments = collect();
        foreach ($assignments as $key => $assignment) {
            foreach ([['Skills Check', 30, 'quiz'], ['Term 1 Assessment', 100, 'exam']] as $offset => [$label, $maximum, $type]) {
                $assessment = Assessment::updateOrCreate([
                    'branch_id' => $branch->id, 'academic_year_id' => $year->id, 'name' => $assignment->subject->name.' '.$label.' · '.$assignment->grade->name.' '.$assignment->section->name,
                ], [
                    'academic_term_id' => $termOne->id, 'teacher_assignment_id' => $assignment->id, 'subject_id' => $assignment->subject_id, 'grade_id' => $assignment->grade_id, 'section_id' => $assignment->section_id,
                    'type' => $type, 'maximum_score' => $maximum, 'assessment_date' => today()->subDays(21 - ($offset * 8)), 'status' => 'published', 'notes' => 'Term 1 academic assessment.',
                ]);
                $assessments->put($key.'-'.$offset, $assessment);
            }
        }

        foreach ($students as $studentData) {
            $classKey = $studentData['class']['grade']->id.'-'.$studentData['class']['section']->id;
            foreach ($subjects as $code => $subject) {
                $assignment = $assignments[$classKey.'-'.$code];
                foreach ([0, 1] as $assessmentOffset) {
                    $assessment = $assessments[$classKey.'-'.$code.'-'.$assessmentOffset];
                    $score = $assessmentOffset === 0 ? 19 + (($studentData['student']->id + $assignment->id) % 11) : 68 + (($studentData['student']->id + $assignment->id) % 29);
                    GradeEntry::updateOrCreate(['student_id' => $studentData['student']->id, 'assessment_id' => $assessment->id], [
                        'branch_id' => $branch->id, 'student_registration_id' => $studentData['registration']->id, 'teacher_assignment_id' => $assignment->id, 'teacher_id' => $assignment->teacher_id,
                        'score' => $score, 'notes' => $score < 75 ? 'Practice plan shared with student.' : 'Strong progress this term.', 'status' => 'published', 'is_locked' => $assessmentOffset === 1,
                        'recorded_by' => $teacherUsers[$code]->id, 'published_at' => now()->subDays($assessmentOffset + 1), 'published_by' => $teacherUsers[$code]->id,
                    ]);
                }
            }
            for ($day = 1; $day <= 10; $day++) {
                $date = today()->subWeekdays($day);
                $status = ($studentData['student']->id + $day) % 23 === 0 ? 'absent' : (($studentData['student']->id + $day) % 13 === 0 ? 'late' : 'present');
                StudentAttendance::updateOrCreate(['student_id' => $studentData['student']->id, 'attendance_date' => $date, 'period_number' => 0], [
                    'branch_id' => $branch->id, 'student_registration_id' => $studentData['registration']->id, 'academic_year_id' => $year->id, 'grade_id' => $studentData['class']['grade']->id, 'section_id' => $studentData['class']['section']->id,
                    'status' => $status, 'notes' => $status === 'present' ? null : ($status === 'late' ? 'Arrived after morning registration.' : 'Family notified.'), 'recorded_by' => $administrator->id,
                ]);
            }
            $englishAssignment = $assignments[$classKey.'-ENG'];
            foreach (['Participation', 'Learning habits'] as $evaluationIndex => $category) {
                StudentPerformanceEvaluation::updateOrCreate([
                    'student_id' => $studentData['student']->id, 'category' => $category, 'observed_on' => today()->subDays(4 + $evaluationIndex),
                ], [
                    'branch_id' => $branch->id, 'student_registration_id' => $studentData['registration']->id, 'teacher_assignment_id' => $englishAssignment->id, 'teacher_id' => $englishAssignment->teacher_id,
                    'rating' => 3 + (($studentData['student']->id + $evaluationIndex) % 3), 'note' => $evaluationIndex ? 'Maintains a consistent approach to independent work.' : 'Participates constructively in classroom discussion.',
                    'status' => 'published', 'created_by' => $teacherUsers['ENG']->id,
                ]);
            }
        }

        $featuredStudents = $students->take(5)->values();
        foreach ($featuredStudents as $index => $studentData) {
            $classKey = $studentData['class']['grade']->id.'-'.$studentData['class']['section']->id;
            $assessment = $assessments[$classKey.'-ENG-1'];
            ExamAbsence::updateOrCreate(['student_id' => $studentData['student']->id, 'assessment_id' => $assessment->id], [
                'branch_id' => $branch->id, 'student_registration_id' => $studentData['registration']->id, 'academic_year_id' => $year->id, 'academic_term_id' => $termOne->id, 'subject_id' => $subjects['ENG']->id,
                'grade_id' => $studentData['class']['grade']->id, 'section_id' => $studentData['class']['section']->id, 'absence_date' => $assessment->assessment_date, 'reason' => $index % 2 ? 'Family travel' : 'Medical appointment',
                'is_excused' => true, 'makeup_required' => true, 'makeup_exam_date' => today()->addWeekdays($index + 2), 'notes' => 'Make-up assessment arranged with the family.', 'status' => 'makeup_scheduled', 'created_by' => $administrator->id,
            ]);
        }

        $wellbeing = ActionCategory::updateOrCreate(['branch_id' => $branch->id, 'name' => 'Student wellbeing'], ['kind' => 'wellbeing', 'is_active' => true]);
        $followUp = ActionType::updateOrCreate(['action_category_id' => $wellbeing->id, 'name' => 'Pastoral follow-up'], ['branch_id' => $branch->id, 'is_active' => true]);
        foreach ($featuredStudents->take(3) as $index => $studentData) {
            StudentIncident::updateOrCreate(['student_id' => $studentData['student']->id, 'description' => 'Wellbeing check-in following a classroom concern.'], [
                'branch_id' => $branch->id, 'student_registration_id' => $studentData['registration']->id, 'academic_year_id' => $year->id, 'grade_id' => $studentData['class']['grade']->id, 'section_id' => $studentData['class']['section']->id,
                'action_category_id' => $wellbeing->id, 'action_type_id' => $followUp->id, 'incident_date' => today()->subDays($index + 3), 'location' => 'Student support office',
                'action_taken' => 'Met with student and agreed next steps.', 'parent_notified' => true, 'status' => 'resolved', 'reported_by_teacher_id' => $teachers['ENG']->id, 'created_by' => $administrator->id,
            ]);
            MedicalRecord::updateOrCreate(['student_id' => $studentData['student']->id, 'title' => 'Health office update'], [
                'branch_id' => $branch->id, 'type' => 'health_note', 'recorded_on' => today()->subDays($index + 5), 'description' => 'Routine school health office follow-up.', 'restrictions' => 'No current restrictions.', 'status' => 'active', 'created_by' => $administrator->id,
            ]);
        }

        foreach (range(1, 6) as $period) {
            TimetablePeriod::updateOrCreate(['branch_id' => $branch->id, 'academic_year_id' => $year->id, 'period_number' => $period], [
                'label' => 'Period '.$period, 'starts_at' => sprintf('%02d:00:00', 7 + $period), 'ends_at' => sprintf('%02d:50:00', 7 + $period), 'kind' => 'lesson',
            ]);
        }
        $timetableEntries = collect();
        foreach ($sections as $class) {
            $classKey = $class['grade']->id.'-'.$class['section']->id;
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $dayIndex => $day) {
                $code = array_keys($subjects->all())[$dayIndex % $subjects->count()];
                $assignment = $assignments[$classKey.'-'.$code];
                $timetableEntries->push(TimetableEntry::updateOrCreate([
                    'branch_id' => $branch->id, 'academic_year_id' => $year->id, 'grade_id' => $class['grade']->id, 'section_id' => $class['section']->id, 'day_of_week' => $day, 'period_number' => $dayIndex + 1,
                ], ['teacher_assignment_id' => $assignment->id, 'teacher_id' => $assignment->teacher_id, 'subject_id' => $assignment->subject_id, 'room' => 'Room '.(101 + $class['grade']->sort_order)]));
            }
        }
        foreach ($teachers as $code => $teacher) {
            foreach (range(1, 5) as $day) {
                TeacherAttendance::updateOrCreate(['branch_id' => $branch->id, 'teacher_id' => $teacher->id, 'attendance_date' => today()->subWeekdays($day)], [
                    'checked_in_at' => $day === 2 && $code === 'SCI' ? '08:18:00' : '07:45:00', 'checked_out_at' => '14:30:00', 'status' => $day === 3 && $code === 'FRE' ? 'excused_absence' : 'present',
                    'late_minutes' => $day === 2 && $code === 'SCI' ? 18 : 0, 'notes' => null, 'recorded_by' => $administrator->id,
                ]);
            }
        }
        $substitutionEntry = $timetableEntries->first();
        TeacherSubstitution::updateOrCreate(['substitution_date' => today()->addDay(), 'timetable_entry_id' => $substitutionEntry->id], [
            'branch_id' => $branch->id, 'absent_teacher_id' => $substitutionEntry->teacher_id, 'substitute_teacher_id' => $teachers['MAT']->id, 'status' => 'assigned',
            'recommendation_details' => ['reason' => 'Sample timetable coverage'], 'override_reason' => 'Planned staff development session.', 'assigned_by' => $administrator->id,
        ]);

        foreach (['Term 1 family conference', 'Science discovery afternoon', 'Reading week launch', 'Grade 5 museum visit'] as $index => $title) {
            SchoolEvent::updateOrCreate(['branch_id' => $branch->id, 'title' => $title], [
                'academic_year_id' => $year->id, 'audience' => $index === 3 ? 'students' : 'all', 'description' => 'Global Academy International community event.',
                'starts_at' => now()->addDays($index + 2)->setTime(9 + $index, 0), 'ends_at' => now()->addDays($index + 2)->setTime(10 + $index, 30), 'created_by' => $administrator->id,
            ]);
        }
        foreach ([['Campus safety reminder', 'Please use designated drop-off and collection points.'], ['Library week', 'Families are invited to support daily reading at home.'], ['Assessment timetable published', 'Term 1 assessment dates are now available in the academic workspace.']] as [$title, $body]) {
            SchoolAnnouncement::updateOrCreate(['branch_id' => $branch->id, 'title' => $title], [
                'academic_year_id' => $year->id, 'audience' => 'all', 'body' => $body, 'published_at' => now()->subDays(1), 'expires_on' => today()->addMonth(), 'created_by' => $administrator->id,
            ]);
        }
        foreach ($assignments->take(12) as $assignment) {
            CourseContent::updateOrCreate(['teacher_assignment_id' => $assignment->id, 'title' => $assignment->subject->name.' weekly learning plan'], [
                'branch_id' => $branch->id, 'content' => 'Review the class learning goals, complete the practice activity, and prepare questions for the next lesson.', 'content_type' => 'lesson',
                'available_from' => now()->subDays(2), 'due_on' => today()->addWeek(), 'published_at' => now()->subDay(), 'created_by' => $teacherUsers->first()->id,
            ]);
        }
        foreach ([['Staff briefing: Term 1 priorities', 'Please review the assessment calendar and family conference schedule.'], ['Grade 3 support update', 'Student support notes have been added for the pastoral team.'], ['Library week volunteers', 'Thank you for confirming reading activity supervision.']] as [$subject, $body]) {
            $message = SchoolMessage::updateOrCreate(['branch_id' => $branch->id, 'subject' => $subject], ['sender_id' => $administrator->id, 'body' => $body, 'sent_at' => now()->subHours(3)]);
            foreach ($teacherUsers as $teacherUser) {
                SchoolMessageRecipient::updateOrCreate(['school_message_id' => $message->id, 'recipient_id' => $teacherUser->id], ['read_at' => $teacherUser->id % 2 ? now()->subHour() : null]);
            }
        }

        // Library, stock, billing, receipts, and distributions give the operations
        // areas of the app the same meaningful coverage as the academic workspace.
        $bookCatalog = [
            ['GAI-BK-001', 'English in Action 1', 'Rana Khalil', 'English Language Arts', 18.00, 32.00, 160],
            ['GAI-BK-002', 'Mathematics Explorer', 'Maya Nasser', 'Mathematics', 20.00, 36.00, 150],
            ['GAI-BK-003', 'Young Scientists', 'Sami Tabet', 'Science', 19.00, 34.00, 140],
            ['GAI-BK-004', 'Arabic Language Pathways', 'Hala Aoun', 'Arabic Language', 17.00, 30.00, 145],
            ['GAI-BK-005', 'Bonjour, Classe!', 'Claire Haddad', 'French Language', 18.00, 32.00, 130],
            ['GAI-BK-006', 'Our Communities', 'Nadim Saliba', 'Social Studies', 16.00, 29.00, 125],
            ['GAI-BK-007', 'Reading Adventures', 'Mira Saad', 'Literacy', 14.00, 26.00, 170],
            ['GAI-BK-008', 'Student Learning Planner', 'Global Academy International', 'Student support', 5.00, 12.00, 180],
        ];
        $books = collect();
        foreach ($bookCatalog as $index => [$sku, $title, $author, $subject, $purchasePrice, $sellingPrice, $stock]) {
            $book = Book::withTrashed()->firstOrNew(['sku' => $sku]);
            $book->fill([
                'isbn' => '978-1-9250-'.str_pad((string) ($index + 101), 4, '0', STR_PAD_LEFT),
                'barcode' => 'GAI-BAR-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'title' => $title, 'author' => $author, 'publisher' => 'Global Academy Press', 'edition' => '2026 Edition',
                'subject' => $subject, 'language' => in_array($subject, ['Arabic Language', 'French Language'], true) ? ($subject === 'Arabic Language' ? 'Arabic' : 'French') : 'English',
                'category' => 'Curriculum', 'purchase_price' => $purchasePrice, 'selling_price' => $sellingPrice, 'tax_rate' => 0,
                'minimum_stock' => 25, 'reserved_quantity' => 0, 'shelf_location' => 'Library A-'.($index + 1), 'is_active' => true,
                'notes' => 'Global Academy International curriculum stock.',
            ]);
            $book->stock_on_hand = $stock;
            $book->save();
            if ($book->trashed()) {
                $book->restore();
            }
            BranchBookStock::updateOrCreate(['branch_id' => $branch->id, 'book_id' => $book->id], ['quantity_on_hand' => $stock, 'reserved_quantity' => 0]);
            $books->push($book);
        }

        foreach ($sections as $class) {
            $list = ClassBookList::withoutGlobalScopes()->firstOrNew([
                'academic_year_id' => $year->id, 'grade_id' => $class['grade']->id, 'section_id' => $class['section']->id, 'name' => $class['grade']->name.' '.$class['section']->name.' learning pack',
            ]);
            $list->branch_id = $branch->id;
            $list->is_active = true;
            $list->save();
            foreach ($books->take(3) as $book) {
                ClassBookListItem::updateOrCreate(['class_book_list_id' => $list->id, 'book_id' => $book->id], [
                    'quantity' => 1, 'is_mandatory' => true, 'price_type' => 'paid', 'unit_price' => $book->selling_price, 'teacher_child_discount' => 0,
                ]);
            }
        }

        $suppliers = collect();
        foreach ([
            ['GAI-SUP-001', 'Cedar Educational Supplies', 'Mariam Daher', 'orders@cedareducational.example.test'],
            ['GAI-SUP-002', 'Levant Learning Press', 'Antoine Khoury', 'service@levantlearning.example.test'],
        ] as [$code, $name, $contact, $email]) {
            $suppliers->push(Supplier::withTrashed()->updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $contact, 'phone' => '+961 1 555 '.substr($code, -3), 'email' => $email,
                'address' => 'Beirut, Lebanon', 'payment_terms' => '30 days', 'opening_balance' => 0, 'is_active' => true,
                'notes' => 'Approved Global Academy International supplier.',
            ]));
        }
        foreach ($suppliers as $supplierIndex => $supplier) {
            $purchase = SupplierPurchase::withoutGlobalScopes()->firstOrNew(['purchase_number' => 'GAI-PO-2026-'.str_pad((string) ($supplierIndex + 1), 3, '0', STR_PAD_LEFT)]);
            $purchase->branch_id = $branch->id;
            $purchase->fill([
                'supplier_id' => $supplier->id, 'academic_year_id' => $year->id, 'purchase_date' => $year->starts_on->copy()->addDays(4 + $supplierIndex),
                'supplier_invoice_number' => 'SUP-2026-'.str_pad((string) ($supplierIndex + 1), 3, '0', STR_PAD_LEFT), 'subtotal' => 2500,
                'discount_total' => 100, 'tax_total' => 0, 'total' => 2400, 'amount_paid' => $supplierIndex ? 1200 : 2400,
                'payment_status' => $supplierIndex ? 'partial' : 'paid', 'status' => 'received', 'notes' => 'Opening curriculum stock purchase.', 'created_by' => $administrator->id,
            ]);
            $purchase->save();
            foreach ($books->slice($supplierIndex * 4, 4) as $book) {
                SupplierPurchaseItem::updateOrCreate(['supplier_purchase_id' => $purchase->id, 'book_id' => $book->id], [
                    'quantity_ordered' => 50, 'quantity_received' => 50, 'unit_price' => $book->purchase_price, 'discount_amount' => 0, 'tax_rate' => 0, 'line_total' => $book->purchase_price * 50,
                ]);
            }
            $supplierPayment = SupplierPayment::withoutGlobalScopes()->firstOrNew(['supplier_purchase_id' => $purchase->id, 'reference_number' => 'GAI-SUP-PAY-'.str_pad((string) ($supplierIndex + 1), 3, '0', STR_PAD_LEFT)]);
            $supplierPayment->branch_id = $branch->id;
            $supplierPayment->fill(['amount' => $purchase->amount_paid, 'paid_on' => $purchase->purchase_date, 'method' => 'bank_transfer', 'received_by' => $administrator->id]);
            $supplierPayment->save();
        }

        foreach ($students->values() as $index => $studentData) {
            $book = $books->get($index % $books->count());
            $tuition = 2200 + ($studentData['class']['grade']->sort_order * 50);
            $total = $tuition + (float) $book->selling_price;
            $amountPaid = $index % 11 === 0 ? 0 : ($index % 7 === 0 ? $total / 2 : $total);
            $status = $amountPaid === 0.0 ? 'unpaid' : ($amountPaid < $total ? 'partial' : 'paid');
            $invoice = Invoice::withoutGlobalScopes()->firstOrNew(['invoice_number' => 'GAI-INV-2026-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)]);
            $invoice->branch_id = $branch->id;
            $invoice->fill([
                'student_id' => $studentData['student']->id, 'student_registration_id' => $studentData['registration']->id,
                'responsible_person_id' => ResponsiblePerson::query()->where('parent_code', 'GAI-GDN-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT))->value('id'),
                'academic_year_id' => $year->id, 'grade_id' => $studentData['class']['grade']->id, 'section_id' => $studentData['class']['section']->id,
                'invoice_date' => $year->starts_on, 'due_date' => $year->starts_on->copy()->addDays(30), 'subtotal' => $total, 'discount_total' => 0,
                'tax_total' => 0, 'total' => $total, 'amount_paid' => $amountPaid, 'balance_due' => $total - $amountPaid,
                'payment_status' => $status, 'status' => 'issued', 'notes' => 'Academic tuition and curriculum materials.', 'created_by' => $administrator->id, 'approved_by' => $administrator->id,
            ]);
            $invoice->save();
            InvoiceItem::updateOrCreate(['invoice_id' => $invoice->id, 'description' => 'Academic tuition — Term 1'], [
                'book_id' => null, 'quantity' => 1, 'unit_price' => $tuition, 'discount_amount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $tuition,
            ]);
            InvoiceItem::updateOrCreate(['invoice_id' => $invoice->id, 'description' => $book->title], [
                'book_id' => $book->id, 'quantity' => 1, 'unit_price' => $book->selling_price, 'discount_amount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $book->selling_price,
            ]);
            if ($amountPaid > 0) {
                $payment = Payment::withoutGlobalScopes()->firstOrNew(['payment_number' => 'GAI-RCP-2026-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)]);
                $payment->branch_id = $branch->id;
                $payment->fill([
                    'invoice_id' => $invoice->id, 'student_id' => $studentData['student']->id, 'payment_date' => $year->starts_on->copy()->addDays(7 + ($index % 10)),
                    'amount' => $amountPaid, 'method' => $index % 3 ? 'bank_transfer' : 'cash', 'cashbox' => $index % 3 ? null : 'Main Cashier',
                    'reference_number' => 'GAI-REF-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), 'status' => 'valid',
                    'idempotency_key' => $payment->idempotency_key ?: (string) Str::uuid(), 'notes' => 'Tuition receipt.', 'received_by' => $administrator->id,
                ]);
                $payment->save();
            }
            if ($index < 40) {
                $distribution = BookDistribution::withoutGlobalScopes()->firstOrNew(['student_id' => $studentData['student']->id, 'invoice_id' => $invoice->id]);
                $distribution->branch_id = $branch->id;
                $distribution->fill([
                    'student_registration_id' => $studentData['registration']->id, 'academic_year_id' => $year->id, 'grade_id' => $studentData['class']['grade']->id,
                    'section_id' => $studentData['class']['section']->id, 'distributed_on' => $year->starts_on->copy()->addDays(8 + ($index % 5)),
                    'status' => 'completed', 'notes' => 'Curriculum pack issued to student.', 'distributed_by' => $administrator->id,
                ]);
                $distribution->save();
                BookDistributionItem::updateOrCreate(['book_distribution_id' => $distribution->id, 'book_id' => $book->id], [
                    'quantity' => 1, 'unit_price' => $book->selling_price, 'discount_amount' => 0, 'tax_rate' => 0, 'line_total' => $book->selling_price, 'reason' => 'Term 1 curriculum pack',
                ]);
            }
        }

        $this->command?->info('Global Academy International sample data is ready: 50 students with academic, attendance, timetable, calendar, library, inventory, billing, and collaboration records.');
    }

    private function removeLegacyDemoRecords(): void
    {
        $studentIds = DB::table('students')->where('student_number', 'like', 'DEMO-%')->pluck('id');
        if ($studentIds->isNotEmpty()) {
            DB::table('student_attendances')->whereIn('student_id', $studentIds)->delete();
            DB::table('grade_entries')->whereIn('student_id', $studentIds)->delete();
            DB::table('exam_absences')->whereIn('student_id', $studentIds)->delete();
            DB::table('student_incidents')->whereIn('student_id', $studentIds)->delete();
            DB::table('medical_records')->whereIn('student_id', $studentIds)->delete();
            DB::table('student_performance_evaluations')->whereIn('student_id', $studentIds)->delete();
            DB::table('responsible_person_student')->whereIn('student_id', $studentIds)->delete();
            DB::table('student_registrations')->whereIn('student_id', $studentIds)->delete();
            DB::table('students')->whereIn('id', $studentIds)->delete();
        }
        DB::table('responsible_persons')->where('parent_code', 'like', 'DEMO-%')->delete();
        $assessmentIds = DB::table('assessments')->where('name', 'like', 'Demo %')->pluck('id');
        if ($assessmentIds->isNotEmpty()) {
            DB::table('exam_absences')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('grade_entries')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('assessments')->whereIn('id', $assessmentIds)->delete();
        }
        $assignmentIds = DB::table('teacher_assignments')->where('notes', 'like', 'Demo%')->pluck('id');
        if ($assignmentIds->isNotEmpty()) {
            DB::table('timetable_entries')->whereIn('teacher_assignment_id', $assignmentIds)->delete();
            DB::table('course_contents')->whereIn('teacher_assignment_id', $assignmentIds)->delete();
            DB::table('teacher_assignments')->whereIn('id', $assignmentIds)->delete();
        }
        DB::table('subjects')->where('code', 'DEMO-ENG')->delete();
        DB::table('teachers')->where('employee_number', 'DEMO-TEACH-001')->delete();
        DB::table('action_types')->where('name', 'Demo follow-up')->delete();
        DB::table('action_categories')->where('name', 'Demo wellbeing')->delete();
        $demoLevelIds = DB::table('school_levels')->where('name', 'SBM Demo')->pluck('id');
        if ($demoLevelIds->isNotEmpty()) {
            $demoGradeIds = DB::table('grades')->whereIn('school_level_id', $demoLevelIds)->pluck('id');
            DB::table('sections')->whereIn('grade_id', $demoGradeIds)->delete();
            DB::table('grades')->whereIn('id', $demoGradeIds)->delete();
            DB::table('school_levels')->whereIn('id', $demoLevelIds)->delete();
        }
    }
}
