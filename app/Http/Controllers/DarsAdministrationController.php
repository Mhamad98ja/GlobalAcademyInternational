<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DarsAdministrationController extends Controller
{
    public function show(Request $request, string $page): View
    {
        $pages = collect([
            'school-year' => ['title' => 'School Year', 'group' => 'Configuration', 'legacy' => 'academic.index'],
            'menu' => ['title' => 'Menu', 'group' => 'Configuration', 'legacy' => 'admin.roles'],
            'pages' => ['title' => 'Pages', 'group' => 'Configuration', 'legacy' => 'admin.roles'],
            'report-header' => ['title' => 'Report Header', 'group' => 'Configuration', 'legacy' => 'admin.settings'],
            'configuration' => ['title' => 'Configuration', 'group' => 'Configuration', 'legacy' => 'admin.settings'],
            'dynamic-fields' => ['title' => 'Dynamic Fields', 'group' => 'Configuration', 'legacy' => 'admin.settings'],
            'dictionary' => ['title' => 'Dictionary', 'group' => 'References', 'legacy' => 'admin.settings'],
            'family-type' => ['title' => 'Family Type', 'group' => 'References', 'legacy' => 'responsible.index'],
            'family-remark-type' => ['title' => 'Family Remark Type', 'group' => 'References', 'legacy' => 'discipline.index'],
            'colleges' => ['title' => 'Schools', 'group' => 'References', 'legacy' => 'academic.index'],
            'import' => ['title' => 'Import', 'group' => 'References', 'legacy' => 'admin.imports'],
            'file-category' => ['title' => 'File Category', 'group' => 'References', 'legacy' => 'admin.settings'],
            'mail-type' => ['title' => 'Message Type', 'group' => 'References', 'legacy' => 'messages.index'],
        ]);
        abort_unless($pages->has($page), 404);

        $query = trim((string) $request->query('q', ''));
        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $settings = SystemSetting::query()->when($query !== '', fn ($builder) => $builder->where(fn ($settings) => $settings->whereInsensitiveLike('key', $query)->orWhereInsensitiveLike('value', $query)))->orderBy('key')->get();
        $catalogs = [
            'menu' => [['Order' => '12', 'Menu' => 'Activity', 'CSS class' => 'description'], ['Order' => '13', 'Menu' => 'Reports', 'CSS class' => 'assignment']],
            'pages' => [
                ['Menu' => 'Activity', 'Order' => '1', 'Name' => 'ScoActiviteGroupe.aspx', 'Module' => 'Activity', 'Display name' => 'Activity Group'],
                ['Menu' => '', 'Order' => '2', 'Name' => 'ScoActivite.aspx', 'Module' => 'Activity', 'Display name' => 'Activity'],
                ['Menu' => '', 'Order' => '3', 'Name' => 'ScoActivitePotos.aspx', 'Module' => 'Activity', 'Display name' => 'Activity List'],
                ['Menu' => '', 'Order' => '5', 'Name' => 'ScoActiviteEleveAdmin.aspx', 'Module' => 'Activity', 'Display name' => 'Add Activities to Students'],
            ],
            'dictionary' => [
                ['Code' => 'addedval', 'English term' => 'Added Value'], ['Code' => 'AncBal', 'English term' => 'Previous balance'],
                ['Code' => 'Brut', 'English term' => 'Net'], ['Code' => 'ebita', 'English term' => 'EBITA'],
                ['Code' => 'netincyear', 'English term' => 'Net income for the year'], ['Code' => 'operatinc', 'English term' => 'Operating income'],
                ['Code' => 'Recu', 'English term' => 'Invoice'], ['Code' => 'Red', 'English term' => 'Discount'],
            ],
            'family-type' => [
                ['Type' => 'Opening Discount A', 'Discount percentage' => '50', 'Account number' => '7191.0001 LBP', 'Family type' => 'Yes', 'Discount reason' => 'Yes'],
                ['Type' => 'Opening Discount B', 'Discount percentage' => '35', 'Account number' => '7191.0001 LBP', 'Family type' => 'Yes', 'Discount reason' => 'Yes'],
                ['Type' => 'Opening Discount C', 'Discount percentage' => '30', 'Account number' => '7191.0001 LBP', 'Family type' => 'Yes', 'Discount reason' => 'Yes'],
                ['Type' => 'Regular', 'Discount percentage' => '0', 'Account number' => '7191.0001 LBP', 'Family type' => 'Yes', 'Discount reason' => 'Yes'],
                ['Type' => 'Staff A', 'Discount percentage' => '50', 'Account number' => '7191.0001 LBP', 'Family type' => 'Yes', 'Discount reason' => 'Yes'],
            ],
            'file-category' => [
                ['Order' => '', 'Category' => 'Application', 'Screen code' => 'student', 'Is image' => 'Yes', 'Used during registration' => '', 'Required' => '', 'Class from' => '', 'Class to' => ''],
                ['Order' => '', 'Category' => 'Other Files', 'Screen code' => 'parent', 'Is image' => 'Yes', 'Used during registration' => '', 'Required' => '', 'Class from' => '', 'Class to' => ''],
                ['Order' => '1', 'Category' => 'Civil status extract', 'Screen code' => 'student', 'Is image' => '', 'Used during registration' => 'Yes', 'Required' => 'Yes', 'Class from' => '1', 'Class to' => '115'],
                ['Order' => '2', 'Category' => 'Reason for absence', 'Screen code' => 'student', 'Is image' => '', 'Used during registration' => '', 'Required' => '', 'Class from' => '0', 'Class to' => '0'],
            ],
            'mail-type' => [
                ['Message type' => 'Messages', 'Color' => '#4a5c83', 'Icon URL' => 'forum'], ['Message type' => 'Announcements', 'Color' => '#e31e24', 'Icon URL' => 'new_releases'],
                ['Message type' => 'Calendar', 'Color' => '#0aa9a2', 'Icon URL' => 'event'], ['Message type' => 'Notifications', 'Color' => '#f7191e', 'Icon URL' => 'equalizer'],
                ['Message type' => 'Surveys', 'Color' => '#46c996', 'Icon URL' => 'event_note'], ['Message type' => 'Homework', 'Color' => '#339ec7', 'Icon URL' => 'library_books'],
            ],
        ];

        return view('administration.dars-page', [
            'definition' => $pages->get($page), 'page' => $page, 'query' => $query, 'years' => $years, 'settings' => $settings,
            'catalog' => $catalogs[$page] ?? [],
            'school' => ['name' => SystemSetting::query()->where('key', 'school_name')->value('value') ?: 'Global Academy International', 'address' => SystemSetting::query()->where('key', 'school_address')->value('value') ?: '--', 'phone' => SystemSetting::query()->where('key', 'school_phone')->value('value') ?: '--'],
        ]);
    }
}
