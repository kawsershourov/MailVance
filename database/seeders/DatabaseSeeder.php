<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\SmtpConfig;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Roles & permissions must exist before users are assigned to them
        $this->call(RolesAndPermissionsSeeder::class);

        // 1. Create Default Admin User
        $user = User::firstOrCreate(
            ['email' => 'admin@mailflow.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
            ]
        );

        // The demo admin always holds the super admin role
        $superAdmin = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($superAdmin) {
            $user->roles()->syncWithoutDetaching([$superAdmin->id]);
        }

        // 2. Create Sample SMTP Relay
        $smtp = SmtpConfig::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Primary Amazon SES Relay'],
            [
                'host' => 'smtp.mailgun.org',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'postmaster@sandbox.mailgun.org',
                'password' => 'secret_smtp_password',
                'from_name' => 'MailFlow Updates',
                'from_email' => 'news@mailflow.com',
                'reply_to' => 'support@mailflow.com',
                'is_default' => true,
            ]
        );

        // 3. Create Sample Contact Lists & Subscribers
        $list = ContactList::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'VIP Newsletter Subscribers'],
            [
                'description' => 'Verified subscribers with high open engagement.',
                'total_contacts' => 5,
            ]
        );

        $sampleContacts = [
            ['email' => 'alex.morgan@example.com', 'first_name' => 'Alex', 'last_name' => 'Morgan', 'company' => 'Apex Tech'],
            ['email' => 'sarah.jenkins@example.com', 'first_name' => 'Sarah', 'last_name' => 'Jenkins', 'company' => 'CloudScale'],
            ['email' => 'david.chen@example.com', 'first_name' => 'David', 'last_name' => 'Chen', 'company' => 'Venture Labs'],
            ['email' => 'elena.rostova@example.com', 'first_name' => 'Elena', 'last_name' => 'Rostova', 'company' => 'Nexus Digital'],
            ['email' => 'marcus.vance@example.com', 'first_name' => 'Marcus', 'last_name' => 'Vance', 'company' => 'Hyperion Dynamics'],
        ];

        foreach ($sampleContacts as $sc) {
            Contact::firstOrCreate(
                ['contact_list_id' => $list->id, 'email' => $sc['email']],
                [
                    'first_name' => $sc['first_name'],
                    'last_name' => $sc['last_name'],
                    'company' => $sc['company'],
                    'status' => 'active',
                ]
            );
        }

        // 4. Create Pre-Built Starter Email Template
        $template = EmailTemplate::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'High-Deliverability Promotional Newsletter'],
            [
                'subject' => 'Special update for {{first_name}} at {{company}}',
                'body_html' => '<div style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 32px 16px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <div style="background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 32px; text-align: center; color: #ffffff;">
            <h1 style="margin: 0; font-size: 24px; font-weight: 800;">MailFlow Special Newsletter</h1>
            <p style="margin-top: 8px; font-size: 14px; opacity: 0.9;">Curated insights for {{first_name}} at {{company}}</p>
        </div>
        <div style="padding: 32px; color: #334155; line-height: 1.6;">
            <h2 style="color: #0f172a; font-size: 18px; margin-top: 0;">Maximize Your Inbox Placement</h2>
            <p>Hi {{first_name}},</p>
            <p>We are delighted to share our newest anti-spam deliverability engine featuring RFC 8058 one-click headers, SPF/DKIM verification, and smart sending throttles.</p>
            <p style="text-align: center; margin: 32px 0;">
                <a href="https://example.com" style="background: #4f46e5; color: #ffffff; padding: 14px 28px; border-radius: 12px; font-weight: bold; text-decoration: none; display: inline-block;">Explore Features &rarr;</a>
            </p>
            <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 28px 0;" />
            <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                Sent to {{email}}.<br/>
                <a href="{{unsubscribe_url}}" style="color: #64748b;">Unsubscribe immediately</a>
            </p>
        </div>
    </div>
</div>',
                'body_text' => 'Hi {{first_name}},

Maximize your email deliverability with MailFlow.

Visit: https://example.com

Unsubscribe: {{unsubscribe_url}}',
                'spam_score' => 0,
            ]
        );

        // 5. Create Sample Completed Campaign for Instant Metrics
        $campaign = Campaign::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Q3 Product Announcement'],
            [
                'smtp_config_id' => $smtp->id,
                'contact_list_id' => $list->id,
                'email_template_id' => $template->id,
                'subject' => 'Special update for {{first_name}}',
                'status' => 'completed',
                'delay_seconds' => 2,
                'jitter_enabled' => true,
                'total_recipients' => 5,
                'sent_count' => 5,
                'failed_count' => 0,
                'opened_count' => 4,
                'clicked_count' => 2,
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subHours(1),
            ]
        );

        foreach ($list->contacts as $index => $contact) {
            $isOpened = ($index < 4);
            $isClicked = ($index < 2);

            CampaignLog::firstOrCreate(
                ['campaign_id' => $campaign->id, 'recipient_email' => $contact->email],
                [
                    'contact_id' => $contact->id,
                    'recipient_name' => $contact->full_name,
                    'tracking_token' => Str::uuid()->toString(),
                    'status' => 'sent',
                    'is_opened' => $isOpened,
                    'opened_at' => $isOpened ? now()->subMinutes(rand(10, 50)) : null,
                    'is_clicked' => $isClicked,
                    'clicked_at' => $isClicked ? now()->subMinutes(rand(5, 20)) : null,
                    'sent_at' => now()->subHours(2)->addSeconds($index * 3),
                ]
            );
        }
    }
}
