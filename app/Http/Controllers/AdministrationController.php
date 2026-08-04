<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\ResponsiblePerson;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    public function users(): View
    {
        return view('admin.users', [
            'users' => User::with('roles')->orderBy('name')->paginate(20),
            'roles' => Role::orderBy('label')->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,whatsapp_number'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);
        $user->roles()->sync([$data['role_id']]);

        return back()->with('success', 'User account created.');
    }

    public function editUser(User $user): View
    {
        return view('admin.user-form', [
            'managedUser' => $user->load('roles'),
            'roles' => Role::orderBy('label')->get(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'whatsapp_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'whatsapp_number')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $attributes = ['name' => $data['name'], 'email' => $data['email'], 'whatsapp_number' => $data['whatsapp_number'] ?? null];
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);
        $user->roles()->sync([$data['role_id']]);

        return redirect()->route('admin.users')->with('success', 'User account updated.');
    }

    public function toggleUser(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot disable your own account.');
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'User status updated.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot delete your own account.');

        if ($user->hasRole('super-administrator')) {
            $hasAnotherActiveAdministrator = User::query()
                ->where('is_active', true)
                ->whereKeyNot($user->getKey())
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'super-administrator'))
                ->exists();

            abort_unless($hasAnotherActiveAdministrator, 422, 'At least one active super administrator must remain.');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted. You can restore the account from the recycle bin.');
    }

    public function roles(): View
    {
        return view('admin.roles', ['roles' => Role::with('permissions')->get(), 'permissions' => \App\Models\Permission::orderBy('label')->get()]);
    }

    public function audit(): View
    {
        return view('admin.audit', ['logs' => AuditLog::with('user')->latest()->paginate(30)]);
    }

    public function recycleBin(): View
    {
        return view('admin.recycle-bin', [
            'students' => Student::onlyTrashed()->latest()->get(),
            'books' => Book::onlyTrashed()->latest()->get(),
            'suppliers' => Supplier::onlyTrashed()->latest()->get(),
            'people' => ResponsiblePerson::onlyTrashed()->latest()->get(),
            'users' => User::onlyTrashed()->latest()->get(),
        ]);
    }

    public function restore(Request $request, string $type, string $id): RedirectResponse
    {
        $models = ['student' => Student::class, 'book' => Book::class, 'supplier' => Supplier::class, 'responsible-person' => ResponsiblePerson::class, 'user' => User::class];
        abort_unless(isset($models[$type]), 404);
        $model = $models[$type]::onlyTrashed()->where('public_id', $id)->firstOrFail();
        $model->restore();

        return back()->with('success', 'Record restored.');
    }

    public function settings(): View
    {
        return view('admin.settings', ['settings' => SystemSetting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_address' => ['nullable', 'string', 'max:1000'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'allow_negative_stock' => ['nullable', 'boolean'],
        ]);
        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        return back()->with('success', 'Settings saved.');
    }
}
