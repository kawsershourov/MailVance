@extends('layouts.app')

@php $isEditing = $role->exists; @endphp

@section('header', $isEditing ? 'Edit Role' : 'New Role')

@section('content')
<div class="space-y-6 max-w-5xl"
     x-data="{ selected: {{ json_encode(old('permissions', $assigned)) }},
               toggleGroup(slugs, on) {
                   this.selected = on
                       ? [...new Set([...this.selected, ...slugs])]
                       : this.selected.filter(s => !slugs.includes(s));
               },
               groupState(slugs) {
                   const hits = slugs.filter(s => this.selected.includes(s)).length;
                   return hits === 0 ? 'none' : (hits === slugs.length ? 'all' : 'some');
               } }">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.roles.index') }}" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight">
                {{ $isEditing ? $role->name : 'Create a new role' }}
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Tick the actions this role is allowed to perform.
                <span x-text="selected.length"></span> selected.
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

    <form method="POST" action="{{ $isEditing ? route('admin.roles.update', $role->id) : route('admin.roles.store') }}" class="space-y-6">
        @csrf
        @if($isEditing) @method('PUT') @endif

        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Role Name</label>
                    <input type="text" name="name" value="{{ old('name', $role->name) }}" required placeholder="e.g. Content Editor"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Slug @if($role->is_system)<span class="normal-case tracking-normal text-slate-500 font-normal">— fixed for built-in roles</span>@endif
                    </label>
                    <input type="text" name="slug" value="{{ old('slug', $role->slug) }}" placeholder="auto-generated from the name"
                           @readonly($role->is_system)
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm font-mono focus:ring-2 focus:ring-brand-500 focus:outline-none disabled:opacity-50 read-only:text-slate-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Description</label>
                <input type="text" name="description" value="{{ old('description', $role->description) }}" maxlength="255"
                       placeholder="What this role is for, in one line."
                       class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach($permissionGroups as $group => $permissions)
                @php $slugs = array_keys($permissions); @endphp

                <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 shadow-xl"
                     x-data="{ slugs: {{ json_encode($slugs) }} }">
                    <div class="flex items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-800/80">
                        <p class="text-sm font-bold text-white">{{ $group }}</p>
                        <button type="button" @click="toggleGroup(slugs, groupState(slugs) !== 'all')"
                                class="text-[11px] font-semibold px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
                                x-text="groupState(slugs) === 'all' ? 'Clear all' : 'Select all'"></button>
                    </div>

                    <div class="space-y-2">
                        @foreach($permissions as $slug => $label)
                            <label class="flex items-center gap-2.5 px-3 py-2 rounded-xl cursor-pointer transition"
                                   :class="selected.includes('{{ $slug }}') ? 'bg-brand-600/10' : 'hover:bg-slate-800/50'">
                                <input type="checkbox" name="permissions[]" value="{{ $slug }}" x-model="selected"
                                       class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-brand-600">
                                <span class="text-xs" :class="selected.includes('{{ $slug }}') ? 'text-slate-100' : 'text-slate-400'">{{ $label }}</span>
                                <span class="ml-auto text-[10px] font-mono text-slate-600">{{ $slug }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.roles.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg transition">
                {{ $isEditing ? 'Save Role' : 'Create Role' }}
            </button>
        </div>
    </form>
</div>
@endsection
