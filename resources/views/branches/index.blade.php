@extends('layouts.app', ['title' => 'Branches'])

@section('content')
@php
    $showForm = $editingBranch || request()->boolean('create') || old('form_mode') === 'create';
    $administratorId = old('branch_administrator_id', $editingBranch?->users->first(fn ($user) => $user->pivot->is_branch_administrator)?->id);
    $memberKind = fn ($user) => $user->student_id || $user->roles->contains('name', 'student') ? 'students' : ($user->teacher_id || $user->roles->contains('name', 'teacher') ? 'teachers' : ($user->is_controller_administrator || $user->roles->contains('name', 'super-administrator') ? 'administrators' : 'employees'));
    $memberGroups = ['administrators' => 'Administrators', 'teachers' => 'Teachers', 'employees' => 'Employees', 'students' => 'Students'];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Branches</h1>
        <p class="page-subtitle">Manage branch details and see administrators, teachers, employees, and students separately.</p>
        @if($controllerAdministrator)<p class="mt-2 text-xs text-slate-500">Global Super Administrator: {{ $controllerAdministrator->name }}</p>@endif
    </div>
    <a class="btn btn-primary" href="{{ route('branches.index', ['create' => 1, 'q' => request('q')]) }}">New branch</a>
</div>

<div class="grid items-start gap-6 xl:grid-cols-[minmax(18rem,22rem)_minmax(0,1fr)]">
    <section class="panel" aria-label="Find branches">
        <div class="panel-header"><div><h2 class="panel-title">Find a branch</h2><p class="mt-1 text-xs text-slate-500">Search by name or code.</p></div></div>
        <form method="GET" action="{{ route('branches.index') }}" class="flex gap-2 border-b border-slate-200 p-4">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search branches" aria-label="Search branches" class="min-w-0 flex-1">
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>
        <div class="divide-y divide-slate-100">
            @forelse($branches as $branch)
                <a href="{{ route('branches.index', ['edit' => $branch->id, 'q' => request('q')]) }}" class="flex items-center justify-between gap-3 p-4 transition hover:bg-slate-50 {{ $editingBranch?->id === $branch->id ? 'bg-blue-50' : '' }}">
                    <span class="min-w-0"><strong class="block truncate text-sm text-slate-900">{{ $branch->name }}</strong><small class="mt-1 block text-xs text-slate-500">{{ $branch->code }} · {{ $branch->users_count }} users · {{ $branch->students_count }} students</small></span>
                    <span class="badge {{ $branch->is_active ? 'badge-green' : 'badge-red' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span>
                </a>
            @empty
                <p class="empty">No branches match this search.</p>
            @endforelse
        </div>
        @if($branches->hasPages())<div class="border-t border-slate-200 p-4">{{ $branches->links() }}</div>@endif
    </section>

    @if($showForm)
        <section class="panel" aria-label="{{ $editingBranch ? 'Edit branch' : 'Create branch' }}">
            <div class="panel-header"><div><h2 class="panel-title">{{ $editingBranch ? 'Edit '.$editingBranch->name : 'New branch' }}</h2><p class="mt-1 text-xs text-slate-500">{{ $editingBranch ? 'Manage branch access by person type.' : 'Create the branch and its first administrator together.' }}</p></div><a class="link text-sm" href="{{ route('branches.index', ['q' => request('q')]) }}">Close</a></div>
            <form method="POST" action="{{ $editingBranch ? route('branches.update', $editingBranch) : route('branches.store') }}" data-submit-lock>
                @csrf
                @if($editingBranch)@method('PUT')@endif
                <input type="hidden" name="form_mode" value="{{ $editingBranch ? 'edit' : 'create' }}">
                <div class="space-y-6 p-5">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Branch details</h3>
                        <div class="form-grid mt-3">
                            <div><label class="form-label" for="branch-code">Code *</label><input id="branch-code" name="code" required maxlength="30" value="{{ old('code', $editingBranch?->code) }}" placeholder="BEIRUT" @readonly($editingBranch && strtoupper($editingBranch->code) === 'MAIN')>@error('code')<p class="form-error">{{ $message }}</p>@enderror</div>
                            <div><label class="form-label" for="branch-name">Name *</label><input id="branch-name" name="name" required maxlength="255" value="{{ old('name', $editingBranch?->name) }}">@error('name')<p class="form-error">{{ $message }}</p>@enderror</div>
                        </div>
                        @if($editingBranch && strtoupper($editingBranch->code) === 'MAIN')<p class="mt-2 text-xs text-slate-500">The Main Branch code and active status are protected.</p>@endif
                    </div>

                    <div class="border-t border-slate-200 pt-5" data-branch-user-picker data-search-url="{{ route('branches.users.search') }}" data-branch-id="{{ $editingBranch?->id }}">
                        <h3 class="text-sm font-semibold text-slate-900">Branch administrator and people</h3>
                        <p class="mt-1 text-xs text-slate-500">Search for existing people. Students are shown separately and remain linked to their student profile.</p>
                        @if(! $editingBranch)
                            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4" data-administrator-mode>
                                <p class="text-sm font-semibold text-slate-900">Choose the first Branch Administrator *</p>
                                <div class="mt-3 flex flex-wrap gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" name="administrator_mode" value="new" @checked(old('administrator_mode', 'new') === 'new')> Create a new administrator</label><label class="flex items-center gap-2"><input type="radio" name="administrator_mode" value="existing" @checked(old('administrator_mode') === 'existing')> Use an existing administrator</label></div>
                                <div class="form-grid mt-4" data-new-administrator-fields>
                                    <div><label class="form-label" for="administrator-name">Administrator name *</label><input id="administrator-name" name="administrator_name" value="{{ old('administrator_name') }}">@error('administrator_name')<p class="form-error">{{ $message }}</p>@enderror</div>
                                    <div><label class="form-label" for="administrator-email">Administrator email *</label><input id="administrator-email" type="email" name="administrator_email" value="{{ old('administrator_email') }}">@error('administrator_email')<p class="form-error">{{ $message }}</p>@enderror</div>
                                    <div><label class="form-label" for="administrator-password">Password *</label><input id="administrator-password" type="password" name="administrator_password" autocomplete="new-password" placeholder="At least 12 characters"></div>
                                    <div><label class="form-label" for="administrator-password-confirmation">Confirm password *</label><input id="administrator-password-confirmation" type="password" name="administrator_password_confirmation" autocomplete="new-password">@error('administrator_password')<p class="form-error">{{ $message }}</p>@enderror</div>
                                </div>
                                <p class="mt-2 text-xs text-slate-600">This account will be assigned to the new branch automatically. It cannot change global school configuration.</p>
                            </div>
                        @endif
                        <div class="relative mt-3">
                            <label class="form-label" for="branch-user-search">Add a user</label>
                            <div class="flex gap-2"><select data-user-kind aria-label="Person type" class="max-w-44"><option value="all">All people</option><option value="administrators">Administrators</option><option value="teachers">Teachers</option><option value="employees">Employees</option><option value="students">Students</option></select><input id="branch-user-search" type="search" data-user-search autocomplete="off" placeholder="Search name or email" aria-controls="branch-user-results" class="min-w-0 flex-1"></div>
                            <div id="branch-user-results" data-user-results class="mt-2 max-h-56 overflow-y-auto rounded-md border border-slate-200 bg-white" hidden aria-live="polite"></div>
                        </div>
                        <div data-selected-users class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach($memberGroups as $kind => $label)
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $label }} <span data-group-count="{{ $kind }}">{{ $selectedUsers->filter(fn ($user) => $memberKind($user) === $kind)->count() }}</span></p>
                                    <div data-group="{{ $kind }}" class="max-h-40 space-y-2 overflow-y-auto">
                                        @foreach($selectedUsers->filter(fn ($user) => $memberKind($user) === $kind) as $user)
                                            <div data-member data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-kind="{{ $kind }}" data-admin="{{ ! $user->is_controller_administrator && $user->hasRole('super-administrator') ? '1' : '0' }}" class="flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">
                                                <input type="hidden" name="user_ids[]" value="{{ $user->id }}">
                                                <span class="min-w-0"><strong class="block truncate text-slate-800">{{ $user->name }}</strong><small class="block truncate text-slate-500">{{ $user->email }}</small></span>
                                                @if($kind !== 'students')<button type="button" data-remove-user class="shrink-0 text-xs font-semibold text-rose-700 hover:underline" aria-label="Remove {{ $user->name }}">Remove</button>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('user_ids')<p class="form-error">{{ $message }}</p>@enderror
                        <div class="mt-4" data-existing-administrator-fields><label class="form-label" for="branch-administrator">Responsible Branch Administrator *</label><select id="branch-administrator" name="branch_administrator_id" data-admin-user data-selected="{{ $administratorId }}"><option value="">Select an assigned administrator</option>@foreach($selectedUsers as $user)@if(! $user->is_controller_administrator && $user->hasRole('super-administrator'))<option value="{{ $user->id }}" @selected((string) $administratorId === (string) $user->id)>{{ $user->name }}</option>@endif @endforeach</select><p class="mt-1 text-xs text-slate-500">For an existing administrator, search and add their account above. New administrators are created with the branch in one step.</p>@error('branch_administrator_id')<p class="form-error">{{ $message }}</p>@enderror</div>
                    </div>

                    <details class="border-t border-slate-200 pt-5" @if($errors->hasAny(['phone', 'email', 'address', 'notes'])) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Contact and notes <span class="font-normal text-slate-500">(optional)</span></summary>
                        <div class="form-grid mt-4">
                            <div><label class="form-label" for="branch-phone">Phone</label><input id="branch-phone" name="phone" value="{{ old('phone', $editingBranch?->phone) }}">@error('phone')<p class="form-error">{{ $message }}</p>@enderror</div>
                            <div><label class="form-label" for="branch-email">Email</label><input id="branch-email" type="email" name="email" value="{{ old('email', $editingBranch?->email) }}">@error('email')<p class="form-error">{{ $message }}</p>@enderror</div>
                            <div class="md:col-span-2"><label class="form-label" for="branch-address">Address</label><textarea id="branch-address" name="address" rows="2">{{ old('address', $editingBranch?->address) }}</textarea>@error('address')<p class="form-error">{{ $message }}</p>@enderror</div>
                            <div class="md:col-span-2"><label class="form-label" for="branch-notes">Internal notes</label><textarea id="branch-notes" name="notes" rows="2">{{ old('notes', $editingBranch?->notes) }}</textarea>@error('notes')<p class="form-error">{{ $message }}</p>@enderror</div>
                        </div>
                    </details>

                    @if(! $editingBranch || strtoupper($editingBranch->code) !== 'MAIN')<label class="flex items-center gap-2 text-sm text-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingBranch?->is_active ?? true))> Active branch</label>@endif
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-4">
                    <button class="btn btn-primary order-2" type="submit">{{ $editingBranch ? 'Save changes' : 'Create branch' }}</button>
                    @if($editingBranch && strtoupper($editingBranch->code) !== 'MAIN')<button type="submit" form="delete-branch-{{ $editingBranch->id }}" class="btn btn-danger order-1">Delete branch</button>@else<span class="order-1 text-xs text-slate-500">{{ $editingBranch ? 'Main Branch cannot be deleted.' : 'All required fields are marked *.' }}</span>@endif
                </div>
            </form>
            @if($editingBranch && strtoupper($editingBranch->code) !== 'MAIN')<form id="delete-branch-{{ $editingBranch->id }}" method="POST" action="{{ route('branches.destroy', $editingBranch) }}" onsubmit="return confirm('Delete this branch? This is only allowed when it has no users, students, stock, or transaction history.');">@csrf @method('DELETE')</form>@endif
        </section>
    @else
        <section class="panel p-10 text-center" aria-label="Branch details">
            <div class="mx-auto max-w-md"><h2 class="text-lg font-semibold text-slate-900">Choose a branch to manage</h2><p class="mt-2 text-sm text-slate-500">Select a branch from the list to edit its details or user access. You can also create a new branch.</p></div>
        </section>
    @endif
