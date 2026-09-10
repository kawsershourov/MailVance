<?php

namespace App\Services;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignLog;

class CampaignLaunchService
{
    /**
     * Launch a draft campaign. Returns false when the campaign was not in a
     * launchable state (already processing, completed, cancelled, ...), which
     * is what makes a double-submit safe.
     */
    public function launch(Campaign $campaign): bool
    {
        $claimed = Campaign::where('id', $campaign->id)
            ->where('status', 'draft')
            ->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $campaign->refresh();
        $this->dispatchPendingLogs($campaign->id);

        return true;
    }

    /**
     * Resume a paused campaign. Returns false when the campaign was not paused.
     */
    public function resume(Campaign $campaign): bool
    {
        $claimed = Campaign::where('id', $campaign->id)
            ->where('status', 'paused')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            return false;
        }

        $campaign->refresh();
        $this->dispatchPendingLogs($campaign->id);

        return true;
    }

    /**
     * Queue one send job per recipient still awaiting delivery.
     */
    private function dispatchPendingLogs(int $campaignId): void
    {
        CampaignLog::where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->select('id')
            ->chunkById(1000, function ($logs) use ($campaignId) {
                foreach ($logs as $log) {
                    SendCampaignEmailJob::dispatch($campaignId, $log->id);
                }
            });
    }
}
