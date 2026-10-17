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
    Route::post('/branches/current', [BranchesController::class, 'select'])->middleware('permission:branches.switch')->name('branches.select');
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');
    Route::get('/messages', [MessagesController::class, 'index'])->middleware('permission:messages.view')->name('messages.index');
    Route::post('/messages', [MessagesController::class, 'store'])->middleware('permission:messages.send')->name('messages.store');
    Route::post('/messages/{recipient}/read', [MessagesController::class, 'markRead'])->middleware('permission:messages.view')->name('messages.read');
    Route::post('/messages/read-all', [MessagesController::class, 'markAllRead'])->middleware('permission:messages.view')->name('messages.read-all');
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/student-portal', [StudentPortalController::class, 'index'])->middleware('permission:student-portal.view')->name('student-portal.index');
    Route::get('/student-portal/course-content/{courseContent}/download', [CollaborationController::class, 'downloadCourseContent'])->middleware('permission:student-portal.course-content.download')->name('student-portal.course-content.download');
    Route::get('/workspaces/{workspace}', WorkspaceController::class)
        ->where('workspace', 'students|teachers|academic|operations')
        ->middleware('permission:workspaces.view')
        ->name('workspaces.show');
    Route::get('/finance', [OperationsController::class, 'index'])->middleware('permission:finance.view')->name('finance.index');
    Route::get('/operations', [OperationsController::class, 'index'])->middleware('permission:operations.view')->name('operations.index');
    Route::middleware('permission:class-lists.manage')->group(function (): void {
        Route::get('/class-book-lists/{classBookList}/edit', [ClassBookListsController::class, 'edit'])->name('class-lists.edit');
        Route::put('/class-book-lists/{classBookList}', [ClassBookListsController::class, 'update'])->name('class-lists.update');
    });
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/reports/print', [ReportsController::class, 'print'])->middleware('permission:reports.print')->name('reports.print');
        Route::get('/report-card/reports/{report}', [ReportCardController::class, 'show'])->middleware('permission:report-card.view')->name('report-card.show');
    });
    Route::get('/admin/report-cards', [ReportCardController::class, 'index'])->middleware('permission:report-card.view')->name('admin.report-cards');
    Route::get('/admin/report-cards/generate', [ReportCardController::class, 'index'])->middleware('permission:report-card.generate')->name('admin.report-cards.generate');
    Route::get('/admin/report-cards/print', [ReportCardController::class, 'print'])->middleware('permission:report-card.print')->name('admin.report-cards.print');
    Route::middleware('permission:admin.manage')->group(function (): void {
        Route::get('/admin/dars/{page}', [DarsAdministrationController::class, 'show'])->middleware(['permission:admin.dars.view', 'can:manage-branches'])->name('admin.dars.show');
    });
    Route::middleware('permission:students.manage')->group(function (): void {
        Route::get('/students/section-changes', [StudentDossierController::class, 'sectionChanges'])->name('students.section-changes');
        Route::post('/students/section-changes', [StudentDossierController::class, 'updateSections'])->name('students.section-changes.update');
        Route::post('/students/{student}/photo', [StudentsController::class, 'updatePhoto'])->middleware('permission:students.photo.manage')->name('students.photo.update');
        Route::post('/students/{student}/support', [StudentsController::class, 'storeSupportAssignment'])->middleware('permission:students.support.manage')->name('students.support.store');
        Route::post('/students/{student}/subject-exemptions', [StudentsController::class, 'storeSubjectExemption'])->middleware('permission:students.exemptions.manage')->name('students.subject-exemptions.store');
        Route::delete('/students/{student}/subject-exemptions/{exemption}', [StudentsController::class, 'destroySubjectExemption'])->middleware('permission:students.exemptions.manage')->name('students.subject-exemptions.destroy');
        Route::get('/students/{student}/registrations/{registration}/edit', [StudentRegistrationsController::class, 'edit'])->middleware('permission:students.registrations.manage')->name('students.register.edit');
        Route::put('/students/{student}/registrations/{registration}', [StudentRegistrationsController::class, 'update'])->middleware('permission:students.registrations.manage')->name('students.register.update');
    });
    Route::middleware('permission:students.manage')->group(function (): void {
        Route::get('/students/create', [StudentsController::class, 'create'])->middleware('permission:students.create')->name('students.create');
        Route::post('/students', [StudentsController::class, 'store'])->middleware('permission:students.create')->name('students.store');
        Route::get('/students/{student}/edit', [StudentsController::class, 'edit'])->middleware('permission:students.edit')->name('students.edit');
        Route::put('/students/{student}', [StudentsController::class, 'update'])->middleware('permission:students.edit')->name('students.update');
        Route::get('/students/{student}/leave', [StudentsController::class, 'leaveForm'])->name('students.leave.create');
        Route::post('/students/{student}/leave', [StudentsController::class, 'leave'])->name('students.leave.store');
        Route::delete('/students/{student}', [StudentsController::class, 'destroy'])->middleware('permission:students.delete')->name('students.destroy');
        Route::get('/students/{student}/register', [StudentsController::class, 'registerForm'])->middleware('permission:students.registrations.manage')->name('students.register.create');
        Route::post('/students/{student}/register', [StudentsController::class, 'register'])->middleware('permission:students.registrations.manage')->name('students.register.store');
    });
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/students/attestations', [StudentDossierController::class, 'attestations'])->middleware('permission:students.attestations.view')->name('students.attestations');
        Route::get('/students/attestations/print', [StudentDossierController::class, 'printAttestations'])->middleware('permission:students.attestations.print')->name('students.attestations.print');
        Route::get('/students/reports/{report}', [StudentDossierController::class, 'report'])->middleware('permission:students.reports.view')->where('report', 'class-list|statistical|labels|catalog|registration|dynamic')->name('students.reports.show');
    });
    Route::get('/students/search', [StudentsController::class, 'search'])->middleware('permission:students.search')->name('students.search');
    Route::middleware('permission:students.view')->group(function (): void {
        Route::get('/students/dashboard', [StudentDossierController::class, 'dashboard'])->name('students.dashboard');
        Route::get('/students/observations', [StudentDossierController::class, 'observations'])->middleware('permission:students.observations.view')->name('students.observations');
        Route::get('/students/tools/{section}', [StudentDossierController::class, 'tools'])->where('section', 'file|references')->name('students.tools');
        Route::get('/students/references/{reference}/create', [StudentDossierController::class, 'referenceCreate'])->middleware('can:manage-branches')->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.create');
        Route::get('/students/references/{reference}/{entry}/edit', [StudentDossierController::class, 'referenceEdit'])->middleware('can:manage-branches')->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.edit');
        Route::get('/students/references/{reference}', [StudentDossierController::class, 'reference'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.show');
        Route::middleware('can:manage-branches')->group(function (): void {
            Route::post('/students/references/codes/categories', [StudentDossierController::class, 'referenceCategoryStore'])->name('students.references.categories.store');
            Route::post('/students/references/{reference}', [StudentDossierController::class, 'referenceStore'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.store');
            Route::put('/students/references/{reference}/{entry}', [StudentDossierController::class, 'referenceUpdate'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.update');
            Route::delete('/students/references/{reference}/{entry}', [StudentDossierController::class, 'referenceDestroy'])->where('reference', 'codes|division|class-definition|classes|other-schools|district|towns')->name('students.references.destroy');
        });
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
        Route::get('/books/create', [BooksController::class, 'create'])->middleware('permission:books.create')->name('books.create');
        Route::post('/books', [BooksController::class, 'store'])->middleware('permission:books.create')->name('books.store');
        Route::get('/books/{book}/edit', [BooksController::class, 'edit'])->middleware('permission:books.edit')->name('books.edit');
        Route::put('/books/{book}', [BooksController::class, 'update'])->middleware('permission:books.edit')->name('books.update');
        Route::delete('/books/{book}', [BooksController::class, 'destroy'])->middleware('permission:books.delete')->name('books.destroy');
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
        Route::get('/distributions', [DistributionController::class, 'index'])->middleware('permission:distributions.view')->name('distributions.index');
        Route::get('/distributions/create', [DistributionController::class, 'create'])->name('distributions.create');
        Route::post('/distributions', [DistributionController::class, 'store'])->name('distributions.store');
        Route::get('/distributions/{distribution}/print', [DistributionController::class, 'print'])->middleware('permission:distributions.print')->name('distributions.print');
        Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->middleware('permission:distributions.view')->name('distributions.show');
    });
    Route::middleware('permission:invoices.view')->group(function (): void {
        Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoicesController::class, 'print'])->middleware('permission:invoices.print')->name('invoices.print');
    });
    Route::post('/invoices/{invoice}/cancel', [InvoicesController::class, 'cancel'])->middleware('permission:invoices.cancel', 'can:cancel,invoice')->name('invoices.cancel');
    Route::middleware('permission:payments.view')->group(function (): void {
        Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}/receipt', [PaymentsController::class, 'receipt'])->middleware('permission:payments.receipt')->name('payments.receipt');
    });
    Route::post('/invoices/{invoice}/payments', [PaymentsController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::post('/payments/{payment}/reverse', [PaymentsController::class, 'reverse'])->middleware('permission:payments.reverse')->name('payments.reverse');
    Route::middleware('permission:inventory.view')->group(function (): void {
        Route::get('/inventory/ledger', [InventoryController::class, 'ledger'])->name('inventory.ledger');
        Route::get('/inventory/adjustments', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
    });
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->middleware('permission:inventory.adjust')->name('inventory.adjustments.store');
    Route::middleware('permission:returns.view')->group(function (): void {
        Route::get('/returns', [ReturnsController::class, 'index'])->name('returns.index');
        Route::get('/returns/{return}', [ReturnsController::class, 'show'])->name('returns.show');
        Route::get('/returns/invoices/{invoice}', [ReturnsController::class, 'invoiceItems'])->middleware('permission:returns.create')->name('returns.form');
        Route::post('/returns/invoices/{invoice}', [ReturnsController::class, 'store'])->middleware('permission:returns.create')->name('returns.store');
    });
    Route::middleware('permission:academic.manage')->group(function (): void {
        Route::get('/academic', [AcademicController::class, 'index'])->name('academic.index');
        Route::post('/academic/years', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.years.store');
        Route::put('/academic/years/{academicYear}', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.years.update');
        Route::post('/academic/levels', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.levels.store');
        Route::put('/academic/levels/{schoolLevel}', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.levels.update');
        Route::post('/academic/grades', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.grades.store');
        Route::put('/academic/grades/{grade}', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.grades.update');
        Route::post('/academic/sections', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.sections.store');
        Route::put('/academic/sections/{section}', fn () => abort(403, 'Use References > Codes to manage academic structure.'))->name('academic.sections.update');
    });
    Route::middleware('permission:academic_year.manage')->group(function (): void {
        Route::get('/academic/operations', [SchoolAcademicController::class, 'index'])->middleware('permission:academic.view')->name('school-academic.index');
        Route::get('/academic/operations/subjects', [SchoolAcademicController::class, 'subjects'])->middleware('permission:academic.subjects.view')->name('school-academic.subjects');
        Route::get('/academic/operations/terms', [SchoolAcademicController::class, 'terms'])->middleware('permission:academic_year.view')->name('school-academic.terms');
        Route::get('/academic/operations/teacher-assignments', [SchoolAcademicController::class, 'assignments'])->middleware('permission:academic.teacher-assignments.view')->name('school-academic.assignments');
        // Keep the old URL as a compatibility redirect; evaluations live in Online Collaboration.
        Route::get('/academic/operations/assessments', fn () => redirect()->route('collaboration.section', ['section' => 'modification-requests']))->name('school-academic.assessments');
        Route::post('/academic/subjects', [SchoolAcademicController::class, 'storeSubject'])->middleware('permission:academic.subjects.manage')->name('school-academic.subjects.store');
        Route::put('/academic/subjects/{subject}', [SchoolAcademicController::class, 'updateSubject'])->middleware('permission:academic.subjects.manage')->name('school-academic.subjects.update');
        Route::post('/academic/terms', fn () => abort(403, 'Use References > Codes to manage terms.'))->name('school-academic.terms.store');
        Route::put('/academic/terms/{term}', fn () => abort(403, 'Use References > Codes to manage terms.'))->name('school-academic.terms.update');
        Route::post('/academic/assessments', [SchoolAcademicController::class, 'storeAssessment'])->middleware('permission:academic.assessments.manage')->name('school-academic.assessments.store');
        Route::put('/academic/assessments/{assessment}', [SchoolAcademicController::class, 'updateAssessment'])->middleware('permission:academic.assessments.manage')->name('school-academic.assessments.update');
        Route::post('/academic/assessments/{assessment}/approve', [SchoolAcademicController::class, 'approveAssessment'])->middleware('permission:academic.assessments.approve')->name('school-academic.assessments.approve');
        Route::post('/academic/assessments/{assessment}/reject', [SchoolAcademicController::class, 'rejectAssessment'])->middleware('permission:academic.assessments.approve')->name('school-academic.assessments.reject');
        Route::post('/academic/teacher-assignments', [SchoolAcademicController::class, 'storeAssignment'])->middleware('permission:academic.teacher-assignments.manage')->name('school-academic.assignments.store');
        Route::put('/academic/teacher-assignments/{assignment}', [SchoolAcademicController::class, 'updateAssignment'])->middleware('permission:academic.teacher-assignments.manage')->name('school-academic.assignments.update');
    });
    Route::middleware('permission:attendance.student.view')->group(function (): void {
        Route::get('/attendance/students', [AttendanceController::class, 'students'])->name('attendance.students');
        Route::post('/attendance/students', [AttendanceController::class, 'saveStudents'])->middleware('permission:attendance.student.manage')->name('attendance.students.save');
    });
    Route::middleware('permission:attendance.teacher.view')->group(function (): void {
        Route::get('/attendance/teachers', [AttendanceController::class, 'teachers'])->name('attendance.teachers');
        Route::post('/attendance/teachers', [AttendanceController::class, 'saveTeacher'])->middleware('permission:attendance.teacher.manage')->name('attendance.teachers.save');
    });
    Route::middleware('permission:discipline.view')->group(function (): void {
        Route::get('/discipline', [DisciplineController::class, 'index'])->name('discipline.index');
        Route::middleware('permission:discipline.create')->group(function (): void {
            Route::post('/discipline', [DisciplineController::class, 'store'])->name('discipline.store');
            Route::post('/discipline/categories', [DisciplineController::class, 'storeCategory'])->middleware('permission:discipline.categories.manage')->name('discipline.categories.store');
            Route::post('/discipline/types', [DisciplineController::class, 'storeType'])->middleware('permission:discipline.types.manage')->name('discipline.types.store');
        });
        Route::put('/discipline/{incident}', [DisciplineController::class, 'update'])->middleware('permission:discipline.edit')->name('discipline.update');
    });
    Route::middleware('permission:medical.view')->group(function (): void {
        Route::get('/medical-records', [MedicalRecordsController::class, 'index'])->name('medical.index');
        Route::get('/medical-records/{medicalRecord}/download', [MedicalRecordsController::class, 'download'])->middleware('permission:medical.download')->name('medical.download');
        Route::middleware('permission:medical.manage')->group(function (): void {
            Route::post('/medical-records', [MedicalRecordsController::class, 'store'])->middleware('permission:medical.create')->name('medical.store');
            Route::put('/medical-records/{medicalRecord}', [MedicalRecordsController::class, 'update'])->middleware('permission:medical.edit')->name('medical.update');
        });
    });
    Route::middleware('permission:exam-absences.view')->group(function (): void {
        Route::get('/exam-absences', [ExamAbsencesController::class, 'index'])->name('exam-absences.index');
        Route::post('/exam-absences', [ExamAbsencesController::class, 'store'])->middleware('permission:exam-absences.manage')->name('exam-absences.store');
    });
    Route::middleware('permission:grades.view')->group(function (): void {
        Route::get('/grades', [GradesController::class, 'index'])->name('grades.index');
        Route::get('/grades/assessments/create', [GradesController::class, 'create'])->middleware('permission:grades.create')->name('grades.assessments.create');
        Route::get('/grades/assessments/{assessment}/edit', [GradesController::class, 'edit'])->middleware('permission:grades.edit')->name('grades.assessments.edit');
        Route::post('/grades/assessments', [GradesController::class, 'storeAssessment'])->middleware('permission:grades.create')->name('grades.assessments.store');
        Route::put('/grades/assessments/{assessment}', [GradesController::class, 'updateAssessment'])->middleware('permission:grades.edit')->name('grades.assessments.update');
        Route::post('/grades', [GradesController::class, 'save'])->middleware('permission:grades.save')->name('grades.save');
        Route::post('/grades/{gradeEntry}/lock', [GradesController::class, 'lock'])->middleware('permission:grades.lock')->name('grades.lock');
    Route::post('/collaboration/evaluation-modification-requests', [CollaborationController::class, 'storeModificationRequest'])->middleware('permission:evaluation.modification-requests.create')->name('collaboration.modification-requests.store');
    Route::post('/collaboration/evaluation-modification-requests/{modificationRequest}/approve', [CollaborationController::class, 'approveModificationRequest'])->middleware('permission:evaluation.modification-requests.approve')->name('collaboration.modification-requests.approve');
    Route::post('/collaboration/evaluation-modification-requests/{modificationRequest}/reject', [CollaborationController::class, 'rejectModificationRequest'])->middleware('permission:evaluation.modification-requests.reject')->name('collaboration.modification-requests.reject');
    });
    Route::get('/collaboration', [CollaborationController::class, 'index'])->middleware('permission:collaboration.view')->name('collaboration.index');
    Route::get('/collaboration/{section}', [CollaborationController::class, 'index'])
        ->where('section', 'course-content|messages|calendar|performance|performance-dashboard|evaluation-dashboard|evaluation-grades|grade-book|evaluations-list|student-failures|modification-requests|transcript-subject-choice|evaluation-statement|homework|homework-report|homework-totals|homework-comparison|skills-student|skills-approve|remarks-approve|item-remarks|class-remarks|principal-appreciations|remarks-entry|attendance-management|standards|report-card-receipt|icons|system-error-log|bulk-message|students|teachers')
        ->middleware('permission:collaboration.view')
        ->name('collaboration.section');
    Route::post('/collaboration/announcements', [CollaborationController::class, 'storeAnnouncement'])->middleware('permission:collaboration.announcements.manage')->name('collaboration.announcements.store');
    Route::post('/collaboration/course-content', [CollaborationController::class, 'storeCourseContent'])->middleware('permission:collaboration.course-content.create')->name('collaboration.course-content.store');
    Route::get('/collaboration/course-content/{courseContent}/download', [CollaborationController::class, 'downloadCourseContent'])->middleware('permission:collaboration.course-content.download')->name('collaboration.course-content.download');
    Route::put('/collaboration/course-content/{courseContent}', [CollaborationController::class, 'updateCourseContent'])->middleware('permission:collaboration.course-content.edit')->name('collaboration.course-content.update');
    Route::delete('/collaboration/course-content/{courseContent}', [CollaborationController::class, 'destroyCourseContent'])->middleware('permission:collaboration.course-content.delete')->name('collaboration.course-content.destroy');
    Route::post('/collaboration/events', [CollaborationController::class, 'storeEvent'])->middleware('permission:collaboration.events.manage')->name('collaboration.events.store');
    Route::post('/collaboration/performance-evaluations', [CollaborationController::class, 'storePerformanceEvaluation'])->middleware('permission:performance.create')->name('collaboration.performance.store');
    Route::middleware('permission:timetable.view')->group(function (): void {
        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::middleware('permission:timetable.manage')->group(function (): void {
            Route::post('/timetable/periods', [TimetableController::class, 'storePeriod'])->middleware('permission:timetable.periods.manage')->name('timetable.periods.store');
            Route::delete('/timetable/periods/{timetablePeriod}', [TimetableController::class, 'destroyPeriod'])->middleware('permission:timetable.periods.manage')->name('timetable.periods.destroy');
            Route::post('/timetable/generate', [TimetableController::class, 'generate'])->middleware('permission:timetable.entries.create')->name('timetable.generate');
            Route::post('/timetable/entries', [TimetableController::class, 'store'])->middleware('permission:timetable.entries.create')->name('timetable.entries.store');
            Route::put('/timetable/entries/{timetableEntry}', [TimetableController::class, 'update'])->middleware('permission:timetable.entries.edit')->name('timetable.entries.update');
            Route::delete('/timetable/entries/{timetableEntry}', [TimetableController::class, 'destroy'])->middleware('permission:timetable.entries.delete')->name('timetable.entries.destroy');
        });
    });
    Route::middleware('permission:substitutions.view')->group(function (): void {
        Route::get('/teacher-substitutions', [SubstitutionsController::class, 'index'])->name('substitutions.index');
        Route::post('/teacher-substitutions/{teacherSubstitution}', [SubstitutionsController::class, 'assign'])->middleware('permission:substitutions.assign')->name('substitutions.assign');
        Route::post('/teacher-substitutions/{teacherSubstitution}/recommend', [SubstitutionsController::class, 'recommend'])->middleware('permission:substitutions.recommend')->name('substitutions.recommend');
    });
    Route::middleware('permission:people.view')->group(function (): void {
        Route::get('/teachers', [PeopleController::class, 'teachers'])->name('teachers.index');
        Route::get('/teachers/{teacher}', [PeopleController::class, 'showTeacher'])->name('teachers.show');
        Route::get('/responsible-persons', [PeopleController::class, 'responsible'])->name('responsible.index');
        Route::get('/responsible-persons/students/search', [PeopleController::class, 'searchStudents'])->name('responsible.students.search');
        Route::post('/teachers', [PeopleController::class, 'storeTeacher'])->middleware('permission:people.manage')->name('teachers.store');
        Route::post('/responsible-persons', [PeopleController::class, 'storeResponsible'])->middleware('permission:people.manage')->name('responsible.store');
        Route::get('/responsible-persons/{responsiblePerson}/edit', [PeopleController::class, 'editResponsible'])->middleware('permission:people.manage')->name('responsible.edit');
        Route::put('/responsible-persons/{responsiblePerson}', [PeopleController::class, 'updateResponsible'])->middleware('permission:people.manage')->name('responsible.update');
        Route::delete('/responsible-persons/{responsiblePerson}', [PeopleController::class, 'destroyResponsible'])->middleware('permission:people.manage')->name('responsible.destroy');
    });
    Route::middleware('permission:class-lists.view')->group(function (): void {
        Route::get('/class-book-lists', [ClassBookListsController::class, 'index'])->name('class-lists.index');
        Route::get('/class-book-lists/{classBookList}/print', [ClassBookListsController::class, 'print'])->middleware('permission:class-lists.print')->name('class-lists.print');
    });
    Route::middleware('permission:class-lists.manage')->group(function (): void {
        Route::get('/class-book-lists/create', [ClassBookListsController::class, 'create'])->middleware('permission:class-lists.create')->name('class-lists.create');
        Route::post('/class-book-lists', [ClassBookListsController::class, 'store'])->middleware('permission:class-lists.create')->name('class-lists.store');
    });
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/csv', [ReportsController::class, 'csv'])->middleware('permission:reports.export')->name('reports.csv');
        Route::get('/reports/school', [SchoolReportsController::class, 'index'])->name('reports.school');
        Route::get('/reports/school/print', [SchoolReportsController::class, 'print'])->middleware('permission:reports.print')->name('reports.school.print');
        Route::get('/reports/school/csv', [SchoolReportsController::class, 'csv'])->middleware('permission:reports.export')->name('reports.school.csv');
    });
    Route::middleware('permission:admin.manage')->group(function (): void {
        Route::get('/admin/users', [AdministrationController::class, 'users'])->middleware('permission:admin.users.view')->name('admin.users');
        Route::post('/admin/users', [AdministrationController::class, 'storeUser'])->middleware('permission:admin.users.create')->name('admin.users.store');
        Route::get('/admin/users/{user}/access', [AdministrationController::class, 'editUserAccess'])->middleware('permission:admin.users.access.view')->name('admin.users.access.edit');
        Route::put('/admin/users/{user}/access', [AdministrationController::class, 'updateUserAccess'])->middleware('permission:admin.users.access.manage')->name('admin.users.access.update');
        Route::get('/admin/users/{user}/edit', [AdministrationController::class, 'editUser'])->middleware('permission:admin.users.edit')->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdministrationController::class, 'updateUser'])->middleware('permission:admin.users.edit')->name('admin.users.update');
        Route::get('/admin/users/{user}/photo', [AdministrationController::class, 'userPhoto'])->middleware('permission:admin.users.view')->name('admin.users.photo');
        Route::post('/admin/users/{user}/toggle', [AdministrationController::class, 'toggleUser'])->middleware('permission:admin.users.toggle')->name('admin.users.toggle');
        Route::delete('/admin/users/{user}', [AdministrationController::class, 'destroyUser'])->middleware('permission:admin.users.delete')->name('admin.users.destroy');
        Route::post('/admin/users/{user}/controller-administrator', [AdministrationController::class, 'transferControllerAuthority'])->middleware('can:manage-branches')->name('admin.users.controller.transfer');
        Route::get('/admin/audit', [AdministrationController::class, 'audit'])->middleware(['permission:admin.audit.view', 'can:manage-branches'])->name('admin.audit');
    });
    Route::middleware(['permission:admin.manage', 'can:manage-branches'])->group(function (): void {
        // Keep the legacy URL working, but manage access from each user row instead.
        Route::get('/admin/roles', fn () => redirect()->route('admin.users'))->middleware('permission:admin.roles.view')->name('admin.roles');
        Route::get('/admin/recycle-bin', [AdministrationController::class, 'recycleBin'])->middleware('permission:admin.recycle.view')->name('admin.recycle');
        Route::post('/admin/recycle-bin/{type}/{id}', [AdministrationController::class, 'restore'])->middleware('permission:admin.recycle.restore')->name('admin.recycle.restore');
        Route::get('/admin/settings', [AdministrationController::class, 'settings'])->middleware('permission:admin.settings.view')->name('admin.settings');
        Route::put('/admin/settings', [AdministrationController::class, 'saveSettings'])->middleware('permission:admin.settings.manage')->name('admin.settings.update');
    });
    Route::middleware(['permission:admin.branches.view', 'can:manage-branches'])->group(function (): void {
        Route::get('/admin/branches', [BranchesController::class, 'index'])->name('branches.index');
        Route::get('/admin/branches/users/search', [BranchesController::class, 'searchUsers'])->name('branches.users.search');
        Route::post('/admin/branches', [BranchesController::class, 'store'])->middleware('permission:admin.branches.manage')->name('branches.store');
        Route::put('/admin/branches/{branch}', [BranchesController::class, 'update'])->middleware('permission:admin.branches.manage')->name('branches.update');
        Route::delete('/admin/branches/{branch}', [BranchesController::class, 'destroy'])->middleware('permission:admin.branches.manage')->name('branches.destroy');
    });
    Route::middleware(['permission:admin.imports.view', 'can:manage-branches'])->group(function (): void {
        Route::get('/admin/imports', [DataImportController::class, 'index'])->name('admin.imports');
        Route::post('/admin/imports/students', [DataImportController::class, 'students'])->middleware('permission:admin.imports.manage')->name('admin.imports.students');
        Route::post('/admin/imports/books', [DataImportController::class, 'books'])->middleware('permission:admin.imports.manage')->name('admin.imports.books');
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
