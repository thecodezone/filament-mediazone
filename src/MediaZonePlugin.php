<?php

declare(strict_types=1);

namespace Codezone\MediaZone;

use Codezone\MediaZone\Filament\Resources\MediaResource;
use Codezone\MediaZone\Livewire\MediaCropperPanel;
use Codezone\MediaZone\Livewire\MediaPickerPanel;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;

class MediaZonePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'mediazone';
    }

    public function register(Panel $panel): void
    {
        $resourceClass = config('media.resources.resource', MediaResource::class);

        $panel->resources([
            $resourceClass,
        ]);

        Livewire::component('media-picker-panel', MediaPickerPanel::class);
        Livewire::component('media-cropper-panel', MediaCropperPanel::class);

        FilamentAsset::register([
            Css::make('media-picker', __DIR__.'/../resources/css/media-picker.css'),
            Css::make('media-picker-field', __DIR__.'/../resources/css/media-picker-field.css'),
            Css::make('media-listing', __DIR__.'/../resources/css/media-listing.css'),
            Css::make('media-cropper', __DIR__.'/../resources/css/media-cropper.css'),
            Js::make('cropperjs', __DIR__.'/../resources/js/cropper.min.js'),
            Js::make('media-cropper', __DIR__.'/../resources/js/media-cropper.js'),
        ], package: 'codezone/filament-mediazone');
    }

    public function boot(Panel $panel): void {}
}
