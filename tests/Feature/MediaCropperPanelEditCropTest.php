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
 * Covers editing an existing crop: saveCrop() must replace the crop entry
 * in place (same id) rather than appending a new one, so anything that
 * references the crop by id (e.g. a location's stored crop_key) keeps
 * resolving correctly after the edit.
 */
class MediaCropperPanelEditCropTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuadrantImage(): string
    {
        $image = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($image, 255, 0, 0);
        $yellow = imagecolorallocate($image, 255, 255, 0);
        imagefilledrectangle($image, 0, 0, 49, 49, $red);
        imagefilledrectangle($image, 50, 50, 99, 99, $yellow);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
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

    public function test_editing_a_crop_replaces_it_in_place_instead_of_appending(): void
    {
        $path = 'uploads/edit-source.png';
        Storage::disk('media')->put($path, $this->makeQuadrantImage());
        $media = $this->makeMedia($path);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'hero',
                'format' => 'png',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $original = collect($media->crops)->firstWhere('key', 'hero');
        $originalId = $original['id'];
        $originalPath = $original['path'];

        // Edit: reposition the same crop (by id) to the yellow quadrant instead.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'id' => $originalId,
                'key' => 'hero',
                'format' => 'png',
                'quality' => 90,
                'x' => 50, 'y' => 50, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();

        $this->assertCount(1, $media->crops, 'Editing a crop must replace it, not append a second entry.');

        $updated = $media->crops[0];
        $this->assertSame($originalId, $updated['id'], 'The crop id must be preserved across an edit.');
        $this->assertSame(50, $updated['geometry']['x']);
        $this->assertSame(50, $updated['geometry']['y']);

        // Format didn't change, so the baked output keeps the same path (same
        // id + extension) — it's simply overwritten in place with the new
        // region's pixels, rather than left as a stale duplicate.
        $this->assertSame($originalPath, $updated['path']);
        $this->assertTrue(Storage::disk('media')->exists($updated['path']));

        $image = imagecreatefromstring(Storage::disk('media')->get($updated['path']));
        $rgb = imagecolorsforindex($image, imagecolorat($image, 5, 5));
        imagedestroy($image);
        $this->assertSame([255, 255, 0], [$rgb['red'], $rgb['green'], $rgb['blue']], 'Baked output should now be the yellow quadrant.');
    }

    public function test_editing_a_crop_into_a_new_format_removes_the_stale_baked_file(): void
    {
        $path = 'uploads/edit-format-change.png';
        Storage::disk('media')->put($path, $this->makeQuadrantImage());
        $media = $this->makeMedia($path);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'hero',
                'format' => 'png',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $original = collect($media->crops)->firstWhere('key', 'hero');
        $originalPath = $original['path'];

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'id' => $original['id'],
                'key' => 'hero',
                'format' => 'jpg',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $updated = $media->crops[0];

        $this->assertNotSame($originalPath, $updated['path']);
        $this->assertFalse(Storage::disk('media')->exists($originalPath), 'The stale .png output must be cleaned up after switching to .jpg.');
        $this->assertTrue(Storage::disk('media')->exists($updated['path']));
    }

    public function test_editing_a_deleted_crop_gracefully_creates_a_new_entry_instead_of_erroring(): void
    {
        $path = 'uploads/edit-deleted.png';
        Storage::disk('media')->put($path, $this->makeQuadrantImage());
        $media = $this->makeMedia($path);

        // Simulate a stale "Edit" click on a crop id that no longer exists
        // (e.g. deleted from another tab in the meantime).
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'id' => 'no-such-crop-id',
                'key' => 'hero',
                'format' => 'png',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();

        $this->assertCount(1, $media->crops);
        $this->assertNotSame('no-such-crop-id', $media->crops[0]['id']);
    }
}
