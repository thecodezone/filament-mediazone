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
 * Server-side defense for saveCrop() against out-of-bounds or malformed crop
 * geometry: a stale payload (source image replaced at a different resolution
 * since a crop's geometry was last computed) or a crafted one (bypassing the
 * Cropper.js UI to call the Livewire endpoint directly) must never be able to
 * request pixels outside the source image or force an unbounded canvas
 * allocation.
 */
class MediaCropperPanelGeometryValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSolidImage(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 10, 20, 30);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function makeMedia(string $path, int $width, int $height): Media
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
                'width' => $width,
                'height' => $height,
                'size' => 0,
                'type' => 'image/png',
                'ext' => 'png',
                'crops' => [],
            ]);
        } finally {
            Media::setEventDispatcher($dispatcher);
        }
    }

    public function test_crop_rectangle_extending_past_the_image_is_clamped_not_padded(): void
    {
        $path = 'uploads/clamp-overflow.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(100, 100));
        $media = $this->makeMedia($path, 100, 100);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                // Requests a box that runs 50px past both the right and bottom edges.
                'x' => 70, 'y' => 70, 'width' => 80, 'height' => 80,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $crop = collect($media->crops)->last();

        // Baked output must be a real, in-bounds crop — never padded with
        // synthetic whitespace pixels beyond what the source actually has.
        // The requested 80x80 size fits within the 100x100 source, so it's
        // preserved and the origin is shifted back into bounds instead
        // (rather than truncating the rectangle and distorting its aspect
        // ratio away from what the user configured).
        $this->assertSame(80, $crop['width']);
        $this->assertSame(80, $crop['height']);
        $this->assertSame(20, $crop['geometry']['x']);
        $this->assertSame(20, $crop['geometry']['y']);

        $this->assertLessThanOrEqual(100, $crop['geometry']['x'] + $crop['geometry']['width']);
        $this->assertLessThanOrEqual(100, $crop['geometry']['y'] + $crop['geometry']['height']);
    }

    public function test_negative_origin_is_clamped_into_bounds(): void
    {
        $path = 'uploads/clamp-negative.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(100, 100));
        $media = $this->makeMedia($path, 100, 100);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => -40, 'y' => -40, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $crop = collect($media->crops)->last();

        $this->assertGreaterThanOrEqual(0, $crop['geometry']['x']);
        $this->assertGreaterThanOrEqual(0, $crop['geometry']['y']);
        $this->assertSame(50, $crop['width']);
        $this->assertSame(50, $crop['height']);
    }

    public function test_grossly_oversized_crafted_rectangle_cannot_force_unbounded_canvas(): void
    {
        $path = 'uploads/clamp-attack.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(100, 100));
        $media = $this->makeMedia($path, 100, 100);

        // Simulates a crafted request bypassing the UI entirely.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => 999999999, 'y' => -999999999, 'width' => 999999999, 'height' => 999999999,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $crop = collect($media->crops)->last();

        $this->assertNotNull($crop);
        $this->assertLessThanOrEqual(100, $crop['width']);
        $this->assertLessThanOrEqual(100, $crop['height']);
    }

    public function test_stale_geometry_from_a_replaced_smaller_source_is_clamped_to_new_bounds(): void
    {
        // A crop's geometry was computed against a 200x200 original...
        $path = 'uploads/replaced.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(200, 200));
        $media = $this->makeMedia($path, 200, 200);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => 150, 'y' => 150, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        // ...then the source file is replaced by a smaller 80x80 image, and an
        // edit is retried re-sending the stale geometry unchanged.
        Storage::disk('media')->put($path, $this->makeSolidImage(80, 80));

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => 150, 'y' => 150, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $latestCrop = collect($media->crops)->last();

        $this->assertLessThanOrEqual(80, $latestCrop['width']);
        $this->assertLessThanOrEqual(80, $latestCrop['height']);
        $this->assertLessThanOrEqual(80, $latestCrop['geometry']['x'] + $latestCrop['geometry']['width']);
        $this->assertLessThanOrEqual(80, $latestCrop['geometry']['y'] + $latestCrop['geometry']['height']);
    }
}
