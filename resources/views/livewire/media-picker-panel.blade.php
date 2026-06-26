<div
    x-data="{
        handleItemClick(mediaId) {
            if (!mediaId) return;
            if (!$wire.isMultiple) {
                $wire.selected = [];
            }
            if (this.isSelected(mediaId)) {
                $wire.removeFromSelection(mediaId);
            } else {
                $wire.addToSelection(mediaId);
            }
        },
        isSelected(mediaId) {
            return Array.from($wire.selected ?? []).some(obj => obj.id == mediaId);
        },
        selectionOrder(mediaId) {
            return Array.from($wire.selected ?? []).findIndex(obj => obj.id == mediaId) + 1;
        },
    }"
>
    {{-- Backdrop + modal shell --}}
    <div x-show="$wire.open" x-cloak class="mz-picker__backdrop" x-on:click.self="$wire.close()">
        <div class="mz-picker__shell">
    {{-- Header --}}
    <header class="mz-picker__head">
        <div class="mz-picker__head-text">
            <div class="mz-picker__title">Select media</div>
            <div class="mz-picker__sub">Pick {{ $isMultiple ? 'one or more files' : 'a file' }} from your library, or upload something new.</div>
        </div>
        <button type="button" class="mz-picker__icon-btn" wire:click="close" aria-label="Close">
            <x-filament::icon icon="heroicon-m-x-mark" class="w-[18px] h-[18px]" />
        </button>
    </header>

    {{-- Tabs --}}
    <div class="mz-picker__tabs">
        <button type="button" class="mz-picker__tab {{ $activeTab === 'library' ? 'mz-field__view-btn--active' : '' }}" wire:click="setTab('library')">
            <x-filament::icon icon="heroicon-m-bars-3" class="w-[15px] h-[15px]" />
            Library
            <span class="mz-picker__tab-count">{{ $totalCount }}</span>
        </button>
        @if (empty($allowedMediaIds))
        <button type="button" class="mz-picker__tab {{ $activeTab === 'upload' ? 'mz-field__view-btn--active' : '' }}" wire:click="setTab('upload')">
            <x-filament::icon icon="heroicon-m-arrow-up-tray" class="w-[15px] h-[15px]" />
            Upload
        </button>
        @endif
    </div>

    @if ($activeTab === 'library')
    {{-- Filter bar --}}
    <div class="mz-picker__filter-bar">
        <div class="mz-picker__search">
            <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-[15px] h-[15px]" />
            <input type="text" placeholder="Search media…" wire:model.live.debounce.300ms="search" />
        </div>

        @if (empty($allowedMediaIds) && count($folderOptions))
        <select class="mz-picker__pill-select" wire:model.live="filterFolder">
            <option value="">All folders</option>
            @foreach ($folderOptions as $folder => $_)
                <option value="{{ $folder }}">{{ $folder }}</option>
            @endforeach
        </select>
        @endif

        @if (empty($allowedMediaIds) && count($extOptions))
        <select class="mz-picker__pill-select" wire:model.live="filterExt">
            <option value="">All types</option>
            @foreach ($extOptions as $ext => $_)
                <option value="{{ $ext }}">{{ strtoupper($ext) }}</option>
            @endforeach
        </select>
        @endif

        <div class="mz-picker__spacer"></div>

        <span class="mz-picker__count-label">{{ $totalCount }} {{ $totalCount === 1 ? 'file' : 'files' }}</span>

        <x-media-view-toggle :wire-mode="true" :active-view="$viewMode" />
    </div>

    {{-- Gallery --}}
    <div class="mz-picker__gallery">
        @if ($viewMode === 'grid')
        <div class="mz-picker__grid">
            @forelse ($files as $file)
            <div
                class="mz-picker__tile {{ collect($selected)->contains('id', $file->id) ? 'mz-picker__tile--selected' : '' }}"
                wire:key="picker-{{ $file->id }}"
                x-on:click="handleItemClick({{ $file->id }})"
            >
                <div class="mz-picker__tile-thumb">
                    @if (str_contains($file->type ?? '', 'image') && !str_contains($file->type ?? '', 'svg'))
                        <div class="mz-picker__tile-thumb-img" style="background-image: url('{{ $file->thumbnail_url }}')"></div>
                    @elseif (str_contains($file->type ?? '', 'svg'))
                        <div class="mz-picker__tile-thumb-img mz-picker__tile-thumb-contain" style="background-image: url('{{ \Illuminate\Support\Facades\Storage::disk($file->disk)->url($file->path) }}')"></div>
                    @elseif ($file->ext === 'pdf')
                        <div class="mz-picker__tile-thumb-pdf">
                            <canvas class="mz-listing__pdf-thumb mz-listing__tile-pdf-canvas" data-url="{{ route('media.proxy', $file->id) }}"></canvas>
                        </div>
                    @elseif (str_contains($file->type ?? '', 'video'))
                        <div class="mz-picker__tile-thumb-pdf">
                            <video src="{{ $file->url }}" style="max-width:100%;max-height:100%;display:block;object-fit:contain;"></video>
                        </div>
                    @else
                        <div class="mz-picker__tile-thumb-icon">
                            <x-filament::icon icon="heroicon-o-document" class="w-7 h-7" />
                            <span>{{ strtoupper($file->ext ?? '') }}</span>
                        </div>
                    @endif
                    <span class="mz-picker__tile-type">{{ $file->ext }}</span>
                    <div class="mz-picker__tile-check" x-bind:class="isSelected({{ $file->id }}) ? 'mz-picker__tile-check--checked' : ''">
                        <x-filament::icon icon="heroicon-m-check" class="w-[13px] h-[13px]" />
                    </div>
                    <div class="mz-picker__order-badge" x-show="$wire.isMultiple && isSelected({{ $file->id }})" x-text="selectionOrder({{ $file->id }})"></div>
                </div>
                <div class="mz-picker__tile-meta">
                    <div class="mz-picker__tile-name" title="{{ $file->pretty_name }}">{{ $file->pretty_name }}</div>
                    <div class="mz-picker__tile-sub">
                        @if ($file->width && $file->height){{ $file->width }} × {{ $file->height }} · @endif{{ $file->size_for_humans }}
                    </div>
                </div>
            </div>
            @empty
            <div class="mz-picker__empty">
                <x-filament::icon icon="heroicon-o-photo" class="w-8 h-8" />
                No media found.
            </div>
            @endforelse
        </div>
        @else
        <table class="mz-picker__table">
            <thead>
                <tr>
                    <th style="width:36px"></th>
                    <th style="width:52px"></th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Dimensions</th>
                    <th>Folder</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($files as $file)
                @php $isSel = collect($selected)->contains('id', $file->id); @endphp
                <tr
                    wire:key="picker-row-{{ $file->id }}"
                    class="{{ $isSel ? 'mz-picker__tile--selected' : '' }}"
                    x-on:click="handleItemClick({{ $file->id }})"
                >
                    <td>
                        <span class="mz-picker__row-check {{ $isSel ? 'mz-picker__tile-check--checked' : '' }}">
                            @if ($isSel)
                            <x-filament::icon icon="heroicon-m-check" class="w-[11px] h-[11px]" />
                            @endif
                        </span>
                    </td>
                    <td>
                        <div class="mz-picker__row-thumb"
                            @if (str_contains($file->type ?? '', 'image'))
                                style="background-image: url('{{ $file->thumbnail_url }}')"
                            @endif
                        ></div>
                    </td>
                    <td class="mz-picker__row-name">{{ $file->pretty_name }}</td>
                    <td><span class="mz-listing__badge mz-listing__badge-{{ strtolower($file->ext ?? '') }}">{{ strtolower($file->ext ?? '—') }}</span></td>
                    <td class="mz-picker__row-dim">{{ $file->size_for_humans }}</td>
                    <td class="mz-picker__row-dim">
                        @if ($file->width && $file->height){{ $file->width }} × {{ $file->height }}@else —@endif
                    </td>
                    <td>
                        @if ($file->directory)<span class="mz-listing__badge mz-listing__badge--folder">{{ $file->directory }}</span>@else <span class="mz-picker__row-dim">—</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="mz-picker__table-empty">No media found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- Pagination --}}
        @if ($lastPage > 1)
        <div class="mz-picker__pagi">
            <span class="mz-picker__pagi-overview">
                Showing {{ ($currentPage - 1) * $perPage + 1 }} to {{ min($currentPage * $perPage, $totalCount) }} of {{ $totalCount }} results
            </span>
            @if ($currentPage > 1)
                <button type="button" class="mz-picker__pagi-btn mz-picker__pagi-ic" wire:click="setPage({{ $currentPage - 1 }})">
                    <x-filament::icon icon="heroicon-m-chevron-left" class="w-[13px] h-[13px]" />
                </button>
            @else
                <button type="button" class="mz-picker__pagi-btn mz-picker__pagi-ic" disabled>
                    <x-filament::icon icon="heroicon-m-chevron-left" class="w-[13px] h-[13px]" />
                </button>
            @endif
            @for ($p = max(1, $currentPage - 2); $p <= min($lastPage, $currentPage + 2); $p++)
                <button type="button" class="mz-picker__pagi-btn {{ $p === $currentPage ? 'mz-field__view-btn--active' : '' }}" wire:click="setPage({{ $p }})">{{ $p }}</button>
            @endfor
            @if ($currentPage < $lastPage)
                <button type="button" class="mz-picker__pagi-btn mz-picker__pagi-ic" wire:click="setPage({{ $currentPage + 1 }})">
                    <x-filament::icon icon="heroicon-m-chevron-right" class="w-[13px] h-[13px]" />
                </button>
            @else
                <button type="button" class="mz-picker__pagi-btn mz-picker__pagi-ic" disabled>
                    <x-filament::icon icon="heroicon-m-chevron-right" class="w-[13px] h-[13px]" />
                </button>
            @endif
        </div>
        @endif
    </div>

    @else
    {{-- Upload tab --}}
    <div class="mz-picker__upload-body" x-data="{
        files: [],
        addFiles(fileList) {
            Array.from(fileList).forEach(f => {
                this.files.push({ name: f.name, size: f.size, progress: 0, done: false, error: false });
            });
        },
        handleDrop(e) {
            this.addFiles(e.dataTransfer.files);
            this.$refs.fileInput.files = e.dataTransfer.files;
            this.$refs.fileInput.dispatchEvent(new Event('change'));
        },
        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },
    }"
        x-on:livewire-upload-progress="
            let idx = files.length - 1;
            if (idx >= 0) files[idx].progress = $event.detail.progress;
        "
        x-on:livewire-upload-finish="files.forEach(f => { f.progress = 100; f.done = true; })"
        x-on:livewire-upload-error="files.forEach(f => { if (!f.done) f.error = true; })"
    >
        <label
            class="mz-picker__dropzone"
            x-bind:class="files.length ? 'mz-picker__dropzone--has-files' : ''"
            x-on:dragover.prevent
            x-on:drop.prevent="handleDrop($event)"
        >
            <input type="file" multiple class="sr-only" wire:model="uploads" x-ref="fileInput"
                x-on:change="addFiles($event.target.files)" />
            <template x-if="files.length === 0">
                <div class="mz-picker__dropzone-idle">
                    <div class="mz-picker__dropzone-ico">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/></svg>
                    </div>
                    <h3 class="mz-picker__dropzone-title">Drop files here, or click to browse</h3>
                    <p class="mz-picker__dropzone-text">Upload multiple files at once. They'll appear in your library when finished.</p>
                    <div class="mz-picker__dropzone-formats">JPG · PNG · WEBP · SVG · PDF · MP4 — up to 100 MB each</div>
                </div>
            </template>
            <template x-if="files.length > 0">
                <div class="mz-picker__upload-list" x-on:click.prevent>
                    <template x-for="(f, i) in files" :key="i">
                        <div class="mz-picker__upload-row">
                            <div class="mz-picker__upload-row-info">
                                <span class="mz-picker__upload-row-name" x-text="f.name"></span>
                                <span class="mz-picker__upload-row-size" x-text="formatSize(f.size)"></span>
                                <span class="mz-picker__upload-row-status"
                                    x-text="f.error ? 'Error' : f.done ? 'Done' : f.progress + '%'">
                                </span>
                            </div>
                            <div class="mz-picker__upload-bar-track">
                                <div class="mz-picker__upload-bar-fill"
                                    x-bind:class="f.error ? 'mz-picker__upload-bar-fill--error' : f.done ? 'mz-picker__upload-bar-fill--done' : ''"
                                    x-bind:style="'width:' + f.progress + '%'">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </label>
    </div>
    @endif

    @include('mediazone::media.partials.pdf-renderer')

    {{-- Footer --}}
    <footer class="mz-picker__foot">
        <div class="mz-picker__selection-info">
            @if (count($selected) === 0)
                <span>No files selected</span>
            @else
                <span><b>{{ count($selected) }}</b> {{ count($selected) === 1 ? 'file' : 'files' }} selected</span>
                <button type="button" class="mz-picker__clear-link" wire:click="clearSelection">Clear</button>
            @endif
        </div>
        <div class="mz-picker__foot-actions">
            <button type="button" class="mz-picker__btn mz-picker__btn-gray" wire:click="close">Cancel</button>
            <button type="button" class="mz-picker__btn mz-picker__btn-primary" @disabled(count($selected) === 0) wire:click="insertMedia">
                <x-filament::icon icon="heroicon-m-check" class="w-[15px] h-[15px]" />
                Use {{ count($selected) > 0 ? count($selected) : '' }} {{ count($selected) === 1 ? 'file' : 'files' }}
            </button>
        </div>
    </footer>
    </div>{{-- /mz-picker__shell --}}
</div>{{-- /mz-picker__backdrop --}}
</div>{{-- /root --}}
