<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'timezone' => 'UTC',
        ];
    }

    /**
     * Factory users are super admins by default so tests exercising a feature
     * are never blocked by the permission layer. Use `roles()` or
     * `withoutRoles()` when the test is specifically about access control.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(
                ['slug' => Role::SUPER_ADMIN],
                ['name' => 'Super Admin', 'description' => 'Unrestricted access.', 'is_system' => true],
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    /**
     * Creates the user with no roles at all.
     *
     * Implemented by detaching afterwards rather than by suppressing the default
     * callback: `configure()`'s closure is bound to the factory instance that
     * existed before any clone, so a flag set on a clone would not be visible to
     * it — and the user would silently come back a super admin, quietly voiding
     * every access-control assertion made against them.
     */
    public function withoutRoles(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->roles()->detach();
            $user->forgetPermissionCache();
        });
    }

    /**
     * Creates the user on the given role slugs, seeding the permission
     * catalogue and built-in roles first if they are not there yet.
     *
     * @param  list<string>  $slugs
     */
    public function roles(array $slugs): static
    {
        return $this->afterCreating(function (User $user) use ($slugs) {
            if (Permission::count() < count(PermissionRegistry::slugs())) {
                (new RolesAndPermissionsSeeder)->run();
            }

            $user->roles()->sync(Role::whereIn('slug', $slugs)->pluck('id'));
            $user->forgetPermissionCache();
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