</div>

<script>
document.querySelectorAll('[data-branch-user-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-user-search]');
    const kindSelect = picker.querySelector('[data-user-kind]');
    const results = picker.querySelector('[data-user-results]');
    const selected = picker.querySelector('[data-selected-users]');
    const administratorSelect = picker.querySelector('[data-admin-user]');
    const administratorMode = picker.querySelector('[data-administrator-mode]');
    const newAdministratorFields = picker.querySelector('[data-new-administrator-fields]');
    const existingAdministratorFields = picker.querySelector('[data-existing-administrator-fields]');
    let timer;
    let request;
    let found = [];

    const members = () => [...selected.querySelectorAll('[data-member]')];
    const refreshChoices = () => {
        const administratorValue = administratorSelect.value || administratorSelect.dataset.selected;
        administratorSelect.replaceChildren(new Option('Select an assigned administrator', ''));
        members().forEach((member) => {
            if (member.dataset.admin === '1') administratorSelect.add(new Option(member.dataset.name, member.dataset.id));
        });
        administratorSelect.value = [...administratorSelect.options].some((option) => option.value === administratorValue) ? administratorValue : '';
        administratorSelect.dataset.selected = '';
        selected.querySelectorAll('[data-group-count]').forEach((counter) => {
            counter.textContent = String(members().filter((member) => member.dataset.kind === counter.dataset.groupCount).length);
        });
    };
    const refreshAdministratorMode = () => {
        const mode = administratorMode?.querySelector('input[name="administrator_mode"]:checked')?.value || 'existing';
        if (newAdministratorFields) {
            newAdministratorFields.hidden = mode !== 'new';
            newAdministratorFields.querySelectorAll('input').forEach((input) => { input.disabled = mode !== 'new'; input.required = mode === 'new'; });
        }
        existingAdministratorFields.hidden = mode === 'new';
        administratorSelect.disabled = mode === 'new';
        administratorSelect.required = mode !== 'new';
    };
    const showResults = () => {
        results.replaceChildren();
        const available = found.filter((user) => !members().some((member) => member.dataset.id === String(user.id)));
        if (!available.length) {
            const empty = document.createElement('p');
            empty.className = 'p-3 text-sm text-slate-500';
            empty.textContent = 'No matching users to add.';
            results.append(empty);
        }
        available.forEach((user) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex w-full items-center justify-between gap-2 border-b border-slate-100 p-3 text-left text-sm hover:bg-slate-50';
            const identity = document.createElement('span');
            identity.className = 'min-w-0';
            const name = document.createElement('strong');
            name.className = 'block truncate text-slate-800';
            name.textContent = user.name;
            const email = document.createElement('small');
            email.className = 'block truncate text-slate-500';
            email.textContent = user.email;
            identity.append(name, email);
            const action = document.createElement('span');
            action.className = 'shrink-0 text-xs font-semibold text-blue-700';
            action.textContent = user.can_administer_branch ? 'Add administrator' : 'Add';
            button.append(identity, action);
            button.addEventListener('click', () => {
                const member = document.createElement('div');
                member.dataset.member = '';
                member.dataset.id = String(user.id);
                member.dataset.name = user.name;
                member.dataset.kind = user.kind;
                member.dataset.admin = user.can_administer_branch ? '1' : '0';
                member.className = 'flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = String(user.id);
                const label = document.createElement('span');
                label.className = 'min-w-0 text-slate-800';
                label.textContent = user.name + ' · ' + user.email;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.dataset.removeUser = '';
                remove.className = 'shrink-0 text-xs font-semibold text-rose-700 hover:underline';
                remove.setAttribute('aria-label', 'Remove ' + user.name);
                remove.textContent = 'Remove';
                member.append(input, label);
                if (user.kind !== 'students') member.append(remove);
                selected.querySelector('[data-group="' + user.kind + '"]').append(member);
                refreshChoices();
                showResults();
            });
            results.append(button);
        });
        results.hidden = false;
    };
    selected.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-user]');
        if (!remove) return;
        remove.closest('[data-member]').remove();
        refreshChoices();
        if (!results.hidden) showResults();
    });
    search.addEventListener('input', () => {
        clearTimeout(timer);
        request?.abort();
        const query = search.value.trim();
        if (query.length < 2) { results.hidden = true; found = []; return; }
        timer = setTimeout(async () => {
            request = new AbortController();
            const url = new URL(picker.dataset.searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('kind', kindSelect.value);
            if (picker.dataset.branchId) url.searchParams.set('branch_id', picker.dataset.branchId);
            try {
                const response = await fetch(url, { signal: request.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Search failed');
                found = await response.json();
                showResults();
            } catch (error) {
                if (error.name === 'AbortError') return;
                results.textContent = 'Could not search users. Please try again.';
                results.hidden = false;
            }
        }, 250);
    });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    kindSelect.addEventListener('change', () => search.dispatchEvent(new Event('input')));
    administratorMode?.addEventListener('change', refreshAdministratorMode);
    refreshChoices();
    refreshAdministratorMode();
});
</script>
@endsection
