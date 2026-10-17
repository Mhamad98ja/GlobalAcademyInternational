@extends('layouts.app', ['title' => 'Parents & guardians'])

@section('content')
<div class="page-header"><div><h1 class="page-title">Parents &amp; guardians</h1><p class="page-subtitle">Choose a branch, then create the parent and their student profiles together.</p></div><a class="btn btn-secondary" href="{{ route('students.families') }}">Families without students</a></div>

@php($newPerson = new \App\Models\ResponsiblePerson())
<div class="grid gap-6">
    <section class="panel"><div class="panel-header"><h2 class="panel-title">New parent / guardian</h2></div><form method="POST" action="{{ route('responsible.store') }}" class="p-5" data-submit-lock>@csrf
        <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4"><label class="form-label" for="family-branch-id">Branch *</label>
            @if($canChooseBranch)
                <select id="family-branch-id" name="branch_id" required class="mt-2 max-w-md">@foreach($availableBranches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $selectedBranchId) == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>@endforeach</select>
                <p class="mt-2 text-xs text-slate-600">The parent and each new student will be created in this branch.</p>
            @else
                <input id="family-branch-id" type="hidden" name="branch_id" value="{{ $selectedBranchId }}"><p class="mt-1 text-sm font-semibold text-slate-800">{{ $availableBranches->firstWhere('id', $selectedBranchId)?->name ?? 'Current branch' }}</p><p class="mt-1 text-xs text-slate-600">Branch staff create families only in their assigned branch.</p>
            @endif
            @error('branch_id')<p class="form-error">{{ $message }}</p>@enderror
        </section>
        <x-responsible-person-fields :person="$newPerson" :teachers="$teachers" :selected-students="$selectedStudents" :reference-options="$referenceOptions" /><div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-5"><button class="btn btn-primary">Save parent and student profiles</button></div></form></section>
    <section class="panel">
        <div class="panel-header">
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="panel-title">Saved parents &amp; guardians</h2><p class="mt-1 text-xs text-slate-500">Showing the selected branch only.</p></div>
                <form class="flex w-full flex-wrap gap-2 sm:max-w-xl">
                    @if($canChooseBranch)
                        <select name="branch_id" aria-label="Filter families by branch" class="max-w-40">@foreach($availableBranches as $branch)<option value="{{ $branch->id }}" @selected($selectedBranchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                    @endif
                    <input name="search" value="{{ request('search') }}" placeholder="Parent code, name, or phone" class="min-w-36 flex-1"><button class="btn btn-secondary">Search</button>
                </form>
            </div>
        </div>
        <div class="table-wrap"><table class="data-table w-full"><thead><tr><th>Code</th><th>Name</th><th>Relationship</th><th>Phone</th><th>Linked students</th><th></th></tr></thead><tbody>
            @forelse($people as $person)
                <tr><td>{{ $person->parent_code ?: '—' }}</td><td><strong>{{ $person->full_name }}</strong><div class="text-xs text-slate-500">{{ $person->email ?: 'No email' }}</div></td><td>{{ str($person->type)->headline() }}</td><td>{{ $person->phone ?: '—' }}</td><td>{{ $person->students->pluck('full_name')->join(', ') ?: 'No students linked' }}</td><td><a class="link text-xs whitespace-nowrap" href="{{ route('responsible.edit', $person) }}">Edit person &amp; students</a></td></tr>
            @empty
                <tr><td colspan="6" class="empty">No parent or guardian record found in this branch.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="border-t border-slate-200 p-3"><x-pagination :paginator="$people" /></div>
    </section>
</div>
@endsection
