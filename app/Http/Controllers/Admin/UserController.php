<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $roleFilter = $request->input('role');
        $statusFilter = $request->input('status');

        $users = User::with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $needle = '%'.self::escapeLike($search).'%';

                $query->where(function ($q) use ($needle) {
                    $q->where('name', 'like', $needle)
                        ->orWhere('email', 'like', $needle)
                        ->orWhere('company', 'like', $needle);
                });
            })
            ->when($roleFilter, fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('slug', $roleFilter)))
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->withCount('users')->get(),
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'totals' => [
                'all' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true, 'timezone' => 'UTC']),
            'roles' => $this->assignableRoles(),
            'permissionGroups' => PermissionRegistry::all(),
            'rolePermissionMap' => $this->rolePermissionMap(),
            'assignedRoleIds' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'job_title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'job_title' => $validated['job_title'] ?? null,
            'company' => $validated['company'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'timezone' => ($validated['timezone'] ?? null) ?: 'UTC',
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->roles()->sync($this->allowedRoleIds($request->input('roles', [])));

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} created.");
    }

    public function edit(User $user)
    {
        $this->guardSuperAdminTarget($user);

        $user->load('roles');

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'permissionGroups' => PermissionRegistry::all(),
            'rolePermissionMap' => $this->rolePermissionMap(),
            'assignedRoleIds' => $user->roles->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardSuperAdminTarget($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'job_title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'job_title' => $validated['job_title'] ?? null,
            'company' => $validated['company'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'timezone' => ($validated['timezone'] ?? null) ?: 'UTC',
        ];

        // An admin cannot lock themselves out of their own account.
        $attributes['is_active'] = $user->id === Auth::id() ? true : $request->boolean('is_active');

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $user->update($attributes);

        // Nobody edits their own access level. Without this, anyone holding
        // users.update could hand themselves every remaining permission.
        if ($user->id !== Auth::id()) {
            $roleIds = $this->allowedRoleIds($request->input('roles', []));

            $this->guardLastSuperAdmin($user, $roleIds);

            $user->roles()->sync($roleIds);
        }

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} updated.");
    }

    public function toggleActive(User $user)
    {
        $this->guardSuperAdminTarget($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->name.' has been '.($user->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(User $user)
    {
        $this->guardSuperAdminTarget($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $this->guardLastSuperAdmin($user, []);

        if ($user->avatar_path && Storage::disk('local')->exists($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "User {$name} and all of their data were deleted.");
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** Only a super admin may hand out — or take away — the super admin role. */
    protected function assignableRoles()
    {
        return Role::orderBy('name')
            ->when(! Auth::user()->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', Role::SUPER_ADMIN))
            ->get();
    }

    /** @return list<int> */
    protected function allowedRoleIds(array $submitted): array
    {
        return $this->assignableRoles()
            ->whereIn('id', array_map('intval', $submitted))
            ->pluck('id')
            ->all();
    }

    /** Non-super-admins cannot edit or delete a super admin account. */
    protected function guardSuperAdminTarget(User $user): void
    {
        if ($user->isSuperAdmin() && ! Auth::user()->isSuperAdmin()) {
            abort(403, 'Only a super admin can manage another super admin.');
        }
    }

    /** Refuses a change that would leave the platform with no super admin. */
    protected function guardLastSuperAdmin(User $user, array $newRoleIds): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        $superAdminId = Role::where('slug', Role::SUPER_ADMIN)->value('id');

        if (in_array($superAdminId, $newRoleIds, true)) {
            return;
        }

        $remaining = User::whereHas('roles', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))
            ->where('id', '!=', $user->id)
            ->count();

        if ($remaining === 0) {
            abort(422, 'This is the last super admin — assign the role to someone else first.');
        }
    }

    /**
     * role id => permission slugs it grants, so the edit screen can show which
     * boxes the selected roles already cover.
     *
     * @return array<int, list<string>>
     */
    protected function rolePermissionMap(): array
    {
        return Role::with('permissions')->get()
            ->mapWithKeys(fn (Role $role) => [
                $role->id => $role->isSuperAdmin()
                    ? PermissionRegistry::slugs()
                    : $role->permissions->pluck('slug')->all(),
            ])
            ->all();
    }
}
