<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Services;

use Illuminate\Support\Facades\Storage;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\Server;

class GlideServerFactory
{
    public function getFactory(): \League\Glide\ServerFactory|Server
    {
        $filesystem = Storage::disk(config('media.disk'));
        $defaults = ['bg' => 'ffffff'];

        $driver = config('media.glide.driver', 'imagick');
        $cloudDisks = config('media.cloud_disks', []);

        if (in_array(config('media.disk'), $cloudDisks, true)) {
            return \League\Glide\ServerFactory::create([
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

        return \League\Glide\ServerFactory::create([
            'driver' => $driver,
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => storage_path('app'),
            'source_path_prefix' => 'public',
            'cache' => storage_path('app'),
            'cache_path_prefix' => '.cache',
            'max_image_size' => 5000 * 5000,
            'defaults' => $defaults,
        ]);
    }
}
