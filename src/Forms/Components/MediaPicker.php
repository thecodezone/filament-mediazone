<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Forms\Components;

use Codezone\MediaZone\Media\CropPreset;
use Filament\Actions\StaticAction;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;

class MediaPicker extends Field
{
    protected string $view = 'mediazone::media.forms.picker';

    protected bool $isMultiple = false;

    protected ?int $maxItems = null;

    protected bool $displayAsList = false;

    protected ?string $directory = null;

    protected array $acceptedFileTypes = [];

    protected ?string $orderColumn = null;

    protected ?string $relationshipName = null;

    protected ?string $relationshipTitleAttribute = null;

    protected bool $preserveFilenames = false;

    protected bool $tenantAware = true;

    protected ?string $diskName = null;

    protected bool $isConstrained = false;

    protected bool $shouldLazyLoad = true;

    protected bool $showBreakpointCrops = false;

    protected bool $showCropKeyPicker = false;

    protected ?string $locationKey = null;

    protected string|\Closure|null $initialView = 'grid';

    protected array|\Closure $allowedMediaIds = [];

    // ----------------------------------------------------------------
    // Fluent setters
    // ----------------------------------------------------------------

    public function multiple(bool $multiple = true): static
    {
        $this->isMultiple = $multiple;

        return $this;
    }

    public function maxItems(?int $max): static
    {
        $this->maxItems = $max;

        return $this;
    }

    public function listDisplay(bool $list = true): static
    {
        $this->displayAsList = $list;
        $this->initialView = $list ? 'list' : 'grid';

        return $this;
    }

    public function directory(?string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function acceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }

    public function orderColumn(string $column): static
    {
        $this->orderColumn = $column;

        return $this;
    }

    public function relationship(string $name, string $titleAttribute): static
    {
        $this->relationshipName = $name;
        $this->relationshipTitleAttribute = $titleAttribute;

        return $this;
    }

    public function preserveFilenames(bool $preserve = true): static
    {
        $this->preserveFilenames = $preserve;

        return $this;
    }

    public function tenantAware(bool $aware = true): static
    {
        $this->tenantAware = $aware;

        return $this;
    }

    public function disk(?string $disk): static
    {
        $this->diskName = $disk;

        return $this;
    }

    public function constrained(bool $constrained = true): static
    {
        $this->isConstrained = $constrained;

        return $this;
    }

    public function lazyLoad(bool $lazyLoad = true): static
    {
        $this->shouldLazyLoad = $lazyLoad;

        return $this;
    }

    public function breakpointCrops(bool $enabled = true): static
    {
        $this->showBreakpointCrops = $enabled;

        return $this;
    }

    public function shouldShowBreakpointCrops(): bool
    {
        return $this->showBreakpointCrops;
    }

    public function cropKeyPicker(bool $enabled = true): static
    {
        $this->showCropKeyPicker = $enabled;

        return $this;
    }

    public function shouldShowCropKeyPicker(): bool
    {
        if (! $this->showCropKeyPicker) {
            return false;
        }

        try {
            $statePath = $this->getStatePath();
            if (str_contains($statePath, '.data.')) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        try {
            $livewire = $this->getLivewire();
            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
            if ($record && $this->relationshipName) {
                $relation = $record->{$this->relationshipName}();
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    return true;
                }
                $cropKeyCol = $this->getCropKeyColumnForRecord($record);
                if (! $cropKeyCol || ! \Illuminate\Support\Facades\Schema::hasColumn($record->getTable(), $cropKeyCol)) {
                    return false;
                }
            } elseif ($record && ! $this->relationshipName) {
                $cropKeyCol = $this->getCropKeyColumnForRecord($record);
                if (! $cropKeyCol || ! \Illuminate\Support\Facades\Schema::hasColumn($record->getTable(), $cropKeyCol)) {
                    return false;
                }
            }
        } catch (\Throwable) {
        }

        return true;
    }

    protected function getCropKeyColumnForRecord(Model $record): ?string
    {
        if ($this->relationshipName) {
            $relation = $record->{$this->relationshipName}();
            if (! ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo)) {
                return null;
            }
            $base = preg_replace('/_id$/', '', $relation->getForeignKeyName());

            return $base.'_crop_key';
        }

