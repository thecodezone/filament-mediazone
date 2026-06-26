<?php

declare(strict_types=1);
use Codezone\MediaZone\Filament\Resources\MediaResource;
use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Services\GlideServerFactory;

return [
    'accepted_file_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'video/mp4',
        'video/quicktime',
    ],

    // Disks that serve files directly (CDN/object storage) — Glide bypassed for originals
    'cloud_disks' => [],

    'cropper' => [
        'check_cross_origin' => true,
    ],

    'crop_formats' => [
        'jpg',
        'jpeg',
        'webp',
        'png',
        'avif',
    ],

    // Consuming app registers its own locations here
    'crop_locations' => [],

    // Consuming app registers its own presets here
    'crop_presets' => [],

    'breakpoints' => [
        'mobile' => 767,
        'tablet' => 1174,
    ],

    'directory' => '',

    'disk' => env('MEDIA_FILESYSTEM_DISK', 'media'),

    'glide' => [
        'server' => GlideServerFactory::class,
        'driver' => env('MEDIA_GLIDE_DRIVER', 'imagick'), // 'imagick' or 'gd'
        'fallbacks' => [],
        'route_path' => env('MEDIA_GLIDE_ROUTE', 'media'),
        // Middleware applied to the glide image route. Set to [] to make it public.
        'route_middleware' => ['web'],
    ],

    'image_crop_aspect_ratio' => null,
    'image_resize_mode' => null,
    'image_resize_target_height' => null,
    'image_resize_target_width' => null,

    'is_limited_to_directory' => false,

    'max_size' => 102400,

    'model' => Media::class,

    'min_size' => 0,

    'path_generator' => null,

    'resources' => [
        'label' => 'Media',
        'plural_label' => 'Media',
        'navigation_group' => 'Content',
        'cluster' => null,
        'navigation_label' => 'Media',
        'navigation_icon' => 'heroicon-o-photo',
        'navigation_sort' => 3,
        'navigation_count_badge' => false,
        'resource' => MediaResource::class,
    ],

    'should_preserve_filenames' => true,
    'should_register_navigation' => true,
    'should_check_exists' => env('MEDIA_SHOULD_CHECK_EXISTS', true),
    'visibility' => 'public',

    'tabs' => [
        'display_crop' => true,
        'display_upload_new' => true,
    ],

    'multi_select_key' => 'metaKey',

    // Extra pivot columns to pass through during sync (consuming app sets these)
    'picker' => [
        'pivot_columns' => [],
    ],

    'table' => [
        'layout' => 'grid',
    ],
];
