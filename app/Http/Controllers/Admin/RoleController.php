<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissionCount' => count(PermissionRegistry::slugs()),
        ]);
    }

    public function create()
    {
        return view('admin.roles.form', [
            'role' => new Role,
            'permissionGroups' => PermissionRegistry::all(),
            'assigned' => [],
        ]);
    }

    public function store(Request $request)
    {
        // Normalize first: the value that must be unique — and must not collide
        // with a system slug — is the slugified one that actually gets stored.
        $this->normalizeSlugInput($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug', Rule::notIn([Role::SUPER_ADMIN])],
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(PermissionRegistry::slugs())],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $this->syncPermissions($role, $request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('success', "Role {$role->name} created.");
    }

    public function edit(Role $role)
    {
        return view('admin.roles.form', [
            'role' => $role,
            'permissionGroups' => PermissionRegistry::all(),
            'assigned' => $role->permissions->pluck('slug')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $role->load('permissions');

        if ($role->isSuperAdmin()) {
            return back()->with('error', 'The super admin role always holds every permission and cannot be edited.');
        }

        $this->normalizeSlugInput($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id), Rule::notIn([Role::SUPER_ADMIN])],
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(PermissionRegistry::slugs())],
        ]);

        $role->update([
            'name' => $validated['name'],
            // A system role's slug is referenced in code, so it stays put.
            'slug' => $role->is_system ? $role->slug : $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncPermissions($role, $request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Reassign the users on this role before deleting it.');
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', "Role {$name} deleted.");
    }

    /**
     * Writes a role's permission set, capped at what the signed-in user holds.
     *
     * Without the ceiling, anyone with roles.update could add users.* to a role
     * they themselves are in and escalate on the very next request, since
     * permissions resolve per request.
     *
     * Permissions the actor cannot grant are preserved rather than dropped, so
     * editing a role does not silently strip what a super admin configured.
     *
     * @param  list<string>  $slugs
     */
    protected function syncPermissions(Role $role, array $slugs): void
    {
        $actor = Auth::user();

        if ($actor->isSuperAdmin()) {
            $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

            return;
        }

        $grantable = $actor->permissionSlugs();

        $keep = $role->permissions
            ->pluck('slug')
            ->reject(fn (string $slug) => in_array($slug, $grantable, true));

        $allowed = collect($slugs)
            ->filter(fn (string $slug) => in_array($slug, $grantable, true));

        $role->permissions()->sync(
            Permission::whereIn('slug', $keep->merge($allowed)->unique()->all())->pluck('id')
        );
    }

    /**
     * Slugifies the submitted slug (falling back to the name) in place, so the
     * validator sees the same string the database will.
     */
    protected function normalizeSlugInput(Request $request): void
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name', ''))),
        ]);
    }
}
