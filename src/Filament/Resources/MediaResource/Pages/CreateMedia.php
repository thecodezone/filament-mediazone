<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Filament\Resources\MediaResource\Pages;

use Codezone\MediaZone\Filament\Resources\MediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $model = config('media.model', \Codezone\MediaZone\Models\Media::class);
        $file = $data['file'] ?? null;

        if (! $file) {
            return new $model($data);
        }

        $disk = config('media.disk', 'media');
        $path = $file;

        $storage = Storage::disk($disk);
        $mimeType = $storage->mimeType($path);
        $size = $storage->size($path);
        $name = basename($path);
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        $width = null;
        $height = null;

        if (str_starts_with($mimeType ?? '', 'image/') && ! str_contains($mimeType ?? '', 'svg')) {
            try {
                [$width, $height] = getimagesizefromstring($storage->get($path)) ?: [null, null];
            } catch (\Throwable) {
            }
        }

        $mediaData = [
            'disk' => $disk,
            'directory' => ltrim(dirname($path), './'),
            'name' => pathinfo($name, PATHINFO_FILENAME),
            'path' => $path,
            'ext' => $ext,
            'type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'alt' => $data['alt'] ?? null,
            'title' => $data['title'] ?? null,
            'caption' => $data['caption'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        return $model::create($mediaData);
    }
}
