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
 * Automated stand-in for the "manual QA across breakpoints/locations" pass:
 * verifies that mobile_crop_key/tablet_crop_key/desktop_crop_key resolution
 * (Media::getCropForLocation()) keeps working once a crop can be edited in
 * place, and that editing one breakpoint's crop doesn't disturb a sibling
 * crop under the same key/location that covers a different breakpoint.
 *
 * This exercises the underlying model/Livewire behavior end-to-end; it does
 * not replace clicking through the actual Filament admin UI in a browser,
 * which still needs a human pass across real breakpoints.
 */
class MediaCropperPanelBreakpointQaTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuadrantImage(): string
    {
        $image = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($image, 255, 0, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);
        imagefilledrectangle($image, 0, 0, 49, 49, $red);
        imagefilledrectangle($image, 50, 50, 99, 99, $blue);

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

    public function test_editing_one_breakpoints_crop_does_not_disturb_a_sibling_crop_under_the_same_key(): void
    {
        $path = 'uploads/breakpoints.png';
        Storage::disk('media')->put($path, $this->makeQuadrantImage());
        $media = $this->makeMedia($path);

        // Mobile gets the red quadrant...
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'hero', 'location' => 'hero_image',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile'],
            ]);

        // ...tablet + desktop share the blue quadrant as a separate crop entry.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'hero', 'location' => 'hero_image',
                'format' => 'png', 'quality' => 90,
                'x' => 50, 'y' => 50, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['tablet', 'desktop'],
            ]);

        $media->refresh();
        $this->assertCount(2, $media->crops);

        $mobileCrop = collect($media->crops)->first(fn ($c) => in_array('mobile', $c['breakpoints'] ?? [], true));
        $tabletDesktopCrop = collect($media->crops)->first(fn ($c) => in_array('desktop', $c['breakpoints'] ?? [], true));
        $this->assertNotSame($mobileCrop['id'], $tabletDesktopCrop['id']);

        $this->assertSame($mobileCrop['id'], $media->getCropForLocation('hero_image', 'mobile')['id']);
        $this->assertSame($tabletDesktopCrop['id'], $media->getCropForLocation('hero_image', 'tablet')['id']);
        $this->assertSame($tabletDesktopCrop['id'], $media->getCropForLocation('hero_image', 'desktop')['id']);

        // Now edit the mobile crop (by id) and have it also claim 'tablet',
        // repositioning it in the process.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'id' => $mobileCrop['id'],
                'key' => 'hero', 'location' => 'hero_image',
                'format' => 'png', 'quality' => 90,
                'x' => 10, 'y' => 10, 'width' => 30, 'height' => 30,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet'],
            ]);

        $media->refresh();
        $this->assertCount(2, $media->crops, 'Editing must still replace in place, not append a third entry.');

        $updatedMobileCrop = $media->getCropById($mobileCrop['id']);
        $untouchedSibling = $media->getCropById($tabletDesktopCrop['id']);

        // The sibling crop's own bake (path/geometry/pixels) must be completely
        // untouched by an edit to a different crop entry...
        $this->assertNotNull($untouchedSibling);
        $this->assertSame($tabletDesktopCrop['path'], $untouchedSibling['path']);
        $this->assertSame($tabletDesktopCrop['geometry'], $untouchedSibling['geometry']);
        // ...but it must lose 'tablet' now that the edited crop claims it,
        // so a breakpoint is never assigned to two crops under the same key.
        $this->assertSame(['desktop'], array_values($untouchedSibling['breakpoints']));

        $this->assertSame(['mobile', 'tablet'], array_values($updatedMobileCrop['breakpoints']));
        $this->assertSame(10, $updatedMobileCrop['geometry']['x']);

        // Location/breakpoint resolution reflects the new assignment.
        $this->assertSame($updatedMobileCrop['id'], $media->getCropForLocation('hero_image', 'mobile')['id']);
        $this->assertSame($updatedMobileCrop['id'], $media->getCropForLocation('hero_image', 'tablet')['id']);
        $this->assertSame($untouchedSibling['id'], $media->getCropForLocation('hero_image', 'desktop')['id']);
    }
}
