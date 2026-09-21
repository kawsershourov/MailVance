@php
    $size ??= 'sm';
    $boxClass = match ($size) {
        'lg' => 'w-14 h-14 rounded-2xl',
        'md' => 'w-11 h-11 rounded-xl',
        default => 'w-9 h-9 rounded-lg',
    };
    $iconPx = match ($size) {
        'lg' => 28,
        'md' => 22,
        default => 18,
    };
@endphp

@if(($siteSettings ?? null)?->logo_path)
    <img src="{{ $siteSettings->logoUrl() }}" alt="{{ $siteSettings->displayTitle() }}"
         class="{{ $boxClass }} object-cover flex-shrink-0">
@else
    <span class="inline-flex items-center justify-center {{ $boxClass }} bg-brand-600 text-white flex-shrink-0">
        <svg width="{{ $iconPx }}" height="{{ $iconPx }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 18 V6 L12 13.5 L20 6 V18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
@endif
