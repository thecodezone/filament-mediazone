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
 * Server-side handling of a crop rectangle that extends beyond the source
 * image: this is a real, wanted editorial workflow (e.g. deliberately adding
 * blank whitespace above a photo), so it's baked as whitespace padding
 * rather than rejected - but bounded to a size-proportional maximum so a
 * stale payload (source image replaced at a different resolution since a
 * crop's geometry was last computed) or a crafted one (bypassing the
 * Cropper.js UI to call the Livewire endpoint directly) can't force an
 * unbounded canvas allocation.
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

    private function pixelAt(string $binaryPng, int $x, int $y): array
    {
        $image = imagecreatefromstring($binaryPng);
        $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        imagedestroy($image);

        return [$rgb['red'], $rgb['green'], $rgb['blue']];
    }

    public function test_crop_rectangle_extending_moderately_past_the_image_is_baked_as_whitespace_padding(): void
    {
        $path = 'uploads/pad-overflow.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(100, 100));
        $media = $this->makeMedia($path, 100, 100);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                // Runs 50px past both the right and bottom edges - within the
                // allowed padding budget, so it's honored as requested rather
                // than shifted or truncated.
                'x' => 70, 'y' => 70, 'width' => 80, 'height' => 80,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $crop = collect($media->crops)->last();

        $this->assertSame(80, $crop['width']);
        $this->assertSame(80, $crop['height']);
        $this->assertSame(70, $crop['geometry']['x']);
        $this->assertSame(70, $crop['geometry']['y']);

        $bytes = Storage::disk('media')->get($crop['path']);
        // Top-left of the baked crop (source pixel 70,70) is real image content...
        $this->assertSame([10, 20, 30], $this->pixelAt($bytes, 2, 2));
        // ...bottom-right (past the source's 100x100 edge) is whitespace padding.
        $this->assertSame([255, 255, 255], $this->pixelAt($bytes, 78, 78));
    }

    public function test_negative_origin_is_baked_as_whitespace_padding_above_and_left_of_the_image(): void
    {
        $path = 'uploads/pad-negative.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(100, 100));
        $media = $this->makeMedia($path, 100, 100);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                // This is the "add whitespace above/left of the image" workflow:
                // the box starts 40px above and left of the source image.
                'x' => -40, 'y' => -40, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $crop = collect($media->crops)->last();

        $this->assertSame(-40, $crop['geometry']['x']);
        $this->assertSame(-40, $crop['geometry']['y']);
        $this->assertSame(50, $crop['width']);
        $this->assertSame(50, $crop['height']);

        $bytes = Storage::disk('media')->get($crop['path']);
        // Top-left of the baked crop is whitespace padding (above/left of the source)...
        $this->assertSame([255, 255, 255], $this->pixelAt($bytes, 2, 2));
        // ...bottom-right (source pixel 8,8) is real image content.
        $this->assertSame([10, 20, 30], $this->pixelAt($bytes, 48, 48));
    }

    public function test_grossly_oversized_crafted_rectangle_is_bounded_to_a_size_proportional_maximum(): void
    {
        $path = 'uploads/pad-attack.png';
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
        // Bounded to (source dimension) * (1 + 2 * MAX_PADDING_RATIO) = 100 * 3 = 300,
        // not the attacker-supplied ~1e9.
        $this->assertLessThanOrEqual(300, $crop['width']);
        $this->assertLessThanOrEqual(300, $crop['height']);
    }

    public function test_stale_geometry_from_a_replaced_smaller_source_is_bounded_to_new_dimensions(): void
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

        // Bounded to the new 80x80 source's padding budget (80 * (1 + 2) = 240),
        // not left referencing the old 200x200 source's coordinate space.
        $this->assertLessThanOrEqual(240, $latestCrop['width']);
        $this->assertLessThanOrEqual(240, $latestCrop['height']);
        $this->assertLessThanOrEqual(160, $latestCrop['geometry']['x'] + $latestCrop['geometry']['width']);
        $this->assertLessThanOrEqual(160, $latestCrop['geometry']['y'] + $latestCrop['geometry']['height']);
    }
}
