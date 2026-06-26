@php
    $record = $this->getRecord();
    $isImage = str_contains($record->type ?? '', 'image');
    $isVideo = str_contains($record->type ?? '', 'video');
    $isPdf = ($record->ext ?? '') === 'pdf';
@endphp

@include('mediazone::media.partials.pdf-renderer')

<div
    x-data="{ menuOpen: false }"
    class="relative flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm w-1/2"
>
    {{-- Thumbnail --}}
    <div class="checkered bg-gray-100 dark:bg-gray-900 rounded-t-xl overflow-hidden" style="position:relative;padding-top:100%;">
        @if ($isImage)
            <img
                src="{{ $record->url }}"
                alt="{{ $record->alt ?? '' }}"
                class="absolute inset-0 w-full h-full object-contain"
            />
        @elseif ($isVideo)
            <video src="{{ $record->url }}" controls class="absolute inset-0 w-full h-full object-contain"></video>
        @elseif (($record->ext ?? '') === 'pdf')
            <canvas class="absolute inset-0 w-full h-full object-contain mz-listing__pdf-thumb mz-listing__preview-pdf-canvas"
                data-url="{{ route('media.proxy', $record->id) }}"
                width="400" height="400"></canvas>
        @else
            <div class="absolute inset-0 grid place-items-center text-xs text-gray-400">
                <x-filament::icon icon="heroicon-o-document" class="w-10 h-10 opacity-30" />
                <span class="uppercase mt-1">{{ $record->ext ?? '' }}</span>
            </div>
        @endif
    </div>

    {{-- Caption + dots menu --}}
    <div class="px-2 py-1.5 text-xs flex items-center gap-1">
        <div class="flex-1 min-w-0">
            <p class="truncate font-medium text-gray-700 dark:text-gray-200">{{ $record->pretty_name }}</p>
            <div class="flex items-center gap-1.5 mt-0.5">
                <span class="mz-listing__badge mz-listing__badge-{{ strtolower($record->ext ?? '') }}">{{ strtolower($record->ext ?? '—') }}</span>
                <span class="text-gray-400">{{ $record->size_for_humans }}</span>
            </div>
        </div>

        {{-- Dots menu --}}
        <div class="relative flex-shrink-0" x-data="{ open: false }" @click.outside="open = false">
            <button
                type="button"
                @click="open = !open"
                class="p-1 rounded text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            >
                <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="w-4 h-4" />
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition
                class="absolute bottom-full right-0 mb-1 z-50 w-36 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1 text-sm"
            >
                <a
                    href="{{ $record->url }}"
                    target="_blank"
                    class="flex items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="open = false"
                >
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="w-4 h-4 opacity-60" />
                    View
                </a>
                <button
                    type="button"
                    class="w-full flex items-center gap-2 px-3 py-1.5 text-danger-600 dark:text-danger-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="open = false; $wire.mountAction('delete')"
                >
                    <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 opacity-60" />
                    Remove
                </button>
            </div>
        </div>
    </div>
</div>
