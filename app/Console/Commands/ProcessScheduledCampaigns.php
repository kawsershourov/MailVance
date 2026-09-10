<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignLaunchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:process-scheduled';

    protected $description = 'Launch campaigns whose scheduled_at has arrived, and resume campaigns auto-paused by an SMTP hourly cap.';

    public function handle(CampaignLaunchService $launcher): int
    {
        $this->launchDueCampaigns($launcher);
        $this->resumeHourlyCappedCampaigns($launcher);

        return self::SUCCESS;
    }

    private function launchDueCampaigns(CampaignLaunchService $launcher): void
    {
        Campaign::where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get()
            ->each(function (Campaign $campaign) use ($launcher) {
                if ($launcher->launch($campaign)) {
                    $this->info("Launched scheduled campaign #{$campaign->id}: {$campaign->name}");
                }
            });
    }

    /**
     * Only campaigns paused by the hourly-cap guard carry a resume marker, so a
     * campaign a user paused by hand is never restarted here.
     */
    private function resumeHourlyCappedCampaigns(CampaignLaunchService $launcher): void
    {
        Campaign::where('status', 'paused')
            ->get()
            ->each(function (Campaign $campaign) use ($launcher) {
                $key = "campaign:{$campaign->id}:hourly_cap_resume_at";
                $resumeAt = Cache::get($key);

                if (! $resumeAt || now()->lessThan($resumeAt)) {
                    return;
                }

                Cache::forget($key);

                if ($launcher->resume($campaign)) {
                    $this->info("Resumed hourly-capped campaign #{$campaign->id}: {$campaign->name}");
                }
            });
    }
}
