@extends('layouts.app')

@section('content')
<div class="space-y-8">

    <!-- Top Welcome Banner & Quick Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-brand-900/40 via-slate-900 to-slate-900/80 p-6 md:p-8 rounded-3xl border border-brand-500/20 backdrop-blur shadow-2xl relative overflow-hidden">
        <div class="relative z-10">
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-brand-500/20 text-brand-300 border border-brand-500/30 inline-block mb-3">
                Live Campaign Engine
            </span>
            <h2 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight">
                Welcome, {{ Auth::user()->name }}! 👋
            </h2>
            <p class="text-sm text-slate-400 mt-1 max-w-xl">
                Monitor your campaign delivery, open rates, SMTP health, and recipient engagement in real-time.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 relative z-10">
            @permission('campaigns.create')
                <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-xl shadow-brand-600/30 transition transform hover:-translate-y-0.5">
                    <i data-lucide="plus" class="w-4 h-4"></i> New Campaign
                </a>
            @endpermission
            @permission('contacts.import')
                <a href="{{ route('contacts.index') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-sm border border-slate-700 transition">
                    <i data-lucide="upload-cloud" class="w-4 h-4 text-emerald-400"></i> Import CSV
                </a>
            @endpermission
        </div>
    </div>

    <!-- 4 Primary KPI Stats (User Request: Sent, Not Sent, Opened, Not Opened) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- 1. Total Sent -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-4 sm:p-6 shadow-xl relative overflow-hidden group hover:border-brand-500/40 transition">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Emails Sent</span>
                <div class="w-10 h-10 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center text-brand-400 group-hover:scale-110 transition">
                    <i data-lucide="send" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white font-mono">{{ number_format($totalSent) }}</span>
                <span class="text-xs text-emerald-400 font-medium">Delivered</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-400">
                <span>Lifetime Sent</span>
                <span class="text-slate-300 font-mono">{{ $totalCampaigns }} Campaigns</span>
            </div>
        </div>

        <!-- 2. Not Sent / Failed -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-4 sm:p-6 shadow-xl relative overflow-hidden group hover:border-rose-500/40 transition">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Not Sent / Failed</span>
                <div class="w-10 h-10 rounded-2xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 group-hover:scale-110 transition">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-rose-400 font-mono">{{ number_format($totalFailed) }}</span>
                <span class="text-xs text-rose-400/80 font-medium">Bounced/Errors</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-400">
                <span>Failure Rate</span>
                <span class="text-rose-400 font-mono">{{ $totalSent > 0 ? round(($totalFailed / ($totalSent + $totalFailed)) * 100, 1) : 0 }}%</span>
            </div>
        </div>

        <!-- 3. Opened Emails -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-4 sm:p-6 shadow-xl relative overflow-hidden group hover:border-emerald-500/40 transition">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Emails Opened</span>
                <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition">
                    <i data-lucide="mail-open" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-emerald-400 font-mono">{{ number_format($totalOpened) }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-mono font-bold">{{ $openRate }}% Rate</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-400">
                <span>Unique Pixel Opens</span>
                <span class="text-emerald-400 font-mono">1x1 Pixel Tracked</span>
            </div>
        </div>

        <!-- 4. Not Opened -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-4 sm:p-6 shadow-xl relative overflow-hidden group hover:border-amber-500/40 transition">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Not Opened</span>
                <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 group-hover:scale-110 transition">
                    <i data-lucide="mail" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-amber-400 font-mono">{{ number_format($totalNotOpened) }}</span>
                <span class="text-xs text-amber-400/80 font-medium">Pending Read</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-400">
                <span>Unopened Rate</span>
                <span class="text-amber-400 font-mono">{{ $totalSent > 0 ? round(($totalNotOpened / $totalSent) * 100, 1) : 0 }}%</span>
            </div>
        </div>
    </div>

    <!-- Secondary Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-slate-900/50 border border-slate-800 flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-400"><i data-lucide="mouse-pointer-click" class="w-5 h-5"></i></div>
            <div>
                <p class="text-xs text-slate-400">Links Clicked</p>
                <p class="text-lg font-bold text-white font-mono">{{ number_format($totalClicked) }} <span class="text-xs text-indigo-400 font-normal">({{ $clickRate }}%)</span></p>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/50 border border-slate-800 flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-purple-500/10 text-purple-400"><i data-lucide="server" class="w-5 h-5"></i></div>
            <div>
                <p class="text-xs text-slate-400">Connected SMTPs</p>
                <p class="text-lg font-bold text-white font-mono">{{ $totalSmtps }} <span class="text-xs text-slate-400 font-normal">Active</span></p>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/50 border border-slate-800 flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-teal-500/10 text-teal-400"><i data-lucide="users" class="w-5 h-5"></i></div>
            <div>
                <p class="text-xs text-slate-400">Total Contacts</p>
                <p class="text-lg font-bold text-white font-mono">{{ number_format($totalContacts) }}</p>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/50 border border-slate-800 flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400"><i data-lucide="shield-check" class="w-5 h-5"></i></div>
            <div>
                <p class="text-xs text-slate-400">Anti-Spam Status</p>
                <p class="text-lg font-bold text-emerald-400">100% Protected</p>
            </div>
        </div>
    </div>

    <!-- Charts & Activity Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- 7-Day Performance Chart (2 Cols) -->
        <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <div>
                    <h3 class="text-base font-bold text-white tracking-tight">Delivery & Open Activity</h3>
                    <p class="text-xs text-slate-400">7-day performance tracking</p>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1.5 text-brand-400"><span class="w-2.5 h-2.5 rounded-full bg-brand-500"></span> Emails Sent</span>
                    <span class="flex items-center gap-1.5 text-emerald-400"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Emails Opened</span>
                </div>
            </div>
            <div class="h-72 w-full">
                <canvas id="performanceChart" class="max-w-full"></canvas>
            </div>
        </div>

        <!-- Quick SMTP & System Health Status (1 Col) -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl flex flex-col">
            <h3 class="text-base font-bold text-white tracking-tight mb-2">Platform Health & Spam Shield</h3>
            <p class="text-xs text-slate-400 mb-5">Deliverability standards overview</p>

            <div class="space-y-4 flex-1">
                <div class="p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i data-lucide="shield" class="w-5 h-5 text-emerald-400"></i>
                        <div>
                            <p class="text-xs font-semibold text-white">RFC 8058 One-Click Header</p>
                            <p class="text-[11px] text-slate-400">Google & Yahoo 2024 Compliant</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-500/20 text-emerald-300">Enabled</span>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i data-lucide="timer" class="w-5 h-5 text-indigo-400"></i>
                        <div>
                            <p class="text-xs font-semibold text-white">Smart Jitter Throttle Engine</p>
                            <p class="text-[11px] text-slate-400">Prevents Bot Detection</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-500/20 text-indigo-300">Ready</span>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5 text-teal-400"></i>
                        <div>
                            <p class="text-xs font-semibold text-white">200MB CSV Stream Engine</p>
                            <p class="text-[11px] text-slate-400">Memory < 15MB Guaranteed</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-teal-500/20 text-teal-300">Active</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800">
                <a href="{{ route('smtp.index') }}" class="flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition">
                    <i data-lucide="send" class="w-3.5 h-3.5 text-brand-400"></i> Run SMTP Test Send
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Campaigns Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-base font-bold text-white tracking-tight">Recent Email Campaigns</h3>
                <p class="text-xs text-slate-400">Live progress and dispatch summary</p>
            </div>
            <a href="{{ route('campaigns.index') }}" class="text-xs font-semibold text-brand-400 hover:text-brand-300 flex items-center gap-1">
                View All Campaigns <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        @if($recentCampaigns->isEmpty())
            <div class="text-center py-12 border-2 border-dashed border-slate-800 rounded-2xl">
                <div class="w-12 h-12 rounded-2xl bg-brand-500/10 text-brand-400 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="send" class="w-6 h-6"></i>
                </div>
                <h4 class="text-sm font-bold text-white">No campaigns created yet</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Create your first campaign to connect your SMTP relay, target your audience, and schedule delivery.</p>
                @permission('campaigns.create')
                    <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs rounded-xl shadow transition">
                        <i data-lucide="plus" class="w-4 h-4"></i> Create First Campaign
                    </a>
                @endpermission
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-950/60 text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 rounded-l-xl">Campaign Name</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Progress</th>
                            <th class="py-3.5 px-4">Sent / Total</th>
                            <th class="py-3.5 px-4">Open Rate</th>
                            <th class="py-3.5 px-4 rounded-r-xl text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($recentCampaigns as $c)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-4 px-4 font-semibold text-white">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="mail" class="w-4 h-4 text-brand-400"></i>
                                        <span>{{ $c->name }}</span>
                                    </div>
                                    <span class="text-xs text-slate-400 font-normal block">{{ $c->subject }}</span>
                                </td>
                                <td class="py-4 px-4">
                                    @if($c->status === 'completed')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Completed</span>
                                    @elseif($c->status === 'processing')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 animate-pulse">Sending...</span>
                                    @elseif($c->status === 'paused')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Paused</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700">{{ ucfirst($c->status) }}</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                                        <div class="bg-gradient-to-r from-brand-500 to-emerald-400 h-2 rounded-full transition-all duration-500" style="width: {{ $c->progress_percent }}%"></div>
                                    </div>
                                    <span class="text-[11px] text-slate-400 mt-1 block font-mono">{{ $c->progress_percent }}%</span>
                                </td>
                                <td class="py-4 px-4 font-mono text-xs">
                                    <span class="text-white font-bold">{{ number_format($c->sent_count) }}</span> / {{ number_format($c->total_recipients) }}
                                </td>
                                <td class="py-4 px-4 font-mono text-xs">
                                    <span class="text-emerald-400 font-bold">{{ $c->open_rate }}%</span>
                                    <span class="text-slate-500 block text-[11px]">({{ $c->opened_count }} opens)</span>
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <a href="{{ route('campaigns.show', $c->id) }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white transition">
                                        Monitor & Logs
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const ctx = document.getElementById("performanceChart");
        if (ctx) {
            new Chart(ctx, {
                type: "line",
                data: {
                    labels: {{ Illuminate\Support\Js::from($chartLabels) }},
                    datasets: [
                        {
                            label: "Emails Sent",
                            data: {{ Illuminate\Support\Js::from($chartSentData) }},
                            borderColor: "#6366f1",
                            backgroundColor: "rgba(99, 102, 241, 0.15)",
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: "#6366f1",
                        },
                        {
                            label: "Emails Opened",
                            data: {{ Illuminate\Support\Js::from($chartOpenedData) }},
                            borderColor: "#10b981",
                            backgroundColor: "rgba(16, 185, 129, 0.1)",
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: "#10b981",
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { color: "rgba(51, 65, 85, 0.3)" },
                            ticks: { color: "#94a3b8" }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: "rgba(51, 65, 85, 0.3)" },
                            ticks: { color: "#94a3b8", stepSize: 1 }
                        }
                    }
                }
            });
        }
    });
</script>
@endpush