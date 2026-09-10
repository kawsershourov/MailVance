@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="campaignWizard()">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('campaigns.index') }}" class="hover:text-brand-400">Campaigns</a>
                <span>/</span>
                <span class="text-white">Create New Campaign</span>
            </div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="rocket" class="w-6 h-6 text-brand-400"></i> New Campaign Wizard
            </h2>
        </div>
    </div>

    <!-- Wizard Steps Indicator -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl">
        <div class="flex items-center justify-between max-w-3xl mx-auto">
            
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 flex-shrink-0 rounded-xl flex items-center justify-center font-bold text-xs"
                     :class="step >= 1 ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30' : 'bg-slate-800 text-slate-400'">1</div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 1 ? 'text-white' : 'text-slate-500'">Details</span>
            </div>
            <div class="h-0.5 w-6 sm:w-12 flex-shrink-0 bg-slate-800" :class="step >= 2 ? 'bg-brand-600' : 'bg-slate-800'"></div>

            <div class="flex items-center gap-2">
                <div class="w-8 h-8 flex-shrink-0 rounded-xl flex items-center justify-center font-bold text-xs"
                     :class="step >= 2 ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30' : 'bg-slate-800 text-slate-400'">2</div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 2 ? 'text-white' : 'text-slate-500'">SMTP & Audience</span>
            </div>
            <div class="h-0.5 w-6 sm:w-12 flex-shrink-0 bg-slate-800" :class="step >= 3 ? 'bg-brand-600' : 'bg-slate-800'"></div>

            <div class="flex items-center gap-2">
                <div class="w-8 h-8 flex-shrink-0 rounded-xl flex items-center justify-center font-bold text-xs"
                     :class="step >= 3 ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30' : 'bg-slate-800 text-slate-400'">3</div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 3 ? 'text-white' : 'text-slate-500'">Template</span>
            </div>
            <div class="h-0.5 w-6 sm:w-12 flex-shrink-0 bg-slate-800" :class="step >= 4 ? 'bg-brand-600' : 'bg-slate-800'"></div>

            <div class="flex items-center gap-2">
                <div class="w-8 h-8 flex-shrink-0 rounded-xl flex items-center justify-center font-bold text-xs"
                     :class="step >= 4 ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30' : 'bg-slate-800 text-slate-400'">4</div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 4 ? 'text-white' : 'text-slate-500'">Delay & Schedule</span>
            </div>

        </div>
    </div>

    <!-- Main Wizard Form -->
    <form id="campaignForm" method="POST" action="{{ route('campaigns.store') }}" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        @csrf

        <!-- STEP 1: Campaign Name & Subject -->
        <div x-show="step === 1" class="space-y-5">
            <h3 class="text-lg font-bold text-white mb-2">Step 1: Campaign Details</h3>
            <p class="text-xs text-slate-400 mb-6">Enter the internal campaign name, sender information, and email subject line.</p>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Campaign Name (Internal Reference)</label>
                <input type="text" name="name" x-model="formData.name" required placeholder="e.g. September Product Update, Q3 Promo"
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Email Subject Line</label>
                <input type="text" name="subject" x-model="formData.subject" required placeholder="e.g. Special news for @{{first_name}}"
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Sender Name (From Name)</label>
                    <input type="text" name="sender_name" x-model="formData.sender_name" placeholder="e.g. Kawser Deals"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">From Email Address</label>
                    <input type="email" name="sender_email" x-model="formData.sender_email" placeholder="e.g. newsletter@yourdomain.com"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Reply-To Email (Optional)</label>
                <input type="email" name="reply_to" x-model="formData.reply_to" placeholder="support@yourdomain.com"
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div class="pt-6 flex justify-end">
                <button type="button" @click="if(formData.name && formData.subject) step = 2; else alert('Please enter Campaign Name and Subject.')"
                        class="px-6 py-3 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                    <span>Next: Select SMTP & Audience</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: SMTP & Audience Selection -->
        <div x-show="step === 2" x-cloak class="space-y-6">
            <h3 class="text-lg font-bold text-white mb-2">Step 2: SMTP Relay & Target Audience</h3>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Select SMTP Relay to Dispatch Emails Through</label>
                @if($smtps->isEmpty())
                    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
                        ⚠️ No SMTP relays connected yet. Please <a href="{{ route('smtp.index') }}" target="_blank" class="underline font-bold">connect an SMTP relay</a> first.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($smtps as $s)
                            <label class="p-4 rounded-2xl bg-slate-950 border border-slate-800 cursor-pointer flex items-center gap-3 transition"
                                   :class="formData.smtp_config_id == {{ $s->id }} ? 'border-brand-500 bg-brand-950/20' : 'hover:border-slate-700'">
                                <input type="radio" name="smtp_config_id" value="{{ $s->id }}" x-model="formData.smtp_config_id" class="text-brand-600">
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $s->title }}</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ $s->host }}:{{ $s->port }} ({{ strtoupper($s->encryption) }})</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Select Target Contact List (Audience)</label>
                @if($lists->isEmpty())
                    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
                        ⚠️ No contact lists available. Please <a href="{{ route('contacts.index') }}" target="_blank" class="underline font-bold">import contacts or CSV</a> first.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($lists as $l)
                            <label class="p-4 rounded-2xl bg-slate-950 border border-slate-800 cursor-pointer flex items-center justify-between gap-3 transition"
                                   :class="formData.contact_list_id == {{ $l->id }} ? 'border-brand-500 bg-brand-950/20' : 'hover:border-slate-700'">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="contact_list_id" value="{{ $l->id }}" x-model="formData.contact_list_id" class="text-brand-600">
                                    <div>
                                        <p class="text-sm font-bold text-white">{{ $l->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $l->description ?: "Active list" }}</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-slate-900 text-emerald-400 border border-slate-800">
                                    {{ $l->contacts_count }} Contacts
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-6 flex justify-between">
                <button type="button" @click="step = 1" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl">Back</button>
                <button type="button" @click="if(formData.smtp_config_id && formData.contact_list_id) step = 3; else alert('Please select an SMTP server and a Contact List.')"
                        class="px-6 py-3 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                    <span>Next: Select Template</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- STEP 3: Template Selection -->
        <div x-show="step === 3" x-cloak class="space-y-6">
            <h3 class="text-lg font-bold text-white mb-2">Step 3: Select Email Template</h3>

            @if($templates->isEmpty())
                <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
                    ⚠️ No email templates created yet. Please <a href="{{ route('templates.create') }}" target="_blank" class="underline font-bold">create a template</a> first.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($templates as $t)
                        <label class="p-5 rounded-2xl bg-slate-950 border border-slate-800 cursor-pointer flex flex-col justify-between transition"
                               :class="formData.email_template_id == {{ $t->id }} ? 'border-brand-500 bg-brand-950/20' : 'hover:border-slate-700'">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-bold text-white flex items-center gap-2">
                                        <input type="radio" name="email_template_id" value="{{ $t->id }}" x-model="formData.email_template_id" class="text-brand-600">
                                        {{ $t->name }}
                                    </span>
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full {{ $t->spam_score > 50 ? 'bg-rose-500/20 text-rose-300' : 'bg-emerald-500/20 text-emerald-300' }}">
                                        Spam Score: {{ $t->spam_score }}/100
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 font-mono mb-2">Subject: {{ $t->subject }}</p>
                                <div class="p-2.5 bg-slate-900 rounded-lg text-xs text-slate-500 line-clamp-2">
                                    {{ Str::limit(strip_tags($t->body_html), 100) }}
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="pt-6 flex justify-between">
                <button type="button" @click="step = 2" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl">Back</button>
                <button type="button" @click="if(formData.email_template_id) step = 4; else alert('Please select an Email Template.')"
                        class="px-6 py-3 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                    <span>Next: Anti-Spam Delays & Launch</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- STEP 4: Delay, Jitter & Throttle Settings (User Request: one email send then how many time wait) -->
        <div x-show="step === 4" x-cloak class="space-y-6">
            <h3 class="text-lg font-bold text-white mb-1">Step 4: Sending Speed, Anti-Spam Delays & Launch</h3>
            <p class="text-xs text-slate-400 mb-6">Configure wait times between each email send to mimic human sending and prevent SMTP spam rate limits.</p>

            <div class="space-y-4 bg-slate-950 p-4 sm:p-6 rounded-2xl border border-slate-800">
                
                <!-- Delay Seconds (User requested wait setting) -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-300">Wait Delay Between Each Email (Seconds)</label>
                        <span class="text-sm font-bold font-mono text-brand-400" x-text="formData.delay_seconds + ' Seconds Wait'"></span>
                    </div>
                    <input type="range" name="delay_seconds" min="0" max="30" step="1" x-model="formData.delay_seconds"
                           class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-brand-500">
                    <div class="flex justify-between text-[10px] text-slate-500 mt-1">
                        <span>0s (Instant Send)</span>
                        <span class="hidden sm:inline">2s (Recommended Safe)</span>
                        <span class="hidden sm:inline">5s (Strict Warming)</span>
                        <span>30s (Slow Drip)</span>
                    </div>
                </div>

                <!-- Jitter Option -->
                <div class="pt-3 border-t border-slate-800/80">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" name="jitter_enabled" value="1" x-model="formData.jitter_enabled" class="w-4 h-4 rounded text-brand-600 bg-slate-900 border-slate-700">
                        <span><strong>Enable Randomized Jitter (&plusmn; 400ms)</strong>: Varies delay naturally so receiving mailboxes (Gmail, Yahoo) cannot detect a bot pattern.</span>
                    </label>
                </div>

                <!-- Batch Throttling -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-slate-800/80">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Batch Size (Optional)</label>
                        <input type="number" name="batch_size" x-model="formData.batch_size" placeholder="e.g. 50 (Send 50 then pause)"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Batch Pause Delay (Seconds)</label>
                        <input type="number" name="batch_delay_seconds" x-model="formData.batch_delay_seconds" placeholder="e.g. 60 (Pause 60s)"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-white text-xs">
                    </div>
                </div>

            </div>

            <!-- Launch Mode -->
            <div class="space-y-3 bg-slate-950 p-4 sm:p-6 rounded-2xl border border-slate-800">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300 block mb-2">Schedule & Launch Mode:</span>
                
                <div class="space-y-2">
                    <label class="flex items-center gap-3 cursor-pointer text-sm text-white">
                        <input type="radio" name="send_mode" value="now" x-model="sendMode" class="text-brand-600">
                        <span><strong>Launch Immediately</strong> (Queue starts sending right away)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer text-sm text-white">
                        <input type="radio" name="send_mode" value="schedule" x-model="sendMode" class="text-brand-600">
                        <span><strong>Schedule for Later</strong> (Sends automatically at the time you pick)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer text-sm text-white">
                        <input type="radio" name="send_mode" value="draft" x-model="sendMode" class="text-brand-600">
                        <span><strong>Save as Draft</strong> (Manual launch later from dashboard)</span>
                    </label>
                </div>

                <div x-show="sendMode === 'schedule'" x-cloak class="pt-3 mt-3 border-t border-slate-800">
                    <label for="scheduled_at" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Send date &amp; time
                        <span class="font-normal normal-case tracking-normal text-slate-500">({{ config('app.timezone') }})</span>
                    </label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" x-model="formData.scheduled_at"
                           :required="sendMode === 'schedule'"
                           min="{{ now()->format('Y-m-d\TH:i') }}"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <p class="mt-1.5 text-[11px] text-slate-500">
                        Requires <code class="text-slate-400">php artisan schedule:work</code> (or a cron running <code class="text-slate-400">schedule:run</code>) plus a queue worker.
                    </p>
                </div>
            </div>

            <input type="hidden" name="send_now" :value="sendMode === 'now' ? '1' : '0'">

            <div class="pt-6 flex justify-between">
                <button type="button" @click="step = 3" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl">Back</button>
                <button type="submit" class="px-8 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm rounded-xl shadow-xl shadow-emerald-600/30 transition flex items-center gap-2">
                    <i data-lucide="rocket" class="w-5 h-5"></i>
                    <span x-text="sendMode === 'now' ? 'Launch Campaign Now 🚀' : (sendMode === 'schedule' ? 'Schedule Campaign' : 'Save Campaign Draft')"></span>
                </button>
            </div>
        </div>

    </form>

</div>
@endsection

@push('scripts')
<script>
    function campaignWizard() {
        return {
            step: 1,
            sendMode: "now",
            formData: {
                name: "",
                subject: "",
                sender_name: "",
                sender_email: "",
                reply_to: "",
                smtp_config_id: "{{ $smtps->first()->id ?? '' }}",
                contact_list_id: "{{ $lists->first()->id ?? '' }}",
                email_template_id: "{{ $templates->first()->id ?? '' }}",
                delay_seconds: 2,
                jitter_enabled: true,
                batch_size: 0,
                batch_delay_seconds: 0,
                scheduled_at: "",
            }
        }
    }
</script>
@endpush