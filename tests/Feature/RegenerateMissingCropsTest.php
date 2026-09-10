<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Feature;

use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Coverage for the `media:regenerate-missing-crops` recovery command, added
 * alongside the MediaObserver::deleted() fix for crops being wiped out from
 * under unrelated Media records. Every crop already stores the geometry it
 * was baked from, so a missing crop file can be recreated from the still-
 * present source image rather than requiring anyone to manually re-crop.
 */
class RegenerateMissingCropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real local-disk writes persist on the filesystem across tests
        // (RefreshDatabase only resets the database), so fake the disk per
        // test for isolation.
        Storage::fake('media');
    }

    private function makeSourceImage(): string
    {
        $image = imagecreatetruecolor(200, 200);
        imagefilledrectangle($image, 0, 0, 199, 199, imagecolorallocate($image, 10, 20, 30));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private function makeMedia(string $sourcePath, array $crops): Media
    {
        Storage::disk('media')->put($sourcePath, $this->makeSourceImage());

        $dispatcher = Media::getEventDispatcher();
        Media::unsetEventDispatcher();
        try {
            return Media::factory()->create([
                'disk' => 'media',
                'directory' => 'uploads',
                'path' => $sourcePath,
                'url' => 'https://example.com/media/'.$sourcePath,
                'crops' => $crops,
            ]);
        } finally {
            Media::setEventDispatcher($dispatcher);
        }
    }

    private function cropWithGeometry(string $path): array
    {
        return [
            'id' => 'crop-1',
            'key' => 'thumbnail',
            'disk' => 'media',
            'path' => $path,
            'crop' => ['format' => 'webp', 'quality' => 90, 'width' => 50, 'height' => 50],
            'geometry' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100, 'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1],
        ];
    }

    public function test_regenerates_a_missing_crop_from_stored_geometry_and_source_image(): void
    {
        $cropPath = 'uploads/crops/crop-1.webp';
        $this->makeMedia('uploads/source.png', [$this->cropWithGeometry($cropPath)]);

        $this->assertFalse(Storage::disk('media')->exists($cropPath));

        $exitCode = Artisan::call('media:regenerate-missing-crops');

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Storage::disk('media')->exists($cropPath), 'The missing crop should have been regenerated.');
        $this->assertGreaterThan(0, Storage::disk('media')->size($cropPath));
    }

    public function test_dry_run_reports_without_writing_anything(): void
    {
        $cropPath = 'uploads/crops/crop-1.webp';
        $this->makeMedia('uploads/source.png', [$this->cropWithGeometry($cropPath)]);

        Artisan::call('media:regenerate-missing-crops', ['--dry-run' => true]);

        $this->assertFalse(Storage::disk('media')->exists($cropPath), 'Dry run must not write any files.');
    }

    public function test_skips_a_crop_with_no_stored_geometry_without_failing_the_run(): void
    {
        $cropPath = 'uploads/crops/crop-1.webp';
        $crop = $this->cropWithGeometry($cropPath);
        unset($crop['geometry']);
        $this->makeMedia('uploads/source.png', [$crop]);

        $exitCode = Artisan::call('media:regenerate-missing-crops');

        $this->assertSame(0, $exitCode);
        $this->assertFalse(Storage::disk('media')->exists($cropPath));
    }

    public function test_skips_a_crop_whose_source_image_is_also_missing(): void
    {
        $cropPath = 'uploads/crops/crop-1.webp';
        $media = $this->makeMedia('uploads/source.png', [$this->cropWithGeometry($cropPath)]);
        Storage::disk('media')->delete($media->path);

        $exitCode = Artisan::call('media:regenerate-missing-crops');

        $this->assertSame(0, $exitCode);
        $this->assertFalse(Storage::disk('media')->exists($cropPath));
    }

    public function test_leaves_an_already_present_crop_untouched(): void
    {
        $cropPath = 'uploads/crops/crop-1.webp';
        $this->makeMedia('uploads/source.png', [$this->cropWithGeometry($cropPath)]);
        Storage::disk('media')->put($cropPath, 'already-there');

        Artisan::call('media:regenerate-missing-crops');

        $this->assertSame('already-there', Storage::disk('media')->get($cropPath));
    }

    public function test_regenerates_a_crop_stored_on_a_different_disk_than_its_source(): void
    {
        config()->set('filesystems.disks.r2', [
            'driver' => 'local',
            'root' => storage_path('app/public/r2'),
            'url' => 'https://cdn.example-r2.com',
            'visibility' => 'public',
        ]);
        Storage::fake('r2');

        $cropPath = 'uploads/crops/crop-1.webp';
        $crop = $this->cropWithGeometry($cropPath);
        $crop['disk'] = 'r2';
        $this->makeMedia('uploads/source.png', [$crop]);

        Artisan::call('media:regenerate-missing-crops');

        $this->assertTrue(Storage::disk('r2')->exists($cropPath));
    }
}
