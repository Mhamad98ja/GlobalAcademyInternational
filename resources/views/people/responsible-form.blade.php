@extends('layouts.app', ['title' => 'Edit parent or guardian'])

@section('content')
<div class="page-header"><div><h1 class="page-title">Edit parent or guardian</h1><p class="page-subtitle">Update the family record and students in {{ $person->branch?->name ?? 'its branch' }}.</p></div><a class="btn btn-secondary" href="{{ route('responsible.index', ['branch_id' => $person->branch_id]) }}">Cancel</a></div>
<form method="POST" action="{{ route('responsible.update', $person) }}" class="panel max-w-6xl" data-submit-lock>@csrf @method('PUT')<div class="p-5"><input type="hidden" name="branch_id" value="{{ $person->branch_id }}"><p class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">Branch: <strong>{{ $person->branch?->name ?? 'Main Branch' }}</strong>. To move a family, transfer its students through their profiles first.</p><x-responsible-person-fields :person="$person" :teachers="$teachers" :selected-students="$selectedStudents" :reference-options="$referenceOptions" /></div><div class="flex justify-end border-t border-slate-200 p-5"><button class="btn btn-primary">Save family record</button></div></form>
@endsection
