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

    /**
     * Delete the media's own file and its crops — never a shared "crops"
     * directory. Multiple Media records commonly share a parent directory
     * (e.g. everything uploaded through the generic "uploads" bucket has a
     * path like "uploads/{name}.ext"), so dirname($model->path)."/crops"
     * is not exclusive to this record: deleting it would also destroy crop
     * files that belong to every other Media row stored under that same
     * parent directory. Each crop already records its own disk and path
     * (crops can even live on a different disk than the source file, e.g.
     * a cloud disk), so deleting exactly those is both safe and correct
     * regardless of storage layout.
     */
    public function deleted($model): void
    {
        if ($model->disk && $model->path) {
            Storage::disk($model->disk)->delete($model->path);
        }

        foreach ($model->crops ?? [] as $crop) {
            if (! empty($crop['disk']) && ! empty($crop['path'])) {
                Storage::disk($crop['disk'])->delete($crop['path']);
            }
        }

        Cache::forget("mediazone.media.{$model->id}");
    }
}
