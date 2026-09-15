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
 * Saving a crop claims its breakpoints and strips them from every same-key
 * crop, both on the same record and on siblings in the same directory. The
 * strip is correct in principle, but previously overwrote the list in place
 * with no record of what was taken - and no layer restores an explicitly
 * empty list, so an emptied crop never recovered. These cover the additive
 * previous_breakpoints history that makes the strip reversible.
 */
class CropBreakpointHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeSolidImage(): string
    {
        $image = imagecreatetruecolor(100, 100);
        imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 10, 20, 30));
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

    private function cropEntry(string $id, array $breakpoints): array
    {
        return [
            'id' => $id,
            'key' => 'hero',
            'location' => 'hero_image',
            'crop' => ['key' => 'hero', 'label' => 'Hero'],
            'breakpoints' => $breakpoints,
            'path' => 'uploads/crops/'.$id.'.png',
            'url' => 'https://example.com/'.$id.'.png',
            'updated_at' => '2020-01-01T00:00:00Z',
        ];
    }

    private function seedCrops(Media $media, array $crops): void
    {
        $media->crops = $crops;
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;
    }

    public function test_stripping_a_sibling_records_what_it_was_serving(): void
    {
        $media = $this->makeMedia('uploads/a.png');
        $sibling = $this->makeMedia('uploads/b.png');
        $this->seedCrops($sibling, [$this->cropEntry('sib-1', ['mobile', 'tablet', 'desktop'])]);

        $media->removeBreakpointsFromSiblings('hero_image', 'hero', ['mobile', 'tablet', 'desktop']);

        $sibling->refresh();
        $this->assertSame([], $sibling->crops[0]['breakpoints']);
        $this->assertSame(
            ['mobile', 'tablet', 'desktop'],
            $sibling->crops[0]['previous_breakpoints'],
            'An emptied crop must retain what it was serving before the strike.'
        );
    }

    public function test_a_second_strike_does_not_overwrite_the_original_assignment(): void
    {
        $media = $this->makeMedia('uploads/a.png');
        $sibling = $this->makeMedia('uploads/b.png');
        $this->seedCrops($sibling, [$this->cropEntry('sib-1', ['mobile', 'tablet', 'desktop'])]);

        // First strike takes desktop, leaving mobile+tablet.
        $media->removeBreakpointsFromSiblings('hero_image', 'hero', ['desktop']);
        $sibling->refresh();
        $this->assertSame(['mobile', 'tablet'], $sibling->crops[0]['breakpoints']);
        $this->assertSame(['mobile', 'tablet', 'desktop'], $sibling->crops[0]['previous_breakpoints']);

        // Second strike empties it - history must still show all three, not
        // the already-reduced mobile+tablet.
        $media->removeBreakpointsFromSiblings('hero_image', 'hero', ['mobile', 'tablet']);
        $sibling->refresh();
        $this->assertSame([], $sibling->crops[0]['breakpoints']);
        $this->assertSame(
            ['mobile', 'tablet', 'desktop'],
            $sibling->crops[0]['previous_breakpoints'],
            'Only the first strike may write history, or the original assignment is lost.'
        );
    }

    public function test_a_no_op_strike_records_no_history(): void
    {
        $media = $this->makeMedia('uploads/a.png');
        $sibling = $this->makeMedia('uploads/b.png');
        $this->seedCrops($sibling, [$this->cropEntry('sib-1', ['mobile'])]);

        // Nothing this crop serves is being claimed.
        $media->removeBreakpointsFromSiblings('hero_image', 'hero', ['desktop']);

        $sibling->refresh();
        $this->assertSame(['mobile'], $sibling->crops[0]['breakpoints']);
        $this->assertArrayNotHasKey(
            'previous_breakpoints',
            $sibling->crops[0],
            'A strike that removed nothing must not write history.'
        );
    }

    public function test_stripping_a_crop_on_the_same_record_records_history(): void
    {
        $path = 'uploads/same-record.png';
        Storage::disk('media')->put($path, $this->makeSolidImage());
        $media = $this->makeMedia($path);
        $this->seedCrops($media, [$this->cropEntry('own-1', ['mobile', 'tablet', 'desktop'])]);

        // A new crop under the same key claims all three breakpoints.
        Livewire::test(MediaCropperPanel::class, ['media' => $media->id])
            ->call('saveCrop', [
                'key' => 'hero', 'location' => 'hero_image',
                'format' => 'png', 'quality' => 90,
                'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
                'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1,
                'targetWidth' => 0, 'targetHeight' => 0,
                'breakpoints' => ['mobile', 'tablet', 'desktop'],
            ]);

        $media->refresh();
        $stripped = collect($media->crops)->firstWhere('id', 'own-1');
        $this->assertSame([], $stripped['breakpoints']);
        $this->assertSame(
            ['mobile', 'tablet', 'desktop'],
            $stripped['previous_breakpoints'],
            'The same-record strip must preserve history too.'
        );
    }
}
