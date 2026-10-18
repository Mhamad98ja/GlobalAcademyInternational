<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ isset($title) ? $title.' · Global Academy International' : 'Global Academy International' }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@if(isset($title))<script>document.title = @json($title.' · Global Academy International');</script>@endif
@php
    $signedInUser = auth()->user();
    $isTeacherAccount = $signedInUser && ($signedInUser->hasRole('teacher') || $signedInUser->teacher_id);
    $homeRoute = $isTeacherAccount ? route('collaboration.index', ['section' => 'homework']) : route('dashboard');
    $workspace = request()->route('workspace');
    $module = match (true) {
        request('module') === 'students' && request()->routeIs('reports.*', 'school-academic.*') => 'students',
        request()->routeIs('students.references.*', 'academic.*') => 'administration',
        request()->routeIs('students.*', 'responsible.*', 'attendance.students*', 'discipline.*', 'medical.*', 'exam-absences.*') || $workspace === 'students' => 'students',
        request()->routeIs('grades.*') => 'collaboration',
        request()->routeIs('reports.*', 'report-card.*', 'school-academic.*', 'timetable.*') || $workspace === 'academic' => 'reports',
        request()->routeIs('collaboration.*', 'messages.*', 'attendance.teachers*', 'substitutions.*') || $workspace === 'teachers' => 'collaboration',
        request()->routeIs('admin.*', 'books.*', 'class-lists.*', 'suppliers.*', 'purchases.*', 'supplier-returns.*', 'distributions.*', 'inventory.*', 'transfers.*', 'finance.*', 'invoices.*', 'payments.*', 'returns.*', 'operations.*', 'teachers.*') || $workspace === 'operations' => 'administration',
        default => null,
    };
    $moduleMenus = [
        'students' => [
            'home' => ['route' => 'students.dashboard', 'label' => 'Home', 'icon' => 'dashboard', 'permission' => 'students.view'],
            'items' => [
                ['route' => 'students.index', 'label' => 'Student Search', 'icon' => 'students', 'permission' => 'students.view'],
                ['route' => 'attendance.students', 'label' => 'Manipulation absence', 'icon' => 'students', 'permission' => 'attendance.student.view'],
                ['route' => 'medical.index', 'label' => 'Medical Records', 'icon' => 'reports', 'permission' => 'reports.medical.view'],
                ['route' => 'discipline.index', 'label' => 'Issues & Actions', 'icon' => 'reports', 'permission' => 'reports.discipline.view'],
                ['route' => 'students.attestations', 'label' => 'Attestations', 'icon' => 'reports', 'permission' => 'students.attestations.view'],
            ],
            'groups' => [],
        ],
        'reports' => [
            'home' => ['route' => 'workspaces.show', 'params' => ['workspace' => 'academic'], 'label' => 'Dashboard', 'icon' => 'dashboard', 'permission' => 'dashboard.view'],
            'items' => [],
            'groups' => [],
        ],
        'administration' => [
            'home' => ['route' => 'workspaces.show', 'params' => ['workspace' => 'operations'], 'label' => 'Administration & Operations', 'icon' => 'dashboard', 'permission' => 'admin.view'],
            'items' => [
                ['route' => 'academic.index', 'label' => 'School Structure (read-only)', 'icon' => 'academic', 'permission' => 'academic.manage'],
                ['route' => 'teachers.index', 'label' => 'Teachers List', 'icon' => 'teachers', 'permission' => 'people.view'],
                ['route' => 'attendance.teachers', 'label' => 'Teacher Attendance', 'icon' => 'reports', 'permission' => 'attendance.teacher.view'],
                ['route' => 'substitutions.index', 'label' => 'Teacher Substitutions', 'icon' => 'teachers', 'permission' => 'substitutions.view'],
                ['route' => 'timetable.index', 'label' => 'Timetable & Period Setup', 'icon' => 'academic', 'permission' => 'timetable.view'],
            ],
            'groups' => [
                ['label' => 'References', 'items' => [
                    ['route' => 'students.references.show', 'params' => ['reference' => 'codes'], 'label' => 'Codes', 'icon' => 'administration', 'permission' => 'students.view', 'controller_only' => true],
                    ['route' => 'students.references.show', 'params' => ['reference' => 'other-schools'], 'label' => 'Other Schools', 'icon' => 'administration', 'permission' => 'students.view', 'controller_only' => true],
                    ['route' => 'admin.imports', 'label' => 'Import', 'icon' => 'administration', 'permission' => 'admin.manage', 'controller_only' => true],
                ]],
            ],
        ],
        'collaboration' => [
            'home' => ['route' => 'collaboration.index', 'label' => 'Home', 'icon' => 'dashboard', 'permission' => 'collaboration.view'],
            'items' => [
                ['route' => 'collaboration.index', 'params' => ['section' => 'homework'], 'label' => 'Subject Agenda', 'icon' => 'academic', 'permission' => 'collaboration.agenda.view'],
                ['route' => 'messages.index', 'label' => 'Messages', 'icon' => 'responsible', 'permission' => 'messages.view'],
                ['route' => 'collaboration.index', 'params' => ['section' => 'calendar'], 'label' => 'Calendar', 'icon' => 'academic', 'permission' => 'collaboration.calendar.view'],
            ],
            'groups' => [
                ['label' => 'Evaluations', 'items' => [
                    ['route' => 'collaboration.index', 'params' => ['section' => 'evaluation-dashboard'], 'label' => 'Dashboard', 'icon' => 'reports', 'permission' => 'evaluation.dashboard.view'],
                    ['route' => 'grades.index', 'params' => ['view' => 'evaluations'], 'label' => 'Evaluation', 'icon' => 'academic', 'permission' => 'evaluation.view'],
                    ['route' => 'grades.assessments.create', 'label' => 'New evaluation', 'icon' => 'academic', 'permission' => 'evaluation.create'],
                    ['route' => 'collaboration.section', 'params' => ['section' => 'modification-requests'], 'label' => 'Modification request', 'icon' => 'reports', 'permission' => 'evaluation.modification-requests.view'],
                ]],
            ],
        ],
    ];
    $moduleMenus['students']['groups'] = collect(\App\Support\StudentDossierNavigation::sections())
        ->map(fn (array $section) => ['label' => $section['label'], 'items' => $section['items']])->values()->all();
    $adminMenu = [
        'School Year' => ['page' => 'school-year', 'label' => 'Année Scolaire'],
        'Menu' => ['page' => 'menu', 'label' => 'Menu'],
        'Pages' => ['page' => 'pages', 'label' => 'Pages'],
        'Report Header' => ['page' => 'report-header', 'label' => 'Entête des Rapports'],
        'Configuration' => ['page' => 'configuration', 'label' => 'Configuration'],
        'Dynamic Fields' => ['page' => 'dynamic-fields', 'label' => 'Champs Dynamiques'],
        'Dictionary' => ['page' => 'dictionary', 'label' => 'Dictionnaire'],
        'Family Type' => ['page' => 'family-type', 'label' => 'Type de Famille'],
        'Family Remark Type' => ['page' => 'family-remark-type', 'label' => 'Type de Remarque Famille'],
        'Colleges' => ['page' => 'colleges', 'label' => 'Collèges'],
        'Import' => ['page' => 'import', 'label' => 'Importer'],
        'File Category' => ['page' => 'file-category', 'label' => 'Catégorie de Fichier'],
        'Mail Type' => ['page' => 'mail-type', 'label' => 'Type de Courrier'],
    ];
    foreach ($moduleMenus['administration']['groups'] as &$adminGroup) {
        $adminGroup['label'] = $adminGroup['label'] === 'References' ? 'Références' : $adminGroup['label'];
        foreach ($adminGroup['items'] as &$adminItem) {
            if (isset($adminMenu[$adminItem['label']])) {
                $adminItem['adminPage'] = $adminMenu[$adminItem['label']]['page'];
                $adminItem['label'] = $adminMenu[$adminItem['label']]['label'];
            }
        }
        unset($adminItem);
    }
    unset($adminGroup);
    $collaborationMenu = [
        'Teachers List' => ['page' => 'teachers', 'label' => 'Liste des Enseignants'],
        'Course Content' => ['page' => 'course-content', 'label' => 'Subject Agenda'],
        'Messages' => ['page' => 'messages', 'label' => 'Messages'],
        'Calendar' => ['page' => 'calendar', 'label' => 'Calendrier'],
        'Performance Appraisals' => ['page' => 'performance', 'label' => 'Évaluations de performance'],
        'Students' => ['page' => 'students', 'label' => 'Élèves'],
        'Performance Dashboard' => ['page' => 'performance-dashboard', 'label' => 'Tableau de bord de performance'],
        'Dashboard' => ['page' => 'evaluation-dashboard', 'label' => 'Tableau de Bord'],
        'Evaluation Grades' => ['page' => 'evaluation-grades', 'label' => 'Notes d’Évaluations'],
        'Grade Book' => ['page' => 'grade-book', 'label' => 'Cahier de Notes'],
        'Evaluations List' => ['page' => 'evaluations-list', 'label' => 'Liste des Évaluations'],
        'Student Failures' => ['page' => 'student-failures', 'label' => 'Échec par Élève'],
        'Modification Requests' => ['page' => 'modification-requests', 'label' => 'Demandes de Modifications'],
        'Transcript by Subject Choice' => ['page' => 'transcript-subject-choice', 'label' => 'Relevé par Choix de Matières'],
        'Evaluation Statement' => ['page' => 'evaluation-statement', 'label' => 'Relevé des Évaluations'],
        'Homework' => ['page' => 'homework', 'label' => 'Agenda'],
        'Homework Report' => ['page' => 'homework-report', 'label' => 'Rapport de l’Agenda'],
        'Homework Report Totals' => ['page' => 'homework-totals', 'label' => 'Rapport Total de l’Agenda'],
        'Homework Comparison' => ['page' => 'homework-comparison', 'label' => 'Comparaison de l’Agenda'],
        'Skills per Student' => ['page' => 'skills-student', 'label' => 'Compétences par Élève'],
        'Skills to Approve' => ['page' => 'skills-approve', 'label' => 'Compétences à Approuver'],
        'Remarks to Approve' => ['page' => 'remarks-approve', 'label' => 'Remarques à Approuver'],
        'Item Remarks' => ['page' => 'item-remarks', 'label' => 'Remarques des Éléments'],
        'Remarks per Class' => ['page' => 'class-remarks', 'label' => 'Remarques par Classe'],
        'Cycle Principal Appreciations' => ['page' => 'principal-appreciations', 'label' => 'Appréciations du Responsable de Cycle'],
        'Remarks Entry' => ['page' => 'remarks-entry', 'label' => 'Saisie des Remarques'],
        'Attendance Management' => ['page' => 'attendance-management', 'label' => 'Gestion des Absences'],
        'Standards' => ['page' => 'standards', 'label' => 'Standards'],
        'Report Card Acknowledgment of Receipt' => ['page' => 'report-card-receipt', 'label' => 'Accusé de Réception du Bulletin Scolaire'],
        'Icons' => ['page' => 'icons', 'label' => 'Icônes'],
        'System Error Log' => ['page' => 'system-error-log', 'label' => 'Journal des Erreurs Système'],
        'Bulk Message' => ['page' => 'bulk-message', 'label' => 'Message Groupé'],
    ];
    $moduleMenus['collaboration']['home'] = ['route' => 'collaboration.index', 'label' => 'Accueil', 'icon' => 'dashboard', 'permission' => 'collaboration.view'];
    foreach ($moduleMenus['collaboration']['items'] as &$collaborationItem) {
        if (isset($collaborationMenu[$collaborationItem['label']])) {
            $collaborationItem['collabPage'] = $collaborationMenu[$collaborationItem['label']]['page'];
            $collaborationItem['label'] = $collaborationMenu[$collaborationItem['label']]['label'];
        }
    }
    unset($collaborationItem);
    foreach ($moduleMenus['collaboration']['groups'] as &$collaborationGroup) {
        $collaborationGroup['label'] = match ($collaborationGroup['label']) {
            'Evaluations' => 'Notes d’évaluations',
            'Agenda' => 'Agenda',
            'Skills' => 'Compétences',
            'Remarks' => 'Remarques',
            'Administration' => 'Administration',
            default => $collaborationGroup['label'],
        };
        foreach ($collaborationGroup['items'] as &$collaborationItem) {
            if (isset($collaborationMenu[$collaborationItem['label']])) {
                $collaborationItem['collabPage'] = $collaborationMenu[$collaborationItem['label']]['page'];
                $collaborationItem['label'] = $collaborationMenu[$collaborationItem['label']]['label'];
            }
        }
        unset($collaborationItem);
    }
    unset($collaborationGroup);
    $moduleMenus['reports']['home']['label'] = 'Home';
    $adminLabels = ['school-year' => 'School Year', 'menu' => 'Menu', 'pages' => 'Pages', 'report-header' => 'Report Header', 'configuration' => 'Configuration', 'dynamic-fields' => 'Dynamic Fields', 'dictionary' => 'Dictionary', 'family-type' => 'Family Type', 'family-remark-type' => 'Family Remark Type', 'colleges' => 'Schools', 'import' => 'Import', 'file-category' => 'File Category', 'mail-type' => 'Message Type'];
    foreach ($moduleMenus['administration']['groups'] as &$group) {
        $group['label'] = $group['label'] === 'Rأ©fأ©rences' ? 'References' : $group['label'];
        foreach ($group['items'] as &$item) {
            if (isset($item['adminPage'], $adminLabels[$item['adminPage']])) $item['label'] = $adminLabels[$item['adminPage']];
        }
        unset($item);
    }
    unset($group);
    foreach ($moduleMenus['administration']['groups'] as &$group) {
        // Keep this administration category in English even if legacy menu
        // definitions contain translated or mis-encoded labels.
        if (in_array($group['label'], ['References', 'Références', 'Rأ©fأ©rences', 'Rط£آ©fط£آ©rences'], true)) {
            $group['label'] = 'References';
        }
    }
    unset($group);
    $collaborationLabels = ['teachers' => 'Teachers List', 'course-content' => 'Subject Agenda', 'messages' => 'Messages', 'calendar' => 'Calendar', 'performance' => 'Performance Appraisals', 'students' => 'Students', 'performance-dashboard' => 'Performance Dashboard', 'evaluation-dashboard' => 'Dashboard', 'evaluation-grades' => 'Evaluation Grades', 'grade-book' => 'Grade Book', 'evaluations-list' => 'Evaluations List', 'student-failures' => 'Student Failures', 'modification-requests' => 'My Requests', 'transcript-subject-choice' => 'Transcript by Subject Choice', 'evaluation-statement' => 'Evaluation Statement', 'homework' => 'Agenda Overview', 'homework-report' => 'Agenda Report', 'homework-totals' => 'Agenda Totals', 'homework-comparison' => 'Agenda Comparison', 'skills-student' => 'Skills per Student', 'skills-approve' => 'Skills to Approve', 'remarks-approve' => 'Remarks to Approve', 'item-remarks' => 'Item Remarks', 'class-remarks' => 'Remarks per Class', 'principal-appreciations' => 'Cycle Principal Appreciations', 'remarks-entry' => 'Remarks Entry', 'attendance-management' => 'Attendance Management', 'standards' => 'Standards', 'report-card-receipt' => 'Report Card Acknowledgment of Receipt', 'icons' => 'Icons', 'system-error-log' => 'System Error Log', 'bulk-message' => 'Bulk Message'];
    foreach ($moduleMenus['collaboration']['items'] as &$item) if (isset($item['collabPage'], $collaborationLabels[$item['collabPage']])) $item['label'] = $collaborationLabels[$item['collabPage']];
    unset($item);
    foreach ($moduleMenus['collaboration']['groups'] as &$group) {
        $group['label'] = match ($group['label']) {
            'Notes d’évaluations' => 'Evaluations',
            'Compétences' => 'Skills',
            'Remarques' => 'Remarks',
            default => $group['label'],
        };
        foreach ($group['items'] as &$item) if (isset($item['collabPage'], $collaborationLabels[$item['collabPage']])) $item['label'] = $collaborationLabels[$item['collabPage']];
        unset($item);
    }
    unset($group);
    $moduleMenus['collaboration']['home']['label'] = 'Home';
    // Academic & Report Card is intentionally a shallow workspace. Detailed
    // report choices live in the report centre; the sidebar should only expose
    // the few destinations users need every day.
    $moduleMenus['reports'] = [
        'home' => ['route' => 'workspaces.show', 'params' => ['workspace' => 'academic'], 'label' => 'Academic & Report Card', 'icon' => 'dashboard', 'permission' => 'dashboard.view'],
        'items' => [
            ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'reports', 'permission' => 'reports.view'],
            ['route' => 'school-academic.index', 'label' => 'Academic Operations', 'icon' => 'academic', 'permission' => 'academic_year.manage'],
        ],
        'groups' => [],
    ];
    $menu = $module ? $moduleMenus[$module] : null;
    $coreNavigation = [
        ['workspace' => 'students', 'label' => 'Student Records', 'icon' => 'students', 'permission' => 'students.view'],
        ['workspace' => 'academic', 'label' => 'Academic & Report Card', 'icon' => 'reports', 'permission' => 'reports.view'],
        ['workspace' => 'teachers', 'label' => 'Teaching & Collaboration', 'icon' => 'responsible', 'permission' => 'collaboration.view'],
        ['workspace' => 'operations', 'label' => 'Administration & Operations', 'icon' => 'administration', 'permission' => 'admin.view'],
    ];
    $showAdministrationReferences = $module === 'administration'
        && (request()->routeIs('operations.index') || request()->routeIs('students.references.*') || request()->routeIs('academic.*')
            || (request()->routeIs('workspaces.show') && $workspace === 'operations'));
    $canSee = fn (array $item) => ($item['financial'] ?? false) ? $signedInUser->canViewFinancials() : ((! ($item['controller_only'] ?? false) || $signedInUser->isControllerAdministrator()) && $signedInUser->hasPermission($item['permission']));
    $darsAdministrationLabels = ['School Year', 'Menu', 'Pages', 'Report Header', 'Configuration', 'Dynamic Fields', 'Dictionary', 'Family Type', 'Family Remark Type', 'Colleges', 'Import', 'File Category', 'Mail Type'];
    $darsCollaborationSections = ['Teachers List' => 'teachers', 'Messages' => 'messages', 'Calendar' => 'calendar', 'Performance Appraisals' => 'performance', 'Students' => 'students', 'Performance Dashboard' => 'performance-dashboard', 'Evaluation Grades' => 'evaluation-grades', 'Grade Book' => 'grade-book', 'Evaluations List' => 'evaluations-list', 'Student Failures' => 'student-failures', 'Modification Requests' => 'modification-requests', 'Transcript by Subject Choice' => 'transcript-subject-choice', 'Evaluation Statement' => 'evaluation-statement', 'Skills per Student' => 'skills-student', 'Skills to Approve' => 'skills-approve', 'Remarks to Approve' => 'remarks-approve', 'Item Remarks' => 'item-remarks', 'Remarks per Class' => 'class-remarks', 'Cycle Principal Appreciations' => 'principal-appreciations', 'Remarks Entry' => 'remarks-entry', 'Attendance Management' => 'attendance-management', 'Standards' => 'standards', 'Report Card Acknowledgment of Receipt' => 'report-card-receipt', 'Icons' => 'icons', 'System Error Log' => 'system-error-log', 'Bulk Message' => 'bulk-message'];
    $darsStudentPaths = [
        'Dashboard' => url('/StudentProfile/Profile/Dashboard.aspx'),
        'Search' => url('/StudentProfile/Profile/SearchStudentParent.aspx'),
        'Attendance' => url('/ReportCard/InscFillAbsence.aspx?master=ins'),
        'Attestation' => url('/StudentProfile/Attestation/AttestationMultiReport.aspx'),
        'New Parent' => url('/StudentProfile/Profile/Parent.aspx'),
        'Families without Students' => url('/StudentProfile/Profile/InscFamiliesWithoutChildren.aspx'),
        'Change Section' => url('/StudentProfile/References/InscChangeSection.aspx'),
        'Student Photos' => url('/StudentProfile/References/InscImages.aspx'),
        'Parents Modifications' => url('/StudentProfile/Profile/ParentsModifications.aspx'),
        'Class List Reports' => url('/StudentProfile/Reports/RptInsReports.aspx?pid=2124'),
        'Statistical Report' => url('/StudentProfile/Reports/RptInsReports.aspx?pid=2125'),
        'Label Reports' => url('/StudentProfile/Reports/RptInsReports.aspx?pid=2126'),
        'Catalog Report' => url('/StudentProfile/Reports/RptInsReports.aspx?pid=2127'),
        'Registration Report' => url('/StudentProfile/Reports/RptInsReports.aspx?pid=2128'),
        'Dynamic Reports' => url('/StudentProfile/Reports/StudentFamilyReport.aspx'),
    ];
    $darsAdministrationPaths = ['Users and Roles' => '/Admin/Users.aspx', 'School Year' => '/StudentProfile/References/SchoolYear.aspx', 'Menu' => '/Admin/UsrMenu.aspx', 'Pages' => '/Admin/UsrManagePages.aspx', 'Report Header' => '/Admin/HeaderParameter.aspx', 'Configuration' => '/Admin/UserConfig.aspx', 'Dynamic Fields' => '/Admin/UsrDynamicFields.aspx', 'Dictionary' => '/Admin/Dictionary.aspx', 'Family Type' => '/StudentProfile/References/FamilyType.aspx', 'Family Remark Type' => '/StudentProfile/References/ParentRemarkType.aspx', 'Colleges' => '/Admin/Usrcollege.aspx', 'Import' => '/Admin/ImportData.aspx', 'File Category' => '/Admin/UsrFileCategory.aspx', 'Mail Type' => '/Admin/Ent_MailType.aspx'];
    $darsCollaborationPaths = ['Home' => '/Online/Admin/admHome.aspx', 'Teachers List' => '/Online/Front/TeachersList.aspx', 'Course Content' => '/Online/Timeline/CourseContent.aspx', 'Messages' => '/Online/Mail/MailBox.aspx', 'Calendar' => '/Online/mail/Calendar.aspx', 'Performance Appraisals' => '/Online/Eval/evalPaRecords.aspx', 'Students' => '/Online/Front/SearchStudent.aspx', 'Performance Dashboard' => '/Online/PerformanceDashboard/PerformanceDashboard.aspx', 'Dashboard' => '/Online/Eval/evalTeacherDashboard.aspx', 'Evaluation Grades' => '/Online/Eval/evalList.aspx', 'Grade Book' => '/Online/Eval/evalTeacher.aspx', 'Evaluations List' => '/Online/eval/evalRptListEvaluations.aspx', 'Student Failures' => '/Online/Eval/EvalRptEleveEchoue.aspx', 'Modification Requests' => '/Online/Eval/evalEleveEvaluationLog.aspx', 'Transcript by Subject Choice' => '/Online/Eval/EvalRptClasseMatieresColonne.aspx', 'Evaluation Statement' => '/Online/front/evalparclasse.aspx', 'Homework' => '/Online/Agenda/agendaTeacherPrincipal.aspx', 'Homework Report' => '/Online/Agenda/RptAgenda.aspx', 'Homework Report Totals' => '/Online/Agenda/RptAgendaProfCount.aspx', 'Homework Comparison' => '/Online/Agenda/RptAgendaComparaison.aspx', 'Skills per Student' => '/Online/Competence/CmpEleves.aspx', 'Skills to Approve' => '/Online/Competence/CmpFillCompetencePrincipal.aspx', 'Remarks to Approve' => '/Online/Competence/CmpFillRemarquePrincipal.aspx', 'Item Remarks' => '/Online/Competence/CmpFillRemarks.aspx', 'Remarks per Class' => '/Online/Front/RptEleveClasseRemarque.aspx', 'Cycle Principal Appreciations' => '/Online/Front/RemarquePrincipalParClasse.aspx', 'Remarks Entry' => '/Online/Front/RemarquePrincipalParClasseManipulation.aspx', 'Attendance Management' => '/Online/Front/StudentsAttendance.aspx', 'Standards' => '/Online/Admin/courseStandard.aspx', 'Report Card Acknowledgment of Receipt' => '/Online/Admin/RptReportCardView.aspx', 'Icons' => '/Online/Admin/AdmEntFileTypeIcon.aspx', 'System Error Log' => '/Online/Admin/admUsrErrorLog.aspx', 'Bulk Message' => '/Online/Mail/TemplateMessage.aspx'];
    // The sidebar is the primary way through the application. Always use the
    // current Laravel routes here, rather than historical .aspx aliases which
    // redirect again and make the user's location hard to follow.
    $itemUrl = function (array $item) use ($module): string {
        $url = $item['url'] ?? null;
        if (! $url && $module === 'collaboration' && isset($item['collabPage'])) {
            $url = route('collaboration.section', ['section' => $item['collabPage']]);
        } elseif (! $url && $module === 'administration' && isset($item['adminPage'])) {
            $url = route('admin.dars.show', ['page' => $item['adminPage']]);
        } else {
            $url ??= route($item['route'], $item['params'] ?? []);
        }

        return $url.(! empty($item['anchor']) ? '#'.$item['anchor'] : '');
    };
    $itemIsActive = fn (array $item) => (($module === 'collaboration' && isset($item['collabPage']))
         ? request()->routeIs('collaboration.section') && request()->route('section') === $item['collabPage']
         : (($module === 'collaboration' && isset($darsCollaborationSections[$item['label']]))
         ? (request()->routeIs('collaboration.index') || request()->routeIs('collaboration.section')) && (request('section') ?? request()->route('section')) === $darsCollaborationSections[$item['label']]
         : (($module === 'administration' && isset($item['adminPage']))
         ? request()->routeIs('admin.dars.show') && request()->route('page') === $item['adminPage']
         : (($module === 'administration' && in_array($item['label'], $darsAdministrationLabels, true))
         ? request()->routeIs('admin.dars.show') && request()->route('page') === \Illuminate\Support\Str::slug($item['label'])
         : request()->routeIs($item['route']) && !isset($item['url'])))))
        && collect($item['params'] ?? [])->every(fn ($value, $key) => (string) (request()->route($key) ?? request($key)) === (string) $value);
    $moduleContext = match ($module) {
        'students' => ['label' => 'Student Records', 'route' => route('students.index')],
        'reports' => ['label' => 'Academic & Report Card', 'route' => route('workspaces.show', ['workspace' => 'academic'])],
        'collaboration' => ['label' => 'Teaching & Collaboration', 'route' => route('collaboration.index')],
        'administration' => ['label' => 'Administration & Operations', 'route' => route('workspaces.show', ['workspace' => 'operations'])],
        default => null,
    };
    $showNavigationContext = ! request()->routeIs('dashboard');
    $backFallback = $moduleContext['route'] ?? $homeRoute;
    if (url()->current() === $backFallback) {
        $backFallback = $homeRoute;
    }
