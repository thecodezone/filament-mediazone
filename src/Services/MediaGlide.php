<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Services;

use League\Glide\Urls\UrlBuilderFactory;

class MediaGlide
{
    public static $urlBuilder = null;

    public static function urlBuilder()
    {
        if (! static::$urlBuilder) {
            $routePath = config('media.glide.route_path', 'media');
            static::$urlBuilder = UrlBuilderFactory::create(
                '/'.$routePath.'/',
                config('app.key')
            );
        }

        return static::$urlBuilder;
    }

    public static function signedUrl(string $path, array $params = []): string
    {
        return static::urlBuilder()->getUrl($path, $params);
    }

    public static function isResizable(string $type): bool
    {
        if (str_starts_with($type, 'image/svg')) {
            return false;
        }

        return str_starts_with($type, 'image/') || $type === 'image';
    }
}
