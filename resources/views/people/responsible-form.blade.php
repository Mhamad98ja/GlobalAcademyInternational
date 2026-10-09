@extends('layouts.app', ['title' => 'Edit parent or guardian'])

@section('content')
<div class="page-header"><div><div class="text-sm text-slate-500">Student dossier / Families</div><h1 class="page-title mt-1">Edit parent or guardian</h1><p class="page-subtitle">Update the detailed family record and the students connected to it.</p></div><a class="btn btn-secondary" href="{{ route('responsible.index') }}">Cancel</a></div>
<form method="POST" action="{{ route('responsible.update', $person) }}" class="panel max-w-6xl" data-submit-lock>@csrf @method('PUT')<div class="p-5"><x-responsible-person-fields :person="$person" :teachers="$teachers" :selected-students="$selectedStudents" /></div><div class="flex justify-end border-t border-slate-200 p-5"><button class="btn btn-primary">Save family record</button></div></form>
@endsection
