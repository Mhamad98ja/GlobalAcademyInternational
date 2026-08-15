@extends('layouts.app', ['title' => 'Import data'])

@section('content')
<div class="page-header"><div><h1 class="page-title">Import data</h1><p class="page-subtitle">Upload the original Excel files to populate the Main Branch. Uploaded files are deleted immediately after processing.</p></div></div>

@if(session('import_output'))<pre class="mb-5 overflow-x-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs leading-5 text-slate-100">{{ session('import_output') }}</pre>@endif

<div class="grid gap-6 lg:grid-cols-2">
    <section class="panel"><div class="panel-header"><h2 class="panel-title">Student roster</h2></div><form method="POST" action="{{ route('admin.imports.students') }}" enctype="multipart/form-data" class="space-y-4 p-5" data-submit-lock>@csrf
        <div><label class="form-label">Student Excel file *</label><input type="file" name="workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>@error('workbook')<p class="form-error">{{ $message }}</p>@enderror</div>
        <div><label class="form-label">Academic year *</label><input name="year" list="student-import-years" value="{{ old('year', '2024-2025') }}" pattern="\d{4}-\d{4}" required><datalist id="student-import-years">@foreach($years as $year)<option value="{{ $year->name }}">@endforeach</datalist></div>
        <div><label class="form-label">Registration date *</label><input type="date" name="registered_on" value="{{ old('registered_on', '2024-09-01') }}" required></div>
        <p class="text-xs text-slate-500">Names, grades, and divisions are imported. G.R. numbers are ignored.</p><button class="btn btn-primary w-full">Import students</button>
    </form></section>

    <section class="panel"><div class="panel-header"><h2 class="panel-title">Book list</h2></div><form method="POST" action="{{ route('admin.imports.books') }}" enctype="multipart/form-data" class="space-y-4 p-5" data-submit-lock>@csrf
        <div><label class="form-label">Book-list Excel file *</label><input type="file" name="workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>@error('workbook')<p class="form-error">{{ $message }}</p>@enderror</div>
        <div><label class="form-label">Academic year *</label><input name="year" list="book-import-years" value="{{ old('year', '2024-2025') }}" pattern="\d{4}-\d{4}" required><datalist id="book-import-years">@foreach($years as $year)<option value="{{ $year->name }}">@endforeach</datalist></div>
        <p class="text-xs text-slate-500">The Excel Qty column sets the Main Branch stock for every book. Re-importing the same file does not double quantities. Student resources enter class packages; teacher resources are recorded as teacher usage only.</p><button class="btn btn-primary w-full">Import books</button>
    </form></section>
</div>
@endsection
