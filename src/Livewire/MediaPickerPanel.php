<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Livewire;

use Codezone\MediaZone\Livewire\Concerns\HasMediaFilters;
use Codezone\MediaZone\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class MediaPickerPanel extends Component
{
    use HasMediaFilters, WithFileUploads;

    public string $statePath = '';

    public string $diskName = '';

    public ?string $directory = null;

    public array $acceptedFileTypes = [];

    public bool $isMultiple = false;

    public ?int $maxItems = null;

    public string $modalId = '';

    public array $selected = [];

    public array $uploads = [];

    public bool $uploading = false;

    public bool $open = false;

    public string $viewMode = 'grid';

    public string $activeTab = 'library';

    public int $perPage = 36;

    public int $page = 1;

    public int $lastPage = 1;

    public int $currentPage = 1;

    public array $allowedMediaIds = [];

    public function mount(
        string $statePath = '',
        string $diskName = '',
        ?string $directory = null,
        array $acceptedFileTypes = [],
        bool $isMultiple = false,
        ?int $maxItems = null,
        string $modalId = '',
        array $selected = [],
        $imageCropAspectRatio = null,
        $imageResizeMode = null,
        $imageResizeTargetWidth = null,
        $imageResizeTargetHeight = null,
        $isLimitedToDirectory = false,
        $maxWidth = null,
        $minSize = null,
        $maxSize = null,
        $pathGenerator = null,
        $rules = [],
        $shouldPreserveFilenames = false,
        $types = [],
        $visibility = 'public',
    ): void {
        $this->statePath = $statePath;
        $this->diskName = $diskName ?: config('media.disk', 'media');
        $this->directory = $directory;
        $this->acceptedFileTypes = $acceptedFileTypes;
        $this->isMultiple = $isMultiple;
        $this->maxItems = $maxItems;
        $this->modalId = $modalId ?: 'open_media_picker';
        $this->selected = $selected;
    }

    #[On('open-media-picker')]
    public function openFor(string $statePath, bool $isMultiple = false, array $selected = [], array $allowedMediaIds = []): void
    {
        $this->statePath = $statePath;
        $this->isMultiple = $isMultiple;
        $this->selected = $selected;
        $this->allowedMediaIds = $allowedMediaIds;

        if (! empty($allowedMediaIds)) {
            session(['media_picker_allowed_ids.'.md5($statePath) => $allowedMediaIds]);
        } else {
            session()->forget('media_picker_allowed_ids.'.md5($statePath));
        }

        $this->search = '';
        $this->filterExt = '';
        $this->filterFolder = '';
        $this->page = 1;
        $this->activeTab = 'library';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->allowedMediaIds = [];
        if ($this->statePath) {
            session()->forget('media_picker_allowed_ids.'.md5($this->statePath));
        }
    }

    protected function getMediaModel(): string
    {
        return config('media.model', Media::class);
    }

    public function getFilesProperty(): array
    {
        $model = $this->getMediaModel();
        $allowedIds = $this->resolvedAllowedIds();

        $query = $model::query()
            ->search($this->search)
            ->when(! empty($allowedIds), fn ($q) => $q->whereIn('id', $allowedIds))
            ->when(empty($allowedIds) && $this->directory, fn ($q) => $q->where('directory', $this->directory))
            ->when(empty($allowedIds), fn ($q) => $q
                ->when($this->filterExt, fn ($q) => $q->where('ext', $this->filterExt))
                ->when($this->filterFolder, fn ($q) => $q->where('directory', $this->filterFolder))
            )
            ->latest()
            ->paginate($this->perPage, ['*'], 'page', $this->page);

        $this->lastPage = $query->lastPage();
        $this->currentPage = $query->currentPage();

        return $query->items();
    }

    protected function resolvedAllowedIds(): array
    {
        if (! empty($this->allowedMediaIds)) {
            return $this->allowedMediaIds;
        }

        if ($this->statePath) {
            return session('media_picker_allowed_ids.'.md5($this->statePath), []);
        }

        return [];
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

    public function getTotalCountProperty(): int
    {
        $model = $this->getMediaModel();
        $allowedIds = $this->resolvedAllowedIds();

        return $model::query()
            ->search($this->search)
            ->when(! empty($allowedIds), fn ($q) => $q->whereIn('id', $allowedIds))
            ->when(empty($allowedIds), fn ($q) => $q
                ->when($this->filterExt, fn ($q) => $q->where('ext', $this->filterExt))
                ->when($this->filterFolder, fn ($q) => $q->where('directory', $this->filterFolder))
            )
            ->count();
    }

    public function addToSelection(int $id): void
    {
        $model = $this->getMediaModel();
        $media = $model::find($id);
        if (! $media) {
            return;
        }

        if (! $this->isMultiple) {
            $this->selected = [$media->toMediaArray()];

            return;
        }

        $existingIds = array_column($this->selected, 'id');
        if (! in_array($id, $existingIds)) {
            $this->selected[] = $media->toMediaArray();
        }
    }

    public function removeFromSelection(int $id): void
    {
        $this->selected = array_values(array_filter(
            $this->selected, fn ($item) => ($item['id'] ?? null) != $id
        ));
    }

    public function insertMedia(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $media = [];
        foreach ($this->selected as $item) {
            $uuid = (string) ($item['id'] ?? Str::uuid());
            $media[$uuid] = $item;
        }

        $this->open = false;
        $this->dispatch('insert-content', statePath: $this->statePath, media: $media);
    }

    public function loadMoreFiles(): void
    {
        if ($this->page < $this->lastPage) {
            $this->page++;
        }
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, min($page, $this->lastPage ?: 1));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function updatedUploads(): void
    {
        $disk = config('media.disk', 'media');
        $storage = Storage::disk($disk);
        $model = $this->getMediaModel();
        $newMedia = [];

        foreach ($this->uploads as $upload) {
            if (! $upload instanceof TemporaryUploadedFile) {
                continue;
            }

            try {
                $originalName = $upload->getClientOriginalName();
                $ext = $upload->getClientOriginalExtension();
                $mimeType = $upload->getMimeType();
                $size = $upload->getSize();
                $name = pathinfo($originalName, PATHINFO_FILENAME);
                $dir = $this->directory ?? 'uploads';
                $path = $upload->storeAs($dir, $originalName, $disk);

                $width = null;
                $height = null;
                if (str_starts_with($mimeType ?? '', 'image/') && ! str_contains($mimeType ?? '', 'svg')) {
                    try {
                        [$width, $height] = getimagesizefromstring($storage->get($path)) ?: [null, null];
                    } catch (\Throwable) {
                    }
                }

                $media = $model::create([
                    'disk' => $disk,
                    'directory' => $dir,
                    'name' => $name,
                    'path' => $path,
                    'ext' => $ext,
                    'type' => $mimeType,
                    'size' => $size,
                    'width' => $width,
                    'height' => $height,
                ]);

                $newMedia[] = $media;
            } catch (\Throwable) {
            }
        }

        $this->uploads = [];

        if (! empty($newMedia)) {
            if (! $this->isMultiple) {
                $this->selected = [$newMedia[0]->toMediaArray()];
            } else {
                $existingIds = array_column($this->selected, 'id');
                foreach ($newMedia as $media) {
                    if (! in_array($media->id, $existingIds)) {
                        $this->selected[] = $media->toMediaArray();
                    }
                }
            }
        }

        $this->activeTab = 'library';
        $this->page = 1;
    }

    public function render(): View
    {
        return view('mediazone::livewire.media-picker-panel', [
            'files' => $this->getFilesProperty(),
            'extOptions' => $this->getExtOptionsProperty(),
            'folderOptions' => $this->getFolderOptionsProperty(),
            'totalCount' => $this->getTotalCountProperty(),
        ]);
    }
}
