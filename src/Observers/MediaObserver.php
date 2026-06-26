<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MediaObserver
{
    public function saving($model): void
    {
        if ($model->isDirty('path') || ! $model->url) {
            $model->generateUrl();
        }

        $model->withoutNameExtension();
    }

    public function saved($model): void
    {
        Cache::forget("mediazone.media.{$model->id}");
    }

    public function deleted($model): void
    {
        if ($model->disk && $model->path) {
            $disk = Storage::disk($model->disk);
            $disk->delete($model->path);

            $cropsDir = dirname($model->path).'/crops';
            if ($disk->exists($cropsDir)) {
                $disk->deleteDirectory($cropsDir);
            }
        }

        Cache::forget("mediazone.media.{$model->id}");
    }
}
