# Glide

MediaZone uses [League Glide](https://glide.thephpleague.com/) to serve on-the-fly image transformations. A signed route handles all Glide requests.

## How it works

When a media record is saved, `generateUrl()` builds a URL pointing at the Glide route:

```
/media/glide/{id}?w=400&h=300&fm=webp&q=90
```

The Glide server reads the original file from the configured disk, applies the requested transforms, and returns the processed image. Responses are cached by Glide on subsequent requests.

## Configuration

```php
'glide' => [
    'server'           => \Codezone\MediaZone\Services\GlideServerFactory::class,
    'driver'           => env('MEDIA_GLIDE_DRIVER', 'imagick'), // 'imagick' or 'gd'
    'fallbacks'        => [],
    'route_path'       => env('MEDIA_GLIDE_ROUTE', 'media'),
    'route_middleware' => ['web'],
],
```

### `driver`

Set to `gd` if your server does not have ImageMagick installed. `imagick` is recommended for better quality and format support (including AVIF).

### `route_path`

The URL prefix for the Glide route. Defaults to `media`, producing `/media/glide/{id}`.

### `route_middleware`

Middleware applied to the Glide route. Defaults to `['web']`. Set to `[]` to make images publicly accessible without session context, or add `auth` to protect them.

### `fallbacks`

An array of fallback disk paths used when the primary file is not found. Useful during migrations between storage backends.

## Cloud disks

For disks listed in `config('media.cloud_disks')`, Glide is bypassed. The original CDN/storage URL is used directly in `generateUrl()`. This avoids proxying large files through your application server.

```php
'cloud_disks' => ['r2', 's3'],
```

## Custom server factory

To customise the Glide server (e.g. add watermarks or custom presets), point `glide.server` at your own factory class:

```php
namespace App\Services;

use Codezone\MediaZone\Services\GlideServerFactory as BaseFactory;

class MyGlideServerFactory extends BaseFactory
{
    protected function serverConfig(): array
    {
        return array_merge(parent::serverConfig(), [
            'watermarks' => storage_path('watermarks'),
        ]);
    }
}
```

```php
// config/media.php
'glide' => [
    'server' => \App\Services\MyGlideServerFactory::class,
],
```

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>

<tip>
Filament MediaZone is open source. <a href="https://github.com/thecodezone/filament-mediazone">View the repository on GitHub</a> to report issues, contribute, or browse the source.
</tip>
