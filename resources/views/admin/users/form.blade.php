@extends('layouts.app')

@php $isEditing = $user->exists; @endphp

@section('header', $isEditing ? 'Edit User' : 'New User')

@section('content')
<div class="space-y-6 max-w-5xl"
     x-data="userAccessForm({
        selectedRoles: {{ json_encode(array_map('intval', old('roles', $assignedRoleIds))) }},
        rolePermissions: {{ json_encode($rolePermissionMap) }}
     })">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.users.index') }}" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight">
                {{ $isEditing ? $user->name : 'Create a new user' }}
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Set the account details, then grant access by assigning roles.
            </p>
        </div>
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

    <form method="POST" action="{{ $isEditing ? route('admin.users.update', $user->id) : route('admin.users.store') }}" class="space-y-6">
        @csrf
        @if($isEditing) @method('PUT') @endif

        <!-- Account details -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-5">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="user-round" class="w-4 h-4 text-brand-400"></i> Account Details
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Password @if($isEditing)<span class="normal-case tracking-normal text-slate-500 font-normal">— leave blank to keep current</span>@endif
                    </label>
                    <input type="password" name="password" autocomplete="new-password" {{ $isEditing ? '' : 'required' }}
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Job Title</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Company</label>
                    <input type="text" name="company" value="{{ old('company', $user->company) }}"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Timezone</label>
                    <select name="timezone" class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        @foreach(\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $user->timezone ?: 'UTC') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-300 pt-1">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $user->exists ? $user->is_active : true))
                       @disabled($isEditing && $user->id === Auth::id())
                       class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-brand-600">
                <span>Account is active (can sign in)</span>
                @if($isEditing && $user->id === Auth::id())
                    <span class="text-[11px] text-slate-500">— you cannot deactivate your own account</span>
                @endif
            </label>
        </div>

        <!-- Roles -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-4">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i data-lucide="shield" class="w-4 h-4 text-brand-400"></i> Roles
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    A user gets the combined permissions of every role assigned to them.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($roles as $role)
                    <label class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition"
                           :class="selectedRoles.includes({{ $role->id }}) ? 'bg-brand-600/10 border-brand-500/40' : 'bg-slate-950/60 border-slate-800 hover:border-slate-700'">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" x-model.number="selectedRoles"
                               class="mt-0.5 w-4 h-4 rounded bg-slate-950 border-slate-700 text-brand-600">
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-white">
                                {{ $role->name }}
                                @if($role->isSuperAdmin())
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">ALL ACCESS</span>
                                @endif
                            </span>
                            <span class="block text-xs text-slate-400 mt-0.5">{{ $role->description }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- Read-only effective access list -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-5">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i data-lucide="list-checks" class="w-4 h-4 text-brand-400"></i> Access List
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    What this user can access, based on the role(s) selected above. Read-only —
                    change it by adjusting their roles or editing a role's permissions at
                    <a href="{{ route('admin.roles.index') }}" class="text-brand-400 hover:underline">Roles</a>.
                </p>
            </div>

            <div x-show="hasSuperAdminRole" x-cloak
                 class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-200 text-xs flex items-center gap-3">
                <i data-lucide="crown" class="w-4 h-4 flex-shrink-0"></i>
                This user holds the Super Admin role, so every permission is granted.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($permissionGroups as $group => $permissions)
                    <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3">{{ $group }}</p>

                        <div class="space-y-2">
                            @foreach($permissions as $slug => $label)
                                <label class="flex items-center gap-3 cursor-default">
                                    <input type="checkbox" disabled :checked="inherited('{{ $slug }}')"
                                           class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-brand-600 disabled:opacity-100">
                                    <span class="text-xs min-w-0 truncate"
                                          :class="inherited('{{ $slug }}') ? 'text-slate-200' : 'text-slate-600'">
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg transition">
                {{ $isEditing ? 'Save Changes' : 'Create User' }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function userAccessForm(config) {
        return {
            selectedRoles: config.selectedRoles || [],
            rolePermissions: config.rolePermissions || {},
            superAdminRoleIds: @json($roles->filter->isSuperAdmin()->pluck('id')->values()),

            get hasSuperAdminRole() {
                return this.selectedRoles.some(id => this.superAdminRoleIds.includes(id));
            },

            /** Does any currently ticked role grant this permission? */
            inherited(slug) {
                return this.selectedRoles.some(id => (this.rolePermissions[id] || []).includes(slug));
            },
        };
    }
</script>
@endpush
@endsection
