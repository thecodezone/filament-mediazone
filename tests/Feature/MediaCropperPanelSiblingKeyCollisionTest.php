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
 * Regression coverage for a crop-key collision that stripped breakpoints from
 * unrelated Media records. MediaPicker fields used without ->location(...)
 * (e.g. an irregular-grid product picker) fall back to an implicit crop key,
 * and every Media saved into a shared directory (Ancillary funnels many
 * unrelated products into a couple of fixed directories) without a real
 * location previously collided on that same fallback, so saving a crop for
 * one product silently wiped the crop breakpoints of another.
 */
class MediaCropperPanelSiblingKeyCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSolidImage(int $r, int $g, int $b): string
    {
        $image = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, 99, 99, $color);

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

    public function test_two_unrelated_media_saved_without_a_key_or_location_do_not_strip_each_others_breakpoints(): void
    {
        $pathA = 'uploads/product-a.png';
        $pathB = 'uploads/product-b.png';
        Storage::disk('media')->put($pathA, $this->makeSolidImage(255, 0, 0));
        Storage::disk('media')->put($pathB, $this->makeSolidImage(0, 0, 255));

        $mediaA = $this->makeMedia($pathA);
        $mediaB = $this->makeMedia($pathB);

        // Neither save specifies a key or a location - both would previously
        // fall back to the same literal 'custom' key.
        Livewire::test(MediaCropperPanel::class, ['media' => $mediaA->id])
            ->call('saveCrop', [
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $mediaA->refresh();
        $this->assertCount(1, $mediaA->crops);
        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaA->crops[0]['breakpoints']));

        Livewire::test(MediaCropperPanel::class, ['media' => $mediaB->id])
            ->call('saveCrop', [
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $mediaA->refresh();
        $mediaB->refresh();

        $this->assertCount(1, $mediaB->crops);
        $this->assertNotSame($mediaA->crops[0]['key'], $mediaB->crops[0]['key'], 'The implicit fallback key must be unique per media, not a shared literal.');

        // Saving B's crop must not have touched A's breakpoints.
        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaA->crops[0]['breakpoints']));
        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaB->crops[0]['breakpoints']));
    }

    public function test_siblings_without_a_shared_location_are_never_touched_even_with_the_same_explicit_key(): void
    {
        $pathA = 'uploads/product-a.png';
        $pathB = 'uploads/product-b.png';
        Storage::disk('media')->put($pathA, $this->makeSolidImage(255, 0, 0));
        Storage::disk('media')->put($pathB, $this->makeSolidImage(0, 0, 255));

        $mediaA = $this->makeMedia($pathA);
        $mediaB = $this->makeMedia($pathB);

        // Both explicitly use the same key ("custom") but neither has a
        // location - a location-less crop is never a shared, site-wide slot.
        Livewire::test(MediaCropperPanel::class, ['media' => $mediaA->id])
            ->call('saveCrop', [
                'key' => 'custom',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        Livewire::test(MediaCropperPanel::class, ['media' => $mediaB->id])
            ->call('saveCrop', [
                'key' => 'custom',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $mediaA->refresh();
        $mediaB->refresh();

        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaA->crops[0]['breakpoints']));
        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaB->crops[0]['breakpoints']));
    }

    public function test_siblings_sharing_a_real_location_still_correctly_split_breakpoints(): void
    {
        $pathA = 'uploads/product-a.png';
        $pathB = 'uploads/product-b.png';
        Storage::disk('media')->put($pathA, $this->makeSolidImage(255, 0, 0));
        Storage::disk('media')->put($pathB, $this->makeSolidImage(0, 0, 255));

        $mediaA = $this->makeMedia($pathA);
        $mediaB = $this->makeMedia($pathB);

        Livewire::test(MediaCropperPanel::class, ['media' => $mediaA->id])
            ->call('saveCrop', [
                'key' => 'hero', 'location' => 'hero_image',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $mediaA->refresh();
        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaA->crops[0]['breakpoints']));

        Livewire::test(MediaCropperPanel::class, ['media' => $mediaB->id])
            ->call('saveCrop', [
                'key' => 'hero', 'location' => 'hero_image',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $mediaA->refresh();
        $mediaB->refresh();

        // Genuinely sharing a location + key means the same logical slot, so
        // the later save correctly reclaims the breakpoints from the earlier one.
        $this->assertSame([], array_values($mediaA->crops[0]['breakpoints']));
        $this->assertSame(['mobile', 'tablet', 'desktop'], array_values($mediaB->crops[0]['breakpoints']));
    }

    public function test_re_editing_a_crop_saved_before_the_id_suffix_keeps_its_original_key(): void
    {
        $path = 'uploads/legacy.png';
        Storage::disk('media')->put($path, $this->makeSolidImage(10, 20, 30));
        $media = $this->makeMedia($path);

        // A crop stored before the implicit key gained its media-id suffix.
        $media->crops = [[
            'id' => 'legacy-crop-id',
            'key' => 'custom',
            'crop' => ['key' => 'custom', 'label' => 'custom'],
            'breakpoints' => ['mobile', 'tablet', 'desktop'],
            'path' => 'uploads/crops/legacy-crop-id.png',
            'url' => 'https://example.com/legacy.png',
            'updated_at' => '2020-01-01T00:00:00Z',
        ]];
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;

        // Re-editing that crop must not mint a new key - a host record's stored
        // crop_key (and Media::getCrop()) still references the original.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'id' => 'legacy-crop-id',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $media->refresh();

        $this->assertCount(1, $media->crops);
        $this->assertSame('custom', $media->crops[0]['key'], 'Re-editing an existing crop must preserve its stored key.');
        $this->assertNotNull($media->getCrop('custom'), 'A consumer referencing the original key must still resolve.');
    }
}
