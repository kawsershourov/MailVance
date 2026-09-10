<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\SmtpConfig;
use App\Models\SuppressionList;
use App\Services\CampaignLaunchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $campaigns = Campaign::where('user_id', $user->id)
            ->with(['smtpConfig', 'contactList', 'template'])
            ->latest()
            ->paginate(15);

        return view('campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $user = Auth::user();
        $smtps = SmtpConfig::where('user_id', $user->id)->get();
        $lists = ContactList::where('user_id', $user->id)->withCount('contacts')->get();
        $templates = EmailTemplate::where('user_id', $user->id)->get();

        return view('campaigns.create', compact('smtps', 'lists', 'templates'));
    }

    public function store(Request $request, CampaignLaunchService $launcher)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'smtp_config_id' => 'required|exists:smtp_configs,id',
            'contact_list_id' => 'required|exists:contact_lists,id',
            'email_template_id' => 'required|exists:email_templates,id',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'delay_seconds' => 'required|integer|min:0|max:120',
            'jitter_enabled' => 'nullable|boolean',
            'batch_size' => 'nullable|integer|min:0',
            'batch_delay_seconds' => 'nullable|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'send_now' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        // Ownership must be enforced server-side: `exists:` only proves the row exists,
        // not that it belongs to this user.
        $smtp = SmtpConfig::findOrFail($validated['smtp_config_id']);
        if ($smtp->user_id !== Auth::id()) {
            abort(403);
        }

        $list = ContactList::findOrFail($validated['contact_list_id']);
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }

        $template = EmailTemplate::findOrFail($validated['email_template_id']);
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        // `send_now` reaches launchCampaign() as a plain method call, which skips
        // the `permission:campaigns.launch` middleware guarding the launch route.
        // The shipped `member` role has campaigns.create but deliberately not
        // campaigns.launch, so this has to be re-checked by hand.
        $sendNow = $request->boolean('send_now');

        if ($sendNow && ! $user->hasPermission('campaigns.launch')) {
            abort(403, 'You do not have permission to launch campaigns.');
        }

        $validated['user_id'] = $user->id;
        $validated['jitter_enabled'] = $request->boolean('jitter_enabled', true);
        $validated['status'] = 'draft';

        $campaign = Campaign::create($validated);

        $recipientCount = $this->buildRecipientLogs($campaign, $list);

        $campaign->update(['total_recipients' => $recipientCount]);

        if ($sendNow) {
            return $this->launchCampaign($campaign, $launcher);
        }

        return redirect()->route('campaigns.show', $campaign->id)->with('success', 'Campaign created in draft mode. Ready for launch.');
    }

    /**
     * Creates one pending CampaignLog per eligible recipient.
     *
     * Streamed in chunks rather than loaded at once: a list built by the 200MB
     * CSV importer does not fit in a request's memory, and neither does the
     * suppression table. Suppressions are looked up per chunk and scoped to the
     * campaign owner, so one account's unsubscribes never shrink another's send.
     */
    private function buildRecipientLogs(Campaign $campaign, ContactList $list): int
    {
        $recipientCount = 0;
        $now = now();

        Contact::where('contact_list_id', $list->id)
            ->where('status', 'active')
            ->select(['id', 'email', 'first_name', 'last_name'])
            ->chunkById(1000, function ($contacts) use ($campaign, $now, &$recipientCount) {
                $emails = $contacts->pluck('email')
                    ->map(fn ($email) => strtolower((string) $email))
                    ->all();

                $suppressed = SuppressionList::query()
                    ->appliesTo($campaign->user_id)
                    ->whereIn('email', $emails)
                    ->pluck('email')
                    ->flip()
                    ->all();

                $logs = [];

                foreach ($contacts as $c) {
                    $email = strtolower((string) $c->email);

                    if (isset($suppressed[$email])) {
                        continue;
                    }

                    $logs[] = [
                        'campaign_id' => $campaign->id,
                        'contact_id' => $c->id,
                        'recipient_email' => $email,
                        'recipient_name' => $c->full_name,
                        'tracking_token' => Str::uuid()->toString(),
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($logs !== []) {
                    CampaignLog::insert($logs);
                    $recipientCount += count($logs);
                }
            });

        return $recipientCount;
    }

    public function show(Request $request, Campaign $campaign)
    {
        if ($campaign->user_id !== Auth::id()) {
            abort(403);
        }

        $campaign->load(['smtpConfig', 'contactList', 'template']);

        $query = $campaign->logs();
        if ($filter = $request->query('status')) {
            $query->where('status', $filter);
        }
        if ($search = $request->query('search')) {
            $query->where('recipient_email', 'like', '%'.self::escapeLike($search).'%');
        }

        $logs = $query->latest()->paginate(25)->withQueryString();

        return view('campaigns.show', compact('campaign', 'logs'));
    }

    public function launchCampaign(Campaign $campaign, CampaignLaunchService $launcher)
    {
        if ($campaign->user_id !== Auth::id()) {
            abort(403);
        }

        if (! $launcher->launch($campaign)) {
            return redirect()->route('campaigns.show', $campaign->id)
                ->with('error', 'Campaign could not be launched — it is already running or is no longer a draft.');
        }

        return redirect()->route('campaigns.show', $campaign->id)->with('success', 'Campaign launched! Sending emails in background queue.');
    }

    public function pause(Campaign $campaign)
    {
        if ($campaign->user_id !== Auth::id()) {
            abort(403);
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('info', 'Campaign paused. Remaining sends temporarily stopped.');
    }

    public function resume(Campaign $campaign, CampaignLaunchService $launcher)
    {
        if ($campaign->user_id !== Auth::id()) {
            abort(403);
        }

        if (! $launcher->resume($campaign)) {
            return back()->with('error', 'Campaign could not be resumed — it is not currently paused.');
        }

        return back()->with('success', 'Campaign resumed! Sending remaining emails.');
    }

    public function cancel(Campaign $campaign)
    {
        if ($campaign->user_id !== Auth::id()) {
            abort(403);
        }

        $campaign->update(['status' => 'cancelled']);

        return back()->with('info', 'Campaign cancelled.');
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->user_id !== Auth::id()) {
            abort(403);
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign and logs deleted.');
    }

    public function statusJson(Campaign $campaign)
    {
        if ($campaign->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => $campaign->status,
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count,
            'opened_count' => $campaign->opened_count,
            'clicked_count' => $campaign->clicked_count,
            'progress_percent' => $campaign->progress_percent,
            'open_rate' => $campaign->open_rate,
            'click_rate' => $campaign->click_rate,
        ]);
    }
}
