<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeEntry;
use App\Models\Permission;
use App\Models\ResponsiblePerson;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentReferenceEntry;
use App\Models\StudentRegistration;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\StudentDossierNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentDossierTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: AcademicYear, 2: Grade, 3: Section, 4: Section, 5: StudentRegistration} */
    private function dossierData(): array
    {
        $role = Role::create(['name' => 'super-administrator', 'label' => 'Super Administrator']);
        $user = User::factory()->create(['is_active' => true, 'is_controller_administrator' => true, 'has_all_branch_access' => true]);
        $user->roles()->attach($role);
        $year = AcademicYear::create(['name' => '2030-2031', 'starts_on' => '2030-09-01', 'ends_on' => '2031-06-30', 'status' => 'open', 'created_by' => $user->id]);
        $level = SchoolLevel::create(['name' => 'Dossier level']);
        $grade = Grade::create(['school_level_id' => $level->id, 'name' => 'Dossier Grade']);
        $sectionA = Section::create(['grade_id' => $grade->id, 'name' => 'A']);
        $sectionB = Section::create(['grade_id' => $grade->id, 'name' => 'B']);
        $student = Student::create(['student_number' => 'DOS-001', 'first_name' => 'Maya', 'last_name' => 'Dossier', 'gender' => 'female']);
        $registration = StudentRegistration::create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $sectionA->id, 'status' => 'active', 'registered_on' => '2030-09-01', 'created_by' => $user->id]);

        return [$user, $year, $grade, $sectionA, $sectionB, $registration];
    }

    public function test_student_dossier_tools_operate_on_existing_student_records(): void
    {
        [$user, $year, $grade, $sectionA, $sectionB, $registration] = $this->dossierData();
        ResponsiblePerson::create(['parent_code' => 'DOS-UNLINKED', 'full_name' => 'Unlinked Dossier Family', 'type' => 'guardian']);

        $this->actingAs($user)->get(route('students.index'))
            ->assertOk()
            ->assertSee('Student search')
            ->assertSee('Maya Dossier');
        $this->actingAs($user)->get(route('students.families'))
            ->assertOk()
            ->assertSee('Unlinked Dossier Family');
        $this->actingAs($user)->get(route('students.photos', ['academic_year_id' => $year->id, 'grade_id' => $grade->id]))
            ->assertOk()
            ->assertSee('Maya Dossier');
        $this->actingAs($user)->get(route('students.attestations', ['academic_year_id' => $year->id]))
            ->assertOk()
            ->assertSee('Print selected attestations');

        $this->actingAs($user)->post(route('students.section-changes.update'), [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'new_section_id' => $sectionB->id,
            'registration_ids' => [$registration->id],
        ])->assertRedirect(route('students.section-changes', ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $sectionB->id]));
        $this->assertDatabaseHas('student_registrations', ['id' => $registration->id, 'section_id' => $sectionB->id]);

        $this->actingAs($user)->get(route('students.attestations.print', [
            'registration_ids' => [$registration->id], 'certificate_type' => 'enrollment', 'reference_date' => '2030-10-01', 'include_enrollment_date' => 1,
        ]))->assertOk()->assertSee('Enrolment Attestation')->assertSee('Maya Dossier');
    }

    public function test_detailed_parent_fields_are_saved_without_breaking_student_links(): void
    {
        [$user, , , , , $registration] = $this->dossierData();

        $this->actingAs($user)->post(route('responsible.store'), [
            'parent_code' => 'DOS-PARENT-001', 'full_name' => 'Rana Dossier', 'first_name' => 'Rana', 'last_name' => 'Dossier',
            'type' => 'mother', 'blood_type' => 'O-', 'religion' => 'Demo', 'family_type' => 'Ordinary', 'occupation' => 'Designer',
            'district' => 'Beirut', 'town' => 'Beirut', 'nationality' => 'Lebanese', 'employment_status' => 'Employed',
            'employer' => 'Demo Studio', 'family_status' => 'Married', 'is_alumni' => true, 'student_ids' => [$registration->student_id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('responsible_persons', ['parent_code' => 'DOS-PARENT-001', 'full_name' => 'Rana Dossier', 'occupation' => 'Designer', 'blood_type' => 'O-', 'is_alumni' => true]);
        $this->assertDatabaseHas('responsible_person_student', ['student_id' => $registration->student_id]);
    }

    public function test_student_dashboard_uses_the_selected_year_and_daily_absences(): void
    {
        [$user, $year, , , , $registration] = $this->dossierData();
        $this->travelTo(Carbon::parse('2030-09-10'));
        $family = ResponsiblePerson::create(['full_name' => 'Maya Guardian', 'type' => 'guardian']);
        $family->students()->attach($registration->student_id);
        foreach ([0, 2] as $period) {
            StudentAttendance::create(['student_id' => $registration->student_id, 'student_registration_id' => $registration->id, 'academic_year_id' => $year->id, 'grade_id' => $registration->grade_id, 'attendance_date' => '2030-09-10', 'period_number' => $period, 'status' => 'absent', 'recorded_by' => $user->id]);
        }
        $this->actingAs($user)->get(route('workspaces.show', ['workspace' => 'students']))
            ->assertOk()->assertSee('Remarks')
            ->assertViewHas('studentOverview', fn ($data) => $data['totalStudents'] === 1 && $data['gender']['female'] === 1 && $data['familySizes']->get(1) === 1 && $data['absences']['Today'] === 1);
        $otherYear = AcademicYear::create(['name' => '2029-2030', 'starts_on' => '2029-09-01', 'ends_on' => '2030-06-30', 'status' => 'closed', 'created_by' => $user->id]);
        $this->get(route('workspaces.show', ['workspace' => 'students', 'academic_year_id' => $otherYear->id]))
            ->assertOk()->assertViewHas('studentOverview', fn ($data) => $data['totalStudents'] === 0 && $data['absences']['Today'] === 0);
    }

    public function test_student_file_reference_and_report_tools_open_their_inner_pages(): void
    {
        [$user, , , , , $registration] = $this->dossierData();
        $this->actingAs($user);
        foreach (StudentDossierNavigation::sections() as $key => $section) {
            $this->get(route('students.tools', ['section' => $key]))->assertOk()->assertSee($section['label']);
            foreach ($section['items'] as $item) {
                $this->get(route($item['route'], $item['params']))->assertOk();
            }
        }
        $this->get(route('students.show', $registration->student))->assertOk()->assertSee('Personal details')->assertSee('View all records');
        $this->get(route('academic.index', ['module' => 'students']))->assertSee('References')->assertSee('id="sections"', false);
        $this->get(route('reports.index', ['module' => 'students', 'report' => 'class_sections']))->assertSee('name="module" value="students"', false);
    }

    public function test_references_can_be_created_edited_and_deleted_with_safe_dependency_checks(): void
    {
        [$user, , $grade, $section] = $this->dossierData();
        $this->actingAs($user);

        $this->post(route('students.references.store', ['reference' => 'other-schools']), ['name' => 'Harbor International School', 'number' => 'HIS-10'])
            ->assertRedirect(route('students.references.show', ['reference' => 'other-schools']));
        $entry = StudentReferenceEntry::query()->where('reference', 'other-schools')->firstOrFail();
        $entryKey = rtrim(strtr(base64_encode('entry|'.$entry->id), '+/', '-_'), '=');
        $this->put(route('students.references.update', ['reference' => 'other-schools', 'entry' => $entryKey]), ['name' => 'Harbor Academy', 'number' => 'HIS-11'])
            ->assertRedirect(route('students.references.show', ['reference' => 'other-schools']));
        $this->assertDatabaseHas('student_reference_entries', ['id' => $entry->id, 'value' => 'Harbor Academy', 'code' => 'HIS-11']);
        $this->delete(route('students.references.destroy', ['reference' => 'other-schools', 'entry' => $entryKey]))
            ->assertRedirect(route('students.references.show', ['reference' => 'other-schools']));
        $this->assertDatabaseMissing('student_reference_entries', ['id' => $entry->id]);

        $student = Student::create(['student_number' => 'DOS-REFERENCE-001', 'first_name' => 'Raw', 'last_name' => 'School', 'previous_school' => 'Legacy Academy']);
        $rawSchoolKey = rtrim(strtr(base64_encode('raw-school|Legacy Academy'), '+/', '-_'), '=');
        $this->delete(route('students.references.destroy', ['reference' => 'other-schools', 'entry' => $rawSchoolKey]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'previous_school' => null]);

        $builtInCodeKey = rtrim(strtr(base64_encode("builtin-code|blood-type\x1fO+"), '+/', '-_'), '=');
        $this->delete(route('students.references.destroy', ['reference' => 'codes', 'entry' => $builtInCodeKey]))
            ->assertRedirect(route('students.references.show', ['reference' => 'codes']));
        $this->assertDatabaseHas('student_reference_entries', ['reference' => 'codes', 'category' => 'blood-type', 'code' => 'O+', 'value' => '__deleted__']);
        $this->get(route('students.references.show', ['reference' => 'codes', 'type' => 'blood-type']))->assertOk()->assertDontSee('>O+<', false);
        $this->post(route('students.references.store', ['reference' => 'codes']), ['category' => 'blood-type', 'code' => 'O+', 'value' => 'O+', 'value_en' => 'O+', 'value_ar' => ''])
            ->assertRedirect(route('students.references.show', ['reference' => 'codes']));
        $this->assertDatabaseHas('student_reference_entries', ['reference' => 'codes', 'category' => 'blood-type', 'code' => 'O+', 'value' => 'O+']);

        $gradeKey = rtrim(strtr(base64_encode('model|'.$grade->id), '+/', '-_'), '=');
        $this->delete(route('students.references.destroy', ['reference' => 'class-definition', 'entry' => $gradeKey]))
            ->assertSessionHasErrors('reference');
        $this->assertDatabaseHas('grades', ['id' => $grade->id]);

        $emptyLevel = SchoolLevel::create(['name' => 'Unused division']);
        $emptyGrade = Grade::create(['school_level_id' => $emptyLevel->id, 'name' => 'Unused class']);
        $emptySection = Section::create(['grade_id' => $emptyGrade->id, 'name' => 'A']);
        $emptyLevelKey = rtrim(strtr(base64_encode('model|'.$emptyLevel->id), '+/', '-_'), '=');
        $this->delete(route('students.references.destroy', ['reference' => 'division', 'entry' => $emptyLevelKey]))
            ->assertRedirect(route('students.references.show', ['reference' => 'division']));
        $this->assertDatabaseMissing('school_levels', ['id' => $emptyLevel->id]);
        $this->assertDatabaseMissing('grades', ['id' => $emptyGrade->id]);
        $this->assertDatabaseMissing('sections', ['id' => $emptySection->id]);

        $unusedSection = Section::create(['grade_id' => $grade->id, 'name' => 'Unused']);
        $sectionKey = rtrim(strtr(base64_encode('model|'.$unusedSection->id), '+/', '-_'), '=');
        $this->delete(route('students.references.destroy', ['reference' => 'classes', 'entry' => $sectionKey]))
            ->assertRedirect(route('students.references.show', ['reference' => 'classes']));
        $this->assertDatabaseMissing('sections', ['id' => $unusedSection->id]);

        $this->get(route('students.references.show', ['reference' => 'classes']))
            ->assertOk()->assertSee('Delete')->assertSee($section->name);
    }

    public function test_custom_code_categories_can_be_opened_after_creation(): void
    {
        [$user] = $this->dossierData();

        $this->actingAs($user)->post(route('students.references.categories.store'), ['category' => 'Languages'])
            ->assertRedirect(route('students.references.show', ['reference' => 'codes', 'type' => 'Languages']));
        $this->get(route('students.references.show', ['reference' => 'codes', 'type' => 'Languages']))
            ->assertOk()->assertSee('Languages');
    }

    public function test_student_search_matches_full_names_and_a_single_registration(): void
    {
        [$user, $year, $grade, $sectionA, $sectionB, $registration] = $this->dossierData();
        $olderYear = AcademicYear::create(['name' => '2029-2030', 'starts_on' => '2029-09-01', 'ends_on' => '2030-06-30', 'status' => 'closed', 'created_by' => $user->id]);
        $older = StudentRegistration::create(['student_id' => $registration->student_id, 'academic_year_id' => $olderYear->id, 'grade_id' => $grade->id, 'section_id' => $sectionB->id, 'status' => 'completed', 'registered_on' => '2029-09-01', 'created_by' => $user->id]);
        $this->actingAs($user)->get(route('students.index', ['search' => 'Maya Dossier']))->assertOk()->assertViewHas('students', fn ($students) => $students->count() === 1);
        $this->get(route('students.index', ['academic_year_id' => $year->id, 'registration_status' => 'completed']))->assertOk()->assertViewHas('students', fn ($students) => $students->isEmpty());
        $this->get(route('students.index', ['academic_year_id' => $olderYear->id, 'registration_status' => 'completed']))->assertOk()
            ->assertViewHas('students', fn ($students) => $students->count() === 1 && $students->first()->registrations->first()->id === $older->id);
    }

    public function test_period_attendance_reloads_the_saved_period_and_status_filter_keeps_the_correct_roster(): void
    {
        [$user, $year, $grade, $section, , $registration] = $this->dossierData();
        $base = ['academic_year_id' => $year->id, 'attendance_date' => '2030-09-10', 'grade_id' => $grade->id, 'section_id' => $section->id];
        $this->actingAs($user)->post(route('attendance.students.save'), $base + ['period_number' => 0, 'entries' => [['registration_id' => $registration->id, 'status' => 'present']]])->assertSessionHasNoErrors();
        $this->post(route('attendance.students.save'), $base + ['period_number' => 3, 'entries' => [['registration_id' => $registration->id, 'status' => 'absent', 'notes' => 'Period absence']]])
            ->assertRedirect(route('attendance.students', ['academic_year_id' => $year->id, 'attendance_date' => '2030-09-10', 'period_number' => 3, 'grade_id' => $grade->id, 'section_id' => $section->id]));
        $this->get(route('attendance.students', $base + ['period_number' => 3, 'status' => 'absent']))->assertOk()
            ->assertViewHas('registrations', fn ($rows) => $rows->count() === 1)
            ->assertViewHas('existing', fn ($rows) => $rows->get($registration->id)->notes === 'Period absence');
        $this->get(route('attendance.students', $base + ['period_number' => 3, 'status' => 'present']))->assertOk()->assertViewHas('registrations', fn ($rows) => $rows->isEmpty());
        $this->assertDatabaseHas('student_attendances', ['student_id' => $registration->student_id, 'period_number' => 0, 'status' => 'present']);
    }

    public function test_attendance_rejects_an_assignment_from_another_class(): void
    {
        [$user, $year, $grade, , $sectionB, $registration] = $this->dossierData();
        $teacher = Teacher::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Class subject']);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $sectionB->id, 'is_active' => true]);
        $this->actingAs($user)->post(route('attendance.students.save'), ['academic_year_id' => $year->id, 'attendance_date' => '2030-09-10', 'period_number' => 1, 'teacher_assignment_id' => $assignment->id, 'entries' => [['registration_id' => $registration->id, 'status' => 'present']]])->assertSessionHasErrors('teacher_assignment_id');
        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_attestations_from_a_profile_are_scoped_and_deleted_students_are_excluded(): void
    {
        [$user, $year, $grade, $section, , $registration] = $this->dossierData();
        $other = Student::create(['student_number' => 'DOS-OTHER', 'first_name' => 'Other', 'last_name' => 'Student']);
        StudentRegistration::create(['student_id' => $other->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'status' => 'active', 'registered_on' => '2030-09-01', 'created_by' => $user->id]);
        $this->actingAs($user)->get(route('students.attestations', ['student_id' => $registration->student_id]))->assertOk()
            ->assertViewHas('registrations', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $registration->id);
        $registration->student->delete();
        $this->get(route('students.attestations'))->assertOk()->assertDontSee('Maya Dossier');
        $this->get(route('students.attestations.print', ['registration_ids' => [$registration->id], 'certificate_type' => 'attendance', 'reference_date' => '2030-09-10']))->assertSessionHasErrors('registration_ids');
    }

    public function test_read_only_student_viewer_has_no_edit_or_protected_report_links(): void
    {
        [, , , , , $registration] = $this->dossierData();
        $viewer = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'dossier-reader', 'label' => 'Dossier reader']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'students.view'], ['label' => 'View students']));
        $viewer->roles()->attach($role);
        $this->actingAs($viewer)->get(route('students.show', $registration->student))->assertOk()->assertDontSee('Edit profile')->assertDontSee('New registration')->assertDontSee('Medical archive');
        $this->get(route('students.references.show', ['reference' => 'other-schools']))->assertOk()->assertSee('Add New');
        $this->post(route('attendance.students.save'), [])->assertForbidden();
    }

    public function test_student_reports_print_and_export_the_same_selected_student(): void
    {
        [$user, , , , , $registration] = $this->dossierData();
        $this->actingAs($user);
        foreach (['students', 'student_information', 'class_sections'] as $report) {
            $filters = ['report' => $report, 'student_ids' => [$registration->student_id], 'registration_ids' => [$registration->id], 'module' => 'students'];
            $this->get(route('reports.print', $filters))->assertOk()->assertSee('Maya Dossier');
            $this->assertStringContainsString('Maya Dossier', $this->get(route('reports.csv', $filters))->assertOk()->streamedContent());
        }
    }

    public function test_each_consolidated_school_report_opens_its_own_report_view(): void
    {
        [$user] = $this->dossierData();

        $this->actingAs($user);
        foreach ([
            'student_attendance', 'teacher_attendance', 'incidents', 'medical_records', 'exam_absences', 'substitutions',
            'teacher_workload', 'timetable', 'grades', 'academic_years', 'class_sections',
        ] as $report) {
            $this->get(route('reports.school', ['report' => $report]))->assertOk()->assertViewHas('report', $report);
        }
    }

    public function test_navigation_uses_canonical_module_routes_and_a_clear_parent_path(): void
    {
        [$user] = $this->dossierData();
        $this->actingAs($user);
        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['name' => 'reports.view'], ['label' => 'View reports'])->id,
        ]);

        $this->get(route('students.dashboard'))
            ->assertOk()
            ->assertSee(route('students.index'))
            ->assertDontSee('/StudentProfile/Profile/Dashboard.aspx');
        $this->get(route('students.index'))
            ->assertOk()
            ->assertSee('Back to Student Records');
        $this->get(route('dars.student-dashboard'))
            ->assertRedirect(route('students.dashboard'));

        $this->get(route('workspaces.show', ['workspace' => 'students']))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee(route('reports.school', ['report' => 'student_attendance']));
    }

    public function test_grade_reports_apply_term_class_and_assessment_date_filters_to_exports_too(): void
    {
        [$user, $year, $grade, $section, $sectionB, $registration] = $this->dossierData();
        $subject = Subject::create(['name' => 'Report subject']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'First term', 'starts_on' => '2030-09-01', 'ends_on' => '2030-12-31', 'status' => 'open']);
        $assessment = Assessment::create(['academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'name' => 'Selected assessment', 'type' => 'exam', 'maximum_score' => 100, 'assessment_date' => '2030-10-10', 'status' => 'open']);
        $teacher = Teacher::create(['first_name' => 'Report', 'last_name' => 'Teacher', 'is_active' => true]);
        $assignment = TeacherAssignment::create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'is_active' => true]);
        GradeEntry::create(['teacher_id' => $teacher->id, 'teacher_assignment_id' => $assignment->id, 'student_id' => $registration->student_id, 'student_registration_id' => $registration->id, 'assessment_id' => $assessment->id, 'score' => 85, 'status' => 'draft', 'recorded_by' => $user->id]);
        $filters = ['report' => 'grades', 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'grade_id' => $grade->id, 'section_id' => $section->id, 'date_from' => '2030-10-01', 'date_to' => '2030-10-31'];
        $this->actingAs($user)->get(route('reports.school', $filters))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 1);
        $this->get(route('reports.school.print', $filters))->assertOk()->assertSee('Selected assessment');
        $this->assertStringContainsString('Selected assessment', $this->get(route('reports.school.csv', $filters))->assertOk()->streamedContent());
        foreach ([['section_id' => $sectionB->id], ['date_from' => '2030-10-11']] as $exclude) {
            $this->get(route('reports.school', array_replace($filters, $exclude)))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
        }
    }
}
