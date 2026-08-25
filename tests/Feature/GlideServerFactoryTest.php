<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Feature;

use Codezone\MediaZone\Services\GlideServerFactory;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

/**
 * GlideServerFactory used to hardcode the local-disk source as
 * storage_path('app') with a "public" prefix — i.e. it assumed the
 * configured media disk's root *is* Laravel's built-in "public" disk
 * (storage/app/public). Any consuming app whose media disk root sits one
 * level deeper (e.g. storage/app/public/media, which is exactly what this
 * package's own test harness configures in TestCase::defineEnvironment())
 * had every local-disk image request fail inside Glide's own filesystem
 * lookup with a FileNotFoundException, because it was always searching one
 * directory too shallow — regardless of whether the request was correctly
 * signed.
 */
class GlideServerFactoryTest extends TestCase
{
    public function test_it_locates_a_file_on_a_local_disk_whose_root_is_not_the_public_disk(): void
    {
        $image = imagecreatetruecolor(4, 4);
        imagefilledrectangle($image, 0, 0, 3, 3, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagewebp($image);
        $bytes = ob_get_clean();

        Storage::disk('media')->put('crops/example.webp', $bytes);

        $response = app(GlideServerFactory::class)
            ->getFactory()
            ->getImageResponse('crops/example.webp', []);

        $this->assertNotNull($response);
    }
}
