@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="campaignLiveMonitor({{ $campaign->id }})">

    <!-- Header & Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('campaigns.index') }}" class="hover:text-brand-400">Campaigns</a>
                <span>/</span>
                <span class="text-white">{{ $campaign->name }}</span>
            </div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="mail" class="w-6 h-6 text-brand-400"></i> {{ $campaign->name }}
            </h2>
            <p class="text-xs text-slate-400 font-mono mt-1">Subject: {{ $campaign->subject }}</p>
        </div>

        <!-- Campaign Action Buttons (Pause / Resume / Cancel / Launch) -->
        <div class="flex flex-wrap items-center gap-2">
            @permission('campaigns.launch')
            @if($campaign->status === 'draft')
                <form method="POST" action="{{ route('campaigns.launch', $campaign->id) }}">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-1.5">
                        <i data-lucide="play" class="w-4 h-4"></i> Launch Campaign Now
                    </button>
                </form>
            @elseif($campaign->status === 'processing')
                <form method="POST" action="{{ route('campaigns.pause', $campaign->id) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-1.5">
                        <i data-lucide="pause" class="w-4 h-4"></i> Pause Campaign
                    </button>
                </form>
            @elseif($campaign->status === 'paused')
                <form method="POST" action="{{ route('campaigns.resume', $campaign->id) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-1.5">
                        <i data-lucide="play" class="w-4 h-4"></i> Resume Campaign
                    </button>
                </form>
            @endif

            @if(in_array($campaign->status, ['processing', 'paused']))
                <form method="POST" action="{{ route('campaigns.cancel', $campaign->id) }}" onsubmit="return confirm('Cancel this campaign?')">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-bold rounded-xl border border-rose-500/20 transition flex items-center gap-1.5">
                        <i data-lucide="stop-circle" class="w-4 h-4"></i> Cancel
                    </button>
                </form>
            @endif
            @endpermission
        </div>
    </div>

    <!-- Live Progress Banner -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-bold">Execution Progress:</span>
                <h3 class="text-2xl font-black text-white font-mono mt-0.5" x-text="progress + '%'"></h3>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs px-3 py-1 rounded-full font-bold font-mono"
                      :class="status === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : (status === 'processing' ? 'bg-indigo-500/20 text-indigo-300 animate-pulse' : 'bg-amber-500/20 text-amber-300')"
                      x-text="status.toUpperCase()"></span>
                <span class="text-xs text-slate-400 font-mono" x-text="sentCount + ' / ' + totalRecipients + ' Dispatched'"></span>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-slate-950 rounded-full h-3.5 p-0.5 border border-slate-800 overflow-hidden">
            <div class="bg-gradient-to-r from-brand-500 via-indigo-500 to-emerald-400 h-2.5 rounded-full transition-all duration-500" :style="'width: ' + progress + '%'"></div>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-400 font-mono">
            <span>Delay: <strong>{{ $campaign->delay_seconds }}s</strong> {{ $campaign->jitter_enabled ? "(+ Jitter)" : "" }}</span>
            <span>&bull;</span>
            <span>Relay: <strong>{{ $campaign->smtpConfig->title ?? "None" }}</strong></span>
            <span>&bull;</span>
            <span>Audience: <strong>{{ $campaign->contactList->name ?? "List" }}</strong></span>
        </div>
    </div>

    <!-- 4 Stats Cards (Sent, Failed, Opened, Clicked) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">
            <span class="text-xs text-slate-400 uppercase font-bold">Successfully Sent</span>
            <p class="text-2xl font-black text-emerald-400 font-mono mt-1" x-text="sentCount"></p>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">
            <span class="text-xs text-slate-400 uppercase font-bold">Failed / Bounced</span>
            <p class="text-2xl font-black text-rose-400 font-mono mt-1" x-text="failedCount"></p>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">
            <span class="text-xs text-slate-400 uppercase font-bold">Emails Opened</span>
            <p class="text-2xl font-black text-brand-400 font-mono mt-1">
                <span x-text="openedCount"></span>
                <span class="text-xs font-normal text-slate-400" x-text="'(' + openRate + '%)'"></span>
            </p>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">
            <span class="text-xs text-slate-400 uppercase font-bold">Links Clicked</span>
            <p class="text-2xl font-black text-indigo-400 font-mono mt-1">
                <span x-text="clickedCount"></span>
                <span class="text-xs font-normal text-slate-400" x-text="'(' + clickRate + '%)'"></span>
            </p>
        </div>
    </div>

    <!-- Recipient Delivery Logs Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-base font-bold text-white">Recipient Delivery & Engagement Log</h3>
                <p class="text-xs text-slate-400">Detailed per-subscriber status, 1x1 open pixel events, and error messages.</p>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="{{ route('campaigns.show', $campaign->id) }}" class="flex flex-col sm:flex-row sm:items-center gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search email..."
                       class="w-full sm:w-auto sm:flex-1 px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs">
                <select name="status" class="w-full sm:w-auto px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs">
                    <option value="">All Statuses</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <button type="submit" class="w-full sm:w-auto px-3 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl">Filter</button>
            </form>
        </div>

        @if($logs->isEmpty())
            <div class="text-center py-8 text-slate-400 text-xs">No delivery logs found.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-xs text-slate-300">
                    <thead class="text-[11px] uppercase bg-slate-950/60 text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3 px-3 rounded-l-xl">Recipient</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Open Tracking</th>
                            <th class="py-3 px-3">Click Tracking</th>
                            <th class="py-3 px-3">Dispatched Time</th>
                            <th class="py-3 px-3 rounded-r-xl">Diagnostic / Errors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($logs as $l)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-3 px-3 font-mono text-white font-semibold">
                                    {{ $l->recipient_email }}
                                    <span class="block text-[10px] text-slate-400 font-sans">{{ $l->recipient_name }}</span>
                                </td>
                                <td class="py-3 px-3">
                                    @if($l->status === 'sent')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Sent</span>
                                    @elseif($l->status === 'failed')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300">Failed</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400">Pending</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    @if($l->is_opened)
                                        <span class="text-emerald-400 font-semibold flex items-center gap-1 font-mono">
                                            <i data-lucide="check" class="w-3 h-3"></i> Opened ({{ $l->opened_at->format('H:i') }})
                                        </span>
                                    @else
                                        <span class="text-slate-500 font-mono">Unopened</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    @if($l->is_clicked)
                                        <span class="text-indigo-400 font-semibold flex items-center gap-1 font-mono">
                                            <i data-lucide="check" class="w-3 h-3"></i> Clicked
                                        </span>
                                    @else
                                        <span class="text-slate-500 font-mono">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-slate-400 font-mono">
                                    {{ $l->sent_at ? $l->sent_at->format('H:i:s') : '-' }}
                                </td>
                                <td class="py-3 px-3 text-rose-400 font-mono text-[11px]">
                                    <div class="truncate max-w-xs">{{ $l->error_message ?: "-" }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
    function campaignLiveMonitor(campaignId) {
        return {
            id: campaignId,
            status: "{{ $campaign->status }}",
            progress: {{ $campaign->progress_percent }},
            sentCount: {{ $campaign->sent_count }},
            failedCount: {{ $campaign->failed_count }},
            openedCount: {{ $campaign->opened_count }},
            clickedCount: {{ $campaign->clicked_count }},
            totalRecipients: {{ $campaign->total_recipients }},
            openRate: {{ $campaign->open_rate }},
            clickRate: {{ $campaign->click_rate }},

            init() {
                if (this.status === "processing") {
                    this.startPolling();
                }
            },
            startPolling() {
                const interval = setInterval(async () => {
                    if (this.status !== "processing") {
                        clearInterval(interval);
                        return;
                    }
                    try {
                        const res = await fetch(`/campaigns/${this.id}/status-json`);
                        const data = await res.json();
                        this.status = data.status;
                        this.progress = data.progress_percent;
                        this.sentCount = data.sent_count;
                        this.failedCount = data.failed_count;
                        this.openedCount = data.opened_count;
                        this.clickedCount = data.clicked_count;
                        this.openRate = data.open_rate;
                        this.clickRate = data.click_rate;

                        if (this.status === "completed") {
                            clearInterval(interval);
                        }
                    } catch (e) {
                        console.error("Polling error", e);
                    }
                }, 2500);
            }
        }
    }
</script>
@endpush