<?php

declare(strict_types=1);

use Codezone\MediaZone\Http\Controllers\MediaGlideController;
use Codezone\MediaZone\Models\Media;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(config('media.glide.route_middleware', ['web']))
    ->get('/'.config('media.glide.route_path', 'media').'/{path}', [MediaGlideController::class, 'show'])
    ->where('path', '.*')
    ->name('media.glide');

Route::get('/media-proxy/{id}', function (int $id) {
    $model = config('media.model', Media::class);
    $media = $model::findOrFail($id);
    $stream = Storage::disk($media->disk)->readStream($media->path);

    return response()->stream(
        function () use ($stream) {
            fpassthru($stream);
        },
        200,
        [
            'Content-Type' => $media->type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($media->path)).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]
    );
})->middleware(['web', 'auth'])->name('media.proxy');

Route::get('/media-download/{id}', function (int $id) {
    $model = config('media.model', Media::class);
    $media = $model::findOrFail($id);
    $stream = Storage::disk($media->disk)->readStream($media->path);

    return response()->stream(
        function () use ($stream) {
            fpassthru($stream);
        },
        200,
        [
            'Content-Type' => $media->type ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.addslashes(basename($media->path)).'"',
            'Cache-Control' => 'private, no-store',
        ]
    );
})->middleware(['web', 'auth'])->name('media.download');
