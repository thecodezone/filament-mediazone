<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests;

use Codezone\MediaZone\MediaZoneServiceProvider;
use Codezone\MediaZone\Models\Media;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            MediaZoneServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('media.model', Media::class);
        $app['config']->set('media.disk', 'media');
        $app['config']->set('filesystems.disks.media', [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => env('APP_URL').'/media',
            'visibility' => 'public',
        ]);
        // media.glide.driver defaults to imagick, which is the right default
        // for production but is not guaranteed on a developer machine or in
        // CI. The tests exercise path resolution and crop geometry, not the
        // image driver itself, so pin to whichever extension is present and
        // prefer gd when both are - it ships with far more PHP builds.
        $app['config']->set(
            'media.glide.driver',
            extension_loaded('gd') ? 'gd' : 'imagick'
        );
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