        return $this->getCropKeyColumn();
    }

    public function location(string $locationKey): static
    {
        $this->locationKey = $locationKey;

        return $this;
    }

    public function getLocationKey(): ?string
    {
        return $this->locationKey;
    }

    public function allowedMedia(array|\Closure $media): static
    {
        $this->allowedMediaIds = $media;

        return $this;
    }

    public function hasAllowedMedia(): bool
    {
        return ! empty($this->allowedMediaIds);
    }

    public function getAllowedMediaIds(): array
    {
        $media = $this->evaluate($this->allowedMediaIds);

        return collect($media)->map(fn ($item) => is_object($item) ? $item->id : (int) $item
        )->filter()->values()->all();
    }

    public function initialView(string|\Closure|null $view): static
    {
        $this->initialView = $view;

        return $this;
    }

    public function getInitialView(): ?string
    {
        return $this->evaluate($this->initialView);
    }

    // ----------------------------------------------------------------
    // Getters
    // ----------------------------------------------------------------

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function shouldDisplayAsList(): bool
    {
        $iv = $this->getInitialView();
        if ($iv !== null) {
            return $iv === 'list';
        }

        return $this->displayAsList;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    public function getAcceptedFileTypes(): array
    {
        return $this->acceptedFileTypes;
    }

    public function getOrderColumn(): ?string
    {
        return $this->orderColumn;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationshipName;
    }

    public function getDiskName(): string
    {
        return $this->diskName ?? config('media.disk', 'media');
    }

    public function isConstrained(): bool
    {
        return $this->isConstrained;
    }

    public function shouldLazyLoad(): bool
    {
        return $this->shouldLazyLoad;
    }

    // ----------------------------------------------------------------
    // State hydration / dehydration
    // ----------------------------------------------------------------

    protected function getMediaModel(): string
    {
        return config('media.model', \Codezone\MediaZone\Models\Media::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(static function (MediaPicker $component, $state): void {
            $component->normalizeState($state);
        });

        $this->dehydrateStateUsing(static function (MediaPicker $component, mixed $state): mixed {
            if ($component->getRelationshipName()) {
                try {
                    $livewire = $component->getLivewire();
                    $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                    if ($record) {
                        $relation = $record->{$component->getRelationshipName()}();
                        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                            $items = array_values(is_array($state) ? $state : []);
                            $mediaId = $items[0]['id'] ?? null;

                            $cropKeyCol = $component->getCropKeyColumnForRecord($record);
                            if ($cropKeyCol && \Illuminate\Support\Facades\Schema::hasColumn($record->getTable(), $cropKeyCol)) {
                                $record->{$cropKeyCol} = $items[0]['crop_key'] ?? null;
                                $record->saveQuietly();
                            }

                            return $mediaId;
                        }
                    }
                } catch (\Throwable) {
                }

                return null;
            }

            if (! is_array($state)) {
                return $state;
            }

            $items = array_values($state);

            if (empty($items)) {
                return null;
            }

            if (! $component->isMultiple()) {
                $cropKeyCol = $component->getCropKeyColumn();
                if ($cropKeyCol) {
                    $component->saveCropKeyToRecord();

                    return $items[0]['id'] ?? null;
                }

                $id = $items[0]['id'] ?? null;
                $cropKey = $items[0]['crop_key'] ?? null;

                return $cropKey ? ['id' => $id, 'crop_key' => $cropKey] : $id;
            }

            return array_map(function ($item) {
                $out = ['id' => $item['id']];
                if (! empty($item['crop_key'])) {
                    $out['crop_key'] = $item['crop_key'];
                }

                return $out;
            }, $items);
        });

        $this->registerActions([
            Action::make('open_media_picker')
                ->label('Add Media')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->modalContent(fn (MediaPicker $component) => view(
                    'mediazone::media.actions.picker-action',
                    [
                        'acceptedFileTypes' => $component->getAcceptedFileTypes(),
                        'diskName' => $component->getDiskName(),
                        'directory' => $component->getDirectory(),
                        'imageCropAspectRatio' => null,
                        'imageResizeMode' => null,
                        'imageResizeTargetWidth' => null,
                        'imageResizeTargetHeight' => null,
                        'isLimitedToDirectory' => false,
                        'isMultiple' => $component->isMultiple(),
                        'maxItems' => $component->getMaxItems(),
                        'maxWidth' => null,
                        'minSize' => null,
                        'maxSize' => null,
                        'modalId' => 'open_media_picker',
                        'pathGenerator' => null,
                        'rules' => [],
                        'selected' => array_values($component->getState() ?? []),
                        'shouldPreserveFilenames' => $component->preserveFilenames,
                        'statePath' => $component->getStatePath(),
                        'allowedMediaIds' => $component->getAllowedMediaIds(),
                        'types' => [],
                        'visibility' => 'public',
                    ]
                ))
                ->modalHeading(null)
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modal()
                ->modalWidth('7xl'),

            Action::make('reorder')
                ->label('Reorder')
                ->icon('heroicon-s-arrows-up-down')
                ->color('gray')
                ->action(function (MediaPicker $component, array $arguments): void {
                    $uuids = $arguments['items'] ?? null;
                    if (! is_array($uuids)) {
                        return;
                    }
                    $items = $component->getState() ?? [];
                    $reordered = [];
                    foreach ($uuids as $uuid) {
                        if (array_key_exists($uuid, $items)) {
                            $reordered[$uuid] = $items[$uuid];
                        }
                    }
                    $component->state($reordered);
                }),

            Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-s-pencil')
                ->color('gray')
                ->url(function (array $arguments) {
                    if (! isset($arguments['id'])) {
                        return null;
                    }
                    $resource = config('media.resources.resource', \Codezone\MediaZone\Filament\Resources\MediaResource::class);

                    try {
                        return $resource::getUrl('edit', ['record' => $arguments['id']]);
                    } catch (\Throwable) {
                        return null;
                    }
                })
                ->openUrlInNewTab(),

            Action::make('view')
                ->label('View')
                ->icon('heroicon-s-eye')
                ->color('gray')
                ->url(fn (array $arguments) => $arguments['url'] ?? null)
                ->openUrlInNewTab(),

            Action::make('download')
                ->label('Download')
                ->icon('heroicon-s-arrow-down-tray')
                ->color('gray')
                ->url(function (array $arguments, MediaPicker $component): ?string {
                    $uuid = $arguments['uuid'] ?? null;
                    if (! $uuid) {
                        return null;
                    }
                    $items = $component->getState() ?? [];
                    $item = $items[$uuid] ?? null;
                    $id = $item['id'] ?? null;
                    if (! $id) {
                        return null;
                    }

                    return route('media.download', $id);
                }),

            Action::make('crop')
                ->label('Add Crop')
                ->icon('heroicon-s-scissors')
                ->color('gray')
                ->modalContent(function (array $arguments, MediaPicker $component): \Illuminate\Contracts\View\View {
                    $model = $component->getMediaModel();
                    $id = $arguments['id'] ?? null;
                    $media = $id ? $model::find($id) : null;

                    return view('mediazone::media.actions.crop-action', [
                        'statePath' => $component->getStatePath(),
                        'modalId' => 'crop-picker-'.$id,
                        'media' => $media ? $media->toArray() : [],
                        'presets' => CropPreset::allAsArray(),
                        'formats' => config('media.crop_formats', ['webp', 'jpg', 'png']),
                        'defaultLocation' => $component->getLocationKey(),
                    ]);
                })
                ->modalHeading('Add Crop')
                ->modalSubmitAction(false)
                ->modalCancelAction(fn (StaticAction $action) => $action->label('Close'))
                ->extraModalFooterActions(fn (): array => [
                    StaticAction::make('save_crop')
                        ->button()
                        ->label('Save crop')
                        ->color('primary')
                        ->alpineClickHandler("window.dispatchEvent(new CustomEvent('mz-cropper__save'))")
                        ->extraAttributes([
                            'data-mz-cropper__save-btn' => 'true',
                            'onclick' => "window.dispatchEvent(new CustomEvent('mz-cropper__save'))",
                        ]),
                ])
                ->slideOver()
                ->modalWidth('screen')
                ->extraModalWindowAttributes(['style' => 'overflow:hidden;display:flex;flex-direction:column;'])
                ->after(function (MediaPicker $component, array $arguments): void {
                    $model = $component->getMediaModel();
                    $id = $arguments['id'] ?? null;
                    if (! $id) {
                        return;
                    }
                    $media = $model::find($id);
                    if (! $media) {
                        return;
                    }
                    $uuid = (string) $id;
                    $items = $component->getState() ?? [];
                    if (isset($items[$uuid])) {
                        $items[$uuid] = array_merge($items[$uuid], [
                            'crops' => $media->crops ?? [],
                            'crop_options' => $media->toMediaArray()['crop_options'],
                        ]);
                        $component->state($items);
                    }
                }),

            Action::make('touch_crop')
                ->action(function (array $arguments, MediaPicker $component): void {
                    $model = $component->getMediaModel();
                    $mediaId = $arguments['mediaId'] ?? null;
                    $cropKey = $arguments['key'] ?? null;
                    if (! $mediaId || ! $cropKey) {
                        return;
                    }
                    $media = $model::find($mediaId);
                    if (! $media) {
                        return;
                    }
                    $crops = $media->crops ?? [];
                    $touched = false;
                    $selectedBreakpoints = [];
                    foreach ($crops as &$crop) {
                        if (($crop['key'] ?? null) === $cropKey) {
                            $crop['updated_at'] = now()->toISOString();
                            $selectedBreakpoints = array_merge($selectedBreakpoints, $crop['breakpoints'] ?? []);
                            $touched = true;
                        }
                    }
                    unset($crop);
                    if ($touched) {
                        $media->crops = $crops;
                        $media->timestamps = false;
                        $media->saveQuietly();
                        $media->timestamps = true;
                    }

                    if (! empty($selectedBreakpoints)) {
                        $media->removeBreakpointsFromSiblings($cropKey, array_unique($selectedBreakpoints));
                    }
                }),

            Action::make('select_crop_key')
                ->label('Select Crop')
                ->icon('heroicon-s-scissors')
                ->color('gray')
                ->modalContent(function (array $arguments, MediaPicker $component): \Illuminate\Contracts\View\View {
                    $model = $component->getMediaModel();
                    $id = $arguments['id'] ?? null;
                    $uuid = $arguments['uuid'] ?? null;
                    $media = $id ? $model::find($id) : null;
                    $crops = $media?->crops ?? [];

                    $filterLocation = $component->getLocationKey();
                    $byKey = [];
                    foreach ($crops as $crop) {
                        $key = $crop['key'] ?? ($crop['crop']['key'] ?? null);
                        if (! $key) {
                            continue;
                        }
                        if ($filterLocation && ($crop['location'] ?? null) !== $filterLocation) {
                            continue;
                        }
                        $bps = $crop['breakpoints'] ?? [];
                        $isDesktop = empty($bps) || in_array('desktop', $bps, true);
                        if (! $isDesktop) {
                            continue;
                        }
                        $existing = $byKey[$key] ?? null;
                        if (! $existing || ($crop['updated_at'] ?? '') > ($existing['updated_at'] ?? '')) {
                            $byKey[$key] = $crop;
                        }
                    }

                    $currentKey = null;
                    if ($uuid) {
                        $items = $component->getState() ?? [];
                        $currentKey = $items[$uuid]['crop_key'] ?? null;
                    }

                    return view('mediazone::media.actions.select-crop-key-action', [
                        'uuid' => $uuid,
                        'statePath' => $component->getStatePath(),
                        'locationKey' => $component->getLocationKey(),
                        'cropsByKey' => $byKey,
                        'currentKey' => $currentKey,
                        'mediaUrl' => $media?->url,
                        'mediaName' => $media?->pretty_name ?? $media?->name,
                    ]);
                })
                ->modalHeading('Select Crop')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Done')
                ->modalWidth('4xl'),

            Action::make('remove')
                ->label('Remove')
                ->icon('heroicon-s-trash')
                ->color('danger')
                ->action(function (MediaPicker $component, array $arguments): void {
                    $uuid = $arguments['uuid'] ?? null;
                    if (! $uuid) {
                        return;
                    }
                    $items = $component->getState() ?? [];
                    unset($items[$uuid]);
                    $component->state(array_values($items) ? $items : []);
                }),

            Action::make('removeAll')
                ->label('Remove All')
                ->icon('heroicon-s-trash')
                ->color('danger')
                ->action(function (MediaPicker $component): void {
                    $component->state([]);
                }),
        ]);
    }

    public function normalizeState(mixed $state): void
    {
        if ($this->relationshipName) {
            try {
                $livewire = $this->getLivewire();
                $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                if ($record) {
                    $this->loadRelationshipState($record);

                    return;
                }
            } catch (\Throwable) {
            }
        }

        if ($state instanceof Model) {
            $state = $state->getKey();
        }

        $resolved = $this->resolveStateFromRaw($state);
        if ($resolved !== null) {
            if (! $this->isMultiple() && ! empty($resolved)) {
                $cropKeyCol = $this->getCropKeyColumn();
                if ($cropKeyCol) {
                    try {
                        $livewire = $this->getLivewire();
                        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                        if ($record) {
                            $cropKey = data_get($record, $cropKeyCol);
                            $firstKey = array_key_first($resolved);
                            if ($firstKey !== null && $cropKey) {
                                $resolved[$firstKey]['crop_key'] = $cropKey;
                                $resolved[$firstKey] = $this->hydrateSelectedCropUrl($resolved[$firstKey]);
                            }
                        }
                    } catch (\Throwable) {
                    }
                }
            }

            $this->state($resolved);
        }
    }

    public function resolveStateFromRaw(mixed $state): ?array
    {
        $model = $this->getMediaModel();

        if (is_null($state) || $state === '') {
            return [];
        }

        if (is_int($state) || (is_string($state) && ctype_digit($state))) {
            $media = $model::find((int) $state);

            return $media ? [(string) $media->id => $media->toMediaArray()] : [];
        }

        if (is_array($state) && isset($state['id']) && ! isset($state[0])) {
            $media = $model::find((int) $state['id']);
            if (! $media) {
                return [];
            }
            $arr = $media->toMediaArray();
            if (! empty($state['crop_key'])) {
                $arr['crop_key'] = $state['crop_key'];
            }

            return [(string) $media->id => $arr];
        }

        if (is_array($state)) {
            if (empty($state)) {
                return [];
            }

            $firstValue = reset($state);

            if (is_array($firstValue) && isset($firstValue['id'], $firstValue['url'])) {
                return null;
            }

            if (is_array($firstValue) && isset($firstValue['id']) && ! isset($firstValue['url'])) {
                $entries = array_values($state);
                $ids = array_column($entries, 'id');
                $cropKeys = array_column($entries, 'crop_key', 'id');
                $mediaItems = $model::findMany(array_map('intval', $ids));
                $items = [];
                foreach ($mediaItems as $media) {
                    $arr = $media->toMediaArray();
                    if (! empty($cropKeys[$media->id])) {
                        $arr['crop_key'] = $cropKeys[$media->id];
                    }
                    $items[(string) $media->id] = $arr;
                }

                return $items;
            }

            if (is_int($firstValue) || (is_string($firstValue) && ctype_digit((string) $firstValue))) {
                $mediaItems = $model::findMany(array_map('intval', array_values($state)));
                $items = [];
                foreach ($mediaItems as $media) {
                    $items[(string) $media->id] = $media->toMediaArray();
                }

                return $items;
            }
        }

        return null;
    }

    protected function loadRelationshipState(Model $record): void
    {
        $relation = $record->{$this->relationshipName}();
        $isBelongsTo = $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo;

        $related = $relation->get();

        if ($related->isEmpty()) {
            $this->state([]);

            return;
        }

        $belongsToCropKey = null;
        if ($isBelongsTo) {
            $cropKeyCol = $this->getCropKeyColumnForRecord($record);
            if ($cropKeyCol && \Illuminate\Support\Facades\Schema::hasColumn($record->getTable(), $cropKeyCol)) {
                $belongsToCropKey = $record->{$cropKeyCol};
            }
        }

        $items = [];
        foreach ($related as $media) {
            $uuid = (string) $media->id;
            $arr = $media->toMediaArray();

            if ($isBelongsTo) {
                $arr['crop_key'] = $belongsToCropKey;
            } elseif ($media->pivot ?? null) {
                $arr['mobile_crop_key'] = $media->pivot->mobile_crop_key ?? null;
                $arr['tablet_crop_key'] = $media->pivot->tablet_crop_key ?? null;
                $arr['desktop_crop_key'] = $media->pivot->desktop_crop_key ?? null;
                $arr['crop_key'] = $media->pivot->crop_key ?? null;
            }

            $arr = $this->hydrateSelectedCropUrl($arr);
            $items[$uuid] = $arr;
        }

        $this->state($items);
    }

    protected function hydrateSelectedCropUrl(array $arr): array
    {
        $cropKey = $arr['crop_key'] ?? null;
        if (! $cropKey) {
            return $arr;
        }

        $cropsByLocation = $arr['crops_by_location'] ?? [];
        if (isset($cropsByLocation[$cropKey]['url'])) {
            $arr['selected_crop_url'] = $cropsByLocation[$cropKey]['url'];

            return $arr;
        }

        foreach ($arr['crops'] ?? [] as $crop) {
            $key = $crop['key'] ?? ($crop['crop']['key'] ?? null);
            if ($key === $cropKey && ! empty($crop['url'])) {
                $arr['selected_crop_url'] = $crop['url'];

                return $arr;
            }
        }

        return $arr;
    }

    public function saveRelationships(): void
    {
        $this->saveCropKeyToRecord();

        if (! $this->relationshipName) {
            return;
        }

        $livewire = $this->getLivewire();
        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
        if (! $record) {
            return;
        }

        $relation = $record->{$this->relationshipName}();
        $items = array_values($this->getState() ?? []);

        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
            $id = $items[0]['id'] ?? null;
            if ($id) {
                $relation->associate($id);
            } else {
                $relation->dissociate();
            }
            $record->save();

            return;
        }

        $syncData = [];

        foreach ($items as $order => $item) {
            $id = $item['id'] ?? null;
            if (! $id) {
                continue;
            }

            $pivotData = [];

            if ($this->orderColumn) {
                $pivotData[$this->orderColumn] = $order + 1;
            }

            // Only pass pivot columns that exist on the relation's pivot table
            $pivotColumns = config('media.picker.pivot_columns', []);
            foreach ($pivotColumns as $col) {
                if (array_key_exists($col, $item)) {
                    $pivotData[$col] = $item[$col];
                }
            }

            $syncData[$id] = $pivotData;
        }

        $relation->sync($syncData);
    }

    public function getCropKeyColumn(): ?string
    {
        if ($this->relationshipName || $this->isMultiple()) {
            return null;
        }

        try {
            $path = $this->getStatePath();
        } catch (\Throwable) {
            return null;
        }

        if (preg_match('/^data\..+\.data\./', $path)) {
            return null;
        }

        $path = preg_replace('/^data\./', '', $path);

        if (! $path) {
            return null;
        }

        $segments = explode('.', $path);
        $base = array_pop($segments);

        if (! $base) {
            return null;
        }

        if (! empty($segments)) {
            return implode('.', $segments).'.'.$base.'_crop_key';
        }

        return $base.'_crop_key';
    }

    public function saveCropKeyToRecord(): void
    {
        $cropKeyCol = $this->getCropKeyColumn();
        if (! $cropKeyCol) {
            return;
        }

        try {
            $livewire = $this->getLivewire();
            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
            if (! $record) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $items = array_values($this->getState() ?? []);
        $cropKey = ! empty($items) ? ($items[0]['crop_key'] ?? null) : null;

        $segments = explode('.', $cropKeyCol);
        if (count($segments) > 1) {
            $attribute = array_shift($segments);
            $nested = $record->{$attribute} ?? [];
            data_set($nested, implode('.', $segments), $cropKey);
            $record->{$attribute} = $nested;
        } else {
            $record->{$cropKeyCol} = $cropKey;
        }

        $record->save();
    }
}
