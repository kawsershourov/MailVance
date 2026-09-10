@extends('layouts.app')

@section('content')
<div class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="layout-template" class="w-6 h-6 text-brand-400"></i> Custom Email Templates
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Design responsive HTML emails with merge tags and preview live on desktop & mobile devices.
            </p>
        </div>
        @permission('templates.create')
            <a href="{{ route('templates.create') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-xl shadow-brand-600/30 transition transform hover:-translate-y-0.5">
                <i data-lucide="plus" class="w-4 h-4"></i> Create New Template
            </a>
        @endpermission
    </div>

    <!-- Templates Gallery -->
    @if($templates->isEmpty())
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-xl">
            <div class="w-16 h-16 rounded-3xl bg-brand-500/10 text-brand-400 flex items-center justify-center mx-auto mb-4 border border-brand-500/20">
                <i data-lucide="layout-template" class="w-8 h-8"></i>
            </div>
            <h3 class="text-base font-bold text-white">No Templates Created Yet</h3>
            <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-md mx-auto">
                Create reusable email layouts or choose from pre-built starter designs with anti-spam optimization.
            </p>
            @permission('templates.create')
                <a href="{{ route('templates.create') }}" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-lg transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> Create First Template
                </a>
            @endpermission
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($templates as $t)
                <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl flex flex-col justify-between group hover:border-brand-500/40 transition">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-brand-400">
                                <i data-lucide="mail" class="w-5 h-5"></i>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold font-mono {{ $t->spam_score > 50 ? 'bg-rose-500/20 text-rose-300' : 'bg-emerald-500/20 text-emerald-300' }}">
                                {{ $t->spam_score > 50 ? 'Spam Risk: ' . $t->spam_score : 'Clean Deliverability' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-white tracking-tight">{{ $t->name }}</h3>
                        <p class="text-xs text-slate-400 mt-1 font-mono">Subject: {{ $t->subject }}</p>

                        <div class="mt-4 p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs text-slate-400 line-clamp-3 font-mono">
                            {{ Str::limit(trim(preg_replace('/\s+/', ' ', $t->body_text ?: strip_tags($t->body_html))), 140) }}
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between gap-2">
                        @permission('templates.update')
                            <a href="{{ route('templates.edit', $t->id) }}" class="flex-1 py-2 px-3 bg-brand-600/10 hover:bg-brand-600/20 text-brand-400 text-xs font-semibold text-center rounded-xl border border-brand-500/20 transition flex items-center justify-center gap-1.5">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit Template
                            </a>
                        @endpermission
                        @permission('templates.delete')
                            <form method="POST" action="{{ route('templates.destroy', $t->id) }}" onsubmit="return confirm('Delete this template?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        @endpermission
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection