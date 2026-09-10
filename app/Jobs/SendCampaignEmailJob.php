<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\SmtpConfig;
use App\Services\SmtpMailService;
use App\Services\TemplateRendererService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;

    public int $campaignLogId;

    public function __construct(int $campaignId, int $campaignLogId)
    {
        $this->campaignId = $campaignId;
        $this->campaignLogId = $campaignLogId;
    }

    public function handle(SmtpMailService $mailService, TemplateRendererService $renderer): void
    {
        $campaign = Campaign::find($this->campaignId);
        $log = CampaignLog::find($this->campaignLogId);

        if (! $campaign || ! $log) {
            return;
        }

        // Check if campaign was paused or cancelled
        if (in_array($campaign->status, ['paused', 'cancelled', 'draft'])) {
            return;
        }

        // Atomically claim this recipient. If another job already claimed it (double
        // launch, duplicate dispatch, queue retry) the update affects 0 rows and we
        // bail out, so nobody is ever emailed twice.
        $claimed = CampaignLog::where('id', $log->id)
            ->where('status', 'pending')
            ->update(['status' => 'sending']);

        if ($claimed === 0) {
            return;
        }

        $log->refresh();

        $smtp = $campaign->smtpConfig;
        if (! $smtp) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'No active SMTP configuration attached to campaign.',
            ]);
            $campaign->increment('failed_count');

            return;
        }

        // Enforce the SMTP relay's hourly cap. A blocking sleep here could outlive the
        // queue worker timeout, so instead we hand the recipient back, pause the
        // campaign, and let campaigns:process-scheduled resume it once the hour clears.
        if ($smtp->hourly_limit > 0 && $this->sentInLastHour($smtp) >= $smtp->hourly_limit) {
            CampaignLog::where('id', $log->id)->update(['status' => 'pending']);
            $campaign->update(['status' => 'paused']);
            Cache::put(
                "campaign:{$campaign->id}:hourly_cap_resume_at",
                now()->addHour()->toDateTimeString(),
                now()->addHours(3)
            );

            return;
        }

        $contact = $log->contact;
        $template = $campaign->template;

        $subject = $campaign->subject;
        $htmlBody = $template ? $template->body_html : '<p>No content</p>';
        $textBody = $template ? $template->body_text : '';

        // Unsubscribe Link
        $appUrl = rtrim(config('app.url'), '/');
        $unsubUrl = "{$appUrl}/unsubscribe/{$log->tracking_token}";

        // Render personalization merge tags
        $renderedSubject = $renderer->render($subject, $contact, $unsubUrl);
        $renderedHtml = $renderer->render($htmlBody, $contact, $unsubUrl);
        $renderedText = $renderer->render($textBody, $contact, $unsubUrl);

        $logoPath = $template && $template->logo_path && Storage::disk('local')->exists($template->logo_path)
            ? Storage::disk('local')->path($template->logo_path)
            : null;

        // Rewrite links for click tracking
        $trackedHtml = $renderer->rewriteLinksForTracking($renderedHtml, $log->tracking_token);

        try {
            // Apply delay throttling if configured
            if ($campaign->delay_seconds > 0) {
                $delay = $campaign->delay_seconds;
                if ($campaign->jitter_enabled) {
                    $jitterMs = rand(-400, 400); // +/- 400ms humanized variation
                    $delay = max(0.5, $delay + ($jitterMs / 1000));
                }
                usleep((int) ($delay * 1000000));
            }

            $recipientName = $contact ? $contact->full_name : ($log->recipient_name ?: $log->recipient_email);

            $mailService->sendCampaignEmail(
                $smtp,
                $log->recipient_email,
                $recipientName,
                $renderedSubject,
                $trackedHtml,
                $renderedText,
                $log->tracking_token,
                $unsubUrl,
                $logoPath
            );

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $campaign->increment('sent_count');

            $this->applyBatchThrottle($campaign);

        } catch (Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $campaign->increment('failed_count');
        }

        // Check if all recipients have been processed
        $remaining = CampaignLog::where('campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'sending'])
            ->count();

        if ($remaining === 0) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            Cache::forget("campaign:{$campaign->id}:batch_sent_count");
        }
    }

    /**
     * Count emails already sent through this relay in the trailing hour.
     */
    private function sentInLastHour(SmtpConfig $smtp): int
    {
        return CampaignLog::where('status', 'sent')
            ->where('sent_at', '>=', now()->subHour())
            ->whereHas('campaign', fn ($q) => $q->where('smtp_config_id', $smtp->id))
            ->count();
    }

    /**
     * Pause for batch_delay_seconds every batch_size emails. Blocking by design —
     * same throttle model as the per-email delay above.
     */
    private function applyBatchThrottle(Campaign $campaign): void
    {
        if ($campaign->batch_size <= 0) {
            return;
        }

        $key = "campaign:{$campaign->id}:batch_sent_count";
        $sentInBatch = (int) Cache::get($key, 0) + 1;

        if ($sentInBatch >= $campaign->batch_size) {
            Cache::put($key, 0, now()->addDays(2));
            if ($campaign->batch_delay_seconds > 0) {
                sleep($campaign->batch_delay_seconds);
            }

            return;
        }

        Cache::put($key, $sentInBatch, now()->addDays(2));
    }
}
