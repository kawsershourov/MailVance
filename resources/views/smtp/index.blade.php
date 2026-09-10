@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="smtpManager()">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="server" class="w-6 h-6 text-brand-400"></i> SMTP Connections & Relays
            </h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Configure your SMTP sending servers (Amazon SES, SendGrid, Mailgun, Postmark, Google, or Custom SMTP).
            </p>
        </div>
        @permission('smtp.create')
            <button @click="openAddModal()" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-xl shadow-brand-600/30 transition transform hover:-translate-y-0.5">
                <i data-lucide="plus" class="w-4 h-4"></i> Add New SMTP Relay
            </button>
        @endpermission
    </div>

    <!-- SMTP Relays List -->
    @if($smtps->isEmpty())
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-xl">
            <div class="w-16 h-16 rounded-3xl bg-brand-500/10 text-brand-400 flex items-center justify-center mx-auto mb-4 border border-brand-500/20">
                <i data-lucide="server" class="w-8 h-8"></i>
            </div>
            <h3 class="text-base font-bold text-white">No SMTP Relays Connected</h3>
            <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-md mx-auto">
                Connect an SMTP server so your campaigns can route emails securely with full anti-spam headers.
            </p>
            @permission('smtp.create')
                <button @click="openAddModal()" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-lg transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> Connect First SMTP
                </button>
            @endpermission
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($smtps as $s)
                <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl relative overflow-hidden flex flex-col justify-between group hover:border-brand-500/40 transition">
                    
                    @if($s->is_default)
                        <div class="absolute top-4 right-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand-500/20 text-brand-300 border border-brand-500/30 flex items-center gap-1">
                                <i data-lucide="check" class="w-3 h-3"></i> Default Sender
                            </span>
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center gap-3 mb-4 {{ $s->is_default ? 'pr-28' : '' }}">
                            <div class="w-12 h-12 rounded-2xl bg-slate-800 border border-slate-700/60 flex items-center justify-center text-brand-400 group-hover:scale-105 transition flex-shrink-0">
                                <i data-lucide="mail-check" class="w-6 h-6"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-white tracking-tight truncate">{{ $s->title }}</h3>
                                <p class="text-xs text-slate-400 font-mono truncate">{{ $s->host }}:{{ $s->port }}</p>
                            </div>
                        </div>

                        <div class="space-y-2.5 my-5 text-xs text-slate-300 bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Encryption:</span>
                                <span class="font-mono uppercase font-bold text-indigo-400">{{ $s->encryption }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 flex-shrink-0 mr-3">Username:</span>
                                <span class="font-mono text-slate-300 truncate min-w-0">{{ $s->username ?: "None" }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 flex-shrink-0 mr-3">From Address:</span>
                                <span class="text-slate-200 truncate min-w-0">{{ $s->from_email ?: "System Default" }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 flex-shrink-0 mr-3">Sender Name:</span>
                                <span class="text-slate-200 truncate min-w-0">{{ $s->from_name ?: "MailFlow" }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between gap-2">
                        <!-- Test Send Button -->
                        @permission('smtp.test')
                            <button @click="openTestModal({{ $s->id }}, '{{ addslashes($s->title) }}')"
                                    class="flex-1 py-2 px-3 rounded-xl bg-brand-600/10 hover:bg-brand-600/20 text-brand-400 font-semibold text-xs border border-brand-500/20 transition flex items-center justify-center gap-1.5">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i> Test Send
                            </button>
                        @endpermission

                        <!-- Edit Button -->
                        @permission('smtp.update')
                            <button @click="openEditModal({{ json_encode($s) }})"
                                    class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                        @endpermission

                        <!-- Delete Button -->
                        @permission('smtp.delete')
                            <form method="POST" action="{{ route('smtp.destroy', $s->id) }}" onsubmit="return confirm('Are you sure you want to delete this SMTP relay?')">
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

    <!-- ADD / EDIT MODAL -->
    <div x-show="formModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="formModalOpen = false"></div>
        <div class="relative w-full max-w-xl bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white" x-text="isEditing ? 'Edit SMTP Relay' : 'Connect New SMTP Relay'"></h3>
                <button @click="formModalOpen = false" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form :action="formAction" method="POST" class="space-y-4">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Relay Title / Label</label>
                    <input type="text" name="title" x-model="formData.title" required placeholder="e.g. Primary Amazon SES"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">SMTP Host</label>
                        <input type="text" name="host" x-model="formData.host" required placeholder="smtp.mailgun.org"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Port</label>
                        <input type="number" name="port" x-model="formData.port" required placeholder="587"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Encryption</label>
                        <select name="encryption" x-model="formData.encryption" class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                            <option value="tls">TLS (Port 587 Recommended)</option>
                            <option value="ssl">SSL (Port 465)</option>
                            <option value="starttls">STARTTLS</option>
                            <option value="none">None (Port 25)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Username / Key</label>
                        <input type="text" name="username" x-model="formData.username" placeholder="api or username"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">SMTP Password / API Secret</label>
                    <input type="password" name="password" x-model="formData.password" placeholder="••••••••••••"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Sender Name (From Name)</label>
                        <input type="text" name="from_name" x-model="formData.from_name" placeholder="Acme News"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">From Email Address</label>
                        <input type="email" name="from_email" x-model="formData.from_email" placeholder="news@yourdomain.com"
                               class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Reply-To Email (Optional)</label>
                    <input type="email" name="reply_to" x-model="formData.reply_to" placeholder="support@yourdomain.com"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                        <input type="checkbox" name="is_default" value="1" x-model="formData.is_default" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-brand-600">
                        <span>Set as Default SMTP Sender</span>
                    </label>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3">
                    <button type="button" @click="formModalOpen = false" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg transition">Save SMTP Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TEST SEND & DIAGNOSTIC MODAL -->
    <div x-show="testModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="testModalOpen = false"></div>
        <div class="relative w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <i data-lucide="send" class="w-5 h-5 text-brand-400"></i> Test SMTP Dispatch
                    </h3>
                    <p class="text-xs text-slate-400" x-text="'Testing Relay: ' + testSmtpTitle"></p>
                </div>
                <button @click="testModalOpen = false" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Target Test Email Address</label>
                    <input type="email" x-model="testEmail" placeholder="your-email@gmail.com"
                           class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                <button @click="runTestSend()" :disabled="testing"
                        class="w-full py-3 px-4 bg-brand-600 hover:bg-brand-500 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                    <template x-if="testing">
                        <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full border-2 border-white border-t-transparent animate-spin"></span> Connecting & Handshaking...</span>
                    </template>
                    <template x-if="!testing">
                        <span>Send Diagnostic Email</span>
                    </template>
                </button>

                <!-- Handshake Diagnostic Console -->
                <div x-show="testLogs.length > 0" class="mt-4 p-4 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs max-h-56 overflow-y-auto">
                    <p class="text-slate-500 text-[10px] uppercase font-bold mb-2">Live SMTP Handshake Logs:</p>
                    <template x-for="log in testLogs" :key="log">
                        <p class="leading-relaxed" :class="log.includes('ERROR') ? 'text-rose-400' : (log.includes('250') || log.includes('successfully') ? 'text-emerald-400' : 'text-slate-300')" x-text="log"></p>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function smtpManager() {
        return {
            formModalOpen: false,
            testModalOpen: false,
            isEditing: false,
            formAction: "{{ route('smtp.store') }}",
            testSmtpId: null,
            testSmtpTitle: "",
            testEmail: "",
            testing: false,
            testLogs: [],
            formData: {
                title: "",
                host: "",
                port: 587,
                encryption: "tls",
                username: "",
                password: "",
                from_name: "",
                from_email: "",
                reply_to: "",
                is_default: false,
            },
            openAddModal() {
                this.isEditing = false;
                this.formAction = "{{ route('smtp.store') }}";
                this.formData = {
                    title: "",
                    host: "",
                    port: 587,
                    encryption: "tls",
                    username: "",
                    password: "",
                    from_name: "",
                    from_email: "",
                    reply_to: "",
                    is_default: false,
                };
                this.formModalOpen = true;
            },
            openEditModal(smtp) {
                this.isEditing = true;
                this.formAction = "/smtp/" + smtp.id;
                this.formData = {
                    title: smtp.title,
                    host: smtp.host,
                    port: smtp.port,
                    encryption: smtp.encryption,
                    username: smtp.username,
                    password: "",
                    from_name: smtp.from_name,
                    from_email: smtp.from_email,
                    reply_to: smtp.reply_to,
                    is_default: Boolean(smtp.is_default),
                };
                this.formModalOpen = true;
            },
            openTestModal(id, title) {
                this.testSmtpId = id;
                this.testSmtpTitle = title;
                this.testLogs = [];
                this.testModalOpen = true;
            },
            async runTestSend() {
                if (!this.testEmail) {
                    alert("Please enter a target recipient email address.");
                    return;
                }
                this.testing = true;
                this.testLogs = ["[00:00:00] Initializing diagnostic request..."];
                
                try {
                    const response = await fetch(`/smtp/${this.testSmtpId}/test-send`, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                            "Accept": "application/json"
                        },
                        body: JSON.stringify({ test_email: this.testEmail })
                    });
                    const data = await response.json();
                    this.testLogs = data.logs || [data.message];
                } catch (e) {
                    this.testLogs.push("[ERROR] Network or server communication error: " + e.message);
                } finally {
                    this.testing = false;
                }
            }
        }
    }
</script>
@endpush