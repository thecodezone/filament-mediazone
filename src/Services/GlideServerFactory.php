<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Services;

use Illuminate\Support\Facades\Storage;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\Server;
use League\Glide\ServerFactory;

class GlideServerFactory
{
    public function getFactory(): ServerFactory|Server
    {
        $filesystem = Storage::disk(config('media.disk'));
        $defaults = ['bg' => 'ffffff'];

        $driver = config('media.glide.driver', 'imagick');

        // Read through the configured disk's own Flysystem adapter rather
        // than a hardcoded "storage/app/public" + "public" prefix — that
        // assumed the disk's root *is* Laravel's built-in "public" disk,
        // which only holds for consuming apps that literally named their
        // media disk "public". Any app whose media disk root sits deeper
        // (e.g. "storage/app/public/media") had every local-disk image
        // request 404 inside Glide's own filesystem lookup, since it was
        // always searching one directory too shallow. Cloud disks already
        // resolved this correctly by reading through their own adapter;
        // this does the same for local disks instead of special-casing them.
        return ServerFactory::create([
            'driver' => $driver,
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => $filesystem->getDriver(),
            'source_path_prefix' => null,
            'cache' => storage_path('app'),
            'cache_path_prefix' => '.cache',
            'max_image_size' => 5000 * 5000,
            'defaults' => $defaults,
        ]);
    }
}
