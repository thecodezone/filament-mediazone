# Configuration

After publishing, `config/media.php` contains all package settings. The most commonly overridden keys are shown below.

## Disk and storage

```php
'disk' => env('MEDIA_FILESYSTEM_DISK', 'media'),

// Disks that serve files directly (CDN / object storage).
// Glide is bypassed for originals on these disks.
'cloud_disks' => [],
```

Set `MEDIA_FILESYSTEM_DISK` in your `.env` to point at any configured Laravel filesystem disk.

## Model

```php
'model' => \Codezone\MediaZone\Models\Media::class,
```

Point this at your own model to extend the base. See [Custom Model](custom-model.md).

## Accepted file types and size

```php
'accepted_file_types' => [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/svg+xml',
    'application/pdf',
    'video/mp4',
    'video/quicktime',
],

'max_size' => 102400, // kilobytes
'min_size' => 0,
```

## Glide image server

```php
'glide' => [
    'server'          => \Codezone\MediaZone\Services\GlideServerFactory::class,
    'driver'          => env('MEDIA_GLIDE_DRIVER', 'imagick'), // 'imagick' or 'gd'
    'fallbacks'       => [],
    'route_path'      => env('MEDIA_GLIDE_ROUTE', 'media'),
    'route_middleware' => ['web'],
],
```

See [Glide](glide.md) for full details.

## Cropper

```php
'crop_presets'  => [],  // \App\Media\Crops\MyPreset::class, ...
'crop_locations' => [], // \App\Media\Locations\MyLocation::class, ...

'crop_formats' => ['jpg', 'jpeg', 'webp', 'png', 'avif'],

'breakpoints' => [
    'mobile'  => 767,
    'tablet'  => 1174,
],
```

See [Crop Presets](crop-presets.md) and [Crop Locations](crop-locations.md).

## Filament resource

```php
'resources' => [
    'label'                  => 'Media',
    'plural_label'           => 'Media',
    'navigation_group'       => 'Content',
    'navigation_icon'        => 'heroicon-o-photo',
    'navigation_sort'        => 3,
    'navigation_count_badge' => false,
    'cluster'                => null,
    'resource'               => \Codezone\MediaZone\Filament\Resources\MediaResource::class,
],
```

## Picker

```php
'picker' => [
    // Extra pivot columns synced when attaching media to a model
    'pivot_columns' => [],
],
```

See [Pivot Columns](pivot-columns.md).

## Tabs

```php
'tabs' => [
    'display_crop'       => true,
    'display_upload_new' => true,
],
```

## Multi-select modifier key

```php
'multi_select_key' => 'metaKey', // 'metaKey' (⌘) or 'ctrlKey'
```
