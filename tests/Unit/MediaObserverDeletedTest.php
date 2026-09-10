<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Unit;

use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Regression coverage for a bug where deleting one Media record could wipe
 * out crop files belonging to a completely different, still-live Media
 * record.
 *
 * MediaObserver::deleted() used to compute a "crops" directory from
 * dirname($model->path) and delete the whole thing. That's only safe if
 * each Media record has an exclusive parent directory. In practice many
 * records share one (e.g. everything uploaded through the generic
 * "uploads" bucket is stored as "uploads/{name}.ext", so they all resolve
 * to the same "uploads/crops" directory). Deleting any single one of those
 * records destroyed every sibling's crops too — observed in production as
 * every "Silo Images" thumbnail on unrelated products going instantly
 * broken the moment one, unrelated, media item was deleted.
 */
class MediaObserverDeletedTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedia(string $path, array $crops = []): Media
    {
        $dispatcher = Media::getEventDispatcher();
        Media::unsetEventDispatcher();
        try {
            return Media::factory()->create([
                'disk' => 'media',
                'directory' => 'uploads',
                'path' => $path,
                'url' => 'https://example.com/media/'.$path,
                'crops' => $crops,
            ]);
        } finally {
            Media::setEventDispatcher($dispatcher);
        }
    }

    public function test_deleting_one_media_record_does_not_delete_a_sibling_record_crops_in_the_shared_directory(): void
    {
        Storage::disk('media')->put('uploads/a.jpg', 'source-a');
        Storage::disk('media')->put('uploads/crops/a-crop.webp', 'crop-a');
        Storage::disk('media')->put('uploads/b.jpg', 'source-b');
        Storage::disk('media')->put('uploads/crops/b-crop.webp', 'crop-b');

        $mediaA = $this->makeMedia('uploads/a.jpg', [
            ['id' => 'crop-a', 'key' => 'thumbnail', 'disk' => 'media', 'path' => 'uploads/crops/a-crop.webp'],
        ]);
        $mediaB = $this->makeMedia('uploads/b.jpg', [
            ['id' => 'crop-b', 'key' => 'thumbnail', 'disk' => 'media', 'path' => 'uploads/crops/b-crop.webp'],
        ]);

        $mediaA->delete();

        $this->assertFalse(Storage::disk('media')->exists('uploads/a.jpg'), "The deleted record's own source file should be gone.");
        $this->assertFalse(Storage::disk('media')->exists('uploads/crops/a-crop.webp'), "The deleted record's own crop should be gone.");

        $this->assertTrue(Storage::disk('media')->exists('uploads/b.jpg'), "A sibling record's source file must survive.");
        $this->assertTrue(Storage::disk('media')->exists('uploads/crops/b-crop.webp'), "A sibling record's crop, stored in the same shared directory, must survive.");
    }

    public function test_deleting_media_removes_a_crop_stored_on_a_different_disk_than_the_source(): void
    {
        config()->set('filesystems.disks.r2', [
            'driver' => 'local',
            'root' => storage_path('app/public/r2'),
            'url' => 'https://cdn.example-r2.com',
            'visibility' => 'public',
        ]);

        Storage::disk('media')->put('uploads/c.jpg', 'source-c');
        Storage::disk('r2')->put('uploads/crops/c-crop.webp', 'crop-c');

        $media = $this->makeMedia('uploads/c.jpg', [
            ['id' => 'crop-c', 'key' => 'thumbnail', 'disk' => 'r2', 'path' => 'uploads/crops/c-crop.webp'],
        ]);

        $media->delete();

        $this->assertFalse(Storage::disk('media')->exists('uploads/c.jpg'));
        $this->assertFalse(Storage::disk('r2')->exists('uploads/crops/c-crop.webp'), 'A crop on a different disk than its source must still be cleaned up.');
    }
}
