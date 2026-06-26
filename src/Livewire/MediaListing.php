<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Livewire;

use Codezone\MediaZone\Filament\Resources\MediaResource;
use Codezone\MediaZone\Livewire\Concerns\HasMediaFilters;
use Codezone\MediaZone\Models\Media;
use Livewire\Component;
use Livewire\WithPagination;

class MediaListing extends Component
{
    use HasMediaFilters, WithPagination;

    public string $displayMode = 'grid';

    public string $sort = 'created_at';

    public string $sortDir = 'desc';

    protected $queryString = [
        'displayMode' => ['except' => 'grid', 'as' => 'view'],
        'search' => ['except' => ''],
        'filterExt' => ['except' => ''],
        'filterFolder' => ['except' => ''],
        'sort' => ['except' => 'created_at'],
        'sortDir' => ['except' => 'desc'],
    ];

    protected function getMediaModel(): string
    {
        return config('media.model', Media::class);
    }

    public function setDisplayMode(string $mode): void
    {
        $this->displayMode = $mode;
    }

    protected array $allowedSorts = ['name', 'created_at', 'size', 'type', 'ext'];

    public function setSort(string $column): void
    {
        if (! in_array($column, $this->allowedSorts, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function getMediaProperty()
    {
        $model = $this->getMediaModel();

        $sort = in_array($this->sort, $this->allowedSorts, true) ? $this->sort : 'created_at';
        $sortDir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        return $model::query()
            ->search($this->search)
            ->when($this->filterExt, fn ($q) => $q->where('ext', $this->filterExt))
            ->when($this->filterFolder, fn ($q) => $q->where('directory', $this->filterFolder))
            ->orderBy($sort, $sortDir)
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

    public function getEditUrl(int $id): ?string
    {
        $resource = config('media.resources.resource', MediaResource::class);

        try {
            return $resource::getUrl('edit', ['record' => $id]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function deleteMedia(int $id): void
    {
        $model = $this->getMediaModel();
        $model::findOrFail($id)->delete();
        $this->dispatch('media-deleted');
    }

    public function render()
    {
        return view('mediazone::livewire.media-listing', [
            'media' => $this->media,
            'extOptions' => $this->extOptions,
            'folderOptions' => $this->folderOptions,
        ]);
    }
}
