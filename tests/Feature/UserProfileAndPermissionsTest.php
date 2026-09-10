<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SmtpConfig;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    public function test_a_user_can_update_their_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertStatus(200);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'job_title' => 'Head of Growth',
            'company' => 'Acme Inc.',
            'phone' => '+1 555 0100',
            'timezone' => 'Europe/Berlin',
            'bio' => 'Runs the newsletter.',
        ])->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'timezone' => 'Europe/Berlin',
        ]);
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('0ldPassword!')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'not-the-password',
            'password' => 'Br4ndNewPassword!',
            'password_confirmation' => 'Br4ndNewPassword!',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('0ldPassword!', $user->fresh()->password));

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => '0ldPassword!',
            'password' => 'Br4ndNewPassword!',
            'password_confirmation' => 'Br4ndNewPassword!',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('Br4ndNewPassword!', $user->fresh()->password));
    }

    public function test_an_avatar_is_stored_privately_and_can_be_removed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])->assertRedirect(route('profile.edit'));

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertRedirect(route('profile.edit'));

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar_path);
    }

    /*
    |--------------------------------------------------------------------------
    | Permission resolution
    |--------------------------------------------------------------------------
    */

    public function test_role_permissions_gate_the_module_routes(): void
    {
        $viewer = User::factory()->roles(['viewer'])->create();

        $this->actingAs($viewer)->get(route('campaigns.index'))->assertStatus(200);
        $this->actingAs($viewer)->get(route('campaigns.create'))->assertStatus(403);
        $this->actingAs($viewer)->get(route('admin.users.index'))->assertStatus(403);
    }

    public function test_a_per_user_deny_override_beats_a_role_grant(): void
    {
        $user = User::factory()->roles(['admin'])->create();

        $this->assertTrue($user->hasPermission('campaigns.create'));

        $user->directPermissions()->attach(
            Permission::where('slug', 'campaigns.create')->value('id'),
            ['granted' => false]
        );
        $user->forgetPermissionCache();

        $this->assertFalse($user->hasPermission('campaigns.create'));
        $this->actingAs($user)->get(route('campaigns.create'))->assertStatus(403);
    }

    public function test_a_per_user_allow_override_grants_without_a_role(): void
    {
        $user = User::factory()->roles(['viewer'])->create();

        $this->assertFalse($user->hasPermission('templates.create'));

        $user->directPermissions()->attach(
            Permission::where('slug', 'templates.create')->value('id'),
            ['granted' => true]
        );
        $user->forgetPermissionCache();

        $this->assertTrue($user->hasPermission('templates.create'));
        $this->actingAs($user)->get(route('templates.create'))->assertStatus(200);
    }

    public function test_a_super_admin_holds_every_permission(): void
    {
        $user = User::factory()->roles(['super-admin'])->create();

        $this->assertTrue($user->hasPermission('users.delete', 'roles.delete', 'campaigns.launch'));
    }

    /*
    |--------------------------------------------------------------------------
    | User administration
    |--------------------------------------------------------------------------
    */

    public function test_every_administration_screen_renders(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();
        $target = User::factory()->roles(['manager'])->create();
        $role = Role::where('slug', 'manager')->first();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertStatus(200)
            ->assertSee($target->email);

        $this->actingAs($admin)->get(route('admin.users.create'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.users.edit', $target->id))
            ->assertStatus(200)
            ->assertSee('Permission Overrides');

        $this->actingAs($admin)->get(route('admin.roles.index'))
            ->assertStatus(200)
            ->assertSee('Campaign Manager');

        $this->actingAs($admin)->get(route('admin.roles.create'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.roles.edit', $role->id))->assertStatus(200);
    }

    public function test_the_user_list_can_be_searched_and_filtered(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();
        $needle = User::factory()->roles(['viewer'])->create(['name' => 'Findable Person']);
        $other = User::factory()->roles(['manager'])->create(['name' => 'Someone Else']);

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Findable']))
            ->assertSee('Findable Person')
            ->assertDontSee('Someone Else');

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'viewer']))
            ->assertSee($needle->email)
            ->assertDontSee($other->email);
    }

    public function test_an_admin_can_create_a_user_with_roles_and_overrides(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();
        $managerRole = Role::where('slug', 'manager')->first();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Teammate',
            'email' => 'teammate@example.com',
            'password' => 'S3cretPassword!',
            'password_confirmation' => 'S3cretPassword!',
            'is_active' => 1,
            'roles' => [$managerRole->id],
            'overrides' => ['campaigns.launch' => 'deny', 'users.view' => 'allow'],
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'teammate@example.com')->firstOrFail();

        $this->assertTrue($created->hasRole('manager'));
        $this->assertFalse($created->hasPermission('campaigns.launch'), 'the deny override should win');
        $this->assertTrue($created->hasPermission('users.view'), 'the allow override should grant');
    }

    public function test_a_non_super_admin_cannot_assign_the_super_admin_role(): void
    {
        $admin = User::factory()->roles(['admin'])->create();
        $superAdminRole = Role::where('slug', Role::SUPER_ADMIN)->first();
        $target = User::factory()->roles(['viewer'])->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target->id), [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => 1,
            'roles' => [$superAdminRole->id],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($target->fresh()->isSuperAdmin());
    }

    public function test_a_non_super_admin_cannot_edit_a_super_admin(): void
    {
        $admin = User::factory()->roles(['admin'])->create();
        $superAdmin = User::factory()->roles(['super-admin'])->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $superAdmin->id))->assertStatus(403);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $superAdmin->id))->assertStatus(403);
    }

    public function test_a_super_admin_cannot_strip_their_own_super_admin_role(): void
    {
        $superAdmin = User::factory()->roles(['super-admin'])->create();
        $adminRole = Role::where('slug', 'admin')->first();

        // Unticking your own super admin role silently keeps it, so an install
        // can never end up with nobody able to manage roles.
        $this->actingAs($superAdmin)->put(route('admin.users.update', $superAdmin->id), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'is_active' => 1,
            'roles' => [$adminRole->id],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
    }

    public function test_a_super_admin_can_demote_another_super_admin(): void
    {
        $superAdmin = User::factory()->roles(['super-admin'])->create();
        $other = User::factory()->roles(['super-admin'])->create();
        $adminRole = Role::where('slug', 'admin')->first();

        $this->actingAs($superAdmin)->put(route('admin.users.update', $other->id), [
            'name' => $other->name,
            'email' => $other->email,
            'is_active' => 1,
            'roles' => [$adminRole->id],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($other->fresh()->isSuperAdmin());
        $this->assertTrue($other->fresh()->hasRole('admin'));
    }

    public function test_a_user_cannot_deactivate_or_delete_their_own_account(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();

        $this->actingAs($admin)->post(route('admin.users.toggle-active', $admin->id))
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin->id))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_a_deactivated_user_is_signed_out_and_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password'), 'is_active' => false]);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // An account switched off mid-session loses access on the next request.
        $active = User::factory()->create();
        $this->actingAs($active)->get(route('dashboard'))->assertStatus(200);

        $active->update(['is_active' => false]);
        $this->actingAs($active)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | Role administration
    |--------------------------------------------------------------------------
    */

    public function test_a_role_can_be_created_and_its_permissions_updated(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Content Editor',
            'description' => 'Writes templates only.',
            'permissions' => ['templates.view', 'templates.create', 'templates.update'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('slug', 'content-editor')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['templates.view', 'templates.create', 'templates.update'],
            $role->permissions->pluck('slug')->all()
        );

        $this->actingAs($admin)->put(route('admin.roles.update', $role->id), [
            'name' => 'Content Editor',
            'permissions' => ['templates.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertEquals(['templates.view'], $role->fresh()->permissions->pluck('slug')->all());
    }

    public function test_built_in_roles_and_roles_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();

        $systemRole = Role::where('slug', 'manager')->first();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $systemRole->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);

        $custom = Role::create(['name' => 'Temp', 'slug' => 'temp', 'is_system' => false]);
        User::factory()->create()->roles()->attach($custom);

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $custom->id]);

        $custom->users()->detach();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom->id))
            ->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
    }

    public function test_deleting_a_user_removes_their_owned_data(): void
    {
        $admin = User::factory()->roles(['super-admin'])->create();
        $target = User::factory()->roles(['manager'])->create();

        $smtp = SmtpConfig::create([
            'user_id' => $target->id, 'title' => 'Relay', 'host' => 'smtp.test', 'port' => 587,
            'encryption' => 'tls', 'username' => 'u', 'password' => 'p',
        ]);
        $list = ContactList::create(['user_id' => $target->id, 'name' => 'List']);
        $template = EmailTemplate::create([
            'user_id' => $target->id, 'name' => 'T', 'subject' => 'S', 'body_html' => '<p>Hi</p>',
        ]);
        $campaign = Campaign::create([
            'user_id' => $target->id, 'smtp_config_id' => $smtp->id, 'contact_list_id' => $list->id,
            'email_template_id' => $template->id, 'name' => 'C', 'subject' => 'S', 'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $target->id))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('contact_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('smtp_configs', ['id' => $smtp->id]);
    }
}
