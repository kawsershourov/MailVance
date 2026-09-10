@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="contactListsManager()">

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="users" class="w-6 h-6 text-brand-400"></i> Audience & Contact Lists
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Manage contact segments and import subscriber lists from CSV.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @permission('contacts.create')
                <button @click="openCreateListModal()" class="px-4 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs sm:text-sm font-semibold border border-slate-700 transition flex items-center gap-2">
                    <i data-lucide="folder-plus" class="w-4 h-4"></i> Create New List
                </button>
            @endpermission
            @permission('contacts.import')
                <button @click="openImportModal()" class="px-5 py-2.5 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs sm:text-sm shadow-xl shadow-brand-600/30 transition flex items-center gap-2 transform hover:-translate-y-0.5">
                    <i data-lucide="upload-cloud" class="w-4 h-4 text-emerald-300"></i> Import CSV
                </button>
            @endpermission
        </div>
    </div>

    <!-- Contact Lists Grid -->
    @if($lists->isEmpty())
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-xl">
            <div class="w-16 h-16 rounded-3xl bg-brand-500/10 text-brand-400 flex items-center justify-center mx-auto mb-4 border border-brand-500/20">
                <i data-lucide="users" class="w-8 h-8"></i>
            </div>
            <h3 class="text-base font-bold text-white">No Contact Lists Yet</h3>
            <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-md mx-auto">
                Create a contact list or upload your CSV file to begin sending targeted email campaigns.
            </p>
            <div class="mt-6 flex justify-center gap-3">
                @permission('contacts.create')
                    <button @click="openCreateListModal()" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold">
                        Create Empty List
                    </button>
                @endpermission
                @permission('contacts.import')
                    <button @click="openImportModal()" class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg">
                        Upload CSV File
                    </button>
                @endpermission
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($lists as $l)
                <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl flex flex-col justify-between group hover:border-brand-500/40 transition">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-slate-800 border border-slate-700/60 flex items-center justify-center text-brand-400 group-hover:scale-105 transition">
                                <i data-lucide="folder" class="w-6 h-6"></i>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-slate-950 text-emerald-400 border border-slate-800">
                                {{ number_format($l->contacts_count) }} Contacts
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-white tracking-tight">{{ $l->name }}</h3>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-2">{{ $l->description ?: "No description provided." }}</p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between gap-2">
                        <a href="{{ route('contacts.show', $l->id) }}" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold text-center rounded-xl transition flex items-center justify-center gap-1.5">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> View Contacts
                        </a>
                        @permission('contacts.import')
                            <button @click="openImportModalForList({{ $l->id }}, '{{ addslashes($l->name) }}')" class="p-2 rounded-xl bg-brand-600/10 hover:bg-brand-600/20 text-brand-400 border border-brand-500/20 transition" title="Import CSV into this list">
                                <i data-lucide="upload" class="w-4 h-4"></i>
                            </button>
                        @endpermission
                        @permission('contacts.delete')
                            <form method="POST" action="{{ route('contacts.destroy-list', $l->id) }}" onsubmit="return confirm('Are you sure you want to delete this list and all its contacts?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        @endpermission
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- CREATE LIST MODAL -->
    <div x-show="createListModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="createListModalOpen = false"></div>
        <div class="relative w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white">Create New Contact List</h3>
                <button @click="createListModalOpen = false" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form action="{{ route('contacts.store-list') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">List Name</label>
                    <input type="text" name="name" required placeholder="e.g. VIP Customers, Q3 Leads"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Brief notes about this audience segment..."
                              class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"></textarea>
                </div>
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="createListModalOpen = false" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold rounded-xl shadow-lg">Create List</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 200MB CSV STREAMING IMPORT MODAL -->
    <div x-show="importModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="if(!importing) importModalOpen = false"></div>
        <div class="relative w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
            
            <!-- Step Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300">
                        High-Performance Stream Importer (< 15MB RAM)
                    </span>
                    <h3 class="text-lg font-bold text-white mt-1">Import Contacts via CSV</h3>
                </div>
                <button @click="if(!importing) importModalOpen = false" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <!-- Step 1: Upload File & Select List -->
            <div x-show="importStep === 1" class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Target Contact List</label>
                    <select x-model="selectedListId" class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        <option value="">-- Choose existing list or create below --</option>
                        @foreach($lists as $l)
                            <option value="{{ $l->id }}">{{ $l->name }} ({{ $l->contacts_count }} contacts)</option>
                        @endforeach
                    </select>
                </div>

                <!-- Dropzone for CSV -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Choose CSV file</label>
                    <div class="border-2 border-dashed border-slate-700 hover:border-brand-500 rounded-2xl p-6 sm:p-8 text-center bg-slate-950/60 cursor-pointer transition relative">
                        <input type="file" @change="handleFileSelected($event)" accept=".csv,text/csv" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                        <i data-lucide="file-spreadsheet" class="w-10 h-10 text-brand-400 mx-auto mb-2"></i>
                        <p class="text-sm font-semibold text-white" x-text="csvFileName ? csvFileName : 'Click to browse or drop CSV file here'"></p>
                        <p class="text-xs text-slate-400 mt-1">Supports UTF-8 CSV with unlimited rows (streamed chunking)</p>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button @click="uploadAndInspect()" :disabled="!selectedListId || !csvFile || uploading"
                            class="px-6 py-3 bg-brand-600 hover:bg-brand-500 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                        <template x-if="uploading">
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full border-2 border-white border-t-transparent animate-spin"></span> Inspecting CSV Headers...</span>
                        </template>
                        <template x-if="!uploading">
                            <span>Next: Map Columns & Preview <i data-lucide="arrow-right" class="w-4 h-4 inline ml-1"></i></span>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Step 2: Column Mapping & Sample Preview -->
            <div x-show="importStep === 2" x-cloak class="space-y-6">
                <div>
                    <h4 class="text-sm font-bold text-white mb-1">Map CSV Columns to Contact Attributes</h4>
                    <p class="text-xs text-slate-400">Match the headers detected from your file to the platform contact fields.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-950 p-4 rounded-2xl border border-slate-800">
                    <div>
                        <label class="block text-xs font-semibold text-emerald-400 mb-1">Email Address (Required)</label>
                        <select x-model="columnMap.email" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs">
                            <option value="">-- Select Header --</option>
                            <template x-for="(h, idx) in detectedHeaders" :key="idx">
                                <option :value="idx" x-text="h"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">First Name (Optional)</label>
                        <select x-model="columnMap.first_name" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs">
                            <option value="">-- None / Skip --</option>
                            <template x-for="(h, idx) in detectedHeaders" :key="idx">
                                <option :value="idx" x-text="h"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Last Name (Optional)</label>
                        <select x-model="columnMap.last_name" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs">
                            <option value="">-- None / Skip --</option>
                            <template x-for="(h, idx) in detectedHeaders" :key="idx">
                                <option :value="idx" x-text="h"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Company (Optional)</label>
                        <select x-model="columnMap.company" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs">
                            <option value="">-- None / Skip --</option>
                            <template x-for="(h, idx) in detectedHeaders" :key="idx">
                                <option :value="idx" x-text="h"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Sample Rows Preview Table -->
                <div>
                    <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">First 5 Sample Rows from CSV:</h5>
                    <div class="overflow-x-auto border border-slate-800 rounded-xl max-h-40">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-slate-400">
                                <tr>
                                    <template x-for="h in detectedHeaders" :key="h">
                                        <th class="py-2 px-3 border-b border-slate-800 font-mono text-[11px]" x-text="h"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 bg-slate-900/50">
                                <template x-for="(row, rIdx) in sampleRows" :key="rIdx">
                                    <tr>
                                        <template x-for="(cell, cIdx) in row" :key="cIdx">
                                            <td class="py-2 px-3 text-[11px]"><div class="truncate max-w-[140px]" x-text="cell"></div></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-between">
                    <button type="button" @click="importStep = 1" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl">Back</button>
                    <button type="button" @click="executeStreamImport()" :disabled="columnMap.email === null || columnMap.email === '' || importing"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                        <template x-if="importing">
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full border-2 border-white border-t-transparent animate-spin"></span> Streaming Database Ingestion...</span>
                        </template>
                        <template x-if="!importing">
                            <span>Start Import</span>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Step 3: Finished / Summary -->
            <div x-show="importStep === 3" x-cloak class="text-center py-6 space-y-4">
                <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
                <h4 class="text-lg font-bold text-white">Import Complete!</h4>
                <p class="text-sm text-slate-300 max-w-md mx-auto" x-text="importResultMessage"></p>
                <div class="pt-4">
                    <button @click="window.location.reload()" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl shadow-lg">
                        View Updated Audience
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function contactListsManager() {
        return {
            createListModalOpen: false,
            importModalOpen: false,
            importStep: 1,
            selectedListId: "",
            csvFile: null,
            csvFileName: "",
            uploading: false,
            importing: false,
            tempFilePath: "",
            detectedHeaders: [],
            sampleRows: [],
            columnMap: { email: null, first_name: null, last_name: null, company: null },
            importResultMessage: "",

            openCreateListModal() {
                this.createListModalOpen = true;
            },
            openImportModal() {
                this.importStep = 1;
                this.csvFile = null;
                this.csvFileName = "";
                this.importModalOpen = true;
            },
            openImportModalForList(id, name) {
                this.selectedListId = id;
                this.openImportModal();
            },
            handleFileSelected(e) {
                if (e.target.files.length > 0) {
                    this.csvFile = e.target.files[0];
                    this.csvFileName = this.csvFile.name;
                }
            },
            async uploadAndInspect() {
                if (!this.csvFile || !this.selectedListId) return;
                this.uploading = true;
                const fd = new FormData();
                fd.append("csv_file", this.csvFile);

                try {
                    const res = await fetch("{{ route('contacts.upload-csv-preview') }}", {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                            "Accept": "application/json"
                        },
                        body: fd
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || "Failed to parse CSV file");
                    
                    this.tempFilePath = data.temp_file_path;
                    this.detectedHeaders = data.headers;
                    this.sampleRows = data.sample_rows;
                    this.columnMap = data.auto_mapping;
                    this.importStep = 2;
                } catch (err) {
                    alert("Error: " + err.message);
                } finally {
                    this.uploading = false;
                }
            },
            async executeStreamImport() {
                this.importing = true;
                try {
                    const res = await fetch("{{ route('contacts.process-csv-import') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                            "Accept": "application/json"
                        },
                        body: JSON.stringify({
                            contact_list_id: this.selectedListId,
                            temp_file_path: this.tempFilePath,
                            column_map: this.columnMap
                        })
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || "Import failed");

                    this.importResultMessage = data.message;
                    this.importStep = 3;
                } catch (err) {
                    alert("Stream Import Error: " + err.message);
                } finally {
                    this.importing = false;
                }
            }
        }
    }
</script>
@endpush