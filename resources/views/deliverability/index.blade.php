@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="deliverabilityChecker()">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="shield-check" class="w-6 h-6 text-emerald-400"></i> Anti-Spam & Deliverability Engine
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Inspect your sender domain DNS records (SPF, DKIM, DMARC, MX) and check content spam scores to ensure 100% primary inbox delivery.
            </p>
        </div>
    </div>

    <!-- Domain DNS Health Inspector Form -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
            <i data-lucide="globe" class="w-5 h-5 text-brand-400"></i> Sender Domain DNS Health Inspector
        </h3>
        <p class="text-xs text-slate-400 mb-6">Enter your sending domain or email address (e.g. `yourdomain.com` or `news@yourdomain.com`).</p>

        <form method="GET" action="{{ route('deliverability.index') }}" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="domain" value="{{ $domain }}" placeholder="e.g. yourcompany.com" required
                   class="flex-1 px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            <button type="submit" class="px-6 py-3.5 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs sm:text-sm rounded-2xl shadow-lg transition flex items-center justify-center gap-2">
                <i data-lucide="search" class="w-4 h-4"></i> Run DNS Inspection
            </button>
        </form>

        @if($dnsResult)
            <div class="mt-8 space-y-6 pt-6 border-t border-slate-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-xs text-slate-400">Inspected Domain:</span>
                        <h4 class="text-xl font-mono font-bold text-white">{{ $dnsResult['domain'] }}</h4>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-400">DNS Health Score:</span>
                        <p class="text-2xl font-mono font-black text-emerald-400">{{ $dnsResult['overall_score'] }} / 100</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- SPF Card -->
                    <div class="p-5 rounded-2xl bg-slate-950 border {{ $dnsResult['spf']['status'] ? 'border-emerald-500/30' : 'border-rose-500/30' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-white">1. SPF (Sender Policy)</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $dnsResult['spf']['status'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                {{ $dnsResult['spf']['status'] ? 'PASSED' : 'MISSING' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">{{ $dnsResult['spf']['message'] }}</p>
                    </div>

                    <!-- DMARC Card -->
                    <div class="p-5 rounded-2xl bg-slate-950 border {{ $dnsResult['dmarc']['status'] ? 'border-emerald-500/30' : 'border-rose-500/30' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-white">2. DMARC Policy</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $dnsResult['dmarc']['status'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                {{ $dnsResult['dmarc']['status'] ? 'PASSED' : 'MISSING' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">{{ $dnsResult['dmarc']['message'] }}</p>
                    </div>

                    <!-- MX Routing Card -->
                    <div class="p-5 rounded-2xl bg-slate-950 border {{ $dnsResult['mx']['status'] ? 'border-emerald-500/30' : 'border-rose-500/30' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-white">3. MX Inbound Records</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $dnsResult['mx']['status'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                {{ $dnsResult['mx']['status'] ? 'PASSED' : 'MISSING' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">{{ $dnsResult['mx']['message'] }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Live Spam Content Risk Analyzer Tool -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
            <i data-lucide="scan-line" class="w-5 h-5 text-indigo-400"></i> Interactive Content Spam Score Analyzer
        </h3>
        <p class="text-xs text-slate-400 mb-6">Test your email subject and body content for spam trigger words before dispatching.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Subject Line</label>
                    <input type="text" x-model="subject" placeholder="e.g. Special Offer: Check out our newest products"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Email Body (HTML / Text)</label>
                    <textarea x-model="body" rows="6" placeholder="Paste your email copy here..."
                              class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"></textarea>
                </div>

                <button @click="analyzeContent()" :disabled="analyzing"
                        class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                    <i data-lucide="cpu" class="w-4 h-4"></i> Analyze Spam Score
                </button>
            </div>

            <!-- Score Results Box -->
            <div class="p-4 sm:p-6 rounded-2xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
                <div x-show="!analysisResult" class="text-center py-10 text-slate-500 text-xs">
                    Enter subject & body on the left and click "Analyze Spam Score" to see real-time deliverability insights.
                </div>

                <div x-show="analysisResult" x-cloak class="space-y-4">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div>
                            <span class="text-xs text-slate-400">Risk Assessment:</span>
                            <h4 class="text-base font-bold" :class="analysisResult?.score > 50 ? 'text-rose-400' : 'text-emerald-400'" x-text="analysisResult?.grade"></h4>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400">Spam Risk Score:</span>
                            <p class="text-2xl font-black font-mono" :class="analysisResult?.score > 50 ? 'text-rose-400' : 'text-emerald-400'" x-text="analysisResult?.score + '/100'"></p>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <span class="text-slate-400">All-Caps in Subject:</span>
                            <span :class="analysisResult?.caps_in_subject ? 'text-rose-400 font-bold' : 'text-emerald-400'" x-text="analysisResult?.caps_in_subject ? 'Warning: All-Caps Detected' : 'Clean'"></span>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <span class="text-slate-400">Excessive Exclamations:</span>
                            <span :class="analysisResult?.excessive_exclamation ? 'text-rose-400 font-bold' : 'text-emerald-400'" x-text="analysisResult?.excessive_exclamation ? 'Detected' : 'Clean'"></span>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <span class="text-slate-400">HTML-to-Text Ratio:</span>
                            <span class="text-white font-mono" x-text="analysisResult?.text_ratio + '%'"></span>
                        </div>
                    </div>

                    <template x-if="analysisResult?.matched_keywords?.length > 0">
                        <div class="mt-4 pt-4 border-t border-slate-800">
                            <p class="text-xs font-bold text-rose-400 mb-2">Flagged Spam Trigger Phrases:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="word in analysisResult.matched_keywords" :key="word">
                                    <span class="px-2 py-0.5 rounded-md bg-rose-500/20 text-rose-300 text-[11px] font-mono" x-text="word"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function deliverabilityChecker() {
        return {
            subject: "",
            body: "",
            analyzing: false,
            analysisResult: null,
            async analyzeContent() {
                if (!this.subject && !this.body) {
                    alert("Please enter subject or body text to analyze.");
                    return;
                }
                this.analyzing = true;
                try {
                    const response = await fetch("{{ route('deliverability.check-spam-score') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                            "Accept": "application/json"
                        },
                        body: JSON.stringify({ subject: this.subject, body: this.body })
                    });
                    this.analysisResult = await response.json();
                } catch (e) {
                    alert("Error analyzing content: " + e.message);
                } finally {
                    this.analyzing = false;
                }
            }
        }
    }
</script>
@endpush