{{-- Crop card grid rendered inside the Filament media edit form. --}}
@php
    $locationOptions = \App\Media\MediaLocation::allAsOptions();
@endphp

<div x-data class="grid grid-cols-2 sm:grid-cols-3 gap-3">
    @foreach ($crops as $c)
        @php
            $cropId       = $c['id'] ?? null;
            $key          = $c['key'] ?? '?';
            $label        = $c['crop']['label'] ?? $key;
            $url          = $c['url'] ?? '';
            $w            = $c['width'] ?? '—';
            $h            = $c['height'] ?? '—';
            $locationKey  = $c['location'] ?? '';
            $locationLabel = $locationKey ? ($locationOptions[$locationKey] ?? $locationKey) : $label;
            $breakpoints  = $c['breakpoints'] ?? [];
            $jsArgs       = json_encode(['id' => $cropId]);
            $jsCropId     = json_encode($cropId);
        @endphp

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-xs flex flex-col">

            {{-- Thumbnail --}}
            <div class="aspect-video bg-gray-100 dark:bg-gray-900 overflow-hidden flex-shrink-0">
                <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-full object-contain" />
            </div>

            {{-- Details + dots menu --}}
            <div class="px-2 pt-2 pb-3 min-w-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-b-lg flex items-start gap-1 mt-auto">
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate text-gray-700 dark:text-gray-200 mb-1">{{ $locationLabel }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:4px;">
                        <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:1px 6px;border-radius:9999px;background:#f3f4f6;color:#374151;">
                            {{ $w }}×{{ $h }}
                        </span>
                        @foreach ($breakpoints as $bp)
                            <x-breakpoint-badge :breakpoint="$bp" />
                        @endforeach
                    </div>
                </div>

                <div class="relative flex-shrink-0" x-data="{ open: false }" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        class="p-1 rounded text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    >
                        <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="w-4 h-4" />
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition
                        class="absolute bottom-full right-0 mb-1 z-50 w-32 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1 text-xs"
                    >
                        <button
                            type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="open = false; $wire.mountFormComponentAction('crops_list', 'edit_crop', {{ $jsArgs }})"
                        >
                            <x-filament::icon icon="heroicon-m-pencil" class="w-3.5 h-3.5 opacity-60" /> Edit
                        </button>
                        <button
                            type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-danger-600 dark:text-danger-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="open = false; $wire.deleteCrop({{ $jsCropId }})"
                        >
                            <x-filament::icon icon="heroicon-m-trash" class="w-3.5 h-3.5 opacity-60" /> Delete
                        </button>
                    </div>
                </div>
            </div>

        </div>
    @endforeach
</div>