@endphp
@if(isset($title))<script>document.title = @json($title) + ' ' + String.fromCharCode(183) + ' Global Academy International';</script>@endif
<div class="app-shell" data-app-shell>
    <div class="sidebar-backdrop no-print" data-sidebar-backdrop></div>
    <aside id="primary-sidebar" class="sidebar no-print" aria-label="Module navigation">
        <div class="sidebar-brand">
            <a class="sidebar-brand-mark ga-brand" href="{{ $homeRoute }}" aria-label="Global Academy International home"><img src="{{ asset('images/global-academy-international-logo.png') }}" alt=""><span><strong>Global Academy</strong><small>International</small></span></a>
            <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close navigation">&times;</button>
        </div>
        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-2 py-3">
            @if ($menu)
                @if ($canSee($menu['home']))
                    <a href="{{ $itemUrl($menu['home']) }}" class="sidebar-link {{ $itemIsActive($menu['home']) ? 'active' : '' }}"><span class="sidebar-link-icon"><x-nav-icon :name="$menu['home']['icon']" /></span><span class="sidebar-link-label">{{ $menu['home']['label'] }}</span></a>
                @endif
                @foreach ($menu['items'] as $item)
                    @if ($canSee($item))
                        <a href="{{ $itemUrl($item) }}" class="sidebar-link {{ $itemIsActive($item) ? 'active' : '' }}"><span class="sidebar-link-icon"><x-nav-icon :name="$item['icon']" /></span><span class="sidebar-link-label">{{ $item['label'] }}</span></a>
                    @endif
                @endforeach
                @foreach ($menu['groups'] as $group)
                    @if ($group['label'] === 'References' && ! $showAdministrationReferences)
                        @continue
                    @endif
                    @php($items = collect($group['items'])->filter(fn (array $item) => $canSee($item) && (!str_starts_with($item['route'], 'reports.') || $signedInUser->hasPermission('reports.view'))))
                    @if ($items->isNotEmpty())
                        @php($groupIsActive = $items->contains($itemIsActive))
                        <section class="sidebar-nav-group {{ $groupIsActive ? '' : 'is-collapsed' }}" data-sidebar-group aria-label="{{ $group['label'] }}">
                            <button type="button" class="sidebar-nav-heading" data-sidebar-group-toggle aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}"><span>{{ $group['label'] }}</span><svg class="sidebar-nav-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 8 4 4 4-4" /></svg></button>
                            <div class="sidebar-nav-items">
                                @foreach ($items as $item)
                                    <a href="{{ $itemUrl($item) }}" class="sidebar-link {{ $itemIsActive($item) ? 'active' : '' }}"><span class="sidebar-link-icon"><x-nav-icon :name="$item['icon']" /></span><span class="sidebar-link-label">{{ $item['label'] }}</span></a>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            @else
                <p class="sidebar-nav-heading sidebar-nav-heading-static">Main areas</p>
                @foreach ($coreNavigation as $coreItem)
                    @if ($signedInUser->hasPermission($coreItem['permission']))
                        <a href="{{ route('workspaces.show', ['workspace' => $coreItem['workspace']]) }}" class="sidebar-link"><span class="sidebar-link-icon"><x-nav-icon :name="$coreItem['icon']" /></span><span class="sidebar-link-label">{{ $coreItem['label'] }}</span></a>
                    @endif
                @endforeach
            @endif
        </nav>
        <div class="shrink-0 border-t border-white/10 p-2"><form method="POST" action="{{ route('logout') }}">@csrf<button class="sidebar-link w-full text-left"><span class="sidebar-link-icon"><x-nav-icon name="sign-out" /></span><span class="sidebar-signout-label">Sign out</span></button></form></div>
    </aside>
    <main class="main">
        <header class="topbar no-print">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="nav-toggle" data-sidebar-toggle aria-controls="primary-sidebar" aria-expanded="true"><span class="sr-only">Toggle navigation</span><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                <a href="{{ $homeRoute }}" class="topbar-school-chip ga-topbar-brand"><img src="{{ asset('images/global-academy-international-logo.png') }}" alt=""><span>Global Academy International</span></a>
                <form method="GET" action="{{ route('students.index') }}" class="sbm-global-search" role="search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path></svg>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search students, staff, classes..." aria-label="Search students, staff, or classes">
                </form>
                @php($branchContext = app(\App\Services\BranchContext::class))
                @php($activeBranch = $branchContext->branch())
                @php($availableBranches = $branchContext->accessibleBranches($signedInUser))
                @if ($signedInUser->isControllerAdministrator() && $availableBranches->count() > 1)
                    <form method="POST" action="{{ route('branches.select') }}" class="hidden sm:block">@csrf
                        <select id="branch-switcher" name="branch_id" onchange="this.form.submit()" class="topbar-branch-select">
                            <option value="{{ $activeBranch?->id }}">{{ $activeBranch?->name ?? 'School' }}</option>
                            @foreach ($availableBranches as $availableBranch)
                                @if ($availableBranch->id !== $activeBranch?->id)
                                    <option value="{{ $availableBranch->id }}">{{ $availableBranch->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </form>
                @elseif ($activeBranch)
                    <span class="hidden text-xs font-medium text-slate-500 sm:block">{{ $activeBranch->name }}</span>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-2"><a href="{{ route('notifications.index') }}" class="topbar-icon-button relative" aria-label="Notifications"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.31 6.022 23.848 23.848 0 0 0 5.454 1.31m5.713 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>@if($signedInUser->unreadNotifications()->count())<span class="absolute -right-1 -top-1 min-w-4 rounded-full bg-orange-500 px-1 text-center text-[9px] font-bold leading-4 text-white">{{ min($signedInUser->unreadNotifications()->count(), 99) }}</span>@endif</a><span class="topbar-help">Help</span><span class="topbar-user">{{ $signedInUser->name }}</span><form method="POST" action="{{ route('logout') }}" class="topbar-signout-form">@csrf<button type="submit" class="topbar-signout"><x-nav-icon name="sign-out" /><span>Sign out</span></button></form></div>
        </header>
        @if ($showNavigationContext)
            <nav class="app-context no-print" aria-label="Page navigation">
                <a class="app-context-back" href="{{ $backFallback }}" data-history-back>← Back</a>
            </nav>
        @endif
        <div class="page">@include('components.flash')@yield('content')</div>
    </main>
</div>
<script>document.querySelectorAll('[data-modal]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.modal).classList.add('is-open')));document.querySelectorAll('[data-close-modal]').forEach(b=>b.addEventListener('click',()=>b.closest('.modal').classList.remove('is-open')));document.querySelectorAll('form[data-submit-lock]').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('[type=submit]');if(b){b.disabled=true;b.dataset.original=b.textContent;b.textContent='Saving...'}}));document.querySelectorAll('[data-history-back]').forEach(link=>link.addEventListener('click',event=>{try{const previous=new URL(document.referrer);if(previous.origin===window.location.origin&&previous.href!==window.location.href){event.preventDefault();window.history.back()}}catch{}}));</script>
</body></html>
