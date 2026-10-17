<?php

declare(strict_types=1);

// Initial academic structure imported only once. References > Codes is the
// editing surface after import; rerunning deployment seeding preserves edits.
return [
    'years' => [
        ['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open'],
        ['name' => '2027-2028', 'starts_on' => '2027-06-04', 'ends_on' => '2028-06-04', 'status' => 'draft'],
    ],
    'terms' => [
        ['branch' => 'MAIN', 'year' => '2026-2027', 'name' => 's1', 'type' => 'semester', 'starts_on' => '2026-09-29', 'ends_on' => '2026-09-30', 'status' => 'open', 'sort_order' => 0],
        ['branch' => 'MAIN', 'year' => '2026-2027', 'name' => 'Demo Term 1', 'type' => 'term', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'sort_order' => 1],
        ['branch' => 'MAIN', 'year' => '2026-2027', 'name' => 'Term 1', 'type' => 'term', 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-30', 'status' => 'open', 'sort_order' => 1],
        ['branch' => 'MAIN', 'year' => '2026-2027', 'name' => 'Term 2', 'type' => 'term', 'starts_on' => '2026-12-01', 'ends_on' => '2027-06-30', 'status' => 'planned', 'sort_order' => 2],
    ],
    'levels' => [
        ['name' => 'School', 'sort_order' => 2, 'grades' => [
            ['name' => 'Grade 1', 'sort_order' => 4, 'sections' => ['A', 'B', 'C']],
            ['name' => 'Grade 2', 'sort_order' => 5, 'sections' => ['A', 'B', 'C']],
            ['name' => 'Grade 3', 'sort_order' => 6, 'sections' => ['A', 'B', 'C']],
            ['name' => 'Grade 4', 'sort_order' => 7, 'sections' => ['B', 'C']],
            ['name' => 'Grade 5', 'sort_order' => 8, 'sections' => ['A', 'B']],
            ['name' => 'Grade 6', 'sort_order' => 9, 'sections' => ['A', 'B']],
            ['name' => 'Grade 7', 'sort_order' => 10, 'sections' => ['A', 'B']],
            ['name' => 'Grade 8', 'sort_order' => 11, 'sections' => ['A', 'B', 'C']],
            ['name' => 'Grade 9', 'sort_order' => 12, 'sections' => ['A', 'B', 'C']],
            ['name' => 'Grade 10', 'sort_order' => 13, 'sections' => ['A', 'B', 'C', 'D']],
            ['name' => 'Grade 11', 'sort_order' => 14, 'sections' => ['A', 'B', 'C']],
            ['name' => 'Grade 12', 'sort_order' => 15, 'sections' => ['A', 'B', 'C', 'D']],
        ]],
    ],
];
