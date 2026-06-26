<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Services;

use Illuminate\Support\Facades\Storage;
use PHPExiftool\Reader;

class ImageMetadata
{
    use HasDisk;

    private array $localDisks = ['local', 'public', 'media'];

    /**
     * Get specific metadata key from an image.
     */
    public function get(string $path, string $key): mixed
    {
        $metadata = $this->all($path);

        return $metadata[$key] ?? null;
    }

    /**
     * Get all metadata from an image.
     */
    public function all(string $path): array
    {
        if (! class_exists(Reader::class)) {
            return [];
        }

        $localPath = $this->getLocalPath($path);

        try {
            $reader = Reader::create();

            return $reader->files($localPath)->first()->getData();
        } finally {
            $this->cleanup($localPath);
        }
    }

    private function getLocalPath(string $path): string
    {
        $disk = $this->disk();
        if (in_array($disk, $this->localDisks)) {
            return Storage::disk($disk)->path($path);
        }

        $localPath = storage_path('app/tmp/'.basename($path));
        Storage::disk($disk)->copy($path, $localPath);

        return $localPath;
    }

    public function cleanup(string $path): void
    {
        $disk = $this->disk();
        if (in_array($disk, $this->localDisks) && str_starts_with($path, storage_path('app/tmp/'))) {
            @unlink($path);
        }
    }
}
