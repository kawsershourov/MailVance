<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? "MailFlow" }} - Enterprise Email Marketing & Sender</title>
    
    <!-- Google Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS, Alpine.js, Lucide Icons & Chart.js (bundled by Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
@php
    $currentPageTitle = 'Dashboard';
    foreach ([
        'campaigns.*' => 'Campaigns',
        'contacts.*' => 'Contacts',
        'templates.*' => 'Templates',
        'smtp.*' => 'SMTP Relays',
        'deliverability.*' => 'Deliverability',
        'admin.users.*' => 'Users',
        'admin.roles.*' => 'Roles & Permissions',
        'profile.*' => 'My Profile',
    ] as $pattern => $label) {
        if (request()->routeIs($pattern)) { $currentPageTitle = $label; break; }
    }
@endphp
<body class="h-full antialiased font-sans flex overflow-hidden bg-slate-950 text-slate-200" x-data="{ sidebarOpen: false }">

    <!-- Sidebar for Mobile -->
    <div x-show="sidebarOpen" x-cloak @keydown.escape.window="sidebarOpen = false" class="fixed inset-0 z-50 lg:hidden flex">
        <div x-show="sidebarOpen" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="sidebarOpen = false"></div>
        <div x-show="sidebarOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="relative flex-1 flex flex-col max-w-[17rem] w-full bg-slate-900 border-r border-slate-800 p-3">
            @include('layouts.sidebar-nav')
        </div>
    </div>

    <!-- Static Sidebar for Desktop -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 bg-slate-900 border-r border-slate-800/80 p-3 flex-shrink-0">
        @include('layouts.sidebar-nav')
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        
        <!-- Top Navbar -->
        <header class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-4 sm:px-8 z-10">
            <div class="flex items-center space-x-3">
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        @yield('header', $currentPageTitle)
                    </h1>
                </div>
            </div>

            <div class="flex items-center space-x-4">

                <!-- User Dropdown / Profile -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-3 p-1.5 rounded-xl hover:bg-slate-800/70 transition border border-slate-800">
                        @if(Auth::user()?->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}"
                                 class="w-8 h-8 rounded-lg object-cover border border-slate-700 shadow-md">
                        @else
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-brand-600 to-violet-500 flex items-center justify-center text-white font-bold text-sm shadow-md">
                                {{ Auth::user()?->initials ?? "U" }}
                            </div>
                        @endif
                        <span class="text-sm font-medium text-slate-200 hidden md:block">{{ Auth::user()->name ?? "User" }}</span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </button>

                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-52 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                        <div class="px-4 py-2 border-b border-slate-800">
                            <p class="text-xs text-slate-400">Signed in as</p>
                            <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->email ?? "" }}</p>
                            <p class="text-[11px] text-brand-400 truncate mt-0.5">{{ Auth::user()?->role_names }}</p>
                        </div>

                        <a href="{{ route('profile.edit') }}" class="w-full text-left px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 flex items-center gap-2">
                            <i data-lucide="user-round" class="w-4 h-4"></i> My Profile
                        </a>

                        @permission('users.view', 'roles.view')
                            <a href="{{ Auth::user()->hasPermission('users.view') ? route('admin.users.index') : route('admin.roles.index') }}"
                               class="w-full text-left px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 flex items-center gap-2">
                                <i data-lucide="shield-check" class="w-4 h-4"></i> Administration
                            </a>
                        @endpermission

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-800 mt-2 pt-2">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 flex items-center gap-2">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-8 bg-gradient-to-b from-slate-950 to-slate-900/60">
          <div class="mx-auto w-full max-w-screen-2xl">
            <!-- Flash Toasts -->
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 flex flex-wrap items-center justify-between gap-3 shadow-lg">
                    <div class="flex items-center gap-3">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-400 flex-shrink-0"></i>
                        <span class="text-sm font-medium">{{ session('success') }}</span>
                    </div>
                    <button @click="show = false" class="text-emerald-400 hover:text-white"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 flex flex-wrap items-center justify-between gap-3 shadow-lg">
                    <div class="flex items-center gap-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 flex-shrink-0"></i>
                        <span class="text-sm font-medium">{{ session('error') }}</span>
                    </div>
                    <button @click="show = false" class="text-rose-400 hover:text-white"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            @endif

            @yield('content')
          </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>