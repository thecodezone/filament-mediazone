<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Unit;

use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedia(array $attrs = []): Media
    {
        $dispatcher = Media::getEventDispatcher();
        Media::unsetEventDispatcher();
        try {
            return Media::factory()->create(array_merge([
                'disk' => 'media',
                'directory' => 'silo_images',
                'visibility' => 'public',
                'name' => 'test-image',
                'path' => 'silo_images/test-image.jpg',
                'url' => 'https://example.com/media/silo_images/test-image.jpg',
                'width' => 1200,
                'height' => 900,
                'size' => 204800,
                'type' => 'image/jpeg',
                'ext' => 'jpg',
            ], $attrs));
        } finally {
            Media::setEventDispatcher($dispatcher);
        }
    }

    public function test_get_crop_finds_by_key(): void
    {
        $media = $this->makeMedia(['crops' => [
            ['id' => 'id-1', 'key' => 'thumbnail', 'crop' => ['key' => 'thumbnail', 'label' => 'Thumbnail'], 'url' => '', 'path' => 'crops/id-1.webp', 'ext' => 'webp', 'width' => 200, 'height' => 200],
            ['id' => 'id-2', 'key' => 'hero', 'crop' => ['key' => 'hero', 'label' => 'Hero'], 'url' => '', 'path' => 'crops/id-2.webp', 'ext' => 'webp', 'width' => 1200, 'height' => 600],
        ]]);

        $result = $media->getCrop('thumbnail');

        $this->assertNotNull($result);
        $this->assertSame('thumbnail', $result['key']);
    }

    public function test_get_crop_returns_null_for_missing_key(): void
    {
        $media = $this->makeMedia(['crops' => []]);

        $this->assertNull($media->getCrop('nonexistent'));
    }

    public function test_has_crop_returns_true_when_exists(): void
    {
        $media = $this->makeMedia(['crops' => [
            ['id' => 'id-1', 'key' => 'hero', 'crop' => ['key' => 'hero', 'label' => 'Hero'], 'url' => '', 'path' => 'crops/id-1.webp', 'ext' => 'webp', 'width' => 800, 'height' => 400],
        ]]);

        $this->assertTrue($media->hasCrop('hero'));
        $this->assertFalse($media->hasCrop('missing'));
    }

    public function test_get_crop_by_id_finds_exact_crop(): void
    {
        $media = $this->makeMedia(['crops' => [
            ['id' => 'uuid-abc', 'key' => 'hero', 'crop' => ['key' => 'hero'], 'url' => '', 'path' => 'crops/uuid-abc.webp', 'ext' => 'webp', 'width' => 800, 'height' => 400],
        ]]);

        $result = $media->getCropById('uuid-abc');

        $this->assertNotNull($result);
        $this->assertSame('uuid-abc', $result['id']);
    }

    public function test_get_crop_for_location_matches_by_location_key(): void
    {
        $media = $this->makeMedia(['crops' => [
            ['id' => 'id-1', 'key' => 'hero', 'location' => 'hero_image', 'breakpoints' => ['desktop'], 'url' => '', 'path' => 'crops/id-1.webp', 'ext' => 'webp', 'width' => 800, 'height' => 400],
        ]]);

        $result = $media->getCropForLocation('hero_image', 'desktop');

        $this->assertNotNull($result);
        $this->assertSame('id-1', $result['id']);
    }

    public function test_pretty_name_returns_title_when_set(): void
    {
        $media = $this->makeMedia(['title' => 'My Image Title']);

        $this->assertSame('My Image Title', $media->pretty_name);
    }

    public function test_pretty_name_returns_filename_without_extension(): void
    {
        $media = $this->makeMedia(['title' => null, 'name' => 'my-image']);

        $this->assertSame('my-image', $media->pretty_name);
    }

    public function test_size_for_humans_formats_bytes(): void
    {
        $media = $this->makeMedia(['size' => 500]);
        $this->assertSame('500 B', $media->size_for_humans);
    }

    public function test_size_for_humans_formats_kilobytes(): void
    {
        $media = $this->makeMedia(['size' => 2048]);
        $this->assertStringContainsString('KB', $media->size_for_humans);
    }

    public function test_size_for_humans_returns_dash_for_zero_size(): void
    {
        $media = $this->makeMedia(['size' => 0]);
        $this->assertSame('—', $media->size_for_humans);
    }

    public function test_resizable_true_for_jpeg(): void
    {
        $media = $this->makeMedia(['type' => 'image/jpeg']);
        $this->assertTrue($media->resizable);
    }

    public function test_resizable_false_for_svg(): void
    {
        $media = $this->makeMedia(['type' => 'image/svg+xml']);
        $this->assertFalse($media->resizable);
    }

    public function test_is_croppable_image_true_for_raster(): void
    {
        $media = $this->makeMedia(['type' => 'image/jpeg']);
        $this->assertTrue($media->isCroppableImage());
    }

    public function test_is_croppable_image_false_for_svg(): void
    {
        $media = $this->makeMedia(['type' => 'image/svg+xml']);
        $this->assertFalse($media->isCroppableImage());
    }

    public function test_to_media_array_contains_required_keys(): void
    {
        $media = $this->makeMedia();
        $arr = $media->toMediaArray();

        foreach (['id', 'type', 'url', 'alt', 'name', 'pretty_name', 'ext', 'width', 'height', 'resizable', 'disk', 'directory', 'crops', 'crop_options', 'crops_by_location'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: {$key}");
        }
    }

    public function test_without_name_extension_removes_extension(): void
    {
        $media = $this->makeMedia(['name' => 'image.jpg']);
        $media->withoutNameExtension();

        $this->assertSame('image', $media->name);
    }

    public function test_without_name_extension_no_op_when_no_extension(): void
    {
        $media = $this->makeMedia(['name' => 'image']);
        $media->withoutNameExtension();

        $this->assertSame('image', $media->name);
    }
}
