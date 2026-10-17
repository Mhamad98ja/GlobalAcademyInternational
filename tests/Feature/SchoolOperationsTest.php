<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentModificationRequest;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $role = Role::create(['name' => 'super-administrator', 'label' => 'Super Administrator']);
        $user = User::factory()->create(['is_active' => true, 'is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $user->roles()->attach($role);

        return $user;
    }

    /** @return array{0:AcademicYear,1:Grade,2:Section,3:StudentRegistration} */
    private function classRegistration(User $administrator): array
    {
        $year = AcademicYear::create(['name' => '2028-2029', 'starts_on' => '2028-09-01', 'ends_on' => '2029-06-30', 'status' => 'open', 'created_by' => $administrator->id]);
        $level = SchoolLevel::create(['name' => 'Operations level']);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Operations Grade']);
        $section = Section::create(['grade_id' => $grade->id, 'name' => 'A']);
        AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Active Term', 'starts_on' => '2028-09-01', 'ends_on' => '2028-12-31', 'status' => 'open', 'sort_order' => 1]);
        $student = Student::create(['student_number' => 'OPS-001', 'first_name' => 'Lina', 'last_name' => 'Haddad']);
        $registration = StudentRegistration::create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'status' => 'active', 'registered_on' => '2028-09-01', 'created_by' => $administrator->id]);

        return [$year, $grade, $section, $registration];
    }

    public function test_bulk_student_attendance_is_saved_against_the_existing_registration(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);

        $this->actingAs($administrator)->post(route('attendance.students.save'), [
            'academic_year_id' => $year->id,
            'attendance_date' => '2028-09-04',
            'period_number' => 0,
            'entries' => [[
                'registration_id' => $registration->id,
                'status' => 'late',
                'notes' => 'Bus delay',
            ]],
        ])->assertRedirect(route('attendance.students', ['academic_year_id' => $year->id, 'attendance_date' => '2028-09-04']));

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $registration->student_id,
            'student_registration_id' => $registration->id,
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'status' => 'late',
        ]);
    }

    public function test_attendance_can_be_saved_for_only_the_checked_students(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $firstRegistration] = $this->classRegistration($administrator);
        $secondStudent = Student::create(['student_number' => 'OPS-002', 'first_name' => 'Omar', 'last_name' => 'Haddad']);
        $secondRegistration = StudentRegistration::create(['student_id' => $secondStudent->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'status' => 'active', 'registered_on' => '2028-09-01', 'created_by' => $administrator->id]);

        $this->actingAs($administrator)->post(route('attendance.students.save'), [
            'academic_year_id' => $year->id,
            'attendance_date' => '2028-09-05',
            'entries' => [
                ['registration_id' => $firstRegistration->id, 'selected' => 0, 'status' => 'absent'],
                ['registration_id' => $secondRegistration->id, 'selected' => 1, 'status' => 'present'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('student_attendances', ['student_registration_id' => $firstRegistration->id, 'attendance_date' => '2028-09-05']);
        $this->assertDatabaseHas('student_attendances', ['student_registration_id' => $secondRegistration->id, 'attendance_date' => '2028-09-05 00:00:00', 'status' => 'present']);
    }

    public function test_teacher_grade_entry_is_limited_to_their_course_assignment(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $subject = Subject::create(['name' => 'Mathematics']);
        $teacher = Teacher::create(['first_name' => 'Ahmad', 'last_name' => 'Teacher', 'is_active' => true]);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $assessment = Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => AcademicTerm::where('academic_year_id', $year->id)->value('id'), 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Midterm', 'type' => 'exam', 'maximum_score' => 100, 'status' => 'open']);
        $permission = Permission::firstOrCreate(['name' => 'grades.enter'], ['label' => 'Enter grades']);
        $role = Role::create(['name' => 'teacher-user', 'label' => 'Teacher']);
        $role->permissions()->attach($permission);
        $teacherUser = User::factory()->create(['is_active' => true, 'teacher_id' => $teacher->id]);
        $teacherUser->roles()->attach($role);

        $this->actingAs($teacherUser)->get(route('grades.index', ['teacher_assignment_id' => $assignment->id]))
            ->assertOk()
            ->assertSee('sidebar-link-label">Dashboard</span>', false)
            ->assertSee('sidebar-link-label">Evaluation</span>', false)
            ->assertSee('sidebar-link-label">New evaluation</span>', false)
            ->assertDontSee('sidebar-link-label">Grade Book</span>', false)
            ->assertDontSee('sidebar-link-label">Evaluation Requests</span>', false)
            ->assertDontSee('sidebar-link-label">My Requests</span>', false)
            ->assertDontSee('Grade book')
            ->assertSee('New evaluation')
            ->assertSee('Midterm');

        $this->actingAs($teacherUser)->post(route('grades.save'), [
            'teacher_assignment_id' => $assignment->id,
            'assessment_id' => $assessment->id,
            'save_as' => 'draft',
            'entries' => [[
                'registration_id' => $registration->id,
                'score' => 84.5,
                'notes' => 'Good work',
            ]],
        ])->assertRedirect(route('grades.index', ['teacher_assignment_id' => $assignment->id, 'assessment_id' => $assessment->id]));

        $this->assertDatabaseHas('grade_entries', ['student_id' => $registration->student_id, 'assessment_id' => $assessment->id, 'teacher_id' => $teacher->id, 'score' => 84.5, 'status' => 'draft']);

        $otherTeacher = Teacher::create(['first_name' => 'Maya', 'last_name' => 'Teacher', 'is_active' => true]);
        $otherUser = User::factory()->create(['is_active' => true, 'teacher_id' => $otherTeacher->id]);
        $otherUser->roles()->attach($role);
        $this->actingAs($otherUser)->post(route('grades.save'), [
            'teacher_assignment_id' => $assignment->id,
            'assessment_id' => $assessment->id,
            'save_as' => 'draft',
            'entries' => [['registration_id' => $registration->id, 'score' => 90]],
        ])->assertForbidden();
    }

    public function test_student_support_and_subject_exemptions_are_recorded_on_the_student_profile(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $supportUser = User::factory()->create(['name' => 'Dr. Support', 'email' => 'support@example.test', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Music']);
        TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => Teacher::create(['first_name' => 'Music', 'last_name' => 'Teacher', 'is_active' => true])->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('students.support.store', $registration->student), ['user_id' => $supportUser->id, 'role' => 'Psychologist', 'notes' => 'Weekly wellbeing check-in'])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->post(route('students.subject-exemptions.store', $registration->student), ['student_registration_id' => $registration->id, 'subject_id' => $subject->id, 'reason' => 'Approved learning plan'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_support_assignments', ['student_id' => $registration->student_id, 'user_id' => $supportUser->id, 'role' => 'Psychologist']);
        $this->assertDatabaseHas('student_subject_exemptions', ['student_id' => $registration->student_id, 'student_registration_id' => $registration->id, 'subject_id' => $subject->id]);
    }

    public function test_exempt_student_is_excluded_from_grade_roster_and_cannot_receive_a_grade(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $subject = Subject::create(['name' => 'Exempt Music']);
        $teacher = Teacher::create(['first_name' => 'Music', 'last_name' => 'Teacher', 'is_active' => true]);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $assessment = Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => AcademicTerm::where('academic_year_id', $year->id)->value('id'), 'teacher_assignment_id' => $assignment->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Music quiz', 'type' => 'quiz', 'maximum_score' => 10, 'status' => 'open']);
        $this->actingAs($administrator)->post(route('students.subject-exemptions.store', $registration->student), ['student_registration_id' => $registration->id, 'subject_id' => $subject->id])->assertSessionHasNoErrors();

        $this->post(route('collaboration.course-content.store'), ['teacher_assignment_id' => $assignment->id, 'title' => 'Exempt music agenda', 'content_type' => 'homework', 'publish_now' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $registration->student->notifications()->count());

        $this->get(route('grades.index', ['teacher_assignment_id' => $assignment->id, 'assessment_id' => $assessment->id]))->assertOk()->assertDontSee('Lina Haddad');
        $this->post(route('grades.save'), ['teacher_assignment_id' => $assignment->id, 'assessment_id' => $assessment->id, 'save_as' => 'draft', 'entries' => [['registration_id' => $registration->id, 'score' => 9]]])->assertSessionHasErrors('entries');
        $this->assertDatabaseMissing('grade_entries', ['student_registration_id' => $registration->id, 'assessment_id' => $assessment->id]);
    }

    public function test_agenda_supports_uploaded_resources_and_online_meetings(): void
    {
        Storage::fake('local');
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Agenda', 'last_name' => 'Teacher', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('collaboration.course-content.store'), [
            'teacher_assignment_id' => $assignment->id,
            'title' => 'Read the science guide',
            'content_type' => 'document',
            'attachment' => UploadedFile::fake()->create('science-guide.pdf', 25, 'application/pdf'),
            'publish_now' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->post(route('collaboration.events.store'), [
            'title' => 'Family science meeting',
            'audience' => 'section',
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'starts_at' => '2028-09-10 16:00',
            'meeting_platform' => 'zoom',
            'meeting_url' => 'https://zoom.us/j/12345',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('course_contents', ['teacher_assignment_id' => $assignment->id, 'attachment_name' => 'science-guide.pdf']);
        $this->assertDatabaseHas('school_events', ['title' => 'Family science meeting', 'meeting_platform' => 'zoom']);
    }

    public function test_teacher_agenda_is_limited_to_their_subject_assignments(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $ownTeacher = Teacher::create(['first_name' => 'Own', 'last_name' => 'Teacher', 'is_active' => true]);
        $otherTeacher = Teacher::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'is_active' => true]);
        $ownSubject = Subject::create(['name' => 'Own Subject']);
        $otherSubject = Subject::create(['name' => 'Other Subject']);
        TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $ownTeacher->id, 'subject_id' => $ownSubject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $otherTeacher->id, 'subject_id' => $otherSubject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $view = Permission::firstOrCreate(['name' => 'collaboration.view'], ['label' => 'View collaboration']);
        $manage = Permission::firstOrCreate(['name' => 'collaboration.manage'], ['label' => 'Manage collaboration']);
        $role = Role::create(['name' => 'agenda-teacher', 'label' => 'Agenda teacher']);
        $role->permissions()->attach([$view->id, $manage->id]);
        $teacherUser = User::factory()->create(['is_active' => true, 'teacher_id' => $ownTeacher->id]);
        $teacherUser->roles()->attach($role);

        $this->actingAs($teacherUser)->get(route('collaboration.index', ['section' => 'homework']))
            ->assertOk()
            ->assertSee('My teaching agenda')
            ->assertSee('Own Subject')
            ->assertDontSee('Other Subject')
            ->assertSee('data-agenda-day');
    }

    public function test_evaluation_dashboard_groups_subject_assessments_by_academic_term_and_type(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Evaluation', 'last_name' => 'Teacher', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Evaluation Mathematics']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $termOne = AcademicTerm::where('academic_year_id', $year->id)->firstOrFail();
        $termOne->update(['name' => 'Term 1', 'starts_on' => '2028-09-01', 'ends_on' => '2028-12-31', 'status' => 'open', 'sort_order' => 1]);
        $termTwo = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Term 2', 'starts_on' => '2029-01-01', 'ends_on' => '2029-06-30', 'status' => 'planned', 'sort_order' => 2]);
        Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => $termOne->id, 'teacher_assignment_id' => $assignment->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Math project', 'assessment_column' => '1 - Cntr1', 'type' => 'project', 'maximum_score' => 20, 'status' => 'open']);
        Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => $termTwo->id, 'teacher_assignment_id' => $assignment->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Math quiz', 'assessment_column' => '2 - Cntr2', 'type' => 'quiz', 'maximum_score' => 10, 'status' => 'open']);

        $this->actingAs($administrator)->get(route('collaboration.index', ['section' => 'evaluation-dashboard']))
            ->assertOk()
            ->assertSee('Evaluation overview')
            ->assertSee('Evaluation Mathematics')
            ->assertDontSee('Cntr1')
            ->assertDontSee('Cntr2')
            ->assertSee('Project')
            ->assertSee('Quiz')
            ->assertSee('Term 1')
            ->assertDontSee('Term 2')
            ->assertSee('data-evaluation-tile', false)
            ->assertSee('dars-evaluation-tile-project', false)
            ->assertSee('dars-evaluation-tile-quiz', false);
    }

    public function test_teacher_can_request_modification_of_a_submitted_evaluation_after_admin_approval(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Request', 'last_name' => 'Teacher', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Request Mathematics']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $role = Role::create(['name' => 'modification-teacher', 'label' => 'Modification teacher']);
        $role->permissions()->attach([
            Permission::firstOrCreate(['name' => 'grades.enter'], ['label' => 'Enter grades'])->id,
            Permission::firstOrCreate(['name' => 'collaboration.view'], ['label' => 'View collaboration'])->id,
        ]);
        $teacherUser = User::factory()->create(['is_active' => true, 'teacher_id' => $teacher->id]);
        $teacherUser->roles()->attach($role);
        $assessment = Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => AcademicTerm::where('academic_year_id', $year->id)->value('id'), 'teacher_assignment_id' => $assignment->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Submitted request test', 'type' => 'test', 'maximum_score' => 20, 'status' => 'draft', 'review_status' => 'submitted']);

        $this->actingAs($teacherUser)->get(route('collaboration.section', ['section' => 'modification-requests']))
            ->assertOk()->assertSee('Submitted request test')->assertSee('Send request');
        $this->actingAs($teacherUser)->post(route('collaboration.modification-requests.store'), ['assessment_id' => $assessment->id, 'reason' => 'Correct a student grade entry.'])
            ->assertRedirect(route('collaboration.section', ['section' => 'modification-requests']));
        $modificationRequest = AssessmentModificationRequest::query()->firstOrFail();
        $this->assertSame('pending', $modificationRequest->status);

        $this->actingAs($administrator)->get(route('collaboration.section', ['section' => 'modification-requests']))
            ->assertOk()->assertSee('Correct a student grade entry.')->assertSee('Approve');
        $this->actingAs($administrator)->post(route('collaboration.modification-requests.approve', $modificationRequest))
            ->assertRedirect();
        $this->actingAs($teacherUser)->get(route('grades.assessments.edit', $assessment))
            ->assertOk()->assertSee('Edit evaluation');
        $this->actingAs($teacherUser)->post(route('grades.save'), [
            'teacher_assignment_id' => $assignment->id,
            'assessment_id' => $assessment->id,
            'save_as' => 'draft',
            'entries' => [['registration_id' => $registration->id, 'score' => 18]],
        ])->assertRedirect();
        $this->assertDatabaseHas('grade_entries', ['assessment_id' => $assessment->id, 'student_registration_id' => $registration->id, 'score' => 18]);
    }

    public function test_teachers_manage_their_evaluation_index_and_submit_student_grades_for_review(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Own', 'last_name' => 'Evaluator', 'is_active' => true]);
        $otherTeacher = Teacher::create(['first_name' => 'Other', 'last_name' => 'Evaluator', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Evaluation Science']);
        $otherSubject = Subject::create(['name' => 'Evaluation History']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $otherAssignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $otherTeacher->id, 'subject_id' => $otherSubject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => AcademicTerm::where('academic_year_id', $year->id)->value('id'), 'teacher_assignment_id' => $otherAssignment->id, 'subject_id' => $otherSubject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Other teacher saved evaluation', 'type' => 'quiz', 'maximum_score' => 10, 'status' => 'draft', 'review_status' => 'draft']);
        $permission = Permission::firstOrCreate(['name' => 'grades.enter'], ['label' => 'Enter grades']);
        $role = Role::create(['name' => 'evaluation-teacher', 'label' => 'Evaluation teacher']);
        $role->permissions()->attach($permission);
        $teacherUser = User::factory()->create(['is_active' => true, 'teacher_id' => $teacher->id]);
        $teacherUser->roles()->attach($role);

        $this->actingAs($teacherUser)->get(route('grades.assessments.create', ['teacher_assignment_id' => $assignment->id]))
            ->assertOk()
            ->assertSee('New evaluation')
            ->assertSee('Evaluation Science');
        $this->actingAs($teacherUser)->post(route('grades.assessments.store'), [
            'teacher_assignment_id' => $assignment->id,
            'name' => 'Science monthly test',
            'type' => 'monthly_test',
            'academic_term_id' => AcademicTerm::where('academic_year_id', $year->id)->value('id'),
            'maximum_score' => 20,
            'weight' => 1,
            'assessment_date' => '2028-10-10',
            'notes' => 'Monthly science review.',
        ])->assertRedirect();
        $assessment = Assessment::query()->where('name', 'Science monthly test')->firstOrFail();

        $this->actingAs($teacherUser)->get(route('grades.index', ['view' => 'evaluations']))
            ->assertOk()
            ->assertSee('Science monthly test')
            ->assertDontSee('Other teacher saved evaluation')
            ->assertSee('Saved');
        $this->actingAs($teacherUser)->get(route('grades.index', ['view' => 'grade-book', 'teacher_assignment_id' => $assignment->id, 'assessment_id' => $assessment->id]))
            ->assertOk()
            ->assertSee($registration->student->full_name)
            ->assertSee('Submit to administration');
        $this->actingAs($teacherUser)->post(route('grades.save'), [
            'teacher_assignment_id' => $assignment->id,
            'assessment_id' => $assessment->id,
            'save_as' => 'draft',
            'entries' => [['registration_id' => $registration->id, 'score' => 15]],
        ])->assertRedirect();
        $this->assertDatabaseHas('grade_entries', ['assessment_id' => $assessment->id, 'student_registration_id' => $registration->id, 'score' => 15, 'status' => 'draft']);

        $this->actingAs($teacherUser)->post(route('grades.save'), [
            'teacher_assignment_id' => $assignment->id,
            'assessment_id' => $assessment->id,
            'save_as' => 'draft',
            'submit_for_review' => 1,
            'entries' => [['registration_id' => $registration->id, 'score' => 16]],
        ])->assertSessionHas('success', 'Evaluation submitted to administration.');
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'review_status' => 'submitted']);

        $this->actingAs($administrator)->get(route('grades.index', ['view' => 'evaluations']))
            ->assertOk()
            ->assertSee('Science monthly test')
            ->assertSee('Other teacher saved evaluation')
            ->assertSee('Own Evaluator')
            ->assertSee('Other Evaluator')
            ->assertSee('Submitted')
            ->assertSee('Saved');
    }

    public function test_timetable_rejects_a_teacher_double_booking(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Samir', 'last_name' => 'Teacher', 'is_active' => true]);
        $mathematics = Subject::create(['name' => 'Mathematics']);
        $science = Subject::create(['name' => 'Science']);
        $first = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $mathematics->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $second = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $science->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), ['teacher_assignment_id' => $first->id, 'day_of_week' => 'monday', 'period_number' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('timetable_entries', 1);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), ['teacher_assignment_id' => $second->id, 'day_of_week' => 'monday', 'period_number' => 1])->assertSessionHasErrors('period_number');
        $this->assertDatabaseCount('timetable_entries', 1);
    }

    public function test_timetable_rejects_parallel_lessons_even_when_requested(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $firstTeacher = Teacher::create(['first_name' => 'Samir', 'last_name' => 'Group A', 'is_active' => true]);
        $secondTeacher = Teacher::create(['first_name' => 'Maya', 'last_name' => 'Group B', 'is_active' => true]);
        $firstSubject = Subject::create(['name' => 'Mathematics']);
        $secondSubject = Subject::create(['name' => 'Science']);
        $first = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $firstTeacher->id, 'subject_id' => $firstSubject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $second = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $secondTeacher->id, 'subject_id' => $secondSubject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $first->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $second->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
            'is_parallel' => true,
        ])->assertSessionHasErrors('is_parallel');

        $this->assertDatabaseCount('timetable_entries', 1);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $second->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
        ])->assertSessionHasErrors('period_number');
        $this->assertDatabaseCount('timetable_entries', 1);

        $existingLesson = TimetableEntry::query()->where('teacher_assignment_id', $first->id)->firstOrFail();
        $this->actingAs($administrator)->delete(route('timetable.entries.destroy', $existingLesson))
            ->assertRedirect();
        $this->assertDatabaseCount('timetable_entries', 0);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $second->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('timetable_entries', [
            'teacher_assignment_id' => $second->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
        ]);
    }

    public function test_timetable_uses_sunday_through_thursday_and_keeps_period_setup_editable(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Sunday', 'is_active' => true]);
        $subject = Subject::create(['name' => 'History']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('timetable.periods.store'), [
            'academic_year_id' => $year->id,
            'period_number' => 1,
            'label' => 'Morning lesson',
            'starts_at' => '08:00',
            'ends_at' => '08:45',
            'kind' => 'lesson',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('timetable_periods', ['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Morning lesson']);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $assignment->id,
            'day_of_week' => 'sunday',
            'period_number' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $assignment->id,
            'day_of_week' => 'friday',
            'period_number' => 1,
        ])->assertSessionHasErrors('day_of_week');

        $this->actingAs($administrator)->get(route('timetable.index', [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
        ]))->assertOk()
            ->assertSee('<th>Sunday</th>', false)
            ->assertSee('<th>Thursday</th>', false)
            ->assertDontSee('<th>Friday</th>', false)
            ->assertSee('Period setup')
            ->assertSee('Morning lesson')
            ->assertDontSee('Allow parallel');
    }

    public function test_existing_timetable_period_times_and_lessons_can_be_edited(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Editor', 'is_active' => true]);
        $subject = Subject::create(['name' => 'History']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $period = TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Period 1', 'starts_at' => '08:00', 'ends_at' => '08:45', 'kind' => 'lesson']);
        TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 2, 'label' => 'Period 2', 'starts_at' => '09:00', 'ends_at' => '09:45', 'kind' => 'lesson']);

        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $assignment->id,
            'section_id' => $section->id,
            'day_of_week' => 'sunday',
            'period_number' => 1,
        ])->assertSessionHasNoErrors();
        $entry = TimetableEntry::query()->where('teacher_assignment_id', $assignment->id)->firstOrFail();

        $this->actingAs($administrator)->get(route('timetable.index', [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
        ]))->assertOk()->assertSee('Edit existing periods')->assertSee('Edit lesson')
            ->assertSee('value="08:00"', false)
            ->assertSee('action="'.route('timetable.entries.update', $entry).'"', false);

        $this->actingAs($administrator)->post(route('timetable.periods.store'), [
            'academic_year_id' => $year->id,
            'period_number' => $period->period_number,
            'label' => 'Morning lesson',
            'starts_at' => '08:15',
            'ends_at' => '09:00',
            'kind' => 'lesson',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('timetable_periods', ['id' => $period->id, 'label' => 'Morning lesson', 'starts_at' => '08:15', 'ends_at' => '09:00']);

        $this->actingAs($administrator)->put(route('timetable.entries.update', $entry), [
            'teacher_assignment_id' => $assignment->id,
            'section_id' => $section->id,
            'day_of_week' => 'monday',
            'period_number' => 2,
            'room' => 'Lab 1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id, 'day_of_week' => 'monday', 'period_number' => 2, 'room' => 'Lab 1']);
        $this->assertDatabaseMissing('timetable_entries', ['id' => $entry->id, 'day_of_week' => 'sunday', 'period_number' => 1]);

        $this->actingAs($administrator)->put(route('timetable.entries.update', $entry), [
            'teacher_assignment_id' => $assignment->id,
            'section_id' => $section->id,
            'day_of_week' => 'sunday',
            'period_number' => 1,
            'room' => 'Lab 1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id, 'day_of_week' => 'sunday', 'period_number' => 1]);
    }

    public function test_deleting_a_period_removes_its_whole_row_across_classes(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $secondSection = Section::create(['grade_id' => $grade->id, 'name' => 'B']);
        $period = TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Morning', 'kind' => 'lesson']);
        $otherPeriod = TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 2, 'label' => 'Afternoon', 'kind' => 'lesson']);
        $subject = Subject::create(['name' => 'Mathematics']);

        foreach ([$section, $secondSection] as $index => $classSection) {
            $teacher = Teacher::create(['first_name' => 'Teacher', 'last_name' => (string) $index, 'is_active' => true]);
            $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $classSection->id, 'is_active' => true]);
            $this->actingAs($administrator)->post(route('timetable.entries.store'), [
                'teacher_assignment_id' => $assignment->id,
                'section_id' => $classSection->id,
                'day_of_week' => 'sunday',
                'period_number' => 1,
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($administrator)->get(route('timetable.index', [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
        ]))->assertOk()->assertSee('Delete entire period')->assertSee('2 scheduled lessons');

        $this->actingAs($administrator)->delete(route('timetable.periods.destroy', $period))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Timetable period and 2 scheduled lessons removed.');
        $this->assertDatabaseMissing('timetable_periods', ['id' => $period->id]);
        $this->assertDatabaseHas('timetable_periods', ['id' => $otherPeriod->id]);
        $this->assertDatabaseCount('timetable_entries', 0);
    }

    public function test_a_period_with_substitution_history_cannot_be_deleted(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Substitute', 'is_active' => true]);
        $subject = Subject::create(['name' => 'History']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $period = TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Morning', 'kind' => 'lesson']);
        $this->actingAs($administrator)->post(route('timetable.entries.store'), [
            'teacher_assignment_id' => $assignment->id,
            'section_id' => $section->id,
            'day_of_week' => 'sunday',
            'period_number' => 1,
        ])->assertSessionHasNoErrors();
        $entry = TimetableEntry::query()->firstOrFail();
        TeacherSubstitution::create(['substitution_date' => '2028-09-10', 'timetable_entry_id' => $entry->id, 'absent_teacher_id' => $teacher->id, 'status' => 'pending']);

        $this->actingAs($administrator)->delete(route('timetable.periods.destroy', $period))->assertSessionHasErrors('period');
        $this->assertDatabaseHas('timetable_periods', ['id' => $period->id]);
        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id]);
    }

    public function test_timetable_displays_legacy_capitalized_day_entries_in_the_correct_slot(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Legacy', 'is_active' => true]);
        $subject = Subject::create(['name' => 'History']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $entry = TimetableEntry::create([
            'academic_year_id' => $year->id,
            'teacher_assignment_id' => $assignment->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'day_of_week' => 'Monday',
            'period_number' => 1,
        ]);
        TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Period 1', 'kind' => 'lesson']);

        $this->actingAs($administrator)->get(route('timetable.index', [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
        ]))->assertOk()->assertSee('data-existing-entry-id="'.$entry->public_id.'"', false);
    }

    public function test_timetable_generator_distributes_assignment_lessons_for_the_selected_class(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Planner', 'is_active' => true]);
        $math = Subject::create(['name' => 'Mathematics']);
        $science = Subject::create(['name' => 'Science']);
        TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $math->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'required_periods_per_week' => 2, 'is_active' => true]);
        TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $science->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'required_periods_per_week' => 2, 'is_active' => true]);
        foreach (range(1, 4) as $period) {
            TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => $period, 'label' => 'Period '.$period, 'kind' => 'lesson']);
        }

        $this->actingAs($administrator)->post(route('timetable.generate'), [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entries = TimetableEntry::query()->where('academic_year_id', $year->id)->where('section_id', $section->id)->get();
        $this->assertCount(4, $entries);
        $this->assertCount(4, $entries->map(fn (TimetableEntry $entry) => $entry->day_of_week.'-'.$entry->period_number)->unique());
    }

    public function test_timetable_generate_control_reflects_missing_lessons_after_period_setup(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Planner', 'is_active' => true]);
        $subject = Subject::create(['name' => 'History']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'required_periods_per_week' => 0, 'is_active' => true]);
        TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Morning', 'kind' => 'lesson']);
        $url = route('timetable.index', ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id]);
        $generation = ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id];

        $this->actingAs($administrator)->get($url)->assertOk()
            ->assertSee('Period times are ready. Set required periods per week')
            ->assertDontSee('Generate the missing lessons for this class?');
        $this->actingAs($administrator)->post(route('timetable.generate'), $generation)
            ->assertSessionHas('error');

        $assignment->update(['required_periods_per_week' => 1]);
        $this->actingAs($administrator)->get($url)->assertOk()
            ->assertSee('Generate 1 missing lesson')
            ->assertSee('1 weekly lesson remains across 5 empty slots');
        $this->actingAs($administrator)->post(route('timetable.generate'), $generation)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('timetable_entries', 1);

        $this->actingAs($administrator)->get($url)->assertOk()
            ->assertSee('All required weekly lessons are scheduled for this class.')
            ->assertDontSee('Generate 1 missing lesson');
        $this->actingAs($administrator)->post(route('timetable.generate'), $generation)
            ->assertSessionHas('success', 'All required weekly lessons are already scheduled for this class.');
        $this->assertDatabaseCount('timetable_entries', 1);

        $assignment->update(['required_periods_per_week' => 6]);
        $this->actingAs($administrator)->post(route('timetable.generate'), $generation)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('timetable_entries', 5);
        $this->actingAs($administrator)->get($url)->assertOk()
            ->assertSee('every lesson slot is occupied')
            ->assertDontSee('Generate 1 missing lesson');
        $this->actingAs($administrator)->post(route('timetable.generate'), $generation)
            ->assertSessionHas('error', 'No empty lesson periods remain for this class. Move or remove a lesson, or add more lesson periods.');
    }

    public function test_timetable_generation_counts_required_lessons_separately_for_each_section(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);
        $secondSection = Section::create(['grade_id' => $grade->id, 'name' => 'B']);
        $teacher = Teacher::create(['first_name' => 'Nour', 'last_name' => 'Shared', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science']);
        TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => null, 'required_periods_per_week' => 1, 'is_active' => true]);
        TimetablePeriod::create(['academic_year_id' => $year->id, 'period_number' => 1, 'label' => 'Morning', 'kind' => 'lesson']);

        foreach ([$section, $secondSection] as $classSection) {
            $this->actingAs($administrator)->post(route('timetable.generate'), [
                'academic_year_id' => $year->id,
                'grade_id' => $grade->id,
                'section_id' => $classSection->id,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('timetable_entries', 2);
        $this->assertDatabaseHas('timetable_entries', ['section_id' => $section->id]);
        $this->assertDatabaseHas('timetable_entries', ['section_id' => $secondSection->id]);
    }

    public function test_school_operations_screens_render_with_the_existing_navigation_context(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()->assertSee('Student Profile')->assertSee('Report Card')->assertSee('Administration')->assertSee('Online Collaboration');
        $this->actingAs($administrator)->get(route('workspaces.show', 'students'))->assertOk()->assertSee('Number of students and families')->assertSee('Remarks')->assertSee('Registrations by class');
        $this->actingAs($administrator)->get(route('workspaces.show', 'teachers'))->assertOk()->assertSee('Teacher IN / OUT')->assertSee('Substitution desk')->assertSee('Teacher subject assignments');
        $this->actingAs($administrator)->get(route('workspaces.show', 'academic'))->assertOk()->assertSee('Users')->assertSee('Teacher Subject Assignments')->assertDontSee('Users Access');
        $this->actingAs($administrator)->get(route('workspaces.show', 'operations'))->assertOk()
            ->assertSee('Administration & Operations')->assertSee('Users and Access')->assertSee('References')
            ->assertSee('aria-label="References"', false)
            ->assertSee('School structure')->assertSee('Branches')
            ->assertSee('Distribute books')->assertSee('Distribution history')->assertSee('Inventory ledger');
        $this->actingAs($administrator)->get(route('school-academic.index'))->assertOk()->assertSee('Academic operations');
        $this->actingAs($administrator)->get(route('school-academic.subjects'))->assertOk()->assertSee('Subjects')->assertSee('Add subject');
        $this->actingAs($administrator)->get(route('school-academic.terms'))->assertRedirect(route('academic.index').'#terms-semesters');
        $this->actingAs($administrator)->post(route('school-academic.terms.store'), ['name' => 'Unauthorized term'])->assertForbidden();
        $this->actingAs($administrator)->put(route('school-academic.terms.update', ['term' => 1]), ['name' => 'Unauthorized term'])->assertForbidden();
        $this->actingAs($administrator)->get(route('school-academic.assignments'))->assertOk()->assertSee('Teacher subject assignments')->assertSee('Assign subject to teacher');
        $this->actingAs($administrator)->get(route('school-academic.assessments'))->assertRedirect(route('collaboration.section', ['section' => 'modification-requests']));
        $this->actingAs($administrator)->get(route('collaboration.section', ['section' => 'modification-requests']))->assertOk()->assertSee('Evaluation Requests');
        $this->actingAs($administrator)->get(route('attendance.students', ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id]))->assertOk()->assertSee('Student attendance');
        $this->actingAs($administrator)->get(route('attendance.teachers'))->assertOk()->assertSee('Teacher attendance IN / OUT');
        $this->actingAs($administrator)->get(route('discipline.index'))->assertOk()->assertSee('Student actions & incidents', false);
        $this->actingAs($administrator)->get(route('medical.index'))->assertOk()->assertSee('Medical archive');
        $this->actingAs($administrator)->get(route('exam-absences.index'))->assertOk()->assertSee('Exam absences');
        $this->actingAs($administrator)->get(route('timetable.index', ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id]))
            ->assertOk()->assertSee('Graphical timetable')->assertSee('Subjects to schedule')->assertSee('Class schedule')
            ->assertSee('href="'.route('school-academic.assignments').'"', false)->assertDontSee('Course cards');
        $this->actingAs($administrator)->get(route('reports.school'))->assertOk()->assertSee('School operations reports');
    }

    public function test_administration_workspace_has_tasks_for_an_admin_without_book_permissions(): void
    {
        $role = Role::create(['name' => 'office-admin', 'label' => 'Office Administrator']);
        foreach (['dashboard.view', 'admin.manage'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        }
        $administrator = User::factory()->create(['is_active' => true]);
        $administrator->roles()->attach($role);

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()
            ->assertSee('href="'.route('workspaces.show', 'operations').'"', false);
        $this->actingAs($administrator)->get(route('workspaces.show', 'operations'))->assertOk()
            ->assertSee('Users and Access')
            ->assertSee('href="'.route('admin.users').'"', false)
            ->assertDontSee('Your role does not currently have a task in this workspace.');
        $this->actingAs($administrator)->get(route('admin.users'))->assertOk()
            ->assertSee('sidebar-link-label">Administration &amp; Operations</span>', false)
            ->assertDontSee('sidebar-link-label">Users and Access</span>', false)
            ->assertDontSee('aria-label="References"', false);
    }

    public function test_collaboration_work_is_linked_to_existing_assignments_and_student_archives(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $teacher = Teacher::create(['first_name' => 'Rana', 'last_name' => 'Instructor', 'is_active' => true]);
        $subject = Subject::create(['name' => 'English']);
        $assignment = TeacherAssignment::create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'is_active' => true,
        ]);

        $this->actingAs($administrator)->post(route('collaboration.announcements.store'), [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'audience' => 'section',
            'title' => 'Welcome evening',
            'body' => 'Families are invited to meet the class teacher.',
            'publish_now' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('school_announcements', ['title' => 'Welcome evening', 'audience' => 'section']);

        $this->actingAs($administrator)->post(route('collaboration.course-content.store'), [
            'teacher_assignment_id' => $assignment->id,
            'title' => 'Chapter 1 reading',
            'content' => 'Read the first chapter before Monday.',
            'content_type' => 'homework',
            'resource_url' => 'https://example.test/chapter-1',
            'available_from' => now()->startOfWeek()->format('Y-m-d H:i:s'),
            'due_on' => now()->startOfWeek()->toDateString(),
            'estimated_minutes' => 45,
            'disable_response' => true,
            'publish_now' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('course_contents', ['teacher_assignment_id' => $assignment->id, 'title' => 'Chapter 1 reading', 'estimated_minutes' => 45, 'disable_response' => true]);

        $this->actingAs($administrator)->post(route('collaboration.events.store'), [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'audience' => 'grade',
            'title' => 'Reading week',
            'starts_at' => today()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'ends_at' => today()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('school_events', ['title' => 'Reading week', 'audience' => 'grade']);

        $this->actingAs($administrator)->post(route('collaboration.performance.store'), [
            'student_registration_id' => $registration->id,
            'teacher_assignment_id' => $assignment->id,
            'category' => 'participation',
            'rating' => 5,
            'observed_on' => today()->toDateString(),
            'note' => 'Thoughtful contributions during group reading.',
            'status' => 'published',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_performance_evaluations', ['student_id' => $registration->student_id, 'teacher_assignment_id' => $assignment->id, 'rating' => 5]);

        $this->actingAs($administrator)->get(route('collaboration.index', ['section' => 'course-content']))
            ->assertOk()
            ->assertSee('Welcome evening')
            ->assertSee('Chapter 1 reading')
            ->assertSee('Reading week')
            ->assertSee('Thoughtful contributions during group reading.');
        $this->actingAs($administrator)->get(route('collaboration.index', ['section' => 'homework']))
            ->assertOk()
            ->assertSee('Administration agenda')
            ->assertDontSee('Performance Appraisals')
            ->assertDontSee('Students')
            ->assertDontSee('Performance Dashboard')
            ->assertDontSee('data-agenda-open')
            ->assertSee('Chapter 1 reading')
            ->assertSee('Publication date')
            ->assertSee('data-agenda-day')
            ->assertSee('name="attachment"', false);
        $this->actingAs($administrator)->get(route('students.show', $registration->student))
            ->assertOk()
            ->assertSee('Performance follow-up')
            ->assertSee('Thoughtful contributions during group reading.');
    }

    public function test_internal_messages_are_delivered_and_can_be_marked_as_read(): void
    {
        $administrator = $this->administrator();
        $messageView = Permission::firstOrCreate(['name' => 'messages.view'], ['label' => 'View messages']);
        $messageSend = Permission::firstOrCreate(['name' => 'messages.send'], ['label' => 'Send messages']);
        $recipientRole = Role::create(['name' => 'message-recipient', 'label' => 'Message recipient']);
        $recipientRole->permissions()->attach([$messageView->id, $messageSend->id]);
        $recipient = User::factory()->create(['is_active' => true, 'has_all_branch_access' => true]);
        $recipient->roles()->attach($recipientRole);

        $this->actingAs($administrator)->post(route('messages.store'), [
            'recipient_ids' => [$recipient->id],
            'subject' => 'Parent-teacher evening',
            'body' => 'Please prepare the room before 16:00.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('school_messages', ['sender_id' => $administrator->id, 'subject' => 'Parent-teacher evening']);
        $this->assertDatabaseHas('school_message_recipients', ['recipient_id' => $recipient->id]);

        $inbox = $this->actingAs($recipient)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Parent-teacher evening')
            ->assertSee('Please prepare the room before 16:00.');
        $receiptId = (int) DB::table('school_message_recipients')->where('recipient_id', $recipient->id)->value('id');
        $this->actingAs($recipient)->post(route('messages.read', $receiptId))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('school_message_recipients', ['id' => $receiptId, 'recipient_id' => $recipient->id, 'read_at' => null]);
    }
}
