<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
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
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\StudentPortalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function portalData(): array
    {
        $year = AcademicYear::create(['name' => '2031-2032', 'starts_on' => '2031-09-01', 'ends_on' => '2032-06-30', 'status' => 'open']);
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

        return compact('year', 'grade', 'section', 'subject', 'teacher', 'assignment', 'student', 'registration', 'studentUser');
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

    public function test_teacher_can_create_course_assessment_for_their_assigned_class(): void
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
            'name' => 'October monthly test',
            'type' => 'monthly_test',
            'maximum_score' => 20,
            'weight' => 0.5,
            'assessment_date' => '2031-10-20',
        ])->assertRedirect();

        $this->assertDatabaseHas('assessments', ['name' => 'October monthly test', 'maximum_score' => 20, 'weight' => '0.500', 'grade_id' => $data['grade']->id, 'section_id' => $data['section']->id]);
    }
}
