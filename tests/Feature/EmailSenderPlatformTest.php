<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\User;
use App\Services\DeliverabilityScoreService;
use App\Services\TemplateRendererService;
use App\Services\TrackingUrlSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailSenderPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_access_dashboard(): void
    {
        // Public signup is off by default on a sending platform, so the feature
        // has to be switched on for this flow to exist at all.
        config(['mailflow.allow_registration' => true]);

        $response = $this->post(route('register.post'), [
            'name' => 'Developer Tester',
            'email' => 'dev@mailflow.com',
            'password' => 'Str0ngPassword!',
            'password_confirmation' => 'Str0ngPassword!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $dashResponse = $this->get(route('dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Developer Tester');
    }

    public function test_smtp_configuration_crud(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smtp.store'), [
            'title' => 'AWS Production',
            'host' => 'email-smtp.us-east-1.amazonaws.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'aws_access_key',
            'password' => 'aws_secret_key',
            'from_name' => 'Sender Platform',
            'from_email' => 'sender@mydomain.com',
            'reply_to' => 'reply@mydomain.com',
            'is_default' => 1,
        ]);

        $response->assertRedirect(route('smtp.index'));
        $this->assertDatabaseHas('smtp_configs', [
            'title' => 'AWS Production',
            'host' => 'email-smtp.us-east-1.amazonaws.com',
            'is_default' => 1,
        ]);
    }

    public function test_template_rendering_and_spam_score(): void
    {
        $renderer = new TemplateRendererService;
        $contact = new Contact([
            'email' => 'sarah@example.com',
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'company' => 'Cyberdyne Systems',
        ]);

        $rendered = $renderer->render('Hello {{first_name}} from {{company}}!', $contact, 'https://mailflow.test/unsub');
        $this->assertEquals('Hello Sarah from Cyberdyne Systems!', $rendered);

        $spamService = new DeliverabilityScoreService;
        $spamCheck = $spamService->analyzeSpamScore('URGENT: WIN $$$ 100% FREE ACT NOW', '<p>Free trial giveaway</p>');
        $this->assertGreaterThan(50, $spamCheck['score']);
        $this->assertContains('100% free', $spamCheck['matched_keywords']);
    }

    public function test_open_and_click_tracking(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Track Test',
            'subject' => 'Track Subject',
            'total_recipients' => 1,
            'sent_count' => 1,
        ]);

        $token = Str::uuid()->toString();
        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'recipient_email' => 'track@test.com',
            'tracking_token' => $token,
            'status' => 'sent',
        ]);

        // 1. Test Open Tracking Pixel
        $openResponse = $this->get(route('track.open', $token));
        $openResponse->assertStatus(200);
        $openResponse->assertHeader('Content-Type', 'image/png');

        $this->assertDatabaseHas('campaign_logs', [
            'id' => $log->id,
            'is_opened' => true,
        ]);
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'opened_count' => 1,
        ]);

        // 2. Test Click Tracking Redirection. The destination must carry the
        // signature the link rewriter stamps on it, otherwise /track/click would
        // be an open redirect on the sending domain.
        $signer = new TrackingUrlSigner;

        $clickResponse = $this->get(route('track.click', [
            'token' => $token,
            'url' => 'https://google.com',
            'sig' => $signer->sign('https://google.com'),
        ]));
        $clickResponse->assertRedirect('https://google.com');

        $this->assertDatabaseHas('campaign_logs', [
            'id' => $log->id,
            'is_clicked' => true,
        ]);
    }

    public function test_unsubscribe_adds_to_suppression_list(): void
    {
        $user = User::factory()->create();
        $list = ContactList::create(['user_id' => $user->id, 'name' => 'Sub List']);
        $contact = Contact::create([
            'contact_list_id' => $list->id,
            'email' => 'optout@example.com',
            'first_name' => 'Opt',
            'status' => 'active',
        ]);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Unsub Campaign',
            'subject' => 'Testing Unsub',
        ]);

        $token = Str::uuid()->toString();
        CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'recipient_email' => 'optout@example.com',
            'tracking_token' => $token,
            'status' => 'sent',
        ]);

        // GET only confirms — link scanners and prefetch fire GETs, so it must
        // not mutate anything.
        $this->get(route('unsubscribe', $token))
            ->assertStatus(200)
            ->assertSee('Unsubscribe?');

        $this->assertDatabaseMissing('suppression_lists', [
            'email' => 'optout@example.com',
        ]);

        // POST is the acting route, and is what RFC 8058 one-click calls.
        $unsubResponse = $this->post(route('unsubscribe.confirm', $token));
        $unsubResponse->assertStatus(200);
        $unsubResponse->assertSee('Unsubscribed Successfully');

        $this->assertDatabaseHas('suppression_lists', [
            'user_id' => $user->id,
            'email' => 'optout@example.com',
        ]);
        $this->assertDatabaseHas('contacts', [
            'email' => 'optout@example.com',
            'status' => 'unsubscribed',
        ]);
    }
}
