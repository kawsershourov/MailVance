<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Syncs the permission catalogue and the built-in roles.
     *
     * Safe to re-run: permissions and roles are matched on their slug, and only
     * system roles have their permission set reset — a role an operator created
     * or hand-tuned in the UI is never overwritten.
     */
    public function run(): void
    {
        foreach (PermissionRegistry::all() as $group => $permissions) {
            foreach ($permissions as $slug => $label) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $label, 'group' => $group],
                );
            }
        }

        foreach ($this->roleDefinitions() as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            // The super admin bypasses the permission table entirely, so it
            // deliberately carries no pivot rows.
            if ($role->isSuperAdmin()) {
                $role->permissions()->sync([]);

                continue;
            }

            $role->permissions()->sync(
                Permission::whereIn('slug', $definition['permissions'])->pluck('id')
            );
        }

        // Never leave an install without an administrator: the earliest account
        // becomes super admin, and any other role-less account becomes a member.
        $superAdmin = Role::where('slug', Role::SUPER_ADMIN)->first();
        $member = Role::where('slug', 'member')->first();

        $first = User::oldest('id')->first();

        if ($first && $first->roles()->count() === 0) {
            $first->roles()->attach($superAdmin);
        }

        User::doesntHave('roles')->get()->each(
            fn (User $user) => $user->roles()->attach($member)
        );
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    protected function roleDefinitions(): array
    {
        $all = PermissionRegistry::slugs();

        $adminOnly = array_keys(PermissionRegistry::all()['Administration']);
        $sending = array_values(array_diff($all, $adminOnly));

        // Read-only across the sending modules — deliberately NOT the admin
        // ones, since users.view/roles.view expose the whole user directory.
        $viewOnly = array_values(array_filter($sending, fn ($slug) => Str::endsWith($slug, '.view')));

        return [
            Role::SUPER_ADMIN => [
                'name' => 'Super Admin',
                'description' => 'Unrestricted access, including every future permission.',
                'permissions' => [],
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full platform access plus user and role management.',
                'permissions' => $all,
            ],
            'manager' => [
                'name' => 'Campaign Manager',
                'description' => 'Runs campaigns end to end, but cannot manage users or delete relays.',
                'permissions' => array_values(array_diff($sending, ['smtp.create', 'smtp.update', 'smtp.delete'])),
            ],
            'member' => [
                'name' => 'Member',
                'description' => 'Builds campaigns, contacts and templates, but cannot launch or delete.',
                'permissions' => [
                    'campaigns.view', 'campaigns.create', 'campaigns.update',
                    'contacts.view', 'contacts.create', 'contacts.update', 'contacts.import', 'contacts.export',
                    'templates.view', 'templates.create', 'templates.update',
                    'smtp.view',
                    'deliverability.view',
                ],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Read-only access to every module.',
                'permissions' => $viewOnly,
            ],
        ];
    }
}
