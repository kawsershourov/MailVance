@extends('layouts.app')

@section('header', 'My Profile')

@section('content')
<div class="space-y-8 max-w-5xl" x-data="{ tab: '{{ $errors->has('current_password') || $errors->has('password') ? 'security' : 'details' }}' }">

    <!-- Identity Header -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center gap-6">
            <div class="relative flex-shrink-0">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                         class="w-20 h-20 rounded-2xl object-cover border border-slate-700 shadow-lg">
                @else
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-brand-600 to-violet-500 flex items-center justify-center text-white font-extrabold text-2xl shadow-lg">
                        {{ $user->initials }}
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-extrabold text-white tracking-tight truncate">{{ $user->name }}</h2>
                <p class="text-sm text-slate-400 truncate">{{ $user->email }}</p>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $user->job_title ?: 'No job title set' }}@if($user->company) · {{ $user->company }} @endif
                </p>

                <div class="flex flex-wrap items-center gap-2 mt-3">
                    @forelse($user->roles as $role)
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand-500/20 text-brand-300 border border-brand-500/30">
                            {{ $role->name }}
                        </span>
                    @empty
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">No role assigned</span>
                    @endforelse

                    @if($user->last_login_at)
                        <span class="text-[11px] text-slate-500">Last signed in {{ $user->last_login_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 text-center">
                @foreach ([
                    ['label' => 'Campaigns', 'value' => $stats['campaigns']],
                    ['label' => 'Lists', 'value' => $stats['contact_lists']],
                    ['label' => 'Templates', 'value' => $stats['templates']],
                    ['label' => 'Relays', 'value' => $stats['smtp_configs']],
                ] as $stat)
                    <div class="px-3 py-2 rounded-2xl bg-slate-950/60 border border-slate-800/80 min-w-[72px]">
                        <p class="text-lg font-extrabold text-white leading-none">{{ $stat['value'] }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500 mt-1">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-800 overflow-x-auto">
        @foreach ([
            'details' => ['Profile Details', 'user-round'],
            'security' => ['Password & Security', 'lock'],
            'access' => ['Roles & Permissions', 'shield-check'],
        ] as $key => $meta)
            <button @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'text-white border-brand-500' : 'text-slate-400 border-transparent hover:text-slate-200'"
                    class="flex flex-shrink-0 whitespace-nowrap items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 transition">
                <i data-lucide="{{ $meta[1] }}" class="w-4 h-4"></i> {{ $meta[0] }}
            </button>
        @endforeach
    </div>

    <!-- PROFILE DETAILS -->
    <div x-show="tab === 'details'" x-cloak class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="flex flex-col sm:flex-row sm:items-center gap-4 pb-5 border-b border-slate-800">
                <div class="flex-1">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Profile Photo</label>
                    <input type="file" name="avatar" accept="image/*"
                           class="block w-full text-xs text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-600/20 file:text-brand-300 hover:file:bg-brand-600/30 cursor-pointer">
                    <p class="text-[11px] text-slate-500 mt-1.5">PNG, JPG, GIF or WEBP up to 2MB.</p>
                    @error('avatar') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                @if($user->avatar_path)
                    <button type="button"
                            onclick="document.getElementById('remove-avatar-form').submit()"
                            class="px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 text-xs font-semibold transition">
                        Remove photo
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    @error('name') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    @error('email') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Job Title</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="Head of Growth"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Company</label>
                    <input type="text" name="company" value="{{ old('company', $user->company) }}" placeholder="Acme Inc."
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+1 555 0100"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Timezone</label>
                    <select name="timezone"
                            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $user->timezone) === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Bio</label>
                <textarea name="bio" rows="3" maxlength="1000" placeholder="A short description shown to your teammates."
                          class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">{{ old('bio', $user->bio) }}</textarea>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg transition">
                    Save Profile
                </button>
            </div>
        </form>

        <form id="remove-avatar-form" action="{{ route('profile.avatar.destroy') }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <!-- SECURITY -->
    <div x-show="tab === 'security'" x-cloak class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <h3 class="text-base font-bold text-white mb-1">Change Password</h3>
        <p class="text-xs text-slate-400 mb-6">Use at least 8 characters. You stay signed in on this device after changing it.</p>

        <form action="{{ route('profile.password') }}" method="POST" class="space-y-5 max-w-lg">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Current Password</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                @error('current_password') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">New Password</label>
                <input type="password" name="password" required autocomplete="new-password"
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                @error('password') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg transition">
                    Update Password
                </button>
            </div>
        </form>
    </div>

    <!-- ACCESS -->
    <div x-show="tab === 'access'" x-cloak class="space-y-6">
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
            <h3 class="text-base font-bold text-white mb-1">Your Access</h3>
            <p class="text-xs text-slate-400 mb-6">
                Roles are assigned by an administrator. This is the effective set of actions your account can perform.
            </p>

            @if($user->isSuperAdmin())
                <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-200 text-sm flex items-center gap-3">
                    <i data-lucide="crown" class="w-5 h-5 flex-shrink-0"></i>
                    You are a <strong>Super Admin</strong> — every permission is granted, including any added in future releases.
                </div>
            @else
                @php $held = $user->permissionSlugs(); @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @foreach(\App\Services\PermissionRegistry::all() as $group => $permissions)
                        <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3">{{ $group }}</p>
                            <ul class="space-y-2">
                                @foreach($permissions as $slug => $label)
                                    @php $granted = in_array($slug, $held, true); @endphp
                                    <li class="flex items-center gap-2 text-xs {{ $granted ? 'text-slate-200' : 'text-slate-600' }}">
                                        <i data-lucide="{{ $granted ? 'check-circle-2' : 'x-circle' }}"
                                           class="w-3.5 h-3.5 flex-shrink-0 {{ $granted ? 'text-emerald-400' : 'text-slate-700' }}"></i>
                                        <span>{{ $label }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
