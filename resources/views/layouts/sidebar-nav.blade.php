@php
    $user = Auth::user();

    $navSections = [
        'Overview' => [
            ['route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'permission' => null],
        ],
        'Sending' => [
            ['route' => 'campaigns.index', 'pattern' => 'campaigns.*', 'icon' => 'send', 'label' => 'Campaigns', 'permission' => 'campaigns.view'],
            ['route' => 'contacts.index', 'pattern' => 'contacts.*', 'icon' => 'users', 'label' => 'Contacts', 'permission' => 'contacts.view'],
            ['route' => 'templates.index', 'pattern' => 'templates.*', 'icon' => 'layout-template', 'label' => 'Templates', 'permission' => 'templates.view'],
        ],
        'Configuration' => [
            ['route' => 'smtp.index', 'pattern' => 'smtp.*', 'icon' => 'server', 'label' => 'SMTP Relays', 'permission' => 'smtp.view'],
            ['route' => 'deliverability.index', 'pattern' => 'deliverability.*', 'icon' => 'shield-check', 'label' => 'Deliverability', 'permission' => 'deliverability.view'],
        ],
        'Administration' => [
            ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'icon' => 'users-round', 'label' => 'Users', 'permission' => 'users.view'],
            ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'icon' => 'key-round', 'label' => 'Roles & Permissions', 'permission' => 'roles.view'],
        ],
        'Account' => [
            ['route' => 'profile.edit', 'pattern' => 'profile.*', 'icon' => 'user-round', 'label' => 'My Profile', 'permission' => null],
        ],
    ];

    // Hide whatever this user cannot reach, then drop sections left empty.
    $navSections = array_filter(array_map(
        fn ($items) => array_values(array_filter(
            $items,
            fn ($item) => $item['permission'] === null || ($user && $user->hasPermission($item['permission']))
        )),
        $navSections
    ));

    // The only badge in the nav reports real state: campaigns sending right now.
    $sendingNow = ($user && $user->hasPermission('campaigns.view'))
        ? $user->campaigns()->where('status', 'processing')->count()
        : 0;
@endphp

<div class="flex items-center justify-between gap-3 px-1 mb-6">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
        <span class="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center text-white flex-shrink-0">
            <i data-lucide="send" class="w-[18px] h-[18px]"></i>
        </span>
        <span class="text-[15px] font-bold tracking-tight text-white truncate">MailFlow</span>
    </a>

    <button @click="sidebarOpen = false"
            class="lg:hidden p-1.5 -mr-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 transition"
            aria-label="Close navigation">
        <i data-lucide="x" class="w-5 h-5"></i>
    </button>
</div>

@permission('campaigns.create')
<a href="{{ route('campaigns.create') }}"
   class="flex items-center justify-center gap-2 w-full mb-6 px-3 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-[13px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
    <i data-lucide="plus" class="w-4 h-4"></i>
    New Campaign
</a>
@endpermission

<nav class="flex-1 overflow-y-auto -mx-1 px-1 space-y-6">
    @foreach ($navSections as $section => $items)
        <div>
            <p class="px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $section }}</p>

            <ul class="space-y-0.5">
                @foreach ($items as $item)
                    @php $isActive = request()->routeIs($item['pattern']); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}"
                           @if($isActive) aria-current="page" @endif
                           class="group relative flex items-center gap-3 pl-3.5 pr-3 py-2 rounded-lg text-[13px] transition
                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400
                                  {{ $isActive ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 font-medium hover:text-slate-100 hover:bg-slate-800/50' }}">
                            @if($isActive)
                                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-brand-400"></span>
                            @endif

                            <i data-lucide="{{ $item['icon'] }}"
                               class="w-[18px] h-[18px] flex-shrink-0 {{ $isActive ? 'text-brand-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>

                            <span class="truncate">{{ $item['label'] }}</span>

                            @if($item['route'] === 'campaigns.index' && $sendingNow > 0)
                                <span class="ml-auto flex items-center gap-1.5 text-[11px] font-medium text-emerald-400"
                                      title="{{ $sendingNow }} campaign{{ $sendingNow === 1 ? '' : 's' }} sending now">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    {{ $sendingNow }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
