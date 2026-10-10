<?php

declare(strict_types=1);

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BooksController;
use App\Http\Controllers\BookTransfersController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\ClassBookListsController;
use App\Http\Controllers\CollaborationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\DarsAdministrationController;
use App\Http\Controllers\DarsCompatibilityController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ExamAbsencesController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\MedicalRecordsController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\ReturnsController;
use App\Http\Controllers\SchoolAcademicController;
use App\Http\Controllers\SchoolReportsController;
use App\Http\Controllers\StudentDossierController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentRegistrationsController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\SubstitutionsController;
use App\Http\Controllers\SupplierReturnsController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    $user = auth()->user();

    return $user && ($user->hasRole('teacher') || $user->teacher_id)
        ? redirect()->route('collaboration.index', ['section' => 'homework'])
        : redirect()->route('dashboard');
});
Route::get('/health', static fn () => response()->json(['status' => 'ok']))->name('health');
$authThrottle = config('auth.rate_limiting') ? ['throttle:5,15'] : [];

Route::middleware('guest')->group(function () use ($authThrottle): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware($authThrottle)->name('password.email');
    Route::get('/reset-password/whatsapp', [AuthController::class, 'whatsappResetForm'])->name('password.whatsapp');
    Route::post('/reset-password/whatsapp', [AuthController::class, 'whatsappReset'])->middleware($authThrottle)->name('password.whatsapp.update');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'branch'])->group(function (): void {
    Route::post('/branches/current', [BranchesController::class, 'select'])->name('branches.select');
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');
    Route::get('/messages', [MessagesController::class, 'index'])->middleware('permission:messages.view')->name('messages.index');
    Route::post('/messages', [MessagesController::class, 'store'])->middleware('permission:messages.send')->name('messages.store');
    Route::post('/messages/{recipient}/read', [MessagesController::class, 'markRead'])->middleware('permission:messages.view')->name('messages.read');
    Route::post('/messages/read-all', [MessagesController::class, 'markAllRead'])->middleware('permission:messages.view')->name('messages.read-all');
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/student-portal', [StudentPortalController::class, 'index'])->name('student-portal.index');
    Route::get('/workspaces/{workspace}', WorkspaceController::class)
        ->where('workspace', 'students|teachers|academic|operations')
        ->middleware('permission:dashboard.view')
        ->name('workspaces.show');
    Route::get('/finance', [OperationsController::class, 'index'])->name('finance.index');
    Route::get('/operations', [OperationsController::class, 'index'])->middleware('permission:dashboard.view')->name('operations.index');
    Route::middleware('permission:class-lists.manage')->group(function (): void {
        Route::get('/class-book-lists/{classBookList}/edit', [ClassBookListsController::class, 'edit'])->name('class-lists.edit');
        Route::put('/class-book-lists/{classBookList}', [ClassBookListsController::class, 'update'])->name('class-lists.update');
    });
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/reports/print', [ReportsController::class, 'print'])->name('reports.print');
        Route::get('/report-card/reports/{report}', [ReportCardController::class, 'show'])->name('report-card.show');
    });
    Route::middleware('permission:admin.manage')->group(function (): void {
        Route::get('/admin/dars/{page}', [DarsAdministrationController::class, 'show'])->name('admin.dars.show');
        Route::get('/admin/users/{user}/edit', [AdministrationController::class, 'editUser'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdministrationController::class, 'updateUser'])->name('admin.users.update');
        Route::get('/admin/users/{user}/photo', [AdministrationController::class, 'userPhoto'])->name('admin.users.photo');
    });
    Route::middleware('permission:students.manage')->group(function (): void {
        Route::get('/students/section-changes', [StudentDossierController::class, 'sectionChanges'])->name('students.section-changes');
        Route::post('/students/section-changes', [StudentDossierController::class, 'updateSections'])->name('students.section-changes.update');
        Route::post('/students/{student}/photo', [StudentsController::class, 'updatePhoto'])->name('students.photo.update');
        Route::get('/students/{student}/registrations/{registration}/edit', [StudentRegistrationsController::class, 'edit'])->name('students.register.edit');
        Route::put('/students/{student}/registrations/{registration}', [StudentRegistrationsController::class, 'update'])->name('students.register.update');
    });
    Route::middleware('permission:students.manage')->group(function (): void {
        Route::get('/students/create', [StudentsController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentsController::class, 'store'])->name('students.store');
        Route::get('/students/{student}/edit', [StudentsController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentsController::class, 'update'])->name('students.update');
        Route::get('/students/{student}/leave', [StudentsController::class, 'leaveForm'])->name('students.leave.create');
        Route::post('/students/{student}/leave', [StudentsController::class, 'leave'])->name('students.leave.store');
        Route::delete('/students/{student}', [StudentsController::class, 'destroy'])->name('students.destroy');
        Route::get('/students/{student}/register', [StudentsController::class, 'registerForm'])->name('students.register.create');
        Route::post('/students/{student}/register', [StudentsController::class, 'register'])->name('students.register.store');
    });
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/students/attestations', [StudentDossierController::class, 'attestations'])->name('students.attestations');
        Route::get('/students/attestations/print', [StudentDossierController::class, 'printAttestations'])->name('students.attestations.print');
        Route::get('/students/reports/{report}', [StudentDossierController::class, 'report'])->where('report', 'class-list|statistical|labels|catalog|registration|dynamic')->name('students.reports.show');
    });
    Route::middleware('permission:students.view')->group(function (): void {
        Route::get('/students/dashboard', [StudentDossierController::class, 'dashboard'])->name('students.dashboard');
        Route::get('/students/tools/{section}', [StudentDossierController::class, 'tools'])->where('section', 'file|references')->name('students.tools');
        Route::get('/students/references/{reference}/create', [StudentDossierController::class, 'referenceCreate'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.create');
        Route::get('/students/references/{reference}/{entry}/edit', [StudentDossierController::class, 'referenceEdit'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.edit');
        Route::get('/students/references/{reference}', [StudentDossierController::class, 'reference'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.show');
        Route::post('/students/references/codes/categories', [StudentDossierController::class, 'referenceCategoryStore'])->name('students.references.categories.store');
        Route::post('/students/references/{reference}', [StudentDossierController::class, 'referenceStore'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.store');
        Route::put('/students/references/{reference}/{entry}', [StudentDossierController::class, 'referenceUpdate'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.update');
        Route::delete('/students/references/{reference}/{entry}', [StudentDossierController::class, 'referenceDestroy'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.destroy');
        Route::get('/students/families', [StudentDossierController::class, 'families'])->name('students.families');
        Route::get('/students/photos', [StudentDossierController::class, 'photos'])->name('students.photos');
        Route::get('/students', [StudentsController::class, 'index'])->name('students.index');
        Route::get('/students/{student}/photo', [StudentsController::class, 'photo'])->name('students.photo.show');
        Route::get('/students/{student}', [StudentsController::class, 'show'])->name('students.show');
    });
    Route::middleware('permission:students.manage')->group(function (): void {
    });
    Route::get('/books', [BooksController::class, 'index'])->middleware('permission:books.view')->name('books.index');
    Route::middleware('permission:books.manage')->group(function (): void {
        Route::get('/books/create', [BooksController::class, 'create'])->name('books.create');
        Route::post('/books', [BooksController::class, 'store'])->name('books.store');
        Route::get('/books/{book}/edit', [BooksController::class, 'edit'])->name('books.edit');
        Route::put('/books/{book}', [BooksController::class, 'update'])->name('books.update');
        Route::delete('/books/{book}', [BooksController::class, 'destroy'])->name('books.destroy');
    });
    Route::get('/books/{book}', [BooksController::class, 'show'])->middleware('permission:books.view')->name('books.show');
    Route::resource('suppliers', SuppliersController::class)->except(['show'])->middleware('permission:suppliers.manage');
    Route::get('/suppliers/{supplier}', [SuppliersController::class, 'show'])->middleware('permission:suppliers.view')->name('suppliers.show');
    Route::middleware('permission:purchases.manage')->group(function (): void {
        Route::get('/purchases', [PurchasesController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchasesController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchasesController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}/returns/create', [SupplierReturnsController::class, 'create'])->name('supplier-returns.create');
        Route::post('/purchases/{purchase}/returns', [SupplierReturnsController::class, 'store'])->name('supplier-returns.store');
        Route::get('/supplier-returns/{supplierReturn}', [SupplierReturnsController::class, 'show'])->name('supplier-returns.show');
        Route::get('/purchases/{purchase}', [PurchasesController::class, 'show'])->name('purchases.show');
        Route::post('/purchases/{purchase}/payments', [PurchasesController::class, 'storePayment'])->name('purchases.payments.store');
        Route::post('/purchases/{purchase}/receive-outstanding', [PurchasesController::class, 'receiveOutstanding'])->name('purchases.receive-outstanding');
    });
    Route::middleware('permission:distributions.create')->group(function (): void {
        Route::get('/distributions', [DistributionController::class, 'index'])->name('distributions.index');
        Route::get('/distributions/create', [DistributionController::class, 'create'])->name('distributions.create');
        Route::post('/distributions', [DistributionController::class, 'store'])->name('distributions.store');
        Route::get('/distributions/{distribution}/print', [DistributionController::class, 'print'])->name('distributions.print');
        Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->name('distributions.show');
    });
    Route::middleware('permission:invoices.view')->group(function (): void {
        Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoicesController::class, 'print'])->name('invoices.print');
    });
    Route::post('/invoices/{invoice}/cancel', [InvoicesController::class, 'cancel'])->middleware('permission:invoices.cancel', 'can:cancel,invoice')->name('invoices.cancel');
    Route::middleware('permission:payments.view')->group(function (): void {
        Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}/receipt', [PaymentsController::class, 'receipt'])->name('payments.receipt');
    });
    Route::post('/invoices/{invoice}/payments', [PaymentsController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::post('/payments/{payment}/reverse', [PaymentsController::class, 'reverse'])->middleware('permission:payments.reverse')->name('payments.reverse');
    Route::middleware('permission:inventory.view')->group(function (): void {
        Route::get('/inventory/ledger', [InventoryController::class, 'ledger'])->name('inventory.ledger');
        Route::get('/inventory/adjustments', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
    });
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->middleware('permission:inventory.adjust')->name('inventory.adjustments.store');
    Route::middleware('permission:returns.manage')->group(function (): void {
        Route::get('/returns', [ReturnsController::class, 'index'])->name('returns.index');
        Route::get('/returns/invoices/{invoice}', [ReturnsController::class, 'invoiceItems'])->name('returns.form');
        Route::post('/returns/invoices/{invoice}', [ReturnsController::class, 'store'])->name('returns.store');
        Route::get('/returns/{return}', [ReturnsController::class, 'show'])->name('returns.show');
    });
    Route::middleware('permission:academic.manage')->group(function (): void {
        Route::get('/academic', [AcademicController::class, 'index'])->name('academic.index');
        Route::post('/academic/years', [AcademicController::class, 'storeYear'])->name('academic.years.store');
        Route::put('/academic/years/{academicYear}', [AcademicController::class, 'updateYear'])->name('academic.years.update');
        Route::post('/academic/levels', [AcademicController::class, 'storeLevel'])->name('academic.levels.store');
        Route::put('/academic/levels/{schoolLevel}', [AcademicController::class, 'updateLevel'])->name('academic.levels.update');
        Route::post('/academic/grades', [AcademicController::class, 'storeGrade'])->name('academic.grades.store');
        Route::put('/academic/grades/{grade}', [AcademicController::class, 'updateGrade'])->name('academic.grades.update');
        Route::post('/academic/sections', [AcademicController::class, 'storeSection'])->name('academic.sections.store');
        Route::put('/academic/sections/{section}', [AcademicController::class, 'updateSection'])->name('academic.sections.update');
    });
    Route::middleware('permission:academic_year.manage')->group(function (): void {
        Route::get('/academic/operations', [SchoolAcademicController::class, 'index'])->name('school-academic.index');
        Route::get('/academic/operations/subjects', [SchoolAcademicController::class, 'subjects'])->name('school-academic.subjects');
        Route::get('/academic/operations/terms', [SchoolAcademicController::class, 'terms'])->name('school-academic.terms');
        Route::get('/academic/operations/teacher-assignments', [SchoolAcademicController::class, 'assignments'])->name('school-academic.assignments');
        // Keep the old URL as a compatibility redirect; evaluations live in Online Collaboration.
        Route::get('/academic/operations/assessments', fn () => redirect()->route('collaboration.section', ['section' => 'modification-requests']))->name('school-academic.assessments');
        Route::post('/academic/subjects', [SchoolAcademicController::class, 'storeSubject'])->name('school-academic.subjects.store');
        Route::put('/academic/subjects/{subject}', [SchoolAcademicController::class, 'updateSubject'])->name('school-academic.subjects.update');
        Route::post('/academic/terms', [SchoolAcademicController::class, 'storeTerm'])->name('school-academic.terms.store');
        Route::put('/academic/terms/{term}', [SchoolAcademicController::class, 'updateTerm'])->name('school-academic.terms.update');
        Route::post('/academic/assessments', [SchoolAcademicController::class, 'storeAssessment'])->name('school-academic.assessments.store');
        Route::put('/academic/assessments/{assessment}', [SchoolAcademicController::class, 'updateAssessment'])->name('school-academic.assessments.update');
        Route::post('/academic/assessments/{assessment}/approve', [SchoolAcademicController::class, 'approveAssessment'])->name('school-academic.assessments.approve');
        Route::post('/academic/assessments/{assessment}/reject', [SchoolAcademicController::class, 'rejectAssessment'])->name('school-academic.assessments.reject');
        Route::post('/academic/teacher-assignments', [SchoolAcademicController::class, 'storeAssignment'])->name('school-academic.assignments.store');
        Route::put('/academic/teacher-assignments/{assignment}', [SchoolAcademicController::class, 'updateAssignment'])->name('school-academic.assignments.update');
    });
    Route::middleware('permission:attendance.student.manage')->group(function (): void {
        Route::get('/attendance/students', [AttendanceController::class, 'students'])->name('attendance.students');
        Route::post('/attendance/students', [AttendanceController::class, 'saveStudents'])->name('attendance.students.save');
    });
    Route::middleware('permission:attendance.teacher.manage')->group(function (): void {
        Route::get('/attendance/teachers', [AttendanceController::class, 'teachers'])->name('attendance.teachers');
        Route::post('/attendance/teachers', [AttendanceController::class, 'saveTeacher'])->name('attendance.teachers.save');
    });
    Route::middleware('permission:discipline.view')->group(function (): void {
        Route::get('/discipline', [DisciplineController::class, 'index'])->name('discipline.index');
        Route::middleware('permission:discipline.create')->group(function (): void {
            Route::post('/discipline', [DisciplineController::class, 'store'])->name('discipline.store');
            Route::post('/discipline/categories', [DisciplineController::class, 'storeCategory'])->name('discipline.categories.store');
            Route::post('/discipline/types', [DisciplineController::class, 'storeType'])->name('discipline.types.store');
        });
        Route::put('/discipline/{incident}', [DisciplineController::class, 'update'])->middleware('permission:discipline.edit')->name('discipline.update');
    });
    Route::middleware('permission:medical.view')->group(function (): void {
        Route::get('/medical-records', [MedicalRecordsController::class, 'index'])->name('medical.index');
        Route::get('/medical-records/{medicalRecord}/download', [MedicalRecordsController::class, 'download'])->name('medical.download');
        Route::middleware('permission:medical.manage')->group(function (): void {
            Route::post('/medical-records', [MedicalRecordsController::class, 'store'])->name('medical.store');
            Route::put('/medical-records/{medicalRecord}', [MedicalRecordsController::class, 'update'])->name('medical.update');
        });
    });
    Route::middleware('permission:academic_year.manage')->group(function (): void {
        Route::get('/exam-absences', [ExamAbsencesController::class, 'index'])->name('exam-absences.index');
        Route::post('/exam-absences', [ExamAbsencesController::class, 'store'])->name('exam-absences.store');
    });
    Route::middleware('permission:grades.enter')->group(function (): void {
        Route::get('/grades', [GradesController::class, 'index'])->name('grades.index');
        Route::post('/grades/assessments', [GradesController::class, 'storeAssessment'])->name('grades.assessments.store');
        Route::put('/grades/assessments/{assessment}', [GradesController::class, 'updateAssessment'])->name('grades.assessments.update');
        Route::post('/grades', [GradesController::class, 'save'])->name('grades.save');
        Route::post('/grades/{gradeEntry}/lock', [GradesController::class, 'lock'])->name('grades.lock');
    });
    Route::get('/collaboration', [CollaborationController::class, 'index'])->middleware('permission:collaboration.view')->name('collaboration.index');
    Route::get('/collaboration/{section}', [CollaborationController::class, 'index'])
        ->where('section', 'course-content|messages|calendar|performance|performance-dashboard|evaluation-dashboard|evaluation-grades|grade-book|evaluations-list|student-failures|modification-requests|transcript-subject-choice|evaluation-statement|homework|homework-report|homework-totals|homework-comparison|skills-student|skills-approve|remarks-approve|item-remarks|class-remarks|principal-appreciations|remarks-entry|attendance-management|standards|report-card-receipt|icons|system-error-log|bulk-message|students|teachers')
        ->middleware('permission:collaboration.view')
        ->name('collaboration.section');
    Route::post('/collaboration/announcements', [CollaborationController::class, 'storeAnnouncement'])->middleware('permission:collaboration.manage')->name('collaboration.announcements.store');
    Route::post('/collaboration/course-content', [CollaborationController::class, 'storeCourseContent'])->middleware('permission:collaboration.manage')->name('collaboration.course-content.store');
    Route::put('/collaboration/course-content/{courseContent}', [CollaborationController::class, 'updateCourseContent'])->middleware('permission:collaboration.manage')->name('collaboration.course-content.update');
    Route::delete('/collaboration/course-content/{courseContent}', [CollaborationController::class, 'destroyCourseContent'])->middleware('permission:collaboration.manage')->name('collaboration.course-content.destroy');
    Route::post('/collaboration/events', [CollaborationController::class, 'storeEvent'])->middleware('permission:collaboration.manage')->name('collaboration.events.store');
    Route::post('/collaboration/performance-evaluations', [CollaborationController::class, 'storePerformanceEvaluation'])->middleware('permission:performance.manage')->name('collaboration.performance.store');
    Route::middleware('permission:timetable.view')->group(function (): void {
        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::middleware('permission:timetable.manage')->group(function (): void {
            Route::post('/timetable/periods', [TimetableController::class, 'storePeriod'])->name('timetable.periods.store');
            Route::post('/timetable/entries', [TimetableController::class, 'store'])->name('timetable.entries.store');
            Route::put('/timetable/entries/{timetableEntry}', [TimetableController::class, 'update'])->name('timetable.entries.update');
            Route::delete('/timetable/entries/{timetableEntry}', [TimetableController::class, 'destroy'])->name('timetable.entries.destroy');
        });
    });
    Route::middleware('permission:substitutions.manage')->group(function (): void {
        Route::get('/teacher-substitutions', [SubstitutionsController::class, 'index'])->name('substitutions.index');
        Route::post('/teacher-substitutions/{teacherSubstitution}', [SubstitutionsController::class, 'assign'])->name('substitutions.assign');
        Route::post('/teacher-substitutions/{teacherSubstitution}/recommend', [SubstitutionsController::class, 'recommend'])->name('substitutions.recommend');
    });
    Route::middleware('permission:people.manage')->group(function (): void {
        Route::get('/teachers', [PeopleController::class, 'teachers'])->name('teachers.index');
        Route::post('/teachers', [PeopleController::class, 'storeTeacher'])->name('teachers.store');
        Route::get('/teachers/{teacher}', [PeopleController::class, 'showTeacher'])->name('teachers.show');
        Route::get('/responsible-persons', [PeopleController::class, 'responsible'])->name('responsible.index');
        Route::get('/responsible-persons/students/search', [PeopleController::class, 'searchStudents'])->name('responsible.students.search');
        Route::post('/responsible-persons', [PeopleController::class, 'storeResponsible'])->name('responsible.store');
        Route::get('/responsible-persons/{responsiblePerson}/edit', [PeopleController::class, 'editResponsible'])->name('responsible.edit');
        Route::put('/responsible-persons/{responsiblePerson}', [PeopleController::class, 'updateResponsible'])->name('responsible.update');
        Route::delete('/responsible-persons/{responsiblePerson}', [PeopleController::class, 'destroyResponsible'])->name('responsible.destroy');
    });
    Route::middleware('permission:class-lists.view')->group(function (): void {
        Route::get('/class-book-lists', [ClassBookListsController::class, 'index'])->name('class-lists.index');
        Route::get('/class-book-lists/{classBookList}/print', [ClassBookListsController::class, 'print'])->name('class-lists.print');
    });
    Route::middleware('permission:class-lists.manage')->group(function (): void {
        Route::get('/class-book-lists/create', [ClassBookListsController::class, 'create'])->name('class-lists.create');
        Route::post('/class-book-lists', [ClassBookListsController::class, 'store'])->name('class-lists.store');
    });
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/csv', [ReportsController::class, 'csv'])->name('reports.csv');
        Route::get('/reports/school', [SchoolReportsController::class, 'index'])->name('reports.school');
        Route::get('/reports/school/print', [SchoolReportsController::class, 'print'])->name('reports.school.print');
        Route::get('/reports/school/csv', [SchoolReportsController::class, 'csv'])->middleware('permission:reports.export')->name('reports.school.csv');
    });
    Route::middleware('permission:admin.manage')->group(function (): void {
        Route::get('/admin/users', [AdministrationController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdministrationController::class, 'storeUser'])->name('admin.users.store');
        Route::get('/admin/users/{user}/access', [AdministrationController::class, 'editUserAccess'])->name('admin.users.access.edit');
        Route::put('/admin/users/{user}/access', [AdministrationController::class, 'updateUserAccess'])->name('admin.users.access.update');
        Route::post('/admin/users/{user}/toggle', [AdministrationController::class, 'toggleUser'])->name('admin.users.toggle');
        Route::delete('/admin/users/{user}', [AdministrationController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::post('/admin/users/{user}/controller-administrator', [AdministrationController::class, 'transferControllerAuthority'])->middleware('can:manage-branches')->name('admin.users.controller.transfer');
        Route::get('/admin/audit', [AdministrationController::class, 'audit'])->name('admin.audit');
    });
    Route::middleware(['permission:admin.manage', 'can:manage-branches'])->group(function (): void {
        // Keep the legacy URL working, but manage access from each user row instead.
        Route::get('/admin/roles', fn () => redirect()->route('admin.users'))->name('admin.roles');
        Route::get('/admin/recycle-bin', [AdministrationController::class, 'recycleBin'])->name('admin.recycle');
        Route::post('/admin/recycle-bin/{type}/{id}', [AdministrationController::class, 'restore'])->name('admin.recycle.restore');
        Route::get('/admin/settings', [AdministrationController::class, 'settings'])->name('admin.settings');
        Route::put('/admin/settings', [AdministrationController::class, 'saveSettings'])->name('admin.settings.update');
    });
    Route::middleware(['permission:admin.manage', 'can:manage-branches'])->group(function (): void {
        Route::get('/admin/branches', [BranchesController::class, 'index'])->name('branches.index');
        Route::post('/admin/branches', [BranchesController::class, 'store'])->name('branches.store');
        Route::put('/admin/branches/{branch}', [BranchesController::class, 'update'])->name('branches.update');
        Route::delete('/admin/branches/{branch}', [BranchesController::class, 'destroy'])->name('branches.destroy');
    });
    Route::middleware(['permission:admin.manage', 'can:manage-branches'])->group(function (): void {
        Route::get('/admin/imports', [DataImportController::class, 'index'])->name('admin.imports');
        Route::post('/admin/imports/students', [DataImportController::class, 'students'])->name('admin.imports.students');
        Route::post('/admin/imports/books', [DataImportController::class, 'books'])->name('admin.imports.books');
    });

    /*
     * Dars compatibility URLs. The live Dars application uses WebForms .aspx
     * paths. Keep those paths usable in SBM while the canonical Laravel URLs
     * remain the implementation endpoints behind them.
     */
    Route::get('/home.aspx', fn () => redirect()->route('dashboard'))->name('dars.home');
    Route::get('/Home.aspx', fn () => redirect()->route('dashboard'))->name('dars.lit-home');
    Route::get('/DARSSchools.aspx', fn () => redirect()->route('dashboard'))->name('dars.schools');
    Route::get('/logout.aspx', [AuthController::class, 'destroy'])->name('dars.logout');
    Route::get('/Video_Assistant/HomeAssistant.aspx', fn () => redirect()->route('dashboard'))->name('dars.help');

    Route::get('/StudentProfile/Profile/Dashboard.aspx', fn () => redirect()->route('students.dashboard'))
        ->middleware('permission:students.view')->name('dars.student-dashboard');
    Route::get('/StudentProfile/Profile/SearchStudentParent.aspx', [DarsCompatibilityController::class, 'studentSearch'])
        ->middleware('permission:students.view')->name('dars.student-search');
    Route::get('/StudentProfile/Profile/StudentProfile.aspx', [DarsCompatibilityController::class, 'studentProfile'])
        ->middleware('permission:students.view')->name('dars.student-profile');
    Route::get('/StudentProfile/Profile/InscParentView.aspx', [DarsCompatibilityController::class, 'responsibleProfile'])
        ->middleware('permission:people.manage')->name('dars.parent-profile');
    Route::get('/StudentProfile/Reports/RptInsReports.aspx', [DarsCompatibilityController::class, 'studentReport'])
        ->middleware('permission:reports.view')->name('dars.student-reports');
    Route::get('/StudentProfile/Reports/StudentFamilyReport.aspx', fn () => redirect()->route('students.reports.show', ['report' => 'dynamic']))
        ->middleware('permission:reports.view')->name('dars.student-family-report');
    Route::get('/StudentProfile/Reports/IscRptFamillesParNbEnfants.aspx', fn () => redirect()->route('students.reports.show', ['report' => 'statistical']))
        ->middleware('permission:reports.view')->name('dars.student-family-count-report');
    Route::get('/StudentProfile/Profile/InscStdsDashboardRemarks.aspx', fn () => redirect()->route('attendance.students'))
        ->middleware('permission:attendance.student.manage')->name('dars.student-dashboard-remarks');
    Route::get('/ReportCard/InscFillAbsence.aspx', fn () => redirect()->route('attendance.students'))
        ->middleware('permission:attendance.student.manage')->name('dars.student-absence');

    foreach ([
        'InscProfRemark.aspx' => 'remarks',
        'evalRptEleveResult.aspx' => 'evaluations',
        'rptNoteReleveeComplet.aspx' => 'notes',
        'StudentSubjects.aspx' => 'subjects',
        'StudentHistory.aspx' => 'history',
        'InscStudentOtherSchool.aspx' => 'schools',
        'InscStudentFiles.aspx' => 'files',
        'InscStudentProfessionalFollow.aspx' => 'professional',
        'StudentActivity.aspx' => 'activities',
        'FicheSuivi.aspx' => 'follow-up',
    ] as $path => $section) {
        Route::get('/StudentProfile/Profile/'.$path, [DarsCompatibilityController::class, 'studentSection'])
            ->defaults('section', $section)->middleware('permission:students.view')->name('dars.student.'.str_replace('.aspx', '', $path));
    }
    Route::get('/Medical/Front/MedOnlineProfile.aspx', [DarsCompatibilityController::class, 'studentSection'])
        ->defaults('section', 'medical')->middleware('permission:medical.view')->name('dars.student.medical');

    foreach ([
        '/StudentProfile/Profile/Parent.aspx' => 'responsible.index',
        '/StudentProfile/Profile/InscFamiliesWithoutChildren.aspx' => 'students.families',
        '/StudentProfile/References/InscChangeSection.aspx' => 'students.section-changes',
        '/StudentProfile/References/InscImages.aspx' => 'students.photos',
        '/StudentProfile/Profile/ParentsModifications.aspx' => 'responsible.index',
        '/StudentProfile/Attestation/AttestationMultiReport.aspx' => 'students.attestations',
    ] as $path => $target) {
        Route::get($path, fn () => redirect()->route($target))->name('dars.'.str_replace(['/', '.aspx'], ['.', ''], trim($path, '/')));
    }
    foreach ([
        'Codes.aspx' => 'codes',
        'Division.aspx' => 'division',
        'ClassesDef.aspx' => 'class-definition',
        'Classes.aspx' => 'classes',
        'OtherSchools.aspx' => 'other-schools',
        'Qaza.aspx' => 'district',
        'Town.aspx' => 'towns',
    ] as $path => $reference) {
        Route::get('/StudentProfile/References/'.$path, fn () => redirect()->route('students.references.show', ['reference' => $reference]))
            ->name('dars.reference.'.str_replace('.aspx', '', $path));
    }

    Route::get('/ReportCard/ReportCardMain.aspx', [DarsCompatibilityController::class, 'reportCard'])
        ->defaults('report', 'report-card')->middleware('permission:reports.view')->name('dars.report-card.home');
    foreach ([
        '/ReportCard/Reports/PreviewReportCard.aspx' => 'report-card',
        '/ReportCard/NotBulletinRechercheEleve.aspx' => 'individual-transcript',
        '/ReportCard/Reports/rptNotRemarques.aspx' => 'remarks-summary',
        '/ReportCard/Reports/rptNotReleveGen.aspx' => 'general-transcript',
        '/ReportCard/Reports/rptNotRemarque.aspx' => 'remarks-detail',
        '/ReportCard/Reports/rptNotReleveGenProgress.aspx' => 'progress-transcript',
        '/ReportCard/Reports/rptNotBulletinCondensee.aspx' => 'subjects-list-per-class',
        '/ReportCard/Reports/rptNotBulletinCondenseChoix.aspx' => 'transcript-by-subject-choice',
        '/ReportCard/Reports/RptNotListeClassement.aspx' => 'student-ranking-per-subject',
        '/ReportCard/Reports/rptNotReleveMatiere.aspx' => 'subjects-transcript',
        '/ReportCard/Reports/rptNotReleveMatiere_Ar.aspx' => 'official-statement',
        '/ReportCard/Reports/RptNotNoteOfficielle.aspx' => 'official-statement-mehe',
        '/ReportCard/Reports/RptNotAchievement.aspx' => 'student-performance',
        '/ReportCard/Reports/rptNotMoyenneClasseMatiere.aspx' => 'subject-averages-per-class',
        '/ReportCard/Reports/rptNotNbFailures.aspx' => 'failure-per-subject',
        '/ReportCard/Reports/RptNotUnderachievers.aspx' => 'underachievers-by-subject',
        '/ReportCard/Reports/Not_Rpt_MoyenneEvolution.aspx' => 'annual-progression-of-class-average',
        '/ReportCard/Reports/Not_Rpt_ClassTop5.aspx' => 'top-5-students-per-division',
        '/ReportCard/Reports/RptClassTop1BySubject.aspx' => 'highest-grade-per-subject',
        '/ReportCard/Reports/ExportBaccFrancais.aspx' => 'export-bacc-francais',
        '/ReportCard/Reports/rptMeilleureColonneAnnuelle.aspx' => 'best-annual-columns-transcript',
        '/ReportCard/Reports/rptNotStatNotePerClasse.aspx' => 'students-transcript',
        '/ReportCard/Reports/rptNotCalculStatistiqueColonne.aspx' => 'grade-distribution',
        '/ReportCard/Reports/rptNotStatMoyenneClasse.aspx' => 'class-average-evolution',
        '/ReportCard/Reports/RptNotCalculStatistique.aspx' => 'statistical-calculation',
        '/ReportCard/Reports/rptNotStatMatiereEvolution.aspx' => 'results-comparison-table',
    ] as $path => $report) {
        Route::get($path, [DarsCompatibilityController::class, 'reportCard'])
            ->defaults('report', $report)->middleware('permission:reports.view')->name('dars.report-card.'.str_replace(['/', '.aspx'], ['.', ''], trim($path, '/')));
    }

    Route::get('/Admin/Users.aspx', fn () => redirect()->route('admin.users'))->middleware('permission:admin.manage')->name('dars.admin.users');
    Route::get('/Admin/admUserEdit.aspx', fn () => redirect()->route('admin.users'))->middleware('permission:admin.manage')->name('dars.admin.user-edit');
    Route::get('/Admin/AdminUsrSession.aspx', fn () => redirect()->route('admin.audit'))->middleware('permission:admin.manage')->name('dars.admin.session');
    Route::get('/Admin/ChangePassword.aspx', fn () => redirect()->route('dashboard'))->name('dars.admin.password');
    Route::get('/Online/Admin/admUsers.aspx', fn () => redirect()->route('admin.users'))->middleware('permission:admin.manage')->name('dars.admin.online-users');
    Route::get('/Online/Admin/admUserAcces.aspx', fn () => redirect()->route('admin.users'))->middleware('permission:admin.manage')->name('dars.admin.online-access');
    Route::get('/Online/Admin/NotClasseMatiere.aspx', fn () => redirect()->route('school-academic.assignments'))->middleware('permission:academic_year.manage')->name('dars.admin.online-class-subjects');
    Route::get('/StudentProfile/References/SchoolYear.aspx', fn () => redirect()->route('academic.index'))->middleware('permission:academic.manage')->name('dars.admin.school-year');
    foreach ([
        '/Admin/UsrMenu.aspx' => 'menu',
        '/Admin/UsrManagePages.aspx' => 'pages',
        '/Admin/HeaderParameter.aspx' => 'report-header',
        '/Admin/UserConfig.aspx' => 'configuration',
        '/Admin/UsrDynamicFields.aspx' => 'dynamic-fields',
        '/Admin/Dictionary.aspx' => 'dictionary',
        '/StudentProfile/References/FamilyType.aspx' => 'family-type',
        '/StudentProfile/References/ParentRemarkType.aspx' => 'family-remark-type',
        '/Admin/Usrcollege.aspx' => 'colleges',
        '/Admin/ImportData.aspx' => 'import',
        '/Admin/UsrFileCategory.aspx' => 'file-category',
        '/Admin/Ent_MailType.aspx' => 'mail-type',
    ] as $path => $page) {
        Route::get($path, [DarsCompatibilityController::class, 'administrationPage'])
            ->defaults('page', $page)->middleware('permission:admin.manage')->name('dars.admin.'.str_replace(['/', '.aspx'], ['.', ''], trim($path, '/')));
    }

    Route::get('/Online/Admin/admHome.aspx', fn () => redirect()->route('collaboration.index'))->middleware('permission:collaboration.view')->name('dars.collaboration.home');
    foreach ([
        '/Online/Front/TeachersList.aspx' => 'teachers',
        '/Online/Timeline/CourseContent.aspx' => 'course-content',
        '/Online/Mail/MailBox.aspx' => 'messages',
        '/Online/mail/Calendar.aspx' => 'calendar',
        '/Online/Eval/evalPaRecords.aspx' => 'performance',
        '/Online/Front/SearchStudent.aspx' => 'students',
        '/Online/PerformanceDashboard/PerformanceDashboard.aspx' => 'performance-dashboard',
        '/Online/Eval/evalTeacherDashboard.aspx' => 'evaluation-dashboard',
        '/Online/Eval/evalList.aspx' => 'evaluation-grades',
        '/Online/Eval/evalTeacher.aspx' => 'grade-book',
        '/Online/eval/evalRptListEvaluations.aspx' => 'evaluations-list',
        '/Online/Eval/EvalRptEleveEchoue.aspx' => 'student-failures',
        '/Online/Eval/evalEleveEvaluationLog.aspx' => 'modification-requests',
        '/Online/Eval/EvalRptClasseMatieresColonne.aspx' => 'transcript-subject-choice',
        '/Online/front/evalparclasse.aspx' => 'evaluation-statement',
        '/Online/Agenda/agendaTeacherPrincipal.aspx' => 'homework',
        '/Online/Agenda/RptAgenda.aspx' => 'homework-report',
        '/Online/Agenda/RptAgendaProfCount.aspx' => 'homework-totals',
        '/Online/Agenda/RptAgendaComparaison.aspx' => 'homework-comparison',
        '/Online/Competence/CmpEleves.aspx' => 'skills-student',
        '/Online/Competence/CmpFillCompetencePrincipal.aspx' => 'skills-approve',
        '/Online/Competence/CmpFillRemarquePrincipal.aspx' => 'remarks-approve',
        '/Online/Competence/CmpFillRemarks.aspx' => 'item-remarks',
        '/Online/Front/RptEleveClasseRemarque.aspx' => 'class-remarks',
        '/Online/Front/RemarquePrincipalParClasse.aspx' => 'principal-appreciations',
        '/Online/Front/RemarquePrincipalParClasseManipulation.aspx' => 'remarks-entry',
        '/Online/Front/StudentsAttendance.aspx' => 'attendance-management',
        '/Online/Admin/courseStandard.aspx' => 'standards',
        '/Online/Admin/RptReportCardView.aspx' => 'report-card-receipt',
        '/Online/Admin/AdmEntFileTypeIcon.aspx' => 'icons',
        '/Online/Admin/admUsrErrorLog.aspx' => 'system-error-log',
        '/Online/Mail/TemplateMessage.aspx' => 'bulk-message',
    ] as $path => $section) {
        Route::get($path, [DarsCompatibilityController::class, 'collaborationPage'])
            ->defaults('section', $section)->middleware('permission:collaboration.view')->name('dars.collaboration.'.str_replace(['/', '.aspx'], ['.', ''], trim($path, '/')));
    }
    Route::middleware('permission:inventory.transfer')->group(function (): void {
        Route::get('/transfers', [BookTransfersController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/create', [BookTransfersController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [BookTransfersController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/accept', [BookTransfersController::class, 'accept'])->name('transfers.accept');
        Route::post('/transfers/{transfer}/reject', [BookTransfersController::class, 'reject'])->name('transfers.reject');
        Route::post('/transfers/{transfer}/reverse', [BookTransfersController::class, 'reverse'])->name('transfers.reverse');
        Route::get('/transfers/{transfer}/invoice', [BookTransfersController::class, 'printInvoice'])->name('transfers.invoice');
        Route::get('/transfers/{transfer}', [BookTransfersController::class, 'show'])->name('transfers.show');
    });
});
