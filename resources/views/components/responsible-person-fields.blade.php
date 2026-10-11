@props(['person', 'teachers', 'selectedStudents', 'referenceOptions' => ['familyTypes' => [], 'bloodTypes' => [], 'districts' => [], 'towns' => []]])

<div class="space-y-6">
    <section><h2 class="panel-title">Parent or guardian identity</h2><div class="form-grid mt-4">
        <div><label class="form-label">Parent code</label><input name="parent_code" value="{{ old('parent_code', $person->parent_code) }}" placeholder="Optional family code"></div>
        <div><label class="form-label">Relationship *</label><select name="type" required>@foreach(['guardian' => 'Guardian', 'father' => 'Father', 'mother' => 'Mother', 'teacher' => 'Teacher', 'administrator' => 'Administrator', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $person->type ?: 'guardian') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="form-label">Title</label><input name="title" value="{{ old('title', $person->title) }}" placeholder="Mr., Mrs., Dr."></div>
        <div><label class="form-label">Display / family name *</label><input name="full_name" required value="{{ old('full_name', $person->full_name) }}"></div>
        <div><label class="form-label">First name</label><input name="first_name" value="{{ old('first_name', $person->first_name) }}"></div>
        <div><label class="form-label">Middle name</label><input name="middle_name" value="{{ old('middle_name', $person->middle_name) }}"></div>
        <div><label class="form-label">Last name</label><input name="last_name" value="{{ old('last_name', $person->last_name) }}"></div>
        <div><label class="form-label">Linked teacher</label><select name="teacher_id"><option value="">No linked teacher</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id', $person->teacher_id) == $teacher->id)>{{ $teacher->full_name }}</option>@endforeach</select></div>
    </div></section>
    <section><h2 class="panel-title">Contact, family &amp; work information</h2><div class="form-grid mt-4">
        <div><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $person->phone) }}"></div>
        <div><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $person->email) }}"></div>
        <div><label class="form-label">Blood type</label><select name="blood_type"><option value="">Select blood type</option>@foreach($referenceOptions['bloodTypes'] as $bloodType)<option value="{{ $bloodType }}" @selected(old('blood_type', $person->blood_type) === $bloodType)>{{ $bloodType }}</option>@endforeach</select></div>
        <div><label class="form-label">Religion / community</label><input name="religion" value="{{ old('religion', $person->religion) }}"></div>
        <div><label class="form-label">Nationality</label><input name="nationality" value="{{ old('nationality', $person->nationality) }}"></div>
        <div><label class="form-label">Second nationality</label><input name="secondary_nationality" value="{{ old('secondary_nationality', $person->secondary_nationality) }}"></div>
        <div><label class="form-label">Employment status</label><input name="employment_status" value="{{ old('employment_status', $person->employment_status) }}"></div>
        <div><label class="form-label">Employer</label><input name="employer" value="{{ old('employer', $person->employer) }}"></div>
        <div><label class="form-label">Position</label><input name="position" value="{{ old('position', $person->position) }}"></div>
        <div><label class="form-label">Family status</label><input name="family_status" value="{{ old('family_status', $person->family_status) }}"></div>
        <div class="flex flex-wrap items-center gap-5 pt-7"><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_alumni" value="1" @checked(old('is_alumni', $person->is_alumni))> Alumni</label><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_deceased" value="1" @checked(old('is_deceased', $person->is_deceased))> Deceased</label><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_second_marriage" value="1" @checked(old('is_second_marriage', $person->is_second_marriage))> Second marriage</label></div>
        <div class="md:col-span-2"><label class="form-label">Home address</label><div class="form-grid"><input name="area" placeholder="Area" value="{{ old('area', $person->area) }}"><input name="street" placeholder="Street" value="{{ old('street', $person->street) }}"><input name="area_number" placeholder="Area number (text)" value="{{ old('area_number', $person->area_number) }}"><input name="street_number" placeholder="Street number (text)" value="{{ old('street_number', $person->street_number) }}"><input name="building_name" placeholder="Building name" value="{{ old('building_name', $person->building_name) }}"><input name="building_number" placeholder="Building number" value="{{ old('building_number', $person->building_number) }}"><input name="apartment" placeholder="Apartment" value="{{ old('apartment', $person->apartment) }}"><textarea name="address" rows="2" placeholder="Additional address details">{{ old('address', $person->address) }}</textarea></div></div>
        <div class="md:col-span-2"><label class="form-label">Work address</label><textarea name="work_address" rows="2">{{ old('work_address', $person->work_address) }}</textarea></div>
    </div></section>
    <section id="family-students"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="panel-title">Student profiles</h2><p class="mt-1 text-xs text-slate-500">Create one or more student portal profiles and link them to this parent. Each student receives their own login.</p></div><button type="button" class="btn btn-secondary" data-add-family-student>Add student</button></div><div class="mt-4 grid gap-4" data-family-student-list></div><template data-family-student-template><article class="rounded-lg border border-slate-200 bg-slate-50 p-4"><div class="mb-3 flex items-center justify-between gap-3"><strong>New student</strong><button type="button" class="text-sm font-medium text-rose-700" data-remove-family-student>Remove</button></div><div class="form-grid"><input name="children[__INDEX__][student_number]" placeholder="Student number"><input name="children[__INDEX__][first_name]" required placeholder="First name"><input name="children[__INDEX__][middle_name]" placeholder="Middle name"><input name="children[__INDEX__][last_name]" required placeholder="Last name"><select name="children[__INDEX__][gender]"><option value="">Gender</option><option value="male">Male</option><option value="female">Female</option></select><input type="date" name="children[__INDEX__][date_of_birth]" aria-label="Date of birth"><input name="children[__INDEX__][phone]" placeholder="Phone"><input type="email" name="children[__INDEX__][email]" required placeholder="Student email"><input type="password" name="children[__INDEX__][password]" required minlength="12" placeholder="Portal password"><input type="password" name="children[__INDEX__][password_confirmation]" required minlength="12" placeholder="Confirm password"></div></article></template></section>
    <section><h2 class="panel-title">Link existing students</h2><div class="mt-4"><x-student-multi-select :students="$selectedStudents" /><p class="mt-1 text-xs text-slate-500">Use this only for an existing student profile. New students should be added above so they cannot exist without a parent.</p></div></section>
    <section><label class="form-label">Internal notes</label><textarea name="notes" rows="3">{{ old('notes', $person->notes) }}</textarea></section>
</div>
<script>
(() => {
    const list = document.querySelector('[data-family-student-list]');
    const template = document.querySelector('[data-family-student-template]');
    const add = document.querySelector('[data-add-family-student]');
    if (!list || !template || !add) return;
    let index = 0;
    const addStudent = () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index++));
        const card = wrapper.firstElementChild;
        card.querySelector('[data-remove-family-student]')?.addEventListener('click', () => card.remove());
        list.append(card);
        card.querySelector('input')?.focus();
    };
    add.addEventListener('click', addStudent);
})();
</script>
