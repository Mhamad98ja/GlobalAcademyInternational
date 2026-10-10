<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_teacher_grade_entry_is_limited_to_their_course_assignment(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section, $registration] = $this->classRegistration($administrator);
        $subject = Subject::create(['name' => 'Mathematics']);
        $teacher = Teacher::create(['first_name' => 'Ahmad', 'last_name' => 'Teacher', 'is_active' => true]);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $assessment = Assessment::create(['academic_year_id' => $year->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'name' => 'Midterm', 'type' => 'exam', 'maximum_score' => 100, 'status' => 'open']);
        $permission = Permission::firstOrCreate(['name' => 'grades.enter'], ['label' => 'Enter grades']);
        $role = Role::create(['name' => 'teacher-user', 'label' => 'Teacher']);
        $role->permissions()->attach($permission);
        $teacherUser = User::factory()->create(['is_active' => true, 'teacher_id' => $teacher->id]);
        $teacherUser->roles()->attach($role);

        $this->actingAs($teacherUser)->get(route('grades.index', ['teacher_assignment_id' => $assignment->id]))
            ->assertOk()
            ->assertSee('Evaluation Grades')
            ->assertSee('Add new')
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

    public function test_school_operations_screens_render_with_the_existing_navigation_context(): void
    {
        $administrator = $this->administrator();
        [$year, $grade, $section] = $this->classRegistration($administrator);

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()->assertSee('Student Profile')->assertSee('Report Card')->assertSee('Administration')->assertSee('Online Collaboration');
        $this->actingAs($administrator)->get(route('workspaces.show', 'students'))->assertOk()->assertSee('Number of students and families')->assertSee('Remarks')->assertSee('Registrations by class');
        $this->actingAs($administrator)->get(route('workspaces.show', 'teachers'))->assertOk()->assertSee('Teacher IN / OUT')->assertSee('Substitution desk')->assertSee('Teacher subject assignments');
        $this->actingAs($administrator)->get(route('workspaces.show', 'academic'))->assertOk()->assertSee('Users')->assertSee('Teacher Subject Assignments')->assertDontSee('Users Access');
        $this->actingAs($administrator)->get(route('workspaces.show', 'operations'))->assertOk()->assertSee('Distribute books')->assertSee('Distribution history')->assertSee('Inventory ledger');
        $this->actingAs($administrator)->get(route('school-academic.index'))->assertOk()->assertSee('Academic operations');
        $this->actingAs($administrator)->get(route('school-academic.subjects'))->assertOk()->assertSee('Subjects')->assertSee('Add subject');
        $this->actingAs($administrator)->get(route('school-academic.terms'))->assertOk()->assertSee('Terms / semesters')->assertSee('Add term');
        $this->actingAs($administrator)->get(route('school-academic.assignments'))->assertOk()->assertSee('Teacher subject assignments')->assertSee('Assign subject to teacher');
        $this->actingAs($administrator)->get(route('school-academic.assessments'))->assertRedirect(route('collaboration.section', ['section' => 'modification-requests']));
        $this->actingAs($administrator)->get(route('collaboration.section', ['section' => 'modification-requests']))->assertOk()->assertSee('Evaluation Requests');
        $this->actingAs($administrator)->get(route('attendance.students', ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id]))->assertOk()->assertSee('Student attendance');
        $this->actingAs($administrator)->get(route('attendance.teachers'))->assertOk()->assertSee('Teacher attendance IN / OUT');
        $this->actingAs($administrator)->get(route('discipline.index'))->assertOk()->assertSee('Student actions & incidents', false);
        $this->actingAs($administrator)->get(route('medical.index'))->assertOk()->assertSee('Medical archive');
        $this->actingAs($administrator)->get(route('exam-absences.index'))->assertOk()->assertSee('Exam absences');
        $this->actingAs($administrator)->get(route('timetable.index', ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id]))
            ->assertOk()->assertSee('Graphical timetable')->assertSee('Subjects to schedule')->assertSee('Class schedule')->assertDontSee('Course cards');
        $this->actingAs($administrator)->get(route('reports.school'))->assertOk()->assertSee('School operations reports');
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
            ->assertSee('Subject agenda')
            ->assertSee('Add agenda item')
            ->assertSee('Chapter 1 reading')
            ->assertSee('Publication date');
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
