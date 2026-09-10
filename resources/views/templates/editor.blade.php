@extends('layouts.app')
@section('header', $template->exists ? 'Edit Template' : 'New Template')

@section('content')
<div class="space-y-6" x-data="templateEditor()" x-cloak>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('templates.index') }}" class="hover:text-brand-400">Templates</a>
                <span>/</span>
                <span class="text-white">{{ $template->exists ? "Edit Template" : "New Template" }}</span>
            </div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="layout-template" class="w-6 h-6 text-brand-400"></i>
                <span>{{ $template->exists ? "Edit: " . $template->name : "Create Email Template" }}</span>
            </h2>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 mr-1">
                <button type="button" @click="mode = 'design'"
                        :class="mode === 'design' ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-white'"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                    <i data-lucide="palette" class="w-3.5 h-3.5"></i> Design
                </button>
                <button type="button" @click="mode = 'html'"
                        :class="mode === 'html' ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-white'"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                    <i data-lucide="code" class="w-3.5 h-3.5"></i> HTML
                </button>
            </div>
            <button type="button" @click="saveTemplate()"
                    class="px-4 sm:px-6 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs sm:text-sm rounded-xl shadow-lg transition flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Save Template
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 sm:gap-6">

        <!-- Left: Editor -->
        <div class="xl:col-span-5 space-y-4">
            <form id="templateForm" method="POST" action="{{ $template->exists ? route('templates.update', $template->id) : route('templates.store') }}">
                @csrf
                @if($template->exists) @method('PUT') @endif

                <input type="hidden" name="editor_mode" :value="mode">
                <input type="hidden" name="logo_path" :value="logoPath">
                <input type="hidden" name="body_html" :value="mode === 'html' ? bodyHtml : ''">
                <input type="hidden" name="design_json" :value="JSON.stringify(design)">

                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Template name</label>
                        <input type="text" name="name" x-model="name" required placeholder="e.g. Monthly Newsletter"
                               class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Email subject line</label>
                        <input type="text" name="subject" x-model="subject" @input="schedulePreview()" required placeholder="e.g. Your September update"
                               class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        <p class="mt-1.5 text-[11px] text-slate-500">Merge tags work here too, e.g. <code class="text-brand-300">@{{first_name}}</code></p>
                    </div>
                </div>

                <!-- ===================== DESIGN MODE ===================== -->
                <div x-show="mode === 'design'" class="space-y-3 mt-4">

                    <!-- Which device the controls below are editing -->
                    <div x-show="isMobile" x-cloak
                         class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-200">
                        <div class="flex items-start gap-2.5">
                            <i data-lucide="smartphone" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-bold">Editing the mobile layout</p>
                                <p class="text-[11px] text-amber-200/80 mt-0.5 leading-relaxed">
                                    Font size, alignment and spacing changes apply on phones only — your desktop design stays as it is.
                                </p>
                                <button type="button" x-show="hasAnyMobileOverride" @click="clearMobileOverrides()"
                                        class="mt-2 px-2.5 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-100 text-[11px] font-semibold border border-amber-500/25 transition">
                                    Clear mobile overrides
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="!isMobile && hasAnyMobileOverride" x-cloak
                         class="p-3 rounded-xl bg-slate-800/60 border border-slate-700 text-slate-300 flex items-center gap-2.5">
                        <i data-lucide="smartphone" class="w-4 h-4 flex-shrink-0 text-brand-400"></i>
                        <p class="text-[11px] leading-relaxed">
                            This template has mobile-only overrides. Switch the preview to
                            <span class="font-semibold text-white">Mobile</span> to edit them.
                        </p>
                    </div>


                    <!-- Brand: logo -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'logo' ? '' : 'logo'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="image" class="w-4 h-4 text-brand-400"></i> Logo
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500 transition" :class="panel === 'logo' && 'rotate-180'"></i>
                        </button>
                        <div x-show="panel === 'logo'" class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-3.5 border-t border-slate-800 pt-4">
                            <div class="flex items-start gap-3">
                                <div class="w-24 h-16 rounded-lg bg-slate-950 border border-slate-800 flex items-center justify-center overflow-hidden flex-shrink-0">
                                    <img x-show="logoUrl" :src="logoUrl" alt="" class="max-w-full max-h-full object-contain">
                                    <i x-show="!logoUrl" data-lucide="image" class="w-5 h-5 text-slate-700"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <input type="file" id="logoInput" accept="image/png,image/jpeg,image/gif,image/webp" class="hidden" @change="uploadLogo($event)">
                                    <div class="flex gap-2">
                                        <button type="button" @click="$refs.nothing; document.getElementById('logoInput').click()"
                                                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition">
                                            <span x-text="logoUrl ? 'Replace' : 'Upload logo'"></span>
                                        </button>
                                        <button type="button" x-show="logoUrl" @click="removeLogo()"
                                                class="px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 text-xs font-semibold rounded-lg border border-rose-500/20 transition">
                                            Remove
                                        </button>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-slate-500">PNG, JPG, GIF or WebP · max 2MB</p>
                                    <p x-show="logoError" x-text="logoError" class="mt-1 text-[11px] text-rose-400"></p>
                                </div>
                            </div>
                            <div x-show="logoUrl" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Width: <span x-text="design.logo_width + 'px'" class="text-slate-200"></span></label>
                                    <input type="range" min="60" max="400" step="10" x-model.number="design.logo_width" @input="schedulePreview()" class="w-full accent-brand-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">
                                        Alignment
                                        <span x-show="isMobile" class="text-amber-400">(mobile)</span>
                                    </label>
                                    <select :value="logoAlign" @change="logoAlign = $event.target.value; schedulePreview()" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                        <option value="left">Left</option>
                                        <option value="center">Center</option>
                                        <option value="right">Right</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colors -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'colors' ? '' : 'colors'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="palette" class="w-4 h-4 text-brand-400"></i> Colors
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-4 h-4 rounded border border-slate-700" :style="'background:' + design.brand_color"></span>
                                <span class="w-4 h-4 rounded border border-slate-700" :style="'background:' + design.background_color"></span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500 ml-1" :class="panel === 'colors' && 'rotate-180'"></i>
                            </span>
                        </button>
                        <div x-show="panel === 'colors'" class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-3 border-t border-slate-800 pt-4">
                            <template x-for="c in colorFields" :key="c.key">
                                <div class="flex items-center justify-between gap-3">
                                    <label class="text-xs text-slate-300" x-text="c.label"></label>
                                    <div class="flex items-center gap-2">
                                        <input type="text" x-model="design[c.key]" @input="schedulePreview()"
                                               class="w-20 px-2 py-1 bg-slate-950 border border-slate-800 rounded text-white text-[11px] font-mono focus:ring-1 focus:ring-brand-500 focus:outline-none">
                                        <input type="color" x-model="design[c.key]" @input="schedulePreview()"
                                               class="w-8 h-8 rounded cursor-pointer bg-slate-950 border border-slate-800">
                                    </div>
                                </div>
                            </template>
                            <div class="pt-2 border-t border-slate-800">
                                <p class="text-[11px] text-slate-400 mb-2">Quick palettes</p>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="p in palettes" :key="p.name">
                                        <button type="button" @click="applyPalette(p)" :title="p.name"
                                                class="flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-700 hover:border-slate-500 transition">
                                            <span class="w-3 h-3 rounded-full" :style="'background:' + p.brand_color"></span>
                                            <span class="text-[11px] text-slate-300" x-text="p.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Typography -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'type' ? '' : 'type'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="type" class="w-4 h-4 text-brand-400"></i> Typography
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-400" x-text="design.font_family"></span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500" :class="panel === 'type' && 'rotate-180'"></i>
                            </span>
                        </button>
                        <div x-show="panel === 'type'" class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-3.5 border-t border-slate-800 pt-4">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Font family <span class="text-slate-600">(email-safe)</span></label>
                                <select x-model="design.font_family" @change="schedulePreview()"
                                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                    @foreach($fonts as $label => $stack)
                                        <option value="{{ $label }}" style="font-family: {{ $stack }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">
                                        Body: <span x-text="fontSize + 'px'" class="text-slate-200"></span>
                                        <span x-show="isMobile && design.mobile.font_size === null" class="text-slate-600">(inherited)</span>
                                    </label>
                                    <input type="range" min="12" max="20" :value="fontSize"
                                           @input="fontSize = $event.target.value; schedulePreview()" class="w-full accent-brand-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">
                                        Heading: <span x-text="headingSize + 'px'" class="text-slate-200"></span>
                                        <span x-show="isMobile && design.mobile.heading_size === null" class="text-slate-600">(inherited)</span>
                                    </label>
                                    <input type="range" min="18" max="40" :value="headingSize"
                                           @input="headingSize = $event.target.value; schedulePreview()" class="w-full accent-brand-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Text alignment</label>
                                <div class="flex gap-1.5">
                                    <template x-for="a in ['left','center','right']" :key="a">
                                        <button type="button" @click="design.align = a; schedulePreview()"
                                                :class="design.align === a ? 'bg-brand-600 text-white border-brand-500' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-white'"
                                                class="flex-1 px-3 py-1.5 text-xs font-semibold rounded-lg border capitalize transition" x-text="a"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'content' ? '' : 'content'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="align-left" class="w-4 h-4 text-brand-400"></i> Content
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500" :class="panel === 'content' && 'rotate-180'"></i>
                        </button>
                        <div x-show="panel === 'content'" class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-3.5 border-t border-slate-800 pt-4">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Preview text <span class="text-slate-600">(inbox snippet)</span></label>
                                <input type="text" x-model="design.preheader" @input="schedulePreview()" placeholder="Shown next to the subject in the inbox"
                                       class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Heading</label>
                                <input type="text" x-model="design.heading" @input="schedulePreview()"
                                       class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Body text</label>
                                <textarea x-model="design.body_text" @input="schedulePreview()" rows="5"
                                          class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm leading-relaxed focus:ring-2 focus:ring-brand-500 focus:outline-none"></textarea>
                                <p class="mt-1.5 text-[11px] text-slate-500">Blank line = new paragraph.</p>
                            </div>
                            <div class="pt-1">
                                <span class="block text-[11px] font-medium text-slate-400 mb-1.5">Insert merge tag</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="tag in mergeTags" :key="tag">
                                        <button type="button" @click="insertMergeTag(tag)"
                                                class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-brand-300 rounded-md text-[11px] font-mono border border-slate-700 transition"
                                                x-text="tagLabel(tag)"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Button -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'button' ? '' : 'button'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="mouse-pointer-click" class="w-4 h-4 text-brand-400"></i> Call-to-action button
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500" :class="panel === 'button' && 'rotate-180'"></i>
                        </button>
                        <div x-show="panel === 'button'" class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-3.5 border-t border-slate-800 pt-4">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" x-model="design.show_button" @change="schedulePreview()" class="rounded accent-brand-500">
                                <span class="text-xs text-slate-300">Show button</span>
                            </label>
                            <div x-show="design.show_button" class="space-y-3">
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Button label</label>
                                    <input type="text" x-model="design.button_text" @input="schedulePreview()"
                                           class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Link URL</label>
                                    <input type="url" x-model="design.button_url" @input="schedulePreview()" placeholder="https://"
                                           class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm font-mono focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Corner radius: <span x-text="design.radius + 'px'" class="text-slate-200"></span></label>
                                    <input type="range" min="0" max="32" x-model.number="design.radius" @input="schedulePreview()" class="w-full accent-brand-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Position & spacing (per section) -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'spacing' ? '' : 'spacing'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="move" class="w-4 h-4 text-brand-400"></i> Position &amp; spacing
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500" :class="panel === 'spacing' && 'rotate-180'"></i>
                        </button>

                        <div x-show="panel === 'spacing'" class="border-t border-slate-800">
                            <div class="flex items-center gap-1 px-4 pt-4 pb-3 flex-wrap">
                                <template x-for="sec in sectionList" :key="sec.key">
                                    <button type="button" @click="activeSection = sec.key"
                                            :class="activeSection === sec.key ? 'bg-brand-600 text-white border-brand-500' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-white'"
                                            class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border transition" x-text="sec.label"></button>
                                </template>
                            </div>

                            <div class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-4">
                                <!-- Horizontal position -->
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-400 mb-1.5">Align content</label>
                                    <div class="flex gap-1.5">
                                        <template x-for="a in ['left','center','right']" :key="a">
                                            <button type="button" @click="secAlign = a; schedulePreview()"
                                                    :class="secAlign === a ? 'bg-brand-600 text-white border-brand-500' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-white'"
                                                    class="flex-1 px-3 py-1.5 text-xs font-semibold rounded-lg border capitalize transition" x-text="a"></button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Nudge pad, mirrors the box model -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="text-[11px] font-medium text-slate-400">Spacing (px)</label>
                                        <button type="button" @click="resetSection()" class="text-[11px] text-slate-500 hover:text-slate-300">Reset</button>
                                    </div>

                                    <div class="bg-slate-950 border border-slate-800 rounded-xl p-2 sm:p-3 overflow-x-auto">
                                        <div class="flex justify-center mb-1.5">
                                            <div class="flex items-center gap-0.5 sm:gap-1">
                                                <button type="button" @click="nudge('pt', -4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">&minus;</button>
                                                <input type="number" min="0" max="200" :value="secPt"
                                                       @input="secPt = $event.target.value; schedulePreview()"
                                                       class="w-11 sm:w-14 px-1 py-1 bg-slate-900 border border-slate-800 rounded text-white text-[11px] text-center font-mono focus:ring-1 focus:ring-brand-500 focus:outline-none">
                                                <button type="button" @click="nudge('pt', 4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">+</button>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-center gap-1.5 sm:gap-2">
                                            <div class="flex items-center gap-0.5 sm:gap-1">
                                                <button type="button" @click="nudge('pl', -4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">&minus;</button>
                                                <input type="number" min="0" max="200" :value="secPl"
                                                       @input="secPl = $event.target.value; schedulePreview()"
                                                       class="w-11 sm:w-14 px-1 py-1 bg-slate-900 border border-slate-800 rounded text-white text-[11px] text-center font-mono focus:ring-1 focus:ring-brand-500 focus:outline-none">
                                                <button type="button" @click="nudge('pl', 4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">+</button>
                                            </div>

                                            <div class="w-11 sm:w-14 h-9 rounded border border-dashed border-slate-700 flex items-center justify-center flex-shrink-0">
                                                <span class="text-[10px] text-slate-500" x-text="sectionLabel()"></span>
                                            </div>

                                            <div class="flex items-center gap-0.5 sm:gap-1">
                                                <button type="button" @click="nudge('pr', -4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">&minus;</button>
                                                <input type="number" min="0" max="200" :value="secPr"
                                                       @input="secPr = $event.target.value; schedulePreview()"
                                                       class="w-11 sm:w-14 px-1 py-1 bg-slate-900 border border-slate-800 rounded text-white text-[11px] text-center font-mono focus:ring-1 focus:ring-brand-500 focus:outline-none">
                                                <button type="button" @click="nudge('pr', 4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">+</button>
                                            </div>
                                        </div>

                                        <div class="flex justify-center mt-1.5">
                                            <div class="flex items-center gap-0.5 sm:gap-1">
                                                <button type="button" @click="nudge('pb', -4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">&minus;</button>
                                                <input type="number" min="0" max="200" :value="secPb"
                                                       @input="secPb = $event.target.value; schedulePreview()"
                                                       class="w-11 sm:w-14 px-1 py-1 bg-slate-900 border border-slate-800 rounded text-white text-[11px] text-center font-mono focus:ring-1 focus:ring-brand-500 focus:outline-none">
                                                <button type="button" @click="nudge('pb', 4)" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs leading-none">+</button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-slate-500">Top / right / bottom / left padding for the selected section.</p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-2 pt-1">
                                    <button type="button" @click="applySpacingToAll()"
                                            class="flex-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-[11px] font-semibold rounded-lg border border-slate-700 transition">
                                        Apply side padding to all
                                    </button>
                                    <button type="button" @click="resetAllSections()"
                                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold rounded-lg border border-slate-700 transition">
                                        Reset all
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout & footer -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
                        <button type="button" @click="panel = panel === 'layout' ? '' : 'layout'"
                                class="w-full flex items-center justify-between px-4 sm:px-5 py-3.5 text-left hover:bg-slate-800/40 transition">
                            <span class="flex items-center gap-2.5 text-sm font-semibold text-white">
                                <i data-lucide="layout" class="w-4 h-4 text-brand-400"></i> Layout &amp; footer
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500" :class="panel === 'layout' && 'rotate-180'"></i>
                        </button>
                        <div x-show="panel === 'layout'" class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-3.5 border-t border-slate-800 pt-4">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Content width: <span x-text="design.content_width + 'px'" class="text-slate-200"></span></label>
                                <input type="range" min="480" max="760" step="20" x-model.number="design.content_width" @input="schedulePreview()" class="w-full accent-brand-500">
                            </div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" x-model="design.show_divider" @change="schedulePreview()" class="rounded accent-brand-500">
                                <span class="text-xs text-slate-300">Divider above footer</span>
                            </label>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">Footer text</label>
                                <textarea x-model="design.footer_text" @input="schedulePreview()" rows="2"
                                          class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none"></textarea>
                            </div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" x-model="design.show_address" @change="schedulePreview()" class="rounded accent-brand-500">
                                <span class="text-xs text-slate-300">Show postal address <span class="text-slate-500">(helps deliverability)</span></span>
                            </label>
                            <div x-show="design.show_address">
                                <input type="text" x-model="design.address_text" @input="schedulePreview()"
                                       class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===================== HTML MODE ===================== -->
                <div x-show="mode === 'html'" class="mt-4">
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-5">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Raw HTML</label>
                            <button type="button" @click="loadDesignIntoHtml()" class="text-[11px] text-brand-400 hover:text-brand-300">Copy design HTML in</button>
                        </div>
                        <textarea id="bodyHtmlInput" x-model="bodyHtml" @input="schedulePreview()" rows="18"
                                  class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs font-mono leading-relaxed focus:ring-2 focus:ring-brand-500 focus:outline-none"></textarea>
                        <p class="mt-2 text-[11px] text-slate-500">Full control. Use inline CSS and tables for reliable rendering.</p>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right: Live preview -->
        <div class="xl:col-span-7">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-5 xl:sticky xl:top-4 xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i data-lucide="eye" class="w-4 h-4 text-brand-400"></i> Live preview
                        <span x-show="loading" class="w-3 h-3 rounded-full border-2 border-brand-400 border-t-transparent animate-spin"></span>
                    </h3>
                    <span x-show="previewScale < 0.99" x-cloak
                          class="ml-auto mr-2 px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-[10px] font-mono text-slate-500"
                          :title="'Scaled to fit. The email still renders at ' + previewFrameWidth + 'px.'"
                          x-text="Math.round(previewScale * 100) + '%'"></span>
                    <div class="flex items-center bg-slate-950 p-1 rounded-lg border border-slate-800">
                        <button type="button" @click="previewDevice = 'desktop'" :class="previewDevice === 'desktop' ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-white'" class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition flex items-center gap-1.5">
                            <i data-lucide="monitor" class="w-3.5 h-3.5"></i> Desktop
                        </button>
                        <button type="button" @click="previewDevice = 'mobile'" :class="previewDevice === 'mobile' ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-white'" class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition flex items-center gap-1.5">
                            <i data-lucide="smartphone" class="w-3.5 h-3.5"></i> Mobile
                        </button>
                    </div>
                </div>

                <div class="my-4 px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield" class="w-4 h-4" :class="spamScore > 50 ? 'text-rose-400' : 'text-emerald-400'"></i>
                        <span class="text-slate-400">Spam risk</span>
                        <span class="font-semibold" :class="spamScore > 50 ? 'text-rose-400' : 'text-emerald-400'"
                              x-text="spamScore > 50 ? 'High' : (spamScore > 25 ? 'Moderate' : 'Low')"></span>
                    </div>
                    <span class="text-slate-500 font-mono text-[11px]" x-text="spamScore + '/100'"></span>
                </div>

                <!-- A real email is a fixed pixel width, so scale it to fit rather than clip or reflow it. -->
                <div id="mfPreviewViewport" x-ref="previewViewport" class="bg-slate-950 p-2 sm:p-4 rounded-xl border border-slate-800 overflow-x-auto">
                    <div class="mx-auto transition-all duration-300"
                         :style="'width:' + Math.ceil(previewFrameWidth * previewScale) + 'px;height:' + Math.ceil(previewFrameHeight * previewScale) + 'px'">
                        <div id="mfPreviewFrame" x-ref="previewFrame"
                             :class="previewDevice === 'desktop' ? '' : 'border-4 border-slate-700 rounded-2xl overflow-hidden'"
                             :style="'width:' + previewFrameWidth + 'px;transform:scale(' + previewScale + ')'"
                             class="origin-top-left transition-all duration-300 flex flex-col">
                            <div class="bg-slate-900 px-3 py-2 border-b border-slate-800 text-[11px] text-slate-300 truncate font-mono flex-shrink-0">
                                <span class="text-slate-500">Subject:</span> <span x-text="renderedSubject || subject || '(no subject)'"></span>
                            </div>
                            <iframe x-ref="preview" sandbox class="w-full bg-white flex-1" style="min-height:470px; border:0;" title="Email preview"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@php
    // Merge tags live in @php so Blade's {{ }} compiler leaves them alone.
    $mergeTagList = ['first_name', 'last_name', 'name', 'email', 'company', 'unsubscribe_url'];
    $defaultSubject = 'Your update from {{company}}';
