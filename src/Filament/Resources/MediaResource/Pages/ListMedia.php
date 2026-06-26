<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Filament\Resources\MediaResource\Pages;

use Codezone\MediaZone\Filament\Resources\MediaResource;
use Codezone\MediaZone\Models\Media;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    public string $displayMode = 'grid';

    public string $mediaSearch = '';

    public string $filterExt = '';

    public string $filterFolder = '';

    public string $mediaSort = 'created_at';

    public string $mediaSortDir = 'desc';

    protected $queryString = [
        'displayMode' => ['except' => 'grid', 'as' => 'view'],
        'mediaSearch' => ['except' => '', 'as' => 'search'],
        'filterExt' => ['except' => ''],
        'filterFolder' => ['except' => ''],
        'mediaSort' => ['except' => 'created_at', 'as' => 'sort'],
        'mediaSortDir' => ['except' => 'desc', 'as' => 'dir'],
    ];

    public function updatingMediaSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterExt(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFolder(): void
    {
        $this->resetPage();
    }

    public function updatingMediaSort(): void
    {
        $this->resetPage();
    }

    public function toggleSortDir(): void
    {
        $this->mediaSortDir = $this->mediaSortDir === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->mediaSearch = '';
        $this->filterExt = '';
        $this->filterFolder = '';
        $this->resetPage();
    }

    public function setSort(string $column): void
    {
        if (! in_array($column, $this->allowedSorts, true)) {
            return;
        }

        if ($this->mediaSort === $column) {
            $this->mediaSortDir = $this->mediaSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->mediaSort = $column;
            $this->mediaSortDir = 'asc';
        }
        $this->resetPage();
    }

    protected array $allowedSorts = ['name', 'created_at', 'size', 'type', 'ext'];

    public function getEditUrl(int $id): ?string
    {
        $resource = config('media.resources.resource', MediaResource::class);

        try {
            return $resource::getUrl('edit', ['record' => $id]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getMediaModel(): string
    {
        return config('media.model', Media::class);
    }

    public function deleteMedia(int $id): void
    {
        $model = $this->getMediaModel();
        $model::findOrFail($id)->delete();
    }

    public function getMediaItemsProperty()
    {
        $model = $this->getMediaModel();

        return $model::query()
            ->when($this->mediaSearch, fn ($q) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$this->mediaSearch}%")
                    ->orWhere('title', 'like', "%{$this->mediaSearch}%")
                    ->orWhere('alt', 'like', "%{$this->mediaSearch}%")
            ))
            ->when($this->filterExt, fn ($q) => $q->where('ext', $this->filterExt))
            ->when($this->filterFolder, fn ($q) => $q->where('directory', $this->filterFolder))
            ->orderBy(
                in_array($this->mediaSort, $this->allowedSorts, true) ? $this->mediaSort : 'created_at',
                $this->mediaSortDir === 'asc' ? 'asc' : 'desc'
            )
            ->paginate(36);
    }

    public function getExtOptionsProperty(): array
    {
        $model = $this->getMediaModel();

        return $model::query()
            ->distinct()->orderBy('ext')
            ->pluck('ext', 'ext')->filter()->toArray();
    }

    public function getFolderOptionsProperty(): array
    {
        $model = $this->getMediaModel();

        return $model::query()
            ->distinct()->orderBy('directory')
            ->pluck('directory', 'directory')->filter()->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Upload Media'),
        ];
    }

    public function getView(): string
    {
        return 'mediazone::filament.resources.media.pages.list-media';
    }
}
