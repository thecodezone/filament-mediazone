@php
    /** @var string $uuid */
    /** @var string $statePath */
    /** @var string|null $locationKey */
    /** @var array<string, array> $cropsByKey  one desktop representative per unique key */
    /** @var string|null $currentKey */
@endphp

<style>
.sck-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; padding: 20px; }
.sck-card {
    all: unset; cursor: pointer; box-sizing: border-box;
    display: flex; flex-direction: column; justify-content: space-between;
    height: 100%;
    border-radius: 12px; overflow: hidden;
    border: 2px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 1px 3px 0 rgb(0 0 0/.06);
    transition: border-color 75ms, box-shadow 75ms;
    position: relative;
}
.sck-card:hover { border-color: #fb923c; }
.sck-card.sck-active { border-color: #f97316; box-shadow: 0 0 0 3px rgb(249 115 22 / 0.2); }
.dark .sck-card { background: #1f2937; border-color: #374151; }
.dark .sck-card.sck-active { border-color: #f97316; }
.sck-thumb {
    position: relative; padding-top: 75%;
    background-color: rgba(var(--gray-100), var(--tw-bg-opacity, 1));
    background-image: linear-gradient(45deg,#d1d5db 25%,transparent 25%),linear-gradient(-45deg,#d1d5db 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#d1d5db 75%),linear-gradient(-45deg,transparent 75%,#d1d5db 75%);
    background-size: 12px 12px;
    background-position: 0 0, 0 6px, 6px -6px, -6px 0;
}
.dark .sck-thumb { background-color: #111827; background-image: linear-gradient(45deg,#1f2937 25%,transparent 25%),linear-gradient(-45deg,#1f2937 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#1f2937 75%),linear-gradient(-45deg,transparent 75%,#1f2937 75%); }
.sck-thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
.sck-meta { padding: 8px 10px 10px; border-top: 1px solid #e5e7eb; }
.dark .sck-meta { border-top-color: #374151; }
.sck-name { font-size: 12px; font-weight: 600; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dark .sck-name { color: #e5e7eb; }
.sck-key { font-size: 11px; color: #9ca3af; font-family: ui-monospace, monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sck-size { font-size: 11px; color: #9ca3af; }
.sck-check {
    position: absolute; top: 6px; right: 6px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #f97316; color: #fff;
    display: flex; align-items: center; justify-content: center;
}
.sck-empty { padding: 48px 20px; text-align: center; color: #9ca3af; font-size: 14px; }
</style>

<div
    x-data="{
        selected: {{ json_encode($currentKey) }},
        choose(key, crop) {
            // Toggle: clicking the already-selected crop deselects it (reverts to OG image)
            let isDeselect = this.selected === key;
            this.selected = isDeselect ? null : key;
            let state = $wire.get('{{ $statePath }}') ?? {};
            let uuid = '{{ $uuid }}';
            let mediaId = state[uuid] ? state[uuid]['id'] : null;
            if (state[uuid] !== undefined) {
                state[uuid]['crop_key'] = isDeselect ? null : key;
                if (!isDeselect && crop && crop.url) {
                    state[uuid]['selected_crop_url'] = crop.url;
                } else if (isDeselect) {
                    state[uuid]['selected_crop_url'] = null;
                }
                @if ($locationKey)
                if (!isDeselect) {
                    let cbl = state[uuid]['crops_by_location'] ?? {};
                    cbl[{{ json_encode($locationKey) }}] = crop;
                    state[uuid]['crops_by_location'] = cbl;
                }
                @endif
                // Set state first, then touch the crop's updated_at so future selects sort correctly
                $wire.set('{{ $statePath }}', state).then(function() {
                    if (mediaId && key && !isDeselect) {
                        $wire.mountFormComponentAction('{{ $statePath }}', 'touch_crop', { mediaId: mediaId, key: key });
                    }
                });
            }
            // Update thumbnail and label immediately without waiting for Livewire re-render
            if (!isDeselect && crop && crop.url) {
                window.dispatchEvent(new CustomEvent('picker-crop-selected', {
                    detail: { uuid: uuid, url: crop.url, key: key }
                }));
            }
            if (!isDeselect) {
                var node = this.$el.closest('[wire\\:id]');
                var wireId = node ? node.getAttribute('wire:id') : null;
                if (wireId) {
                    var suffixes = ['-form-component-action', '-table-action', '-action'];
                    for (var s = 0; s < suffixes.length; s++) {
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: wireId + suffixes[s] } }));
                    }
                }
            }
        },
    }"
>
    @if (empty($cropsByKey))
        <div class="sck-empty">
            <svg style="width:40px;height:40px;margin:0 auto 12px;opacity:.3;display:block;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.848 8.25l1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 1 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.331 4.331 0 0 0 10.607 12m3.736 0 7.794 4.5-.802.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664" /></svg>
            No crops saved for this image yet.<br>
            <span style="font-size:12px;">Use the crop tool on the image to create crops first.</span>
        </div>
    @else
        <div class="sck-grid">
            @foreach ($cropsByKey as $key => $crop)
                @php
                    $label = $crop['crop']['label'] ?? $key;
                    $url   = $crop['url'] ?? '';
                    $w     = $crop['width'] ?? null;
                    $h     = $crop['height'] ?? null;
                @endphp
                <button
                    type="button"
                    x-on:click="choose({{ json_encode($key) }}, {{ json_encode($crop) }})"
                    class="sck-card"
                    :class="selected === {{ json_encode($key) }} ? 'sck-active' : ''"
                >
                    <div class="sck-thumb">
                        <img src="{{ $url }}" alt="{{ $label }}" loading="lazy" />
                    </div>
                    <div class="sck-meta">
                        <div class="sck-name">{{ $label }}</div>
                        <div class="sck-key">{{ $key }}</div>
                        @if ($w && $h)
                            <div class="sck-size">{{ $w }}×{{ $h }}</div>
                        @endif
                    </div>
                    <div class="sck-check" x-show="selected === {{ json_encode($key) }}" style="display:none;">
                        <x-filament::icon icon="heroicon-m-check" class="w-3 h-3" />
                    </div>
                </button>
            @endforeach
        </div>
    @endif
</div>
