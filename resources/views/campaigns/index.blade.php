@extends('layouts.app')

@section('content')
<div class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="rocket" class="w-6 h-6 text-brand-400"></i> Email Campaigns
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Create, schedule, and monitor email campaigns with custom delay throttling and real-time open/click tracking.
            </p>
        </div>
        @permission('campaigns.create')
            <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-xl shadow-brand-600/30 transition transform hover:-translate-y-0.5">
                <i data-lucide="plus" class="w-4 h-4"></i> Create Campaign
            </a>
        @endpermission
    </div>

    @if($campaigns->isEmpty())
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-xl">
            <div class="w-16 h-16 rounded-3xl bg-brand-500/10 text-brand-400 flex items-center justify-center mx-auto mb-4 border border-brand-500/20">
                <i data-lucide="send" class="w-8 h-8"></i>
            </div>
            <h3 class="text-base font-bold text-white">No Campaigns Created</h3>
            <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-md mx-auto">
                Launch your first scheduled campaign with custom anti-spam delays and open tracking.
            </p>
            @permission('campaigns.create')
                <a href="{{ route('campaigns.create') }}" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-lg transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> Create First Campaign
                </a>
            @endpermission
        </div>
    @else
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-950/60 text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 rounded-l-xl">Campaign</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Progress</th>
                            <th class="py-3.5 px-4">Delivery</th>
                            <th class="py-3.5 px-4">Opens & Clicks</th>
                            <th class="py-3.5 px-4">Created Date</th>
                            <th class="py-3.5 px-4 rounded-r-xl text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($campaigns as $c)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-4 px-4 font-semibold text-white">
                                    <a href="{{ route('campaigns.show', $c->id) }}" class="hover:text-brand-400 transition flex items-center gap-2">
                                        <i data-lucide="mail" class="w-4 h-4 text-brand-400"></i>
                                        <span>{{ $c->name }}</span>
                                    </a>
                                    <span class="text-xs text-slate-400 font-normal block truncate max-w-xs">{{ $c->subject }}</span>
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
                                        <div class="bg-gradient-to-r from-brand-500 to-emerald-400 h-2 rounded-full" style="width: {{ $c->progress_percent }}%"></div>
                                    </div>
                                    <span class="text-[11px] text-slate-400 mt-1 block font-mono">{{ $c->progress_percent }}% ({{ $c->sent_count }}/{{ $c->total_recipients }})</span>
                                </td>
                                <td class="py-4 px-4 font-mono text-xs">
                                    <span class="text-emerald-400 font-bold">{{ number_format($c->sent_count) }}</span> sent / <span class="text-rose-400">{{ number_format($c->failed_count) }}</span> failed
                                </td>
                                <td class="py-4 px-4 font-mono text-xs">
                                    <div class="text-emerald-400 font-bold whitespace-nowrap">{{ $c->open_rate }}% <span class="text-slate-400 font-normal">({{ $c->opened_count }} opens)</span></div>
                                    <div class="text-indigo-400 font-bold whitespace-nowrap">{{ $c->click_rate }}% <span class="text-slate-400 font-normal">({{ $c->clicked_count }} clicks)</span></div>
                                </td>
                                <td class="py-4 px-4 text-xs text-slate-400 font-mono">
                                    {{ $c->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('campaigns.show', $c->id) }}" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 transition" title="View Monitor">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        @permission('campaigns.delete')
                                        <form method="POST" action="{{ route('campaigns.destroy', $c->id) }}" onsubmit="return confirm('Delete this campaign and all delivery logs?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition" title="Delete">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                {{ $campaigns->links() }}
            </div>
        </div>
    @endif

</div>
@endsection