<?php

declare(strict_types=1);

namespace App\Support;

class StudentDossierNavigation
{
    /** @return array<string, array<string, mixed>> */
    public static function sections(): array
    {
        $item = static fn (string $label, string $description, string $route, string $permission, array $params = [], string $anchor = '') => compact('label', 'description', 'route', 'permission', 'params', 'anchor') + ['icon' => 'students'];

        return [
            'file' => ['label' => 'Student Records', 'description' => 'Student files, family records, class assignments, photos, and historical records.', 'items' => [
                $item('New Parent or Guardian', 'Create a family or guardian record and link their children.', 'responsible.index', 'people.manage'),
                $item('Change Section', 'Move active students to another section in the same grade.', 'students.section-changes', 'students.manage'),
                $item('Student Photos', 'Review and update photos by year, grade, and section.', 'students.photos', 'students.view'),
            ]],
        ];
    }
}
