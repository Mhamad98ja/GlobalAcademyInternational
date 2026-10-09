@php($total = $studentOverview['totalStudents'])
@php($female = $total ? round($studentOverview['gender']['female'] / $total * 100, 2) : 0)
@php($male = $total ? round($studentOverview['gender']['male'] / $total * 100, 2) : 0)
@php($maxClass = max(1, $studentOverview['classes']->max(fn ($class) => $class['active'] + $class['other']) ?? 0))
<section class="dars-student-dashboard" aria-label="Student dashboard overview">
    <form method="GET" class="dars-dashboard-filter">
        <label for="dossier-year">Academic year :</label>
        <select id="dossier-year" name="academic_year_id" onchange="this.form.submit()"><option value="">Current open year</option>@foreach($studentOverview['years'] as $year)<option value="{{ $year->id }}" @selected($activeAcademicYear?->id === $year->id)>{{ $year->name }}</option>@endforeach</select>
    </form>
    <div class="dars-dashboard-summary">
        <section class="dars-dashboard-box">
            <h2>Number of students and families</h2>
            <table><tbody>@foreach([1, 2, 3, 4] as $size)<tr><td>Families with {{ $size }} {{ $size === 1 ? 'student' : 'students' }}</td><td><span>{{ $studentOverview['familySizes']->get($size, 0) }}</span></td></tr>@endforeach<tr class="total"><td>Total number of students: {{ $studentOverview['totalStudents'] }}</td><td><span>{{ $studentOverview['totalFamilies'] }}</span></td></tr></tbody></table>
        </section>
        @if($studentOverview['remarks'] !== null)
            <section class="dars-dashboard-box dars-remarks">
                <h2>Remarks</h2>
                <table>
                    <thead><tr><th></th>@foreach($studentOverview['remarks']->first() as $label => $count)<th>{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>@foreach($studentOverview['remarks'] as $label => $counts)@php($status = ['Absence' => 'absent', 'Authorised leave' => 'excused', 'Late' => 'late'][$label])<tr><td><a href="{{ route('attendance.students', ['academic_year_id' => $activeAcademicYear?->id, 'status' => $status]) }}">{{ $label }}</a></td>@foreach($counts as $count)<td>{{ $count }}</td>@endforeach</tr>@endforeach</tbody>
                </table>
            </section>
        @endif
    </div>
    <div class="dars-dashboard-charts">
        <section class="dars-gender-chart" aria-label="Students by gender">
            <div class="dars-pie" style="--female: {{ $female }}%; --male-end: {{ $female + $male }}%"><span class="dars-pie-female">{{ number_format($female, 2) }}%</span><span class="dars-pie-male">{{ number_format($male, 2) }}%</span></div>
            <div class="dars-chart-legend"><span><i class="female"></i>Girl</span><span><i class="male"></i>Boy</span>@if($studentOverview['gender']['unspecified'])<span><i class="unknown"></i>Not specified</span>@endif</div>
        </section>
        <section class="dars-class-chart" aria-label="Registrations by class">
            <div class="dars-y-axis">@foreach(range(20, 0, -2) as $tick)<span>{{ $tick }}</span>@endforeach</div>
            <div class="dars-class-bars">@foreach($studentOverview['classes'] as $class)<div class="dars-class-column"><div class="dars-bars"><span class="other" style="height: {{ min(100, $class['other'] / max(20, $maxClass) * 100) }}%"><b>{{ $class['other'] }}</b></span><span class="active" style="height: {{ min(100, $class['active'] / max(20, $maxClass) * 100) }}%"><b>{{ $class['active'] }}</b></span></div><small>{{ $class['name'] }}</small></div>@endforeach</div>
            <div class="dars-bar-legend"><span><i class="other"></i>Un-Registered</span><span><i class="active"></i>Registered</span></div>
        </section>
    </div>
</section>
