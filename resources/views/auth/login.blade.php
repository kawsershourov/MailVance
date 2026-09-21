<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - {{ ($siteSettings ?? null)?->displayTitle() ?? 'MailVance' }}</title>
    <link rel="icon" href="{{ ($siteSettings ?? null)?->faviconUrl() ?? asset('img/default-favicon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if(($siteSettings ?? null)?->brand_color)
        <style>
            :root {
                @foreach($siteSettings->brandPaletteRgb() as $shade => $rgb)
                --brand-{{ $shade }}: {{ $rgb }};
                @endforeach
            }
        </style>
    @endif
</head>
<body class="h-full flex items-center justify-center p-4 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(99,102,241,0.25),rgba(255,255,255,0))]">

    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                @include('partials.site-logo', ['size' => 'lg'])
                <div class="text-left leading-tight">
                    <div class="text-2xl font-extrabold tracking-tight text-white">{{ ($siteSettings ?? null)?->displayTitle() ?? 'MailVance' }}</div>
                    <div class="text-[11px] font-semibold text-brand-400 mt-0.5">Powered by Webvance IT</div>
                </div>
            </div>
            <h2 class="text-3xl font-extrabold text-white tracking-tight">Welcome back</h2>
            <p class="text-sm text-slate-400 mt-2">Sign in to manage your high-deliverability email campaigns</p>
        </div>

        <!-- Card -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
            @if(session('info'))
                <div class="mb-6 p-4 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-300 text-sm font-medium">
                    {{ session('info') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Email Address</label>
                    <div class="relative">
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition text-sm"
                               placeholder="you@company.com">
                    </div>
                    @error('email')
                        <p class="mt-2 text-xs text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Password</label>
                    </div>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition text-sm"
                               placeholder="••••••••">
                    </div>
                    @error('password')
                        <p class="mt-2 text-xs text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-400 hover:text-slate-300">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-700 bg-slate-950 text-brand-600 focus:ring-brand-500">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-4 px-6 bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm rounded-2xl shadow-lg shadow-brand-600/30 transition duration-150 transform hover:-translate-y-0.5">
                    Sign In to Dashboard
                </button>
            </form>

            @if (config('mailflow.allow_registration'))
                <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
                    <p class="text-sm text-slate-400">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="font-semibold text-brand-400 hover:text-brand-300 underline underline-offset-4">Create account</a>
                    </p>
                </div>
            @else
                <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
                    <p class="text-sm text-slate-500">
                        Accounts are created by an administrator.
                    </p>
                </div>
            @endif
        </div>
    </div>

    <script>document.addEventListener("DOMContentLoaded", () => lucide.createIcons());</script>
</body>
</html>