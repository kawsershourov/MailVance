@extends('layouts.app')

@section('header', 'Site Settings')

@section('content')
<div class="space-y-6 max-w-3xl">

    <div>
        <h2 class="text-2xl font-extrabold text-white tracking-tight">Site Settings</h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Branding shown across the app, including the sign-in and registration pages.</p>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-5">
            <h3 class="text-sm font-bold text-white">General</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Header Title</label>
                    <input type="text" name="site_title" value="{{ old('site_title', $settings->site_title) }}" maxlength="255" placeholder="MailVance"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    @error('site_title') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Tagline</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $settings->tagline) }}" maxlength="255" placeholder="Enterprise Email Marketing &amp; Sender"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1">Shown after the header title in the browser tab.</p>
                    @error('tagline') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Company Name</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $settings->company_name) }}" maxlength="255"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    @error('company_name') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Support Email</label>
                    <input type="email" name="support_email" value="{{ old('support_email', $settings->support_email) }}" maxlength="255"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    @error('support_email') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Brand Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="brand_color" value="{{ old('brand_color', $settings->brand_color ?? '#4f46e5') }}"
                               class="w-12 h-11 rounded-lg bg-slate-950 border border-slate-800 cursor-pointer">
                        <span class="text-xs text-slate-500">Recolors buttons, links & accents across the whole app.</span>
                    </div>
                    @error('brand_color') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-5">
            <h3 class="text-sm font-bold text-white">Logo</h3>

            <div class="flex items-center gap-4">
                @include('partials.site-logo', ['size' => 'lg', 'siteSettings' => $settings])
                <div class="flex-1 min-w-0">
                    <input type="file" name="logo" accept="image/*"
                           class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700">
                    <p class="text-[11px] text-slate-500 mt-1">PNG, JPG, GIF or WEBP. Max 2MB.</p>
                    @error('logo') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror

                    @if($settings->logo_path)
                        <label class="inline-flex items-center gap-2 mt-2 text-[11px] text-slate-400 cursor-pointer">
                            <input type="checkbox" name="remove_logo" value="1" class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-700 text-rose-500">
                            Remove current logo (revert to default)
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-5">
            <h3 class="text-sm font-bold text-white">Favicon</h3>

            <div class="flex items-center gap-4">
                <img src="{{ $settings->faviconUrl() }}" alt="Favicon" class="w-9 h-9 rounded-lg flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <input type="file" name="favicon" accept="image/png,image/svg+xml,.ico"
                           class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700">
                    <p class="text-[11px] text-slate-500 mt-1">ICO, PNG or SVG. Max 2MB.</p>
                    @error('favicon') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror

                    @if($settings->favicon_path)
                        <label class="inline-flex items-center gap-2 mt-2 text-[11px] text-slate-400 cursor-pointer">
                            <input type="checkbox" name="remove_favicon" value="1" class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-700 text-rose-500">
                            Remove current favicon (revert to default)
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg transition">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection
