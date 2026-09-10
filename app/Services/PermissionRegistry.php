<?php

namespace App\Services;

/**
 * The canonical catalogue of every permission the platform knows about.
 *
 * This is the single source of truth used by the seeder (to sync the
 * `permissions` table) and by the role/user admin screens (to render the
 * permission matrix in a stable, grouped order). Adding a permission here and
 * re-running `php artisan db:seed --class=RolesAndPermissionsSeeder` is all
 * that is needed to expose it in the UI.
 */
class PermissionRegistry
{
    /**
     * Permission slug => label, keyed by the module group it belongs to.
     *
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return [
            'Campaigns' => [
                'campaigns.view' => 'View campaigns',
                'campaigns.create' => 'Create campaigns',
                'campaigns.update' => 'Edit campaigns',
                'campaigns.delete' => 'Delete campaigns',
                'campaigns.launch' => 'Launch, pause, resume & cancel sends',
            ],
            'Contacts' => [
                'contacts.view' => 'View contact lists',
                'contacts.create' => 'Create lists & contacts',
                'contacts.update' => 'Edit lists & contacts',
                'contacts.delete' => 'Delete lists & contacts',
                'contacts.import' => 'Import contacts from CSV',
                'contacts.export' => 'Export contacts to CSV',
            ],
            'Templates' => [
                'templates.view' => 'View email templates',
                'templates.create' => 'Create email templates',
                'templates.update' => 'Edit email templates',
                'templates.delete' => 'Delete email templates',
            ],
            'SMTP Relays' => [
                'smtp.view' => 'View SMTP relays',
                'smtp.create' => 'Add SMTP relays',
                'smtp.update' => 'Edit SMTP relays',
                'smtp.delete' => 'Delete SMTP relays',
                'smtp.test' => 'Run test sends & diagnostics',
            ],
            'Deliverability' => [
                'deliverability.view' => 'Run deliverability & spam checks',
            ],
            'Administration' => [
                'users.view' => 'View users',
                'users.create' => 'Create users',
                'users.update' => 'Edit users & assign roles',
                'users.delete' => 'Delete users',
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.update' => 'Edit roles & their permissions',
                'roles.delete' => 'Delete roles',
            ],
        ];
    }

    /**
     * Every permission slug, flattened.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_merge(...array_map('array_keys', array_values(static::all())));
    }

    /** The group a slug belongs to, or 'Other' for slugs no longer in the registry. */
    public static function groupFor(string $slug): string
    {
        foreach (static::all() as $group => $permissions) {
            if (array_key_exists($slug, $permissions)) {
                return $group;
            }
        }

        return 'Other';
    }

    /** Human label for a slug, falling back to the slug itself. */
    public static function labelFor(string $slug): string
    {
        foreach (static::all() as $permissions) {
            if (isset($permissions[$slug])) {
                return $permissions[$slug];
            }
        }

        return $slug;
    }
}
