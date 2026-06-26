<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Filament\Resources\MediaResource\Pages;

use Codezone\MediaZone\Filament\Resources\MediaResource;
use Codezone\MediaZone\Services\Filenames;
use Filament\Actions;
use Filament\Actions\StaticAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\Action::make('view_file')
                ->label('View File')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->getRecord()->url)
                ->openUrlInNewTab(),

            Actions\Action::make('crop')
                ->label('New Crop')
                ->icon('heroicon-o-scissors')
                ->color('gray')
                ->visible($record->isCroppableImage())
                ->modalContent(fn () => view(
                    'mediazone::media.actions.crop-action',
                    MediaResource::cropActionViewData($this->getRecord())
                ))
                ->modalHeading('New Crop')
                ->modalSubmitAction(false)
                ->modalCancelAction(fn (StaticAction $action) => $action->label('Close'))
                ->extraModalFooterActions(fn (): array => [
                    StaticAction::make('save_crop')
                        ->button()
                        ->label('Save crop')
                        ->color('primary')
                        ->alpineClickHandler("\$dispatch('mz-cropper__save')"),
                ])
                ->slideOver()
                ->modalWidth('screen')
                ->extraModalWindowAttributes(['style' => 'overflow:hidden;display:flex;flex-direction:column;']),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function updateCrop(string $id, array $data): void
    {
        $record = $this->getRecord();

        $newLocation = $data['location'] ?? null;
        $newKey = trim($data['key'] ?? '') ?: $newLocation ?: null;
        $newBreakpoints = array_values(array_filter((array) ($data['breakpoints'] ?? [])));

        $crops = array_map(function ($c) use ($id, $newKey, $newLocation, $newBreakpoints) {
            if (($c['id'] ?? null) === $id) {
                if ($newKey !== null) {
                    $c['key'] = $newKey;
                    $c['name'] = $newKey;
                    if (isset($c['crop']['key'])) {
                        $c['crop']['key'] = $newKey;
                    }
                }
                $c['location'] = $newLocation;
                $c['breakpoints'] = $newBreakpoints;
            } elseif ($newKey && ! empty($newBreakpoints) && ($c['key'] ?? null) === $newKey) {
                $c['breakpoints'] = array_values(array_diff($c['breakpoints'] ?? [], $newBreakpoints));
            }

            return $c;
        }, $record->crops ?? []);

        $record->crops = array_values($crops);
        $record->timestamps = false;
        $record->saveQuietly();
        $record->timestamps = true;

        if (! empty($newBreakpoints) && $newKey) {
            $record->removeBreakpointsFromSiblings($newKey, $newBreakpoints);
        }

        $this->unmountFormComponentAction();
        $this->refreshFormData(['crops']);
    }

    public function deleteCrop(string $id): void
    {
        $record = $this->getRecord();
        $crop = collect($record->crops ?? [])->first(fn ($c) => ($c['id'] ?? null) === $id);

        if ($crop && ! empty($crop['path'])) {
            Storage::disk($record->disk)->delete($crop['path']);
        }

        $record->crops = array_values(
            array_filter($record->crops ?? [], fn ($c) => ($c['id'] ?? null) !== $id)
        );
        $record->save();

        $this->refreshFormData(['crops']);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if (! empty($data['replace_file'])) {
            $newPath = $data['replace_file'];
            $disk = config('media.disk', 'media');

            if (Storage::disk($disk)->exists($newPath)) {
                if ($record->path && Storage::disk($disk)->exists($record->path)) {
                    Storage::disk($disk)->delete($record->path);
                }

                $contents = Storage::disk($disk)->get($newPath);
                $mimeType = Storage::disk($disk)->mimeType($newPath) ?: 'application/octet-stream';
                $size = Storage::disk($disk)->size($newPath);

                $data['path'] = $newPath;
                $data['disk'] = $disk;
                $data['type'] = $mimeType;
                $data['size'] = $size;
                $data['ext'] = pathinfo($newPath, PATHINFO_EXTENSION);
                $data['name'] = pathinfo($newPath, PATHINFO_BASENAME);
                $data['directory'] = ltrim(dirname($newPath), '/');
                $data['url'] = null;

                try {
                    $image = ImageManagerStatic::make($contents);
                    $data['width'] = $image->width();
                    $data['height'] = $image->height();
                } catch (\Throwable) {
                    $data['width'] = null;
                    $data['height'] = null;
                }
            }
        }

        unset($data['replace_file'], $data['_replace']);

        $newName = $data['name'] ?? null;
        if ($newName && $newName !== $record->name && empty($data['replace_file'])) {
            $disk = $record->disk;
            $oldPath = $record->path;
            $ext = $record->ext;
            $dir = ltrim(dirname($oldPath), './');
            $newFilename = $newName.($ext ? '.'.$ext : '');
            $newPath = ($dir && $dir !== '.') ? $dir.'/'.$newFilename : $newFilename;

            if ($newPath !== $oldPath) {
                $newPath = (new Filenames)->disk($disk)->suggestUnique($newPath);
            }

            if (Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->copy($oldPath, $newPath);
                Storage::disk($disk)->delete($oldPath);
            }

            $data['path'] = $newPath;
            $data['url'] = null;
            $data['name'] = pathinfo($newPath, PATHINFO_FILENAME);
        }

        return $data;
    }
}
