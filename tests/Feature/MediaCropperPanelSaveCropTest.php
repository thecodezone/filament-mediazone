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
 * Regression coverage for the "always crop from the original source" guarantee.
 *
 * MediaCropperPanel::saveCrop() must always bake a new crop from the parent
 * Media's original file (its `path`), never from a previously-baked crop
 * output. Otherwise, repeated edit/re-save cycles on the same crop key would
 * compound quality loss and progressively shrink the croppable area, since
 * each save would be cropping an already-cropped (smaller, re-encoded) image
 * instead of the pristine source.
 */
class MediaCropperPanelSaveCropTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a 100x100 PNG fixture with four solid-color quadrants so we can
     * prove *which* region of *which* image a crop was taken from just by
     * sampling a pixel.
     */
    private function makeQuadrantImage(): string
    {
        $image = imagecreatetruecolor(100, 100);

        $red = imagecolorallocate($image, 255, 0, 0);
        $green = imagecolorallocate($image, 0, 255, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);
        $yellow = imagecolorallocate($image, 255, 255, 0);

        imagefilledrectangle($image, 0, 0, 49, 49, $red);       // top-left
        imagefilledrectangle($image, 50, 0, 99, 49, $green);    // top-right
        imagefilledrectangle($image, 0, 50, 49, 99, $blue);     // bottom-left
        imagefilledrectangle($image, 50, 50, 99, 99, $yellow);  // bottom-right

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function dominantColorAt(string $binaryPng, int $x, int $y): array
    {
        $image = imagecreatefromstring($binaryPng);
        $rgb = imagecolorat($image, $x, $y);
        $colors = imagecolorsforindex($image, $rgb);
        imagedestroy($image);

        return [$colors['red'], $colors['green'], $colors['blue']];
    }

    private function makeSourceMedia(string $path): Media
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

    public function test_repeated_saves_always_crop_from_the_original_source_file(): void
    {
        $sourcePath = 'uploads/source.png';
        $originalBytes = $this->makeQuadrantImage();
        Storage::disk('media')->put($sourcePath, $originalBytes);

        $media = $this->makeSourceMedia($sourcePath);

        // First "save": crop the top-left (red) quadrant.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'label' => 'Test',
                'format' => 'png',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $firstCrop = collect($media->crops)->last();
        $this->assertNotNull($firstCrop);

        $firstCropBytes = Storage::disk('media')->get($firstCrop['path']);
        [$r, $g, $b] = $this->dominantColorAt($firstCropBytes, 5, 5);
        $this->assertSame([255, 0, 0], [$r, $g, $b], 'First crop should be the red top-left quadrant.');

        // Second "save" on the SAME key: this simulates an edit/re-save cycle.
        // It targets the bottom-right (yellow) quadrant, which only exists in
        // the ORIGINAL 100x100 image. The first crop output is a 50x50 image
        // containing only red pixels, so if saveCrop() were reading from that
        // baked crop instead of the original, this rectangle would be out of
        // bounds and could never produce yellow.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'label' => 'Test',
                'format' => 'png',
                'quality' => 90,
                'x' => 50, 'y' => 50, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $secondCrop = collect($media->crops)->last();
        $this->assertNotNull($secondCrop);
        $this->assertNotSame($firstCrop['id'], $secondCrop['id']);

        $secondCropBytes = Storage::disk('media')->get($secondCrop['path']);
        [$r, $g, $b] = $this->dominantColorAt($secondCropBytes, 5, 5);
        $this->assertSame(
            [255, 255, 0],
            [$r, $g, $b],
            'Second crop should be the yellow bottom-right quadrant of the ORIGINAL image, '.
            'proving saveCrop() re-read the original source rather than the first baked crop.'
        );

        // The original source file itself must never be mutated by saveCrop().
        $this->assertSame(
            $originalBytes,
            Storage::disk('media')->get($sourcePath),
            'saveCrop() must never overwrite the original source file.'
        );
    }

    public function test_saved_crop_dimensions_are_unaffected_by_a_prior_crop_on_the_same_key(): void
    {
        $sourcePath = 'uploads/source-2.png';
        Storage::disk('media')->put($sourcePath, $this->makeQuadrantImage());

        $media = $this->makeSourceMedia($sourcePath);

        // Bake a small 10x10 crop first...
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        // ...then request a crop far larger than that first baked output.
        // This is only satisfiable if the crop is taken from the 100x100
        // original rather than the 10x10 crop produced a moment ago.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 80, 'height' => 80,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $latestCrop = collect($media->crops)->last();

        $this->assertSame(80, $latestCrop['width']);
        $this->assertSame(80, $latestCrop['height']);
    }

    public function test_save_crop_persists_geometry_alongside_the_baked_output(): void
    {
        $sourcePath = 'uploads/source-3.png';
        Storage::disk('media')->put($sourcePath, $this->makeQuadrantImage());

        $media = $this->makeSourceMedia($sourcePath);

        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'test',
                'format' => 'png',
                'quality' => 90,
                'x' => 12, 'y' => 34, 'width' => 40, 'height' => 22,
                'rotate' => 90, 'scaleX' => -1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['desktop'],
            ]);

        $media->refresh();
        $crop = collect($media->crops)->last();

        $this->assertArrayHasKey('geometry', $crop);
        $this->assertSame(12, $crop['geometry']['x']);
        $this->assertSame(34, $crop['geometry']['y']);
        $this->assertSame(40, $crop['geometry']['width']);
        $this->assertSame(22, $crop['geometry']['height']);
        // Whole-number floats round-trip through the JSON `crops` cast as ints
        // (e.g. 90.0 -> "90" -> 90), so compare numerically rather than by type.
        $this->assertEquals(90.0, $crop['geometry']['rotate']);
        $this->assertEquals(-1.0, $crop['geometry']['scaleX']);
        $this->assertEquals(1.0, $crop['geometry']['scaleY']);
        $this->assertSame(100, $crop['geometry']['source_width']);
        $this->assertSame(100, $crop['geometry']['source_height']);
    }

    public function test_older_crops_without_geometry_are_tolerated(): void
    {
        $sourcePath = 'uploads/source-4.png';
        Storage::disk('media')->put($sourcePath, $this->makeQuadrantImage());

        $media = $this->makeSourceMedia($sourcePath);
        $media->crops = [
            ['id' => 'legacy-1', 'key' => 'legacy', 'crop' => ['key' => 'legacy'], 'path' => $sourcePath, 'url' => '', 'width' => 50, 'height' => 50],
        ];
        $media->timestamps = false;
        $media->saveQuietly();

        $legacyCrop = $media->getCrop('legacy');

        $this->assertNotNull($legacyCrop);
        $this->assertArrayNotHasKey('geometry', $legacyCrop);
    }
}