@endphp

@push('scripts')
<script>
    function templateEditor() {
        return {
            mode: {{ Illuminate\Support\Js::from($template->exists && !$template->design ? 'html' : 'design') }},
            panel: 'content',
            name: {{ Illuminate\Support\Js::from($template->name ?? '') }},
            subject: {{ Illuminate\Support\Js::from($template->subject ?? $defaultSubject) }},
            bodyHtml: {{ Illuminate\Support\Js::from($template->body_html ?? '') }},
            design: {{ Illuminate\Support\Js::from($designDefaults) }},
            logoPath: {{ Illuminate\Support\Js::from($template->logo_path ?? '') }},
            logoUrl: {{ Illuminate\Support\Js::from($logoUrl) }},
            logoError: '',
            mergeTags: {{ Illuminate\Support\Js::from($mergeTagList) }},
            renderedSubject: '',
            spamScore: 0,
            renderedHtml: '',
            previewDevice: 'desktop',
            previewViewportWidth: 0,
            previewFrameHeight: 502,   // subject bar + the iframe's 470px min-height; corrected on first measure
            _previewRO: null,
            loading: false,
            _timer: null,
            _inflight: null,
            activeSection: 'heading',
            sectionDefaults: {{ Illuminate\Support\Js::from(App\Services\EmailTemplateBuilderService::DEFAULTS['sections']) }},

            sectionList: [
                { key: 'logo',    label: 'Logo' },
                { key: 'heading', label: 'Heading' },
                { key: 'body',    label: 'Body' },
                { key: 'button',  label: 'Button' },
                { key: 'divider', label: 'Divider' },
                { key: 'footer',  label: 'Footer' },
            ],

            colorFields: [
                { key: 'brand_color',       label: 'Brand / button' },
                { key: 'background_color',  label: 'Page background' },
                { key: 'card_color',        label: 'Card background' },
                { key: 'heading_color',     label: 'Heading text' },
                { key: 'text_color',        label: 'Body text' },
                { key: 'button_text_color', label: 'Button label' },
                { key: 'muted_color',       label: 'Footer text' },
            ],

            palettes: [
                { name: 'Indigo',  brand_color: '#4f46e5', background_color: '#f1f5f9', heading_color: '#0f172a', text_color: '#334155' },
                { name: 'Emerald', brand_color: '#059669', background_color: '#f0fdf4', heading_color: '#064e3b', text_color: '#374151' },
                { name: 'Rose',    brand_color: '#e11d48', background_color: '#fff1f2', heading_color: '#881337', text_color: '#3f3f46' },
                { name: 'Amber',   brand_color: '#d97706', background_color: '#fffbeb', heading_color: '#78350f', text_color: '#44403c' },
                { name: 'Slate',   brand_color: '#0f172a', background_color: '#f8fafc', heading_color: '#0f172a', text_color: '#334155' },
                { name: 'Sky',     brand_color: '#0284c7', background_color: '#f0f9ff', heading_color: '#0c4a6e', text_color: '#334155' },
            ],

            init() {
                // Templates saved before per-section spacing existed have no sections key.
                if (!this.design.sections) {
                    this.design.sections = JSON.parse(JSON.stringify(this.sectionDefaults));
                }
                this.renderPreview();
                this.$watch('mode', () => this.renderPreview());
                this.$nextTick(() => this.watchPreviewSize());
            },

            sectionLabel() {
                return this.sectionList.find(s => s.key === this.activeSection)?.label ?? '';
            },

            /*
             * Device-aware editing.
             *
             * In desktop view the controls write the design's own values. In
             * mobile view they write design.mobile instead, which the rendered
             * email applies through a max-width:600px media query — so tuning
             * the phone layout never touches what desktop inboxes show.
             * A null override means "inherit the desktop value".
             */
            get isMobile() {
                return this.previewDevice === 'mobile';
            },

            /*
             * The mobile rules live behind max-width:600px, so the desktop
             * preview frame is never allowed below that — otherwise a narrow
             * content_width would make the desktop preview show mobile styling.
             */
            get desktopPreviewWidth() {
                return Math.max(this.design.content_width + 40, 620);
            },

            /*
             * The frame is scaled with a CSS transform, which is paint-only: its
             * layout width - and so the iframe's own viewport - stays at the true
             * pixel width. That is what keeps the 620px floor above meaningful, since
             * the email's max-width:600px rules still see 620px. Never use CSS `zoom`
             * here: zoom does affect layout, would take the iframe's inner viewport
             * under 600px, and would show mobile styling in the desktop view.
             *
             * Note this only reads previewDevice. Nothing may write it but the
             * Desktop/Mobile buttons: previewDevice also decides whether the controls
             * write design.mobile.* or design.*, so changing it on the user's behalf
             * would silently record phone-only overrides they never asked for.
             */
            get previewFrameWidth() {
                return this.previewDevice === 'desktop' ? this.desktopPreviewWidth : 375;
            },

            get previewScale() {
                if (!this.previewViewportWidth) return 1;
                return Math.min(1, this.previewViewportWidth / this.previewFrameWidth);
            },

            /*
             * The frame keeps its true size; the wrapper reserves the scaled size.
             * Elements are looked up by id rather than $refs: $refs is not reliably
             * populated at the point this runs, and a missed measurement would leave
             * previewViewportWidth at 0, which the scale getter reads as "unmeasured"
             * and renders unscaled - the exact overflow this is here to prevent.
             */
            measurePreview() {
                const vp = document.getElementById('mfPreviewViewport');
                const fr = document.getElementById('mfPreviewFrame');
                if (vp) {
                    const cs = getComputedStyle(vp);
                    const inner = vp.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
                    if (inner > 0) this.previewViewportWidth = inner;
                }
                if (fr && Math.abs(fr.offsetHeight - this.previewFrameHeight) > 1) {
                    this.previewFrameHeight = fr.offsetHeight;
                }
            },

            watchPreviewSize() {
                this.measurePreview();
                if (typeof ResizeObserver === 'undefined') return;
                this._previewRO = new ResizeObserver(() => this.measurePreview());
                const vp = document.getElementById('mfPreviewViewport');
                const fr = document.getElementById('mfPreviewFrame');
                if (vp) this._previewRO.observe(vp);
                if (fr) this._previewRO.observe(fr);
                window.addEventListener('resize', this._previewResize = () => this.measurePreview());
            },

            destroy() {
                this._previewRO?.disconnect();
                if (this._previewResize) window.removeEventListener('resize', this._previewResize);
            },

            get mobileSection() {
                return this.design.mobile.sections[this.activeSection];
            },

            get fontSize() {
                return this.isMobile ? (this.design.mobile.font_size ?? this.design.font_size) : this.design.font_size;
            },
            set fontSize(v) {
                if (this.isMobile) this.design.mobile.font_size = Number(v);
                else this.design.font_size = Number(v);
            },

            get headingSize() {
                return this.isMobile ? (this.design.mobile.heading_size ?? this.design.heading_size) : this.design.heading_size;
            },
            set headingSize(v) {
                if (this.isMobile) this.design.mobile.heading_size = Number(v);
                else this.design.heading_size = Number(v);
            },

            /*
             * The logo panel and the "Logo" section in the spacing panel are two
             * views of the same value, so both write design.sections.logo.align
             * (the only alignment the renderer reads), mobile-aware like the rest.
             */
            get logoAlign() {
                return this.isMobile
                    ? (this.design.mobile.sections.logo.align ?? this.design.sections.logo.align)
                    : this.design.sections.logo.align;
            },
            set logoAlign(v) {
                if (this.isMobile) {
                    this.design.mobile.sections.logo.align = v;
                } else {
                    this.design.sections.logo.align = v;
                    this.design.logo_align = v; // kept in step for older saved designs
                }
            },

            get secAlign() {
                return this.isMobile
                    ? (this.mobileSection.align ?? this.design.sections[this.activeSection].align)
                    : this.design.sections[this.activeSection].align;
            },
            set secAlign(v) {
                if (this.isMobile) this.mobileSection.align = v;
                else this.design.sections[this.activeSection].align = v;
            },

            padValue(side) {
                return this.isMobile
                    ? (this.mobileSection[side] ?? this.design.sections[this.activeSection][side])
                    : this.design.sections[this.activeSection][side];
            },
            setPad(side, v) {
                const n = Math.max(0, Math.min(200, Number(v) || 0));
                if (this.isMobile) this.mobileSection[side] = n;
                else this.design.sections[this.activeSection][side] = n;
            },

            get secPt() { return this.padValue('pt'); },
            set secPt(v) { this.setPad('pt', v); },
            get secPr() { return this.padValue('pr'); },
            set secPr(v) { this.setPad('pr', v); },
            get secPb() { return this.padValue('pb'); },
            set secPb(v) { this.setPad('pb', v); },
            get secPl() { return this.padValue('pl'); },
            set secPl(v) { this.setPad('pl', v); },

            /** True when the current section carries any mobile-only value. */
            get sectionHasMobileOverride() {
                return ['align', 'pt', 'pr', 'pb', 'pl'].some(k => this.mobileSection[k] !== null);
            },

            get hasAnyMobileOverride() {
                const m = this.design.mobile;
                if (m.font_size !== null || m.heading_size !== null) return true;
                return Object.values(m.sections).some(sec => Object.values(sec).some(v => v !== null));
            },

            /** Drops every mobile override so the phone inherits desktop again. */
            clearMobileOverrides() {
                this.design.mobile.font_size = null;
                this.design.mobile.heading_size = null;
                Object.keys(this.design.mobile.sections).forEach(k => {
                    ['align', 'pt', 'pr', 'pb', 'pl'].forEach(f => { this.design.mobile.sections[k][f] = null; });
                });
                this.renderPreview();
            },

            nudge(side, delta) {
                this.setPad(side, (Number(this.padValue(side)) || 0) + delta);
                this.schedulePreview();
            },

            resetSection() {
                if (this.isMobile) {
                    ['align', 'pt', 'pr', 'pb', 'pl'].forEach(f => { this.mobileSection[f] = null; });
                } else {
                    this.design.sections[this.activeSection] = { ...this.sectionDefaults[this.activeSection] };
                }
                this.renderPreview();
            },

            resetAllSections() {
                if (this.isMobile) {
                    Object.keys(this.design.mobile.sections).forEach(k => {
                        ['align', 'pt', 'pr', 'pb', 'pl'].forEach(f => { this.design.mobile.sections[k][f] = null; });
                    });
                } else {
                    this.design.sections = JSON.parse(JSON.stringify(this.sectionDefaults));
                }
                this.renderPreview();
            },

            // Keeps the left/right gutters consistent down the whole email.
            applySpacingToAll() {
                const pl = this.padValue('pl');
                const pr = this.padValue('pr');
                const target = this.isMobile ? this.design.mobile.sections : this.design.sections;
                Object.keys(target).forEach(k => {
                    target[k].pl = pl;
                    target[k].pr = pr;
                });
                this.renderPreview();
            },

            applyPalette(p) {
                Object.keys(p).forEach(k => { if (k !== 'name') this.design[k] = p[k]; });
                this.renderPreview();
            },

            tagLabel(tag) {
                // Assembled from single braces so Blade's echo compiler never matches here.
                return '{' + '{' + tag + '}' + '}';
            },

            insertMergeTag(tag) {
                this.design.body_text = (this.design.body_text || '') + this.tagLabel(tag);
                this.renderPreview();
            },

            loadDesignIntoHtml() {
                // Read from the last preview response rather than the iframe: the
                // frame is sandboxed to an opaque origin, so its document is not
                // reachable from here.
                this.bodyHtml = this.renderedHtml || this.bodyHtml;
            },

            async uploadLogo(event) {
                const file = event.target.files[0];
                if (!file) return;
                this.logoError = '';

                const fd = new FormData();
                fd.append('logo', file);
                fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                try {
                    const res = await fetch("{{ route('templates.upload-logo') }}", {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: fd,
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        this.logoError = (data.errors?.logo?.[0]) || data.message || 'Upload failed.';
                        return;
                    }
                    this.logoPath = data.logo_path;
                    this.logoUrl = data.preview_url;
                    this.design.has_logo = true;
                    this.panel = 'logo';
                    this.renderPreview();
                } catch (e) {
                    this.logoError = 'Upload failed. Check the file and try again.';
                } finally {
                    event.target.value = '';
                }
            },

            removeLogo() {
                this.logoPath = '';
                this.logoUrl = '';
                this.design.has_logo = false;
                this.renderPreview();
            },

            // Sliders and text inputs fire constantly; coalesce into one request.
            schedulePreview() {
                clearTimeout(this._timer);
                this._timer = setTimeout(() => this.renderPreview(), 250);
            },

            async renderPreview() {
                // Only the newest preview matters: cancel any in-flight request so
                // responses cannot arrive out of order or pile up on the dev server.
                this._inflight?.abort();
                const controller = new AbortController();
                this._inflight = controller;

                this.loading = true;
                try {
                    const payload = {
                        subject: this.subject,
                        body_html: this.mode === 'html' ? this.bodyHtml : '',
                    };
                    if (this.mode === 'design') {
                        payload.design = this.design;
                        payload.logo_path = this.logoPath;
                    }

                    const res = await fetch("{{ route('templates.render-live-preview') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                        signal: controller.signal,
                    });
                    const data = await res.json();
                    this.renderedSubject = data.rendered_subject;
                    this.spamScore = data.spam_analysis.score;

                    // Rendered into a sandboxed iframe via srcdoc: the email's own
                    // CSS cannot leak into the app, and — because the frame gets an
                    // opaque origin with no allow-scripts — markup pasted into HTML
                    // mode cannot run against this page, read the CSRF token, or
                    // issue requests as the signed-in user.
                    this.renderedHtml = data.rendered_html || '';
                    this.$refs.preview.srcdoc = this.renderedHtml
                        || '<p style="font-family:sans-serif;color:#94a3b8;padding:24px">Nothing to preview yet.</p>';
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        console.error('Preview failed', e);
                    }
                } finally {
                    if (this._inflight === controller) {
                        this._inflight = null;
                        this.loading = false;
                    }
                }
            },

            saveTemplate() {
                document.getElementById('templateForm').submit();
            },
        }
    }
</script>
@endpush
