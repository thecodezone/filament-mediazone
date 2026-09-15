<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Feature;

use Codezone\MediaZone\Livewire\MediaCropperPanel;
use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A crop records the disk it was baked to, and can live on a different disk
 * than its source (e.g. a cloud disk). Deleting a crop via the source's disk
 * therefore orphans the real file and can delete an unrelated file that
 * happens to occupy the same path on the source's disk. MediaObserver already
 * deletes by the crop's own disk; these cover the interactive delete paths.
 */
class CropDeletionDiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.media_cloud', [
            'driver' => 'local',
            'root' => storage_path('app/public/media-cloud'),
            'url' => 'https://cdn.example.com',
            'visibility' => 'public',
        ]);
    }

    private function makeMedia(string $path): Media
    {
        $dispatcher = Media::getEventDispatcher();
        Media::unsetEventDispatcher();
        try {
            return Media::factory()->create([
                'disk' => 'media',
                'directory' => 'uploads',
                'visibility' => 'public',
                'name' => 'source',
                'path' => $path,
                'url' => 'https://example.com/media/'.$path,
                'width' => 100,
                'height' => 100,
                'size' => 0,
                'type' => 'image/png',
                'ext' => 'png',
                'crops' => [],
            ]);
        } finally {
            Media::setEventDispatcher($dispatcher);
        }
    }

    public function test_deleting_a_crop_removes_the_file_from_the_crops_own_disk(): void
    {
        $cropPath = 'uploads/crops/cross-disk.png';

        // The crop lives on the cloud disk; an unrelated file occupies the
        // same path on the source's disk.
        Storage::disk('media_cloud')->put($cropPath, 'the crop');
        Storage::disk('media')->put($cropPath, 'an unrelated file');

        $media = $this->makeMedia('uploads/source.png');
        $media->crops = [[
            'id' => 'crop-1',
            'key' => 'hero',
            'location' => 'hero_image',
            'disk' => 'media_cloud',
            'path' => $cropPath,
            'url' => 'https://cdn.example.com/'.$cropPath,
            'breakpoints' => ['desktop'],
            'updated_at' => '2020-01-01T00:00:00Z',
        ]];
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('deleteCrop', 'crop-1');

        $this->assertFalse(
            Storage::disk('media_cloud')->exists($cropPath),
            'The crop must be deleted from the disk it was baked to.'
        );
        $this->assertTrue(
            Storage::disk('media')->exists($cropPath),
            'An unrelated file sharing that path on the source disk must be untouched.'
        );

        $media->refresh();
        $this->assertSame([], $media->crops);
    }

    public function test_a_crop_without_a_recorded_disk_falls_back_to_the_source_disk(): void
    {
        $cropPath = 'uploads/crops/legacy.png';
        Storage::disk('media')->put($cropPath, 'legacy crop');

        $media = $this->makeMedia('uploads/source2.png');
        $media->crops = [[
            'id' => 'crop-legacy',
            'key' => 'hero',
            'location' => 'hero_image',
            'path' => $cropPath,
            'url' => 'https://example.com/'.$cropPath,
            'breakpoints' => ['desktop'],
            'updated_at' => '2020-01-01T00:00:00Z',
        ]];
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('deleteCrop', 'crop-legacy');

        $this->assertFalse(
            Storage::disk('media')->exists($cropPath),
            'A crop predating the recorded disk must still delete via the source disk.'
        );
    }
}
