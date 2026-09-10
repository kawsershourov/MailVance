<?php

namespace App\Http\Controllers;

use App\Services\DeliverabilityScoreService;
use Illuminate\Http\Request;

class DeliverabilityController extends Controller
{
    public function index(Request $request, DeliverabilityScoreService $dnsService)
    {
        // The domain drives a live DNS lookup, so it is constrained to something
        // that can actually be a domain rather than passed through verbatim.
        $validated = $request->validate([
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/'],
        ]);

        $domain = $validated['domain'] ?? '';
        $dnsResult = null;

        if ($domain !== '') {
            $dnsResult = $dnsService->checkDomainDns($domain);
        }

        return view('deliverability.index', compact('domain', 'dnsResult'));
    }

    public function checkSpamScore(Request $request, DeliverabilityScoreService $scoreService)
    {
        // Bounded, and typed: the analyzer takes strings, and an array payload
        // used to reach it as a TypeError. The cap keeps the keyword sweep from
        // becoming a CPU sink.
        $validated = $request->validate([
            'subject' => 'nullable|string|max:2000',
            'body' => 'nullable|string|max:500000',
        ]);

        $result = $scoreService->analyzeSpamScore(
            $validated['subject'] ?? '',
            $validated['body'] ?? ''
        );

        return response()->json($result);
    }
}
