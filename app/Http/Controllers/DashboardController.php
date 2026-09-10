<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SmtpConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // High Level Metrics
        $totalSent = Campaign::where('user_id', $user->id)->sum('sent_count');
        $totalFailed = Campaign::where('user_id', $user->id)->sum('failed_count');
        $totalOpened = Campaign::where('user_id', $user->id)->sum('opened_count');
        $totalClicked = Campaign::where('user_id', $user->id)->sum('clicked_count');

        $totalNotOpened = max(0, $totalSent - $totalOpened);
        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 1) : 0;

        $totalLists = ContactList::where('user_id', $user->id)->count();
        $totalContacts = Contact::whereHas('contactList', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->count();
        $totalSmtps = SmtpConfig::where('user_id', $user->id)->count();
        $totalCampaigns = Campaign::where('user_id', $user->id)->count();

        // Recent Campaigns
        $recentCampaigns = Campaign::where('user_id', $user->id)
            ->with(['smtpConfig', 'contactList', 'template'])
            ->latest()
            ->take(5)
            ->get();

        // 7-day Activity Chart Data
        $chartLabels = [];
        $chartSentData = [];
        $chartOpenedData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $label = $date->format('M d');
            $chartLabels[] = $label;

            $sentDay = CampaignLog::whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
                ->whereDate('sent_at', $dateStr)
                ->where('status', 'sent')
                ->count();

            $openedDay = CampaignLog::whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
                ->whereDate('opened_at', $dateStr)
                ->where('is_opened', true)
                ->count();

            $chartSentData[] = $sentDay;
            $chartOpenedData[] = $openedDay;
        }

        return view('dashboard.index', compact(
            'totalSent',
            'totalFailed',
            'totalOpened',
            'totalNotOpened',
            'totalClicked',
            'openRate',
            'clickRate',
            'totalLists',
            'totalContacts',
            'totalSmtps',
            'totalCampaigns',
            'recentCampaigns',
            'chartLabels',
            'chartSentData',
            'chartOpenedData'
        ));
    }
}
