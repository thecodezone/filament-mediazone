<x-filament-panels::page
    @class(['fi-resource-list-records-page', 'fi-resource-media'])
>
    @include('mediazone::livewire.media-listing', [
        'media'         => $this->mediaItems,
        'extOptions'    => $this->extOptions,
        'folderOptions' => $this->folderOptions,
        'displayMode'   => $displayMode,
        'mediaSearch'   => $mediaSearch,
        'filterExt'     => $filterExt,
        'filterFolder'  => $filterFolder,
        'mediaSort'     => $mediaSort,
        'mediaSortDir'  => $mediaSortDir,
    ])
</x-filament-panels::page>
