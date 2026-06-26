<?php

declare(strict_types=1);

namespace Codezone\MediaZone;

use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Observers\MediaObserver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaZoneServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mediazone')
            ->hasConfigFile('media')
            ->hasViews()
            ->hasMigrations([
                'create_media_table',
            ])
            ->hasRoutes(['web']);
    }

    public function packageBooted(): void
    {
        $model = config('media.model', Media::class);
        $model::observe(MediaObserver::class);

        $this->publishes([
            __DIR__.'/../resources/css' => public_path('vendor/mediazone/css'),
            __DIR__.'/../resources/js' => public_path('vendor/mediazone/js'),
        ], 'mediazone-assets');
    }
}
