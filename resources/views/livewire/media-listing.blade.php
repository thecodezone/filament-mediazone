<div class="mz-listing__page">

    {{-- Card --}}
    <div class="mz-listing__card">

        {{-- Toolbar --}}
        <div class="mz-listing__toolbar">
            {{-- Search --}}
            <div class="mz-listing__search">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-4 h-4" />
                <input
                    type="text"
                    placeholder="Search by name, folder, or type…"
                    wire:model.live.debounce.300ms="mediaSearch"
                />
            </div>

            {{-- Type filter --}}
            @if (count($extOptions))
            <div class="mz-listing__filter-wrap">
                <select class="mz-listing__filter-select" wire:model.live="filterExt">
                    <option value="">All types</option>
                    @foreach ($extOptions as $ext => $label)
                        <option value="{{ $ext }}">{{ strtoupper($ext) }}</option>
                    @endforeach
                </select>
                <x-filament::icon icon="heroicon-m-chevron-down" class="mz-listing__filter-chev w-3 h-3" />
            </div>
            @endif

            {{-- Folder filter --}}
            @if (count($folderOptions))
            <div class="mz-listing__filter-wrap">
                <select class="mz-listing__filter-select" wire:model.live="filterFolder">
                    <option value="">All folders</option>
                    @foreach ($folderOptions as $folder => $label)
                        <option value="{{ $folder }}">{{ $folder }}</option>
                    @endforeach
                </select>
                <x-filament::icon icon="heroicon-m-chevron-down" class="mz-listing__filter-chev w-3 h-3" />
            </div>
            @endif

            @if ($mediaSearch || $filterExt || $filterFolder)
            <button type="button" class="mz-listing__reset-btn" wire:click="resetFilters" title="Clear filters">
                <x-filament::icon icon="heroicon-m-x-mark" class="w-3.5 h-3.5" />
                Clear
            </button>
            @endif

            <div class="mz-listing__spacer"></div>

            {{-- Sort --}}
            <div class="mz-listing__filter-wrap">
                <select class="mz-listing__filter-select" wire:model.live="mediaSort">
                    <option value="created_at">Date uploaded</option>
                    <option value="updated_at">Date updated</option>
                    <option value="name">Name</option>
                    <option value="size">Size</option>
                    <option value="ext">Type</option>
                </select>
            </div>
            <button type="button" class="mz-listing__sort-dir-btn" wire:click="toggleSortDir" title="{{ $mediaSortDir === 'asc' ? 'Sort descending' : 'Sort ascending' }}">
                @if ($mediaSortDir === 'asc')
                    <x-filament::icon icon="heroicon-o-bars-arrow-up" class="w-3.5 h-3.5" />
                @else
                    <x-filament::icon icon="heroicon-o-bars-arrow-down" class="w-3.5 h-3.5" />
                @endif
            </button>

            {{-- View toggle --}}
            <div class="mz-listing__view-toggle" role="tablist">
                <button
                    type="button"
                    wire:click="$set('displayMode', 'list')"
                    class="{{ $displayMode === 'list' ? 'mz-listing__view-btn--active' : '' }}"
                    title="List view"
                >
                    <x-filament::icon icon="heroicon-m-bars-3" class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    wire:click="$set('displayMode', 'grid')"
                    class="{{ $displayMode === 'grid' ? 'mz-listing__view-btn--active' : '' }}"
                    title="Grid view"
                >
                    <x-filament::icon icon="heroicon-m-squares-2x2" class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- List view --}}
        @if ($displayMode === 'list')
        <table class="mz-listing__table">
            <thead>
                <tr>
                    <th style="width:56px"></th>
                    <th>
                        <button type="button" class="mz-listing__sort-btn {{ $mediaSort === 'name' ? 'mz-listing__pagi-btn--active' : '' }}" wire:click="setSort('name')">
                            Name
                            <x-filament::icon icon="heroicon-m-chevron-up-down" class="w-3 h-3" />
                        </button>
                    </th>
                    <th>
                        <button type="button" class="mz-listing__sort-btn {{ $mediaSort === 'ext' ? 'mz-listing__pagi-btn--active' : '' }}" wire:click="setSort('ext')">
                            Type
                            <x-filament::icon icon="heroicon-m-chevron-up-down" class="w-3 h-3" />
                        </button>
                    </th>
                    <th>
                        <button type="button" class="mz-listing__sort-btn {{ $mediaSort === 'size' ? 'mz-listing__pagi-btn--active' : '' }}" wire:click="setSort('size')">
                            Size
                            <x-filament::icon icon="heroicon-m-chevron-up-down" class="w-3 h-3" />
                        </button>
                    </th>
                    <th>Dimensions</th>
                    <th>Folder</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($media as $item)
                @php $editUrl = $this->getEditUrl($item->id); @endphp
                <tr class="{{ $editUrl ? 'mz-listing__row--linked' : '' }}" @if($editUrl) onclick="window.location='{{ $editUrl }}'" style="cursor:pointer;" @endif>
                    <td>
                        @if ($item->ext === 'pdf')
                            <canvas class="mz-listing__thumb mz-listing__pdf-thumb" data-url="{{ route('media.proxy', $item->id) }}" width="44" height="44"></canvas>
                        @elseif (str_contains($item->type ?? '', 'video'))
                            <div class="mz-listing__thumb" style="display:flex;align-items:center;justify-content:center;background:#18181b;">
                                <video src="{{ $item->url }}" style="max-width:100%;max-height:100%;display:block;"></video>
                            </div>
                        @else
                            <div class="mz-listing__thumb"
                                @if ($item->thumbnail_url)
                                    style="background-image: url('{{ $item->thumbnail_url }}')"
                                @endif
                            ></div>
                        @endif
                    </td>
                    <td>
                        <span class="mz-listing__name">{{ $item->pretty_name }}</span>
                        @if ($item->alt)
                            <span class="mz-listing__meta">{{ $item->alt }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="mz-listing__badge mz-listing__badge-{{ strtolower($item->ext ?? '') }}">{{ strtolower($item->ext ?? '—') }}</span>
                    </td>
                    <td class="mz-listing__mono">{{ $item->size_for_humans }}</td>
                    <td class="mz-listing__mono mz-listing__dim">
                        @if ($item->width && $item->height)
                            {{ $item->width }} × {{ $item->height }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($item->directory)
                            <span class="mz-listing__badge mz-listing__badge--folder">{{ $item->directory }}</span>
                        @else
                            <span class="mz-listing__mono" style="color:var(--gray-300)">—</span>
                        @endif
                    </td>
                    <td onclick="event.stopPropagation();">
                        <div class="mz-listing__row-actions">
                            @if ($editUrl)
                            <a href="{{ $editUrl }}" class="mz-listing__icon-btn" title="Edit">
                                <x-filament::icon icon="heroicon-o-pencil" class="w-4 h-4" />
                            </a>
                            @endif
                            <button type="button" class="mz-listing__icon-btn mz-listing__icon-btn-danger" title="Delete"
                                wire:click="deleteMedia({{ $item->id }})"
                                wire:confirm="Delete this media item?"
                            >
                                <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding: 48px 16px; color: var(--gray-400); font-size: 14px;">
                        No media found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Grid view --}}
        @else
        <div class="mz-listing__grid">
            @forelse ($media as $item)
            @php $editUrl = $this->getEditUrl($item->id); @endphp
            <div class="mz-listing__tile" @if($editUrl) onclick="window.location='{{ $editUrl }}'" @endif>
                <div class="mz-listing__tile-thumb">
                    @if ($item->ext === 'pdf')
                        <canvas class="mz-listing__pdf-thumb mz-listing__tile-pdf-canvas" data-url="{{ route('media.proxy', $item->id) }}"></canvas>
                    @elseif (str_contains($item->type ?? '', 'video'))
                        <video src="{{ $item->url }}" style="max-width:100%;max-height:100%;display:block;object-fit:contain;"></video>
                    @elseif ($item->thumbnail_url)
                        <div class="mz-listing__tile-thumb-img" style="background-image: url('{{ $item->thumbnail_url }}')"></div>
                    @endif
                    <div class="mz-listing__tile-type-badge">
                        <span class="mz-listing__badge mz-listing__badge-{{ strtolower($item->ext ?? '') }}">{{ strtolower($item->ext ?? '—') }}</span>
                    </div>
                    <div class="mz-listing__tile-actions" onclick="event.preventDefault(); event.stopPropagation();">
                        @if ($item->url)
                        <a href="{{ $item->url }}" target="_blank" class="mz-listing__tile-action" title="View">
                            <x-filament::icon icon="heroicon-o-eye" class="w-3.5 h-3.5" />
                        </a>
                        @endif
                        @if ($editUrl)
                        <a href="{{ $editUrl }}" class="mz-listing__tile-action" title="Edit">
                            <x-filament::icon icon="heroicon-o-pencil" class="w-3.5 h-3.5" />
                        </a>
                        @endif
                        <button type="button" class="mz-listing__tile-action mz-listing__tile-action-danger" title="Delete"
                            wire:click="deleteMedia({{ $item->id }})"
                            wire:confirm="Delete this media item?"
                        >
                            <x-filament::icon icon="heroicon-o-trash" class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
                <div class="mz-listing__tile-meta">
                    <div class="mz-listing__tile-name" title="{{ $item->pretty_name }}">{{ $item->pretty_name }}</div>
                    <div class="mz-listing__tile-row">
                        @if ($item->width && $item->height)
                            <span>{{ $item->width }} × {{ $item->height }}</span>
                            <span class="mz-listing__sep">·</span>
                        @endif
                        <span>{{ $item->size_for_humans }}</span>
                    </div>
                    @if ($item->directory)
                    <div class="mz-listing__tile-row">
                        <x-filament::icon icon="heroicon-o-folder" class="w-3 h-3" />
                        <span>{{ $item->directory }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div style="grid-column: 1 / -1; text-align: center; padding: 48px 16px; color: var(--gray-400); font-size: 14px;">
                No media found.
            </div>
            @endforelse
        </div>
        @endif

        {{-- Pagination --}}
        <div class="mz-listing__pagi">
            <span class="mz-listing__pagi-overview">
                Showing {{ $media->firstItem() ?? 0 }} to {{ $media->lastItem() ?? 0 }} of {{ $media->total() }} results
            </span>
        @if ($media->hasPages())
            <div class="mz-listing__pagi-pages">
                @if ($media->onFirstPage())
                    <button type="button" class="mz-listing__pagi-btn mz-listing__pagi-ic" disabled>
                        <x-filament::icon icon="heroicon-m-chevron-left" class="w-3.5 h-3.5" />
                    </button>
                @else
                    <button type="button" class="mz-listing__pagi-btn mz-listing__pagi-ic" wire:click="previousPage">
                        <x-filament::icon icon="heroicon-m-chevron-left" class="w-3.5 h-3.5" />
                    </button>
                @endif

                @foreach ($media->getUrlRange(max(1, $media->currentPage() - 2), min($media->lastPage(), $media->currentPage() + 2)) as $page => $url)
                    <button
                        type="button"
                        class="mz-listing__pagi-btn {{ $page === $media->currentPage() ? 'mz-listing__pagi-btn--active' : '' }}"
                        wire:click="gotoPage({{ $page }})"
                    >{{ $page }}</button>
                @endforeach

                @if ($media->hasMorePages())
                    <button type="button" class="mz-listing__pagi-btn mz-listing__pagi-ic" wire:click="nextPage">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="w-3.5 h-3.5" />
                    </button>
                @else
                    <button type="button" class="mz-listing__pagi-btn mz-listing__pagi-ic" disabled>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="w-3.5 h-3.5" />
                    </button>
                @endif
            </div>
        @endif
        </div>

    </div>{{-- /mz-listing__card --}}

</div>

@include('mediazone::media.partials.pdf-renderer')
