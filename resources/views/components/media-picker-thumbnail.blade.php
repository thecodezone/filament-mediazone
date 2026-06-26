@props([
    'item',           // array — the media item from MediaPicker state
    'uuid',           // string — the picker uuid for event binding
    'locationKey' => null,  // ?string — active location key for crop preview
    'lazy' => false,  // bool — add loading="lazy" to the img tag
])
@php
    $type = $item['type'] ?? '';
    $ext  = $item['ext'] ?? '';

    // Resolve the best thumbnail src, preferring crop URLs when available
    $thumbSrc  = $item['large_url'] ?? $item['url'] ?? '';
    $usingCrop = false;
    if ($locationKey) {
        $cropsByLocation = $item['crops_by_location'] ?? [];
        if (isset($cropsByLocation[$locationKey]['url'])) {
            $thumbSrc  = $cropsByLocation[$locationKey]['url'];
            $usingCrop = true;
        }
    } elseif (! empty($item['selected_crop_url'])) {
        $thumbSrc  = $item['selected_crop_url'];
        $usingCrop = true;
    }
@endphp

<div class="checkered bg-gray-100 dark:bg-gray-900" style="position:relative;padding-top:100%;">
    @if (str($type)->contains('image'))
        <img
            src="{{ $thumbSrc }}"
            alt="{{ $item['alt'] ?? $item['name'] ?? '' }}"
            @if ($lazy) loading="lazy" @endif
            class="absolute inset-0 w-full h-full object-contain"
            x-on:picker-crop-selected.window="if ($event.detail.uuid === '{{ $uuid }}') $el.src = $event.detail.url"
        />
    @elseif (str($type)->contains('video'))
        <video src="{{ $item['url'] ?? '' }}" controls class="absolute inset-0 w-full h-full object-contain"></video>
    @elseif ($ext === 'pdf')
        <div class="absolute inset-0 grid place-items-center overflow-hidden">
            <canvas class="mz-listing__pdf-thumb mz-listing__tile-pdf-canvas max-w-full max-h-full object-contain"
                data-url="{{ isset($item['id']) ? route('media.proxy', $item['id']) : '' }}"></canvas>
        </div>
    @else
        <div class="absolute inset-0 grid place-items-center text-xs text-gray-400">
            <x-filament::icon icon="heroicon-o-document" class="w-10 h-10 opacity-30" />
            <span class="uppercase mt-1">{{ $ext }}</span>
        </div>
    @endif
</div>
