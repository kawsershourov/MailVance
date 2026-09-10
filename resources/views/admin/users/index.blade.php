@extends('layouts.app')

@section('header', 'Users')

@section('content')
<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="users-round" class="w-6 h-6 text-brand-400"></i> User Management
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                {{ $totals['all'] }} account{{ $totals['all'] === 1 ? '' : 's' }} · {{ $totals['active'] }} active · {{ $totals['inactive'] }} deactivated
            </p>
        </div>

        @permission('users.create')
            <a href="{{ route('admin.users.create') }}"
               class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-xl shadow-brand-600/30 transition transform hover:-translate-y-0.5">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Add User
            </a>
        @endpermission
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.users.index') }}"
          class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <i data-lucide="search" class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by name, email or company"
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
        </div>

        <select name="role" class="w-full sm:w-auto px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            <option value="">All roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->slug }}" @selected($roleFilter === $role->slug)>{{ $role->name }} ({{ $role->users_count }})</option>
            @endforeach
        </select>

        <select name="status" class="w-full sm:w-auto px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            <option value="">Any status</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Deactivated</option>
        </select>

        <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition">
            Filter
        </button>
    </form>

    <!-- Users Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl shadow-xl overflow-hidden">
        @if($users->isEmpty())
            <div class="p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-brand-500/10 text-brand-400 flex items-center justify-center mx-auto mb-4 border border-brand-500/20">
                    <i data-lucide="user-round-search" class="w-8 h-8"></i>
                </div>
                <h3 class="text-base font-bold text-white">No users match those filters</h3>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">Try clearing the search or picking a different role.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-sm">
                    <thead class="bg-slate-950/60 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="text-left font-semibold px-6 py-3">User</th>
                            <th class="text-left font-semibold px-6 py-3">Roles</th>
                            <th class="text-left font-semibold px-6 py-3">Status</th>
                            <th class="text-left font-semibold px-6 py-3">Last Sign-in</th>
                            <th class="text-right font-semibold px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach($users as $u)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($u->avatar_url)
                                            <img src="{{ $u->avatar_url }}" alt="" class="w-9 h-9 rounded-xl object-cover border border-slate-700 flex-shrink-0">
                                        @else
                                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-600 to-violet-500 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                                {{ $u->initials }}
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-semibold text-white truncate flex items-center gap-1.5">
                                                {{ $u->name }}
                                                @if($u->id === Auth::id())
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-800 text-slate-400 border border-slate-700">YOU</span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-slate-500 truncate">{{ $u->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse($u->roles as $role)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border
                                                {{ $role->isSuperAdmin()
                                                    ? 'bg-amber-500/15 text-amber-300 border-amber-500/30'
                                                    : 'bg-brand-500/15 text-brand-300 border-brand-500/30' }}">
                                                {{ $role->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-600">No role</span>
                                        @endforelse
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border
                                        {{ $u->is_active
                                            ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                                            : 'bg-rose-500/15 text-rose-300 border-rose-500/30' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $u->is_active ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                                        {{ $u->is_active ? 'Active' : 'Deactivated' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-xs text-slate-400">
                                    {{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never' }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        @permission('users.update')
                                            <a href="{{ route('admin.users.edit', $u->id) }}"
                                               class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition" title="Edit user">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </a>

                                            @if($u->id !== Auth::id())
                                                <form method="POST" action="{{ route('admin.users.toggle-active', $u->id) }}"
                                                      onsubmit="return confirm('{{ $u->is_active ? 'Deactivate' : 'Activate' }} {{ addslashes($u->name) }}?')">
                                                    @csrf
                                                    <button type="submit"
                                                            class="p-2 rounded-xl transition {{ $u->is_active ? 'bg-slate-800 hover:bg-amber-500/20 text-amber-400' : 'bg-slate-800 hover:bg-emerald-500/20 text-emerald-400' }}"
                                                            title="{{ $u->is_active ? 'Deactivate account' : 'Activate account' }}">
                                                        <i data-lucide="{{ $u->is_active ? 'user-round-x' : 'user-round-check' }}" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endpermission

                                        @permission('users.delete')
                                            @if($u->id !== Auth::id())
                                                <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}"
                                                      onsubmit="return confirm('Delete {{ addslashes($u->name) }}? Their campaigns, lists, templates and relays are deleted too.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-rose-400 transition" title="Delete user">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="px-6 py-4 border-t border-slate-800/80">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
