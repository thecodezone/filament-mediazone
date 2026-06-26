<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Services;

use Illuminate\Support\Facades\Storage;

class Filenames
{
    use HasDisk;

    public function unique(string $path): bool
    {
        return ! Storage::disk($this->disk())->exists($path);
    }

    public function suggestUnique(string $path): string
    {
        $candidate = $path;
        $i = 1;
        while (! $this->unique($candidate)) {
            $candidate = $this->suffix($i, $path);
            $i++;
        }

        return $candidate;
    }

    public function suffixIfExists($suffix, string $path): string
    {
        if ($this->unique($path)) {
            return $path;
        }

        return $this->suffix($suffix, $path);
    }

    public function suffix(string|int $suffix, string $path): string
    {
        $info = pathinfo($path);
        $dirname = $info['dirname'];
        $filename = $info['filename'];
        $extension = $info['extension'] ?? '';

        return $dirname.'/'.$filename.'-'.$suffix.'.'.$extension;
    }
}
