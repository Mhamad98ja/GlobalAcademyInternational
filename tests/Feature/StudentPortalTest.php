<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseContent;
use App\Models\Grade;
use App\Models\GradeEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\StudentSubjectExemption;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\StudentPortalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function portalData(): array
    {
        $year = AcademicYear::create(['name' => '2031-2032', 'starts_on' => '2031-09-01', 'ends_on' => '2032-06-30', 'status' => 'open']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'First term', 'starts_on' => '2031-09-01', 'ends_on' => '2031-12-31', 'status' => 'open', 'sort_order' => 1]);
        $level = SchoolLevel::create(['name' => 'Portal level']);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Portal Grade']);
        $section = Section::create(['grade_id' => $grade->id, 'name' => 'A']);
        $subject = Subject::create(['name' => 'Portal Mathematics']);
        $teacher = Teacher::create(['first_name' => 'Portal', 'last_name' => 'Teacher', 'is_active' => true]);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        $student = Student::create(['student_number' => 'PORTAL-001', 'first_name' => 'Portal', 'last_name' => 'Student', 'gender' => 'female']);
        $registration = StudentRegistration::create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'status' => 'active', 'registered_on' => '2031-09-01']);
        $studentRole = Role::firstOrCreate(['name' => 'student'], ['label' => 'Student']);
        $studentUser = User::factory()->create(['name' => 'Portal Student', 'student_id' => $student->id, 'is_active' => true]);
        $studentUser->roles()->attach($studentRole);

        return compact('year', 'term', 'grade', 'section', 'subject', 'teacher', 'assignment', 'student', 'registration', 'studentUser');
    }

    public function test_student_portal_shows_only_published_course_work_assessments_and_grades(): void
    {
        $data = $this->portalData();
        $content = CourseContent::create(['teacher_assignment_id' => $data['assignment']->id, 'title' => 'Algebra homework', 'content' => 'Complete pages 4–6.', 'content_type' => 'homework', 'published_at' => now()]);
        CourseContent::create(['teacher_assignment_id' => $data['assignment']->id, 'title' => 'Teacher draft', 'content_type' => 'lesson']);
        $assessment = Assessment::create(['academic_year_id' => $data['year']->id, 'subject_id' => $data['subject']->id, 'grade_id' => $data['grade']->id, 'section_id' => $data['section']->id, 'name' => 'First quiz', 'type' => 'quiz', 'maximum_score' => 10, 'weight' => 0.5, 'assessment_date' => '2031-10-10', 'status' => 'open']);
        GradeEntry::create(['teacher_id' => $data['teacher']->id, 'teacher_assignment_id' => $data['assignment']->id, 'student_id' => $data['student']->id, 'student_registration_id' => $data['registration']->id, 'assessment_id' => $assessment->id, 'score' => 8.5, 'status' => 'published', 'recorded_by' => $data['studentUser']->id]);
        $data['student']->notify(new StudentPortalNotification('New agenda', 'Your teacher published new homework.'));

        $this->actingAs($data['studentUser'])->get(route('student-portal.index'))
            ->assertOk()
            ->assertSee('Algebra homework')
            ->assertSee('First quiz')
            ->assertSee('8.50 / 10.00')
            ->assertSee('New agenda')
            ->assertDontSee('Teacher draft');
    }

    public function test_unlinked_staff_account_cannot_open_student_portal(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('student-portal.index'))->assertForbidden();
    }

    public function test_subject_exemption_hides_subject_work_and_attachment_but_can_be_removed(): void
    {
        Storage::fake('local');
        $data = $this->portalData();
        Storage::disk('local')->put('course-content/exempt-guide.pdf', 'guide');
        $content = CourseContent::create([
            'teacher_assignment_id' => $data['assignment']->id,
            'title' => 'Exempt mathematics homework',
            'content_type' => 'document',
            'published_at' => now(),
            'attachment_path' => 'course-content/exempt-guide.pdf',
            'attachment_name' => 'exempt-guide.pdf',
            'attachment_mime' => 'application/pdf',
        ]);
        $assessment = Assessment::create(['academic_year_id' => $data['year']->id, 'subject_id' => $data['subject']->id, 'grade_id' => $data['grade']->id, 'section_id' => $data['section']->id, 'name' => 'Exempt mathematics quiz', 'type' => 'quiz', 'maximum_score' => 10, 'status' => 'open']);
        GradeEntry::create(['teacher_id' => $data['teacher']->id, 'teacher_assignment_id' => $data['assignment']->id, 'student_id' => $data['student']->id, 'student_registration_id' => $data['registration']->id, 'assessment_id' => $assessment->id, 'score' => 8.5, 'status' => 'published', 'recorded_by' => $data['studentUser']->id]);
        $data['student']->notify(new StudentPortalNotification('Exempt subject notice', 'Subject-specific reminder', route('student-portal.index'), $data['subject']->id, $data['registration']->id));
        $exemption = StudentSubjectExemption::create(['student_id' => $data['student']->id, 'student_registration_id' => $data['registration']->id, 'subject_id' => $data['subject']->id]);

        $this->actingAs($data['studentUser'])->get(route('student-portal.index'))
            ->assertOk()->assertDontSee('Exempt mathematics homework')->assertDontSee('Exempt mathematics quiz')->assertDontSee('8.50 / 10.00')->assertDontSee('Exempt subject notice');
        $this->get(route('student-portal.course-content.download', $content))->assertForbidden();

        $exemption->delete();
        $this->get(route('student-portal.index'))->assertOk()->assertSee('Exempt mathematics homework')->assertSee('Exempt mathematics quiz')->assertSee('Exempt subject notice');
    }

    public function test_creating_a_student_creates_a_portal_login_account(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'students.manage'], ['label' => 'Manage students']);
        $role = Role::firstOrCreate(['name' => 'student-account-admin'], ['label' => 'Student account administrator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $administrator = User::factory()->create(['is_active' => true]);
        $administrator->roles()->attach($role);

        $this->actingAs($administrator)->post(route('students.store'), [
            'first_name' => 'New',
            'last_name' => 'Portal Student',
            'email' => 'new.portal.student@example.test',
            'password' => 'StudentPortal1234!',
            'password_confirmation' => 'StudentPortal1234!',
        ])->assertRedirect();

        $student = Student::query()->where('email', 'new.portal.student@example.test')->firstOrFail();
        $portalUser = User::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame($student->full_name, $portalUser->name);
        $this->assertTrue($portalUser->hasRole('student'));
        $this->assertTrue(password_verify('StudentPortal1234!', $portalUser->password));
        $this->assertDatabaseHas('branch_user', ['user_id' => $portalUser->id, 'branch_id' => $student->branch_id, 'is_default' => true]);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $portalUser->email, 'password' => 'StudentPortal1234!'])->assertRedirect(route('student-portal.index'));
    }

    public function test_teacher_can_create_subject_assessment_for_their_assigned_class(): void
    {
        $data = $this->portalData();
        $teacherRole = Role::create(['name' => 'teacher-portal', 'label' => 'Teacher']);
        $teacherRole->permissions()->attach([
            Permission::firstOrCreate(['name' => 'grades.enter'], ['label' => 'Enter grades'])->id,
            Permission::firstOrCreate(['name' => 'collaboration.manage'], ['label' => 'Manage collaboration'])->id,
        ]);
        $teacherUser = User::factory()->create(['teacher_id' => $data['teacher']->id, 'is_active' => true]);
        $teacherUser->roles()->attach($teacherRole);

        $this->actingAs($teacherUser)->post(route('grades.assessments.store'), [
            'teacher_assignment_id' => $data['assignment']->id,
            'academic_term_id' => $data['term']->id,
            'name' => 'October monthly test',
            'type' => 'monthly_test',
            'maximum_score' => 20,
            'weight' => 0.5,
            'assessment_date' => '2031-10-20',
            'notes' => 'Bring the revision sheet.',
            'online_quiz' => true,
            'per_question' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('assessments', ['name' => 'October monthly test', 'teacher_assignment_id' => $data['assignment']->id, 'assessment_column' => null, 'maximum_score' => 20, 'weight' => '0.500', 'subject_id' => $data['subject']->id, 'grade_id' => $data['grade']->id, 'section_id' => $data['section']->id, 'notes' => 'Bring the revision sheet.', 'online_quiz' => true, 'per_question' => true]);
    }
}
