<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SuppressionList;
use App\Services\TrackingUrlSigner;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingUrlSigner $signer) {}

    /**
     * 1x1 Transparent PNG Open Tracking Pixel
     */
    public function open(string $token)
    {
        $log = CampaignLog::where('tracking_token', $token)->first();

        if ($log && ! $log->is_opened) {
            $log->update([
                'is_opened' => true,
                'opened_at' => now(),
            ]);

            Campaign::where('id', $log->campaign_id)->increment('opened_count');
        }

        // Base64 1x1 Transparent PNG
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        return response($pixel, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Click Tracking & Redirection.
     *
     * The destination must carry the signature this platform stamped on it when
     * the campaign HTML was rewritten. An unsigned or tampered `url` is dropped
     * rather than followed, so this endpoint cannot be used as an open redirect.
     */
    public function click(string $token, Request $request)
    {
        $log = CampaignLog::where('tracking_token', $token)->first();

        if (! $log) {
            return redirect('/');
        }

        if (! $log->is_clicked) {
            $log->update([
                'is_clicked' => true,
                'clicked_at' => now(),
            ]);

            Campaign::where('id', $log->campaign_id)->increment('clicked_count');
        }

        $url = (string) $request->query('url', '');

        if ($url !== ''
            && $this->signer->isRedirectable($url)
            && $this->signer->verify($url, $request->query('sig'))) {
            return redirect()->away($url);
        }

        return redirect('/');
    }

    /**
     * Unsubscribe confirmation page.
     *
     * Deliberately read-only: mail clients, link scanners and browser prefetch
     * all fire GETs, and a GET that suppressed the recipient would unsubscribe
     * people who never clicked anything.
     */
    public function unsubscribe(string $token)
    {
        $log = CampaignLog::with('campaign')->where('tracking_token', $token)->first();

        return view('tracking.unsubscribe-confirm', [
            'token' => $token,
            'email' => $log?->recipient_email,
            'alreadyDone' => $log ? $this->isSuppressed($log) : false,
        ]);
    }

    /**
     * RFC 8058 One-Click Unsubscribe (and the confirmation form's target).
     *
     * The tracking token is the only credential: it is a per-recipient UUIDv4,
     * and nothing here trusts a caller-supplied address. The previous version
     * fell back to `?email=`, which let anyone suppress any address for every
     * account on the platform.
     */
    public function unsubscribePost(string $token)
    {
        $log = CampaignLog::with('campaign')->where('tracking_token', $token)->first();

        if (! $log) {
            return view('tracking.unsubscribed', ['email' => null, 'failed' => true]);
        }

        $email = strtolower(trim((string) $log->recipient_email));
        $ownerId = $log->campaign?->user_id;

        if ($email !== '' && $ownerId !== null) {
            SuppressionList::firstOrCreate(
                ['user_id' => $ownerId, 'email' => $email],
                ['reason' => 'unsubscribed'],
            );

            // Scoped to the sender's own lists — one account's unsubscribe must
            // never touch another account's contacts.
            Contact::whereIn('contact_list_id', ContactList::where('user_id', $ownerId)->select('id'))
                ->where('email', $email)
                ->update(['status' => 'unsubscribed']);
        }

        return view('tracking.unsubscribed', ['email' => $email, 'failed' => false]);
    }

    private function isSuppressed(CampaignLog $log): bool
    {
        $ownerId = $log->campaign?->user_id;

        if ($ownerId === null) {
            return false;
        }

        return SuppressionList::query()
            ->appliesTo($ownerId)
            ->where('email', strtolower(trim((string) $log->recipient_email)))
            ->exists();
    }
}
