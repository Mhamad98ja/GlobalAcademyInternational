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
            'references' => ['label' => 'References', 'description' => 'Maintain the academic references used by student registrations, attendance, and reports.', 'items' => [
                $item('Codes', 'Maintain registration and profile reference values.', 'students.references.show', 'students.view', ['reference' => 'codes']),
                $item('Division', 'Maintain school divisions and cycles.', 'students.references.show', 'students.view', ['reference' => 'division']),
                $item('Class Definitions', 'Maintain class codes and their display order.', 'students.references.show', 'students.view', ['reference' => 'class-definition']),
                $item('Classes', 'Maintain classes, sections, rooms, and divisions.', 'students.references.show', 'students.view', ['reference' => 'classes']),
                $item('Other Schools', 'Review previous schools recorded for students.', 'students.references.show', 'students.view', ['reference' => 'other-schools']),
                $item('Districts', 'Review family districts and governorates.', 'students.references.show', 'students.view', ['reference' => 'district']),
                $item('Towns', 'Review towns by district.', 'students.references.show', 'students.view', ['reference' => 'towns']),
            ]],
        ];
    }
}
