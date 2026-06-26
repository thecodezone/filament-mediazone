<div style="display:flex;flex-direction:column;flex:1;min-height:0;height:100%;overflow:hidden;">

<div
    class="mz-cropper__shell"
    x-data="mediaCropper({{ Js::from(['presets' => $presets, 'locations' => $locations, 'defaultFormat' => $formats[0] ?? 'webp', 'modalId' => $modalId, 'defaultLocation' => $defaultLocation]) }})"
    wire:ignore
    style="flex:1;min-height:0;overflow:hidden;"
>
    <div class="mz-cropper__body">

        {{-- ── Canvas ── --}}
        <div class="mz-cropper__canvas">
            @if ($cropperImageUrl)
                <img
                    x-ref="image"
                    src="{{ $cropperImageUrl }}"
                    alt="{{ $media->alt ?? '' }}"
                    style="display:block;max-width:100%;max-height:100%;visibility:hidden;"
                    crossorigin="anonymous"
                />
            @else
                <div style="position:absolute;inset:0;display:grid;place-items:center;">
                    <p style="color:#9ca3af;font-size:14px;">No image available.</p>
                </div>
            @endif

            {{-- Guide line overlays --}}
            <template x-if="guideLines.length > 0">
                <div x-ref="guideOverlay" class="mz-cropper__guide-overlay" aria-hidden="true">
                    <template x-for="(guide, i) in guideLines" :key="i">
                        <div
                            class="mz-cropper__guide-line"
                            :class="guide.axis === 'x' ? 'mz-cropper__guide-line--x' : 'mz-cropper__guide-line--y'"
                            :style="guideLineStyle(guide)"
                        ></div>
                    </template>
                </div>
            </template>

            {{-- Floating toolbar --}}
            <div class="mz-cropper__toolbar">
                {{-- Zoom out --}}
                <button type="button" class="mz-cropper__tb-btn" title="Zoom out" x-on:click="cropper && cropper.zoom(-0.1)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                </button>
                <span class="mz-cropper__zoom-label">100%</span>
                {{-- Zoom in --}}
                <button type="button" class="mz-cropper__tb-btn" title="Zoom in" x-on:click="cropper && cropper.zoom(0.1)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
                {{-- Fit --}}
                <button type="button" class="mz-cropper__tb-btn" title="Fit" x-on:click="cropper && cropper.reset()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 20.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                </button>
                <div class="mz-cropper__divider"></div>
                {{-- Flip H --}}
                <button type="button" class="mz-cropper__tb-btn" title="Flip horizontal" x-on:click="flipH()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 4.5v15m16.5-15v15M7.5 8.25 4.5 12l3 3.75M16.5 8.25 19.5 12l-3 3.75"/></svg>
                </button>
                {{-- Flip V --}}
                <button type="button" class="mz-cropper__tb-btn" title="Flip vertical" x-on:click="flipV()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3.75h15m-15 16.5h15M8.25 7.5 12 4.5l3.75 3M8.25 16.5 12 19.5l3.75-3"/></svg>
                </button>
                {{-- Rotate left --}}
                <button type="button" class="mz-cropper__tb-btn" title="Rotate 90° left" x-on:click="cropper && cropper.rotate(-90)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="transform:scaleX(-1)"><path d="M15 3h4.5M19.5 3v4.5M19.5 3 12 10.5"/><path d="M19.5 12a7.5 7.5 0 1 1-7.5-7.5"/></svg>
                </button>
                {{-- Rotate right --}}
                <button type="button" class="mz-cropper__tb-btn" title="Rotate 90° right" x-on:click="cropper && cropper.rotate(90)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4.5M19.5 3v4.5M19.5 3 12 10.5"/><path d="M19.5 12a7.5 7.5 0 1 1-7.5-7.5"/></svg>
                </button>
                <div class="mz-cropper__divider"></div>
                {{-- Reset --}}
                <button type="button" class="mz-cropper__tb-btn" title="Reset all" x-on:click="cropper && (cropper.reset(), cropData = cropper.getData(true))">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 9.348A8.25 8.25 0 0 1 18.483 6.348L21 9M21 4.5v4.5h-4.5M19.5 14.652A8.25 8.25 0 0 1 5.517 17.652L3 15M3 19.5v-4.5h4.5"/></svg>
                </button>
            </div>
        </div>

        {{-- ── Sidebar ── --}}
        <div class="mz-cropper__side" x-on:mz-cropper__save.window="save()">
            <div class="mz-cropper__side-scroll">

                {{-- Keyboard hints --}}
                <div class="mz-cropper__kbd-hints mz-cropper__kbd-hints-sidebar">
                    <span><kbd class="mz-cropper__kbd">Space</kbd> Pan</span>
                    <span><kbd class="mz-cropper__kbd">⌘Z</kbd> Undo</span>
                    <span><kbd class="mz-cropper__kbd">Esc</kbd> Cancel</span>
                </div>

                {{-- Setup --}}
                <div class="mz-cropper__group">
                    <div class="mz-cropper__group-label">Setup</div>

                    {{-- Location --}}
                    <div class="mz-cropper__row">
                        <div class="mz-cropper__lbl">Location</div>
                        <div class="mz-cropper__input-wrap">
                            <select class="mz-cropper__select" x-model="location" x-on:change="selectLocation()">
                                <option value="">Choose location…</option>
                                @foreach ($locations as $loc)
                                    <option value="{{ $loc['key'] }}">{{ $loc['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Key --}}
                    <div class="mz-cropper__row" x-show="!location">
                        <div class="mz-cropper__lbl">Key <span class="mz-cropper__req">*</span></div>
                        <div class="mz-cropper__input-wrap">
                            <input type="text" class="mz-cropper__input" x-model="cropKey" placeholder="my_custom_crop" />
                        </div>
                        <div class="mz-cropper__hint">Unique name used in templates.</div>
                    </div>

                    {{-- Breakpoints --}}
                    <div class="mz-cropper__row" style="margin-bottom:0;">
                        <div class="mz-cropper__lbl">Breakpoints</div>
                        <div class="mz-cropper__chips">
                            <button type="button" class="mz-cropper__chip"
                                x-bind:class="breakpoints.indexOf('mobile') !== -1 ? 'mz-cropper__chip--active' : ''"
                                x-on:click="toggleBreakpoint('mobile')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                                Mobile
                            </button>
                            <button type="button" class="mz-cropper__chip"
                                x-bind:class="breakpoints.indexOf('tablet') !== -1 ? 'mz-cropper__chip--active' : ''"
                                x-on:click="toggleBreakpoint('tablet')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                Tablet
                            </button>
                            <button type="button" class="mz-cropper__chip"
                                x-bind:class="breakpoints.indexOf('desktop') !== -1 ? 'mz-cropper__chip--active' : ''"
                                x-on:click="toggleBreakpoint('desktop')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
                                Desktop
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Output --}}
                <div class="mz-cropper__group">
                    <div class="mz-cropper__group-label">Output</div>

                    {{-- Preset --}}
                    <div class="mz-cropper__row">
                        <div class="mz-cropper__lbl">Preset</div>
                        <div class="mz-cropper__input-wrap">
                            <select class="mz-cropper__select" x-model="preset" x-on:change="selectPreset()">
                                <option value="custom">Custom</option>
                                @foreach ($presets as $p)
                                    <option value="{{ $p['key'] }}">{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Format --}}
                    <div class="mz-cropper__row">
                        <div class="mz-cropper__lbl">Format</div>
                        <div class="mz-cropper__input-wrap">
                            <select class="mz-cropper__select" x-model="format">
                                @foreach ($formats as $fmt)
                                    <option value="{{ $fmt }}">{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Quality --}}
                    <div class="mz-cropper__row">
                        <div class="mz-cropper__lbl">Quality <span class="mz-cropper__qval" x-text="quality"></span></div>
                        <input type="range" class="mz-cropper__slider" x-model="quality" min="1" max="100" />
                    </div>

                    {{-- Output W + H --}}
                    <div class="mz-cropper__row mz-cropper__duo" style="margin-bottom:0;">
                        <div>
                            <div class="mz-cropper__lbl">Output W</div>
                            <div class="mz-cropper__input-wrap">
                                <input type="number" class="mz-cropper__input" x-model="targetWidth" min="0" />
                                <span class="mz-cropper__unit">px</span>
                            </div>
                        </div>
                        <div>
                            <div class="mz-cropper__lbl">Output H</div>
                            <div class="mz-cropper__input-wrap">
                                <input type="number" class="mz-cropper__input" x-model="targetHeight" min="0" />
                                <span class="mz-cropper__unit">px</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Crop frame --}}
                <div class="mz-cropper__group">
                    <div class="mz-cropper__group-label">Crop frame</div>

                    {{-- Aspect ratio --}}
                    <div class="mz-cropper__row">
                        <div class="mz-cropper__lbl">Aspect ratio</div>
                        <div class="mz-cropper__seg">
                            @foreach (['Free' => 'NaN', '16:9' => '16/9', '4:3' => '4/3', '1:1' => '1', '2:3' => '2/3'] as $ratio => $val)
                            <button type="button"
                                class="mz-cropper__seg-btn"
                                x-bind:class="aspectRatio === '{{ $val }}' ? 'mz-cropper__chip--active' : ''"
                                x-on:click="aspectRatio = '{{ $val }}'; cropper && cropper.setAspectRatio({{ $val }})">
                                {{ $ratio }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- X Y W H --}}
                    <div class="mz-cropper__row mz-cropper__duo">
                        @foreach (['X' => 'x', 'Y' => 'y', 'W' => 'width', 'H' => 'height'] as $lbl => $prop)
                        <div>
                            <div class="mz-cropper__lbl">{{ $lbl }}</div>
                            <div class="mz-cropper__input-wrap">
                                <input type="number" class="mz-cropper__input"
                                    :value="cropData.{{ $prop }} ?? 0"
                                    x-on:change="if(cropper){ cropper.setData({ {{ $prop }}: parseFloat($event.target.value) }); cropData = cropper.getData(true); }" />
                                <span class="mz-cropper__unit">px</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Rotate --}}
                    <div class="mz-cropper__row" style="margin-bottom:0;">
                        <div class="mz-cropper__lbl">Rotate</div>
                        <div class="mz-cropper__input-wrap">
                            <input type="number" class="mz-cropper__input"
                                :value="cropData.rotate ?? 0"
                                x-on:change="if(cropper){ cropper.rotateTo(parseFloat($event.target.value)); cropData = cropper.getData(true); }" />
                            <span class="mz-cropper__unit">deg</span>
                        </div>
                    </div>
                </div>

            </div>{{-- /mz-cropper__side-scroll --}}
        </div>{{-- /mz-cropper__side --}}

    </div>{{-- /mz-cropper__body --}}
</div>
</div>
