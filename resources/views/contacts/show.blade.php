@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="{ addContactModal: false }">

    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('contacts.index') }}" class="hover:text-brand-400">Audience Lists</a>
                <span>/</span>
                <span class="text-white">{{ $list->name }}</span>
            </div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="users" class="w-6 h-6 text-brand-400"></i> {{ $list->name }}
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Total Subscribers: <span class="font-mono font-bold text-emerald-400">{{ number_format($list->contacts()->count()) }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @permission('contacts.export')
                <a href="{{ route('contacts.export-csv', $list->id) }}" class="px-4 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs sm:text-sm font-semibold border border-slate-700 transition flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </a>
            @endpermission
            @permission('contacts.create')
                <button @click="addContactModal = true" class="px-5 py-2.5 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs sm:text-sm shadow-xl shadow-brand-600/30 transition flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Add Contact
                </button>
            @endpermission
        </div>
    </div>

    <!-- Search Bar & Filters -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl">
        <form method="GET" action="{{ route('contacts.show', $list->id) }}" class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-500 absolute left-4 top-3.5"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by email, name, or company..."
                       class="w-full pl-11 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-2xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full sm:w-auto px-5 py-3 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-2xl transition">
                Search
            </button>
            @if(request('search'))
                <a href="{{ route('contacts.show', $list->id) }}" class="px-4 py-3 bg-slate-800 text-slate-400 hover:text-white text-xs font-semibold rounded-2xl flex items-center">
                    Clear
                </a>
            @endif
        </form>

        <!-- Contacts Table -->
        @if($contacts->isEmpty())
            <div class="text-center py-12 text-slate-400 text-xs">
                No contacts found matching your criteria.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-950/60 text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3 px-4 rounded-l-xl">Email Address</th>
                            <th class="py-3 px-4">First Name</th>
                            <th class="py-3 px-4">Last Name</th>
                            <th class="py-3 px-4">Company</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Added Date</th>
                            <th class="py-3 px-4 rounded-r-xl text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($contacts as $c)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-3.5 px-4 font-mono text-xs font-semibold text-white">{{ $c->email }}</td>
                                <td class="py-3.5 px-4 text-xs text-slate-300">{{ $c->first_name ?: "-" }}</td>
                                <td class="py-3.5 px-4 text-xs text-slate-300">{{ $c->last_name ?: "-" }}</td>
                                <td class="py-3.5 px-4 text-xs text-slate-300">{{ $c->company ?: "-" }}</td>
                                <td class="py-3.5 px-4">
                                    @if($c->status === 'active')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Active</span>
                                    @elseif($c->status === 'unsubscribed')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300">Unsubscribed</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300">{{ ucfirst($c->status) }}</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-400 font-mono">{{ $c->created_at->format('Y-m-d') }}</td>
                                <td class="py-3.5 px-4 text-right">
                                    @permission('contacts.delete')
                                        <form method="POST" action="{{ route('contacts.destroy-contact', $c->id) }}" onsubmit="return confirm('Remove this contact?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg text-rose-400 hover:bg-rose-500/10 transition">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    @endpermission
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="mt-6">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>

    <!-- ADD SINGLE CONTACT MODAL -->
    <div x-show="addContactModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="addContactModal = false"></div>
        <div class="relative w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white">Add Contact to {{ $list->name }}</h3>
                <button @click="addContactModal = false" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form action="{{ route('contacts.store-contact', $list->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Email Address</label>
                    <input type="email" name="email" required placeholder="contact@domain.com"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">First Name</label>
                        <input type="text" name="first_name" placeholder="John"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Last Name</label>
                        <input type="text" name="last_name" placeholder="Doe"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Company (Optional)</label>
                    <input type="text" name="company" placeholder="Acme Inc."
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="addContactModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold rounded-xl shadow-lg">Save Contact</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection