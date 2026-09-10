@extends('layouts.app')

@section('header', 'Roles')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="shield-check" class="w-6 h-6 text-brand-400"></i> Roles & Permissions
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                A role is a named bundle of permissions. Assign roles to users on the Users screen.
            </p>
        </div>

        @permission('roles.create')
            <a href="{{ route('admin.roles.create') }}"
               class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-xl shadow-brand-600/30 transition transform hover:-translate-y-0.5">
                <i data-lucide="plus" class="w-4 h-4"></i> New Role
            </a>
        @endpermission
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($roles as $role)
            @php $granted = $role->isSuperAdmin() ? $permissionCount : $role->permissions->count(); @endphp

            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl flex flex-col justify-between hover:border-brand-500/40 transition">
                <div>
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-slate-800 border border-slate-700/60 flex items-center justify-center flex-shrink-0
                                        {{ $role->isSuperAdmin() ? 'text-amber-400' : 'text-brand-400' }}">
                                <i data-lucide="{{ $role->isSuperAdmin() ? 'crown' : 'shield' }}" class="w-6 h-6"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-white tracking-tight truncate">{{ $role->name }}</h3>
                                <p class="text-xs text-slate-500 font-mono truncate">{{ $role->slug }}</p>
                            </div>
                        </div>

                        @if($role->is_system)
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-800 text-slate-400 border border-slate-700 flex-shrink-0">BUILT-IN</span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-400 leading-relaxed min-h-[32px]">{{ $role->description ?: 'No description.' }}</p>

                    <div class="grid grid-cols-2 gap-3 my-5">
                        <div class="px-3 py-2.5 rounded-2xl bg-slate-950/60 border border-slate-800/80">
                            <p class="text-lg font-extrabold text-white leading-none">{{ $granted }}<span class="text-xs text-slate-500 font-medium">/{{ $permissionCount }}</span></p>
                            <p class="text-[10px] uppercase tracking-wider text-slate-500 mt-1">Permissions</p>
                        </div>
                        <div class="px-3 py-2.5 rounded-2xl bg-slate-950/60 border border-slate-800/80">
                            <p class="text-lg font-extrabold text-white leading-none">{{ $role->users_count }}</p>
                            <p class="text-[10px] uppercase tracking-wider text-slate-500 mt-1">Users</p>
                        </div>
                    </div>

                    @if($role->isSuperAdmin())
                        <p class="text-[11px] text-amber-300/80 flex items-center gap-1.5">
                            <i data-lucide="info" class="w-3.5 h-3.5"></i> Always holds every permission, including future ones.
                        </p>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($role->permissions->pluck('group')->unique()->sort() as $group)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">{{ $group }}</span>
                            @endforeach
                            @if($role->permissions->isEmpty())
                                <span class="text-[11px] text-slate-600">No permissions granted yet.</span>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="pt-5 mt-5 border-t border-slate-800/80 flex items-center justify-between gap-2">
                    @permission('roles.update')
                        @if($role->isSuperAdmin())
                            <span class="flex-1 py-2 px-3 rounded-xl bg-slate-800/50 text-slate-600 text-xs font-semibold text-center">Not editable</span>
                        @else
                            <a href="{{ route('admin.roles.edit', $role->id) }}"
                               class="flex-1 py-2 px-3 rounded-xl bg-brand-600/10 hover:bg-brand-600/20 text-brand-400 font-semibold text-xs border border-brand-500/20 transition flex items-center justify-center gap-1.5">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit Permissions
                            </a>
                        @endif
                    @endpermission

                    @permission('roles.delete')
                        @unless($role->is_system)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}"
                                  onsubmit="return confirm('Delete the {{ addslashes($role->name) }} role?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-rose-400 transition" title="Delete role">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        @endunless
                    @endpermission
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
