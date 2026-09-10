<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\SmtpConfig;
use App\Models\User;
use App\Services\SmtpMailService;
use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BugFixRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function smtpFor(User $user, array $overrides = []): SmtpConfig
    {
        return SmtpConfig::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Relay',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'relay-user',
            'password' => 'super-secret-relay-pw',
            'from_name' => 'MailFlow',
            'from_email' => 'send@example.com',
        ], $overrides));
    }

    private function listFor(User $user): ContactList
    {
        return ContactList::create([
            'user_id' => $user->id,
            'name' => 'Subscribers',
        ]);
    }

    private function templateFor(User $user): EmailTemplate
    {
        return EmailTemplate::create([
            'user_id' => $user->id,
            'name' => 'Newsletter',
            'subject' => 'Hi {{first_name}}',
            'body_html' => '<p>Hello {{first_name}}</p>',
        ]);
    }

    /** The wizard used to die with a PHP parse error before it ever rendered. */
    public function test_campaign_create_page_renders(): void
    {
        $user = User::factory()->create();
        $this->smtpFor($user);
        $this->listFor($user);
        $this->templateFor($user);

        $this->actingAs($user)->get(route('campaigns.create'))
            ->assertStatus(200)
            ->assertSee('{{first_name}}', false);
    }

    /** Both create and edit share templates.editor, which also failed to compile. */
    public function test_template_editor_pages_render(): void
    {
        $user = User::factory()->create();
        $template = $this->templateFor($user);

        $this->actingAs($user)->get(route('templates.create'))
            ->assertStatus(200)
            // Merge tags are emitted as JS data now, never as compiled Blade echoes.
            // Js::from() hex-escapes the quotes, so the bare name is what shows.
            ->assertSee('first_name', false)
            ->assertDontSee('Undefined constant');

        $this->actingAs($user)->get(route('templates.edit', $template->id))
            ->assertStatus(200);
    }

    public function test_design_mode_generates_email_safe_html(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Designed',
            'subject' => 'Hi {{first_name}}',
            'editor_mode' => 'design',
            'design' => [
                'heading' => 'Welcome {{first_name}}',
                'body_text' => 'Thanks for joining.',
                'brand_color' => '#059669',
                'font_family' => 'Georgia',
                'button_text' => 'Get started',
                'button_url' => 'https://example.com',
                'show_button' => 1,
            ],
        ])->assertRedirect(route('templates.index'));

        $template = EmailTemplate::first();

        $this->assertSame('Georgia', $template->design['font_family']);
        $this->assertStringContainsString('Welcome {{first_name}}', $template->body_html);
        $this->assertStringContainsString('#059669', $template->body_html);
        // Email-safe output: table layout with inline styles. The one <style>
        // block carries nothing but the mobile media query — media queries are
        // the only way to vary a layout by viewport, and clients that drop the
        // block (Outlook desktop) still get the full design from the inlines.
        $this->assertStringContainsString('<table', $template->body_html);
        $this->assertSame(1, substr_count($template->body_html, '<style'));
        preg_match('/<style[^>]*>(.*?)<\/style>/s', $template->body_html, $style);
        $this->assertStringContainsString('@media only screen and (max-width:600px)', $style[1]);
        // Nothing lives outside that media query.
        $inner = trim($style[1]);
        $this->assertStringStartsWith('@media', $inner);
        $this->assertStringEndsWith('}', $inner);
        $this->assertSame(1, substr_count($inner, '@media'));
        $this->assertStringContainsString('{{unsubscribe_url}}', $template->body_html);
        // Plain-text alternative is generated too.
        $this->assertStringContainsString('Thanks for joining.', $template->body_text);
    }

    public function test_each_section_can_be_positioned_independently(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Positioned',
            'subject' => 'x',
            'editor_mode' => 'design',
            'design_json' => json_encode([
                'heading' => 'Centered heading',
                'body_text' => 'Left body.',
                'show_button' => true,
                'button_text' => 'Go',
                'sections' => [
                    'heading' => ['align' => 'center', 'pt' => 64, 'pr' => 10, 'pb' => 8, 'pl' => 10],
                    'button' => ['align' => 'right', 'pt' => 0, 'pr' => 48, 'pb' => 40, 'pl' => 48],
                    // Out-of-range values must be clamped, not trusted.
                    'footer' => ['align' => 'nonsense', 'pt' => -20, 'pr' => 9999, 'pb' => 5, 'pl' => 5],
                ],
            ]),
        ])->assertRedirect(route('templates.index'));

        $template = EmailTemplate::first();
        $sections = $template->design['sections'];

        $this->assertSame('center', $sections['heading']['align']);
        $this->assertSame(64, $sections['heading']['pt']);
        $this->assertSame('right', $sections['button']['align']);
        $this->assertSame(48, $sections['button']['pr']);

        // Clamping: bad align falls back, negative -> 0, oversized -> 200.
        $this->assertSame('center', $sections['footer']['align']);
        $this->assertSame(0, $sections['footer']['pt']);
        $this->assertSame(200, $sections['footer']['pr']);

        // Sections not supplied keep their defaults.
        $this->assertSame(16, $sections['body']['pt']);

        // And the values actually reach the generated HTML.
        $this->assertStringContainsString('padding:64px 10px 8px 10px;', $template->body_html);
        $this->assertStringContainsString('padding:0px 48px 40px 48px;', $template->body_html);
    }

    public function test_mobile_overrides_never_alter_the_desktop_design(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Responsive',
            'subject' => 'x',
            'editor_mode' => 'design',
            'design_json' => json_encode([
                'heading' => 'Hello',
                'body_text' => 'Body copy.',
                'font_size' => 16,
                'heading_size' => 26,
                'sections' => [
                    'heading' => ['align' => 'left', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32],
                ],
                // The phone gets tighter gutters, smaller type and centred text.
                'mobile' => [
                    'font_size' => 13,
                    'heading_size' => 20,
                    'sections' => [
                        'heading' => ['align' => 'center', 'pl' => 16, 'pr' => 16],
                        'footer' => ['align' => 'nonsense', 'pt' => -5, 'pb' => 9999],
                    ],
                ],
            ]),
        ])->assertRedirect(route('templates.index'));

        $template = EmailTemplate::first();
        $design = $template->design;
        $html = $template->body_html;

        // Desktop values are untouched by the mobile edits.
        $this->assertSame(16, $design['font_size']);
        $this->assertSame(26, $design['heading_size']);
        $this->assertSame('left', $design['sections']['heading']['align']);
        $this->assertSame(32, $design['sections']['heading']['pl']);
        $this->assertStringContainsString('padding:32px 32px 0px 32px;', $html);
        $this->assertStringContainsString('font-size:26px;', $html);

        // Mobile overrides are stored, with the same clamping as desktop.
        $this->assertSame(13, $design['mobile']['font_size']);
        $this->assertNull($design['mobile']['sections']['heading']['pt'], 'unset sides keep inheriting');
        $this->assertNull($design['mobile']['sections']['footer']['align'], 'a bad align falls back to inherit');
        $this->assertSame(0, $design['mobile']['sections']['footer']['pt']);
        $this->assertSame(200, $design['mobile']['sections']['footer']['pb']);

        // ...and they reach the email only inside the mobile media query.
        $this->assertStringContainsString('@media only screen and (max-width:600px)', $html);
        $this->assertStringContainsString('.mf-body p{font-size:13px !important;}', $html);
        $this->assertStringContainsString('.mf-heading h1{font-size:20px !important', $html);
        // An overridden side is emitted with the desktop value for the rest.
        $this->assertStringContainsString('.mf-heading{padding:32px 16px 0px 16px !important;text-align:center !important;}', $html);
    }

    public function test_the_card_is_fluid_on_mobile_even_without_overrides(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Plain',
            'subject' => 'x',
            'editor_mode' => 'design',
            'design_json' => json_encode(['heading' => 'Hello', 'body_text' => 'Body.', 'content_width' => 600]),
        ])->assertRedirect(route('templates.index'));

        $html = EmailTemplate::first()->body_html;

        // Without this the 600px card overflows a phone screen instead of
        // shrinking, which is what made the mobile view look broken.
        $this->assertStringContainsString('@media only screen and (max-width:600px)', $html);
        $this->assertStringContainsString('.mf-card{width:100% !important;}', $html);
        $this->assertStringContainsString('class="mf-card"', $html);

        // The desktop width is still declared for clients that ignore media queries.
        $this->assertStringContainsString('width:600px;max-width:100%;', $html);

        // No section rules are emitted when nothing was overridden.
        $this->assertStringNotContainsString('.mf-heading{', $html);
    }

    public function test_the_logo_alignment_control_actually_moves_the_logo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $upload = $this->actingAs($user)->post(route('templates.upload-logo'), [
            'logo' => UploadedFile::fake()->image('brand.png', 300, 100),
        ])->assertStatus(200)->json();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Logo right',
            'subject' => 'x',
            'editor_mode' => 'design',
            'logo_path' => $upload['logo_path'],
            'design_json' => json_encode([
                'heading' => 'Hello',
                'body_text' => 'Body.',
                'logo_width' => 200,
                'sections' => ['logo' => ['align' => 'right', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32]],
            ]),
        ])->assertRedirect(route('templates.index'));

        $template = EmailTemplate::first();
        $this->assertSame('right', $template->design['sections']['logo']['align']);

        preg_match('/<img class="mf-logo-img"[^>]*>/', $template->body_html, $img);
        $this->assertNotEmpty($img, 'the logo should be rendered');

        // display:block ignores the cell's align attribute, so only the margin
        // actually moves the logo.
        $this->assertStringContainsString('margin:0 0 0 auto;', $img[0]);
        $this->assertStringContainsString('cid:mailflow-logo', $img[0]);
    }

    public function test_a_legacy_logo_align_value_is_honoured(): void
    {
        $user = User::factory()->create();

        // Designs saved before the fix carry only the top-level key, which the
        // renderer used to ignore entirely.
        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Legacy',
            'subject' => 'x',
            'editor_mode' => 'design',
            'design_json' => json_encode(['heading' => 'Hello', 'logo_align' => 'right']),
        ])->assertRedirect(route('templates.index'));

        $design = EmailTemplate::first()->design;
        $this->assertSame('right', $design['sections']['logo']['align']);
        $this->assertSame('right', $design['logo_align'], 'both keys stay in step');
    }

    public function test_an_explicit_logo_section_align_wins_over_the_legacy_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Explicit',
            'subject' => 'x',
            'editor_mode' => 'design',
            'design_json' => json_encode([
                'heading' => 'Hello',
                'logo_align' => 'right',
                'sections' => ['logo' => ['align' => 'left', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32]],
            ]),
        ])->assertRedirect(route('templates.index'));

        $this->assertSame('left', EmailTemplate::first()->design['sections']['logo']['align']);
    }

    public function test_the_logo_can_be_aligned_differently_on_mobile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Logo mobile',
            'subject' => 'x',
            'editor_mode' => 'design',
            'design_json' => json_encode([
                'heading' => 'Hello',
                'sections' => ['logo' => ['align' => 'left', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32]],
                'mobile' => ['sections' => ['logo' => ['align' => 'center']]],
            ]),
        ])->assertRedirect(route('templates.index'));

        $html = EmailTemplate::first()->body_html;

        // Desktop keeps the left margin, mobile re-centres it inside the query.
        $this->assertStringContainsString('.mf-logo{text-align:center !important;}', $html);
        $this->assertStringContainsString('.mf-logo-img{margin:0 auto !important;}', $html);
    }

    public function test_logo_upload_is_scoped_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $upload = $this->actingAs($owner)->post(route('templates.upload-logo'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertStatus(200)->json();

        $filename = basename($upload['logo_path']);
        $this->assertStringContainsString('template_logos/'.$owner->id, $upload['logo_path']);

        $this->actingAs($owner)->get(route('templates.logo', ['filename' => $filename]))->assertStatus(200);
        // Another user must not be able to read it, even knowing the filename.
        $this->actingAs($other)->get(route('templates.logo', ['filename' => $filename]))->assertStatus(404);

        // A template cannot claim a logo path belonging to someone else.
        $this->actingAs($other)->post(route('templates.store'), [
            'name' => 'Thief',
            'subject' => 'x',
            'editor_mode' => 'design',
            'logo_path' => $upload['logo_path'],
            'design' => ['heading' => 'x'],
        ])->assertRedirect();

        $this->assertNull(EmailTemplate::where('user_id', $other->id)->first()->logo_path);
    }

    public function test_campaign_store_rejects_another_users_resources(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();

        $response = $this->actingAs($attacker)->post(route('campaigns.store'), [
            'name' => 'Piggyback',
            'subject' => 'Hello',
            'smtp_config_id' => $this->smtpFor($victim)->id,
            'contact_list_id' => $this->listFor($victim)->id,
            'email_template_id' => $this->templateFor($victim)->id,
            'delay_seconds' => 0,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_launching_a_campaign_twice_does_not_duplicate_sends(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'smtp_config_id' => $this->smtpFor($user)->id,
            'contact_list_id' => $this->listFor($user)->id,
            'email_template_id' => $this->templateFor($user)->id,
            'name' => 'Blast',
            'subject' => 'Hello',
            'status' => 'draft',
            'delay_seconds' => 0,
        ]);

        CampaignLog::create([
            'campaign_id' => $campaign->id,
            'recipient_email' => 'someone@example.com',
            'tracking_token' => Str::uuid()->toString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post(route('campaigns.launch', $campaign->id));
        $this->actingAs($user)->post(route('campaigns.launch', $campaign->id));

        // The second launch finds the campaign already 'processing' and claims nothing.
        Queue::assertPushed(SendCampaignEmailJob::class, 1);
    }

    /** Even if a duplicate job slips through, the row claim keeps the send single. */
    public function test_send_job_skips_a_recipient_already_claimed(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'smtp_config_id' => $this->smtpFor($user)->id,
            'contact_list_id' => $this->listFor($user)->id,
            'email_template_id' => $this->templateFor($user)->id,
            'name' => 'Blast',
            'subject' => 'Hello',
            'status' => 'processing',
            'delay_seconds' => 0,
            'total_recipients' => 1,
        ]);

        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'recipient_email' => 'someone@example.com',
            'tracking_token' => Str::uuid()->toString(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        (new SendCampaignEmailJob($campaign->id, $log->id))->handle(
            app(SmtpMailService::class),
            app(TemplateRendererService::class)
        );

        $this->assertSame(0, $campaign->fresh()->sent_count);
    }

    public function test_smtp_password_is_encrypted_at_rest_and_never_serialized(): void
    {
        $user = User::factory()->create();
        $smtp = $this->smtpFor($user);

        $stored = DB::table('smtp_configs')->where('id', $smtp->id)->value('password');
        $this->assertNotSame('super-secret-relay-pw', $stored);
        $this->assertSame('super-secret-relay-pw', $smtp->fresh()->password);

        $this->assertArrayNotHasKey('password', $smtp->toArray());
        $this->assertStringNotContainsString('super-secret-relay-pw', json_encode($smtp));

        $this->actingAs($user)->get(route('smtp.index'))
            ->assertStatus(200)
            ->assertDontSee('super-secret-relay-pw');
    }

    public function test_csv_import_rejects_an_untrusted_file_path(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $response = $this->actingAs($user)->postJson(route('contacts.process-csv-import'), [
            'contact_list_id' => $list->id,
            'temp_file_path' => '../../../../etc/passwd',
            'column_map' => ['email' => 0],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('contacts', 0);
    }

    /** The token round-trip must still let a legitimate upload import normally. */
    public function test_csv_upload_and_import_round_trip_still_works(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $csv = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "email,first_name\nada@example.com,Ada\ngrace@example.com,Grace\n"
        );

        $preview = $this->actingAs($user)->post(route('contacts.upload-csv-preview'), [
            'csv_file' => $csv,
        ])->assertStatus(200)->json();

        $this->assertTrue($preview['success']);
        // The browser must never receive a real filesystem path.
        $this->assertStringNotContainsString('/', $preview['temp_file_path']);

        $this->actingAs($user)->postJson(route('contacts.process-csv-import'), [
            'contact_list_id' => $list->id,
            'temp_file_path' => $preview['temp_file_path'],
            'column_map' => $preview['auto_mapping'],
        ])->assertStatus(200);

        $this->assertDatabaseHas('contacts', [
            'contact_list_id' => $list->id,
            'email' => 'ada@example.com',
        ]);
        $this->assertDatabaseCount('contacts', 2);
    }

    public function test_scheduled_campaign_is_launched_by_the_console_command(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $list = $this->listFor($user);
        $contact = Contact::create([
            'contact_list_id' => $list->id,
            'email' => 'due@example.com',
            'status' => 'active',
        ]);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'smtp_config_id' => $this->smtpFor($user)->id,
            'contact_list_id' => $list->id,
            'email_template_id' => $this->templateFor($user)->id,
            'name' => 'Scheduled blast',
            'subject' => 'Hello',
            'status' => 'draft',
            'delay_seconds' => 0,
            'scheduled_at' => now()->subMinute(),
        ]);

        CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'recipient_email' => $contact->email,
            'tracking_token' => Str::uuid()->toString(),
            'status' => 'pending',
        ]);

        $this->artisan('campaigns:process-scheduled')->assertSuccessful();

        $this->assertSame('processing', $campaign->fresh()->status);
        Queue::assertPushed(SendCampaignEmailJob::class, 1);
    }
}
