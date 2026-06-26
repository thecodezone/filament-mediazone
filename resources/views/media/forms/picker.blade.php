@php
    $statePath     = $getStatePath();
    $rawItems      = $getState() ?? [];
    // Guard: state must be an array of arrays (normalized mediaArrays keyed by uuid).
    // Raw IDs (int) or un-normalized values arrive during Livewire re-renders — treat as empty.
    $items         = is_array($rawItems) && (empty($rawItems) || is_array(reset($rawItems))) ? $rawItems : [];
    $itemsCount    = count($items);
    $isMultiple    = $isMultiple();
    $maxItems      = $getMaxItems();
    $isList        = $shouldDisplayAsList();
    $initialView   = method_exists($field, 'getInitialView') ? $field->getInitialView() : null;
    $defaultView   = $isList ? 'list' : 'grid';
    $showBp        = method_exists($field, 'shouldShowBreakpointCrops') && $field->shouldShowBreakpointCrops();
    $showCropKeyPicker = method_exists($field, 'shouldShowCropKeyPicker') && $field->shouldShowCropKeyPicker();
    $locationKey   = method_exists($field, 'getLocationKey') ? $field->getLocationKey() : null;
    $allowedMediaIds = method_exists($field, 'getAllowedMediaIds') ? $field->getAllowedMediaIds() : [];
    $hasAllowedMedia = method_exists($field, 'hasAllowedMedia') && $field->hasAllowedMedia();

    // Resolve the human-readable label for the configured location key
    $locationLabel = null;
    if ($locationKey) {
        foreach (config('media.crop_locations', []) as $locClass) {
            if (class_exists($locClass)) {
                $loc = new $locClass;
                if ($loc->getKey() === $locationKey) {
                    $locationLabel = $loc->getLabel();
                    break;
                }
            }
        }
        $locationLabel = $locationLabel ?? $locationKey;
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">

    <div
        x-data="{
            view: @if($initialView)'{{ $initialView }}'@else localStorage.getItem('picker-view-{{ $statePath }}') || '{{ $defaultView }}'@endif,
            setView(v) { this.view = v; localStorage.setItem('picker-view-{{ $statePath }}', v); },
            updateBreakpoint(uuid, field, value) {
                let state = $wire.get('{{ $statePath }}') ?? {};
                if (state[uuid]) {
                    state[uuid][field] = value;
                    $wire.set('{{ $statePath }}', state);
                }
            },
            insertMedia(event) {
                if (event.detail.statePath !== '{{ $statePath }}') return;
                $wire.$set(event.detail.statePath, event.detail.media);
            },
            onPickerView(event) {
                if (event.detail.sp !== '{{ $statePath }}') return;
                this.setView(event.detail.v);
            },
            onCropAdded(event) {
                let mediaId = event.detail.mediaId;
                let crop = event.detail.crop;
                if (!mediaId || !crop) return;
                let state = $wire.get('{{ $statePath }}') ?? {};
                let uuid = String(mediaId);
                if (!state[uuid]) return;
                let item = state[uuid];
                let crops = (item.crops ?? []).filter(c => (c.key ?? c?.crop?.key) !== crop.key);
                crops.push(crop);
                let cropsByLocation = Object.assign({}, item.crops_by_location ?? {});
                if (crop.location) cropsByLocation[crop.location] = crop;
                cropsByLocation[crop.key] = crop;
                let updates = { crops: crops, crops_by_location: cropsByLocation };
                @if ($locationKey)
                // Location-keyed field: auto-set crop_key when the new crop matches the location
                if (crop.location === '{{ $locationKey }}' || crop.key === '{{ $locationKey }}') {
                    updates.crop_key = crop.key;
                    updates.selected_crop_url = crop.url ?? item.selected_crop_url;
                }
                @elseif (!$isMultiple)
                // Single (one-to-one) field: always adopt the new crop as the active crop_key
                updates.crop_key = crop.key;
                updates.selected_crop_url = crop.url ?? item.selected_crop_url;
                @endif
                state[uuid] = Object.assign({}, item, updates);
                // Use entangle-style path update to avoid JS reordering numeric keys
                $wire.set('{{ $statePath }}.' + uuid, state[uuid]);
                // Immediately update the card thumbnail if a crop_key was auto-set
                if (updates.crop_key && updates.selected_crop_url) {
                    window.dispatchEvent(new CustomEvent('picker-crop-selected', {
                        detail: { uuid: uuid, url: updates.selected_crop_url, key: updates.crop_key }
                    }));
                }
            },
        }"
        x-on:insert-content.window="insertMedia($event)"
        x-on:add-crop.window="onCropAdded($event)"
        x-on:picker-view.window="onPickerView($event)"
        class="media-picker w-full space-y-3 relative"
    >
        @if ($isMultiple)
            <div style="position:absolute;top:-2.2rem;right:0;z-index:1;">
                <x-media-view-toggle />
            </div>
        @endif

        {{-- ── Grid / card view ── --}}
        @if ($itemsCount > 0)
        <div x-show="view === 'grid'" x-cloak>
            <ul
                class="grid gap-3"
                style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));"
                x-sortable
                wire:end.stop="mountFormComponentAction('{{ $statePath }}', 'reorder', { items: $event.target.sortable.toArray() })"
            >
                @foreach ($items as $uuid => $item)
                    <li
                        wire:key="{{ $this->getId() }}.{{ $uuid }}.item"
                        x-sortable-item="{{ $uuid }}"
                        class="relative group rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm"
                    >
                        {{-- Thumbnail --}}
                        <x-media-picker-thumbnail
                            :item="$item"
                            :uuid="$uuid"
                            :location-key="$locationKey"
                            :lazy="$shouldLazyLoad()"
                        />

                        {{-- Caption + actions --}}
                        <div class="px-2 py-1.5 text-xs flex items-center gap-2">
                            @if ($isMultiple)
                                <div x-sortable-handle class="cursor-grab text-gray-300 hover:text-gray-500 flex-shrink-0 hover:cursor-grab active:cursor-grabbing">
                                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><circle cx="7" cy="4" r="1.5"/><circle cx="13" cy="4" r="1.5"/><circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/><circle cx="7" cy="16" r="1.5"/><circle cx="13" cy="16" r="1.5"/></svg>
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="truncate font-medium text-gray-700 dark:text-gray-200">{{ $item['pretty_name'] ?? $item['name'] ?? '' }}</p>
                                <p class="text-gray-400">{{ $item['size_for_humans'] ?? '' }}</p>
                                @php
                                    $itemCropKey = $item['crop_key'] ?? null;
                                    $usingLocationCrop = ($usingCrop ?? false) || ($locationKey && isset(($item['crops_by_location'] ?? [])[$locationKey]));
                                    $gridCropMeta = null;
                                    if ($locationLabel && $usingLocationCrop) {
                                        $gridCropMeta = $locationLabel . ($itemCropKey && $itemCropKey !== $locationKey ? ' · ' . $itemCropKey : '');
                                    } elseif ($itemCropKey) {
                                        $gridCropMeta = $itemCropKey;
                                    }
                                    // JS label to show after a crop is selected client-side
                                    $jsLocationLabel = $locationLabel ? addslashes($locationLabel) : '';
                                    $jsLocationKey   = addslashes($locationKey ?? '');
                                @endphp
                                @if ($gridCropMeta)
                                    <p class="text-primary-500 dark:text-primary-400 mt-0.5 truncate"
                                       x-on:picker-crop-selected.window="if ($event.detail.uuid === '{{ $uuid }}') {
                                           var loc = '{{ $jsLocationLabel }}';
                                           var lk  = '{{ $jsLocationKey }}';
                                           $el.textContent = loc ? (loc + ($event.detail.key && $event.detail.key !== lk ? ' · ' + $event.detail.key : '')) : $event.detail.key;
                                       }"
                                    >{{ $gridCropMeta }}</p>
                                @else
                                    <p class="text-primary-500 dark:text-primary-400 mt-0.5 truncate"
                                       style="display:none;"
                                       x-on:picker-crop-selected.window="if ($event.detail.uuid === '{{ $uuid }}') {
                                           var loc = '{{ $jsLocationLabel }}';
                                           var lk  = '{{ $jsLocationKey }}';
                                           $el.textContent = loc ? (loc + ($event.detail.key && $event.detail.key !== lk ? ' · ' + $event.detail.key : '')) : $event.detail.key;
                                           $el.style.display = '';
                                       }"
                                    ></p>
                                @endif
                            </div>
                            @php
                                $isItemImage = str($item['type'] ?? '')->contains('image') && ! str($item['type'] ?? '')->contains('svg');
                                $gridActions = [
                                    $getAction('view')(['url' => $item['url'] ?? '']),
                                    $getAction('edit')(['id' => $item['id']]),
                                    $getAction('download')(['uuid' => $uuid]),
                                    $getAction('remove')(['uuid' => $uuid]),
                                ];
                                if ($isItemImage) {
                                    array_splice($gridActions, 1, 0, [$getAction('crop')(['id' => $item['id']])]);
                                }
                                if ($isItemImage && ! empty($item['crops']) && $showCropKeyPicker && ! $locationKey) {
                                    array_splice($gridActions, 2, 0, [$getAction('select_crop_key')(['id' => $item['id'], 'uuid' => $uuid])]);
                                }
                            @endphp
                            <x-filament-actions::group
                                :actions="$gridActions"
                                color="gray"
                                size="xs"
                                icon="heroicon-m-ellipsis-vertical"
                                icon-button="true"
                                dropdown-placement="top-end"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>{{-- /grid view --}}

        {{-- ── List view ── --}}
        <div x-show="view === 'list'" x-cloak>
            <table class="mz-listing__table">
                <thead>
                    <tr>
                        @if ($isMultiple)<th style="width:20px;"></th>@endif
                        <th style="width:56px;"></th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Dimensions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody
                    @if ($isMultiple)
                    x-sortable
                    wire:end.stop="mountFormComponentAction('{{ $statePath }}', 'reorder', { items: $event.target.sortable.toArray() })"
                    @endif
                >
                @foreach ($items as $uuid => $item)
                    @php
                        $cropOptions = $item['crop_options'] ?? [];
                        $listThumbSrc = $item['thumbnail_url'] ?? $item['url'] ?? '';
                        $listUsingCrop = false;
                        if ($locationKey) {
                            $cbl = $item['crops_by_location'] ?? [];
                            if (isset($cbl[$locationKey]['url'])) {
                                $listThumbSrc = $cbl[$locationKey]['url'];
                                $listUsingCrop = true;
                            }
                        } elseif (! empty($item['selected_crop_url'])) {
                            $listThumbSrc = $item['selected_crop_url'];
                            $listUsingCrop = true;
                        }
                    @endphp
                    <tr
                        wire:key="{{ $this->getId() }}.{{ $uuid }}.item-list"
                        @if ($isMultiple) x-sortable-item="{{ $uuid }}" @endif
                    >
                        @if ($isMultiple)
                        <td>
                            <div x-sortable-handle class="mz-listing__icon-btn cursor-grab hover:cursor-grab active:cursor-grabbing" style="color:var(--gray-300);">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><circle cx="7" cy="4" r="1.5"/><circle cx="13" cy="4" r="1.5"/><circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/><circle cx="7" cy="16" r="1.5"/><circle cx="13" cy="16" r="1.5"/></svg>
                            </div>
                        </td>
                        @endif
                        <td>
                            @if (($item['ext'] ?? '') === 'pdf')
                                <canvas class="mz-listing__thumb mz-listing__pdf-thumb" data-url="{{ isset($item['id']) ? route('media.proxy', $item['id']) : '' }}" width="44" height="44"></canvas>
                            @elseif (str($item['type'] ?? '')->contains('video'))
                                <div class="mz-listing__thumb" style="display:flex;align-items:center;justify-content:center;background:#18181b;">
                                    <video src="{{ $item['url'] ?? '' }}" style="max-width:100%;max-height:100%;display:block;"></video>
                                </div>
                            @else
                                <div class="mz-listing__thumb"
                                    @if ($listThumbSrc) style="background-image: url('{{ $listThumbSrc }}')" @endif
                                    x-on:picker-crop-selected.window="if ($event.detail.uuid === '{{ $uuid }}') $el.style.backgroundImage = 'url(' + $event.detail.url + ')'"
                                ></div>
                            @endif
                        </td>
                        <td>
                            <span class="mz-listing__name">{{ $item['pretty_name'] ?? $item['name'] ?? '' }}</span>
                            @php
                                $listItemCropKey = $item['crop_key'] ?? null;
                                $listUsingLocationCrop = $listUsingCrop || ($locationKey && isset(($item['crops_by_location'] ?? [])[$locationKey]));
                                $listCropMeta = null;
                                if ($listUsingLocationCrop && $locationLabel) {
                                    $listCropMeta = $locationLabel . ($listItemCropKey && $listItemCropKey !== $locationKey ? ' · ' . $listItemCropKey : '');
                                } elseif ($listItemCropKey) {
                                    $listCropMeta = $listItemCropKey;
                                }
                            @endphp
                            @if ($listCropMeta)
                                <span class="mz-listing__meta" style="color:var(--primary-600);"
                                    x-on:picker-crop-selected.window="if ($event.detail.uuid === '{{ $uuid }}') {
                                        var loc = '{{ $jsLocationLabel ?? '' }}';
                                        var lk  = '{{ $jsLocationKey ?? '' }}';
                                        $el.textContent = loc ? (loc + ($event.detail.key && $event.detail.key !== lk ? ' · ' + $event.detail.key : '')) : $event.detail.key;
                                    }"
                                >{{ $listCropMeta }}</span>
                            @else
                                <span class="mz-listing__meta" style="color:var(--primary-600);display:none;"
                                    x-on:picker-crop-selected.window="if ($event.detail.uuid === '{{ $uuid }}') {
                                        var loc = '{{ $jsLocationLabel ?? '' }}';
                                        var lk  = '{{ $jsLocationKey ?? '' }}';
                                        $el.textContent = loc ? (loc + ($event.detail.key && $event.detail.key !== lk ? ' · ' + $event.detail.key : '')) : $event.detail.key;
                                        $el.style.display = '';
                                    }"
                                ></span>
                            @endif
                        </td>
                        <td>
                            <span class="mz-listing__badge mz-listing__badge-{{ strtolower($item['ext'] ?? '') }}">{{ strtolower($item['ext'] ?? '—') }}</span>
                        </td>
                        <td class="mz-listing__mono">{{ $item['size_for_humans'] ?? '' }}</td>
                        <td class="mz-listing__mono mz-listing__dim">
                            @if (($item['width'] ?? null) && ($item['height'] ?? null))
                                {{ $item['width'] }} × {{ $item['height'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="mz-listing__row-actions">
                                @php
                                    $isListItemImage = str($item['type'] ?? '')->contains('image') && ! str($item['type'] ?? '')->contains('svg');
                                    $listActions = [
                                        $getAction('view')(['url' => $item['url'] ?? '']),
                                        $getAction('edit')(['id' => $item['id']]),
                                        $getAction('download')(['uuid' => $uuid]),
                                        $getAction('remove')(['uuid' => $uuid]),
                                    ];
                                    if ($isListItemImage) {
                                        array_splice($listActions, 1, 0, [$getAction('crop')(['id' => $item['id']])]);
                                    }
                                    if ($isListItemImage && ! empty($item['crops']) && $showCropKeyPicker && ! $locationKey) {
                                        array_splice($listActions, 2, 0, [$getAction('select_crop_key')(['id' => $item['id'], 'uuid' => $uuid])]);
                                    }
                                @endphp
                                <x-filament-actions::group
                                    :actions="$listActions"
                                    color="gray"
                                    size="xs"
                                    dropdown-placement="bottom-end"
                                />
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>{{-- /list view --}}
        @endif

        {{-- ── Toolbar ── --}}
        <div class="flex items-center gap-3 flex-wrap mt-4">
            @if ($itemsCount === 0 || $isMultiple)
                @if (! $maxItems || $itemsCount < $maxItems)
                    <button
                        type="button"
                        x-on:click="Livewire.dispatch('open-media-picker', { statePath: '{{ $statePath }}', isMultiple: {{ $isMultiple ? 'true' : 'false' }}, selected: {{ Js::from(array_values($items)) }}, allowedMediaIds: {{ Js::from($allowedMediaIds) }} })"
                        class="fi-btn fi-btn-color-gray fi-btn-size-md fi-color-gray fi-btn-outlined inline-flex items-center gap-1.5 font-semibold rounded-lg text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 px-3 py-2 shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        <x-filament::icon icon="heroicon-m-photo" class="w-5 h-5 -ms-0.5" />
                        Add Media
                    </button>
                @endif
            @endif
            @if ($itemsCount > 1)
                {{ $getAction('removeAll') }}
            @endif

        </div>


    </div>

    @include('mediazone::media.partials.pdf-renderer')
</x-dynamic-component>
