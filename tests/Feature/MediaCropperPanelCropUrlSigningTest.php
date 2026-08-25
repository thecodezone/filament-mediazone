<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Feature;

use Codezone\MediaZone\Livewire\MediaCropperPanel;
use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;
use Livewire\Livewire;

/**
 * Regression coverage for the baked-crop URL Glide-signature bug.
 *
 * Local-disk crop files are served under the same "/media/{path}" prefix as
 * the signed Glide transform route (MediaGlideController). Whenever a request
 * for one of these files reaches Laravel instead of being served as a static
 * file (e.g. the baked file went missing after a deploy), that controller
 * demands a valid Glide signature and 403s otherwise. saveCrop() used to
 * write a plain "?v=<timestamp>" cache-busting URL with no such signature,
 * so every one of those fallback requests failed.
 *
 * Cloud disks (e.g. R2) resolve to a different domain entirely and never hit
 * that route, so their crop URLs must stay exactly as they were.
 */
class MediaCropperPanelCropUrlSigningTest extends TestCase
{
    use RefreshDatabase;

    private function makeSourceMedia(string $path, string $disk): Media
    {
        $dispatcher = Media::getEventDispatcher();
        Media::unsetEventDispatcher();
        try {
            return Media::factory()->create([
                'disk' => $disk,
                'directory' => 'uploads',
                'visibility' => 'public',
                'name' => 'source',
                'path' => $path,
                'url' => 'https://example.com/'.$path,
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

    private function makeImage(): string
    {
        $image = imagecreatetruecolor(20, 20);
        imagefilledrectangle($image, 0, 0, 19, 19, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    public function test_local_disk_crop_url_is_a_validly_signed_glide_url(): void
    {
        $sourcePath = 'uploads/source-local.png';
        Storage::disk('media')->put($sourcePath, $this->makeImage());
        $media = $this->makeSourceMedia($sourcePath, 'media');

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

        $media->refresh();
        $crop = collect($media->crops)->last();
        $this->assertNotNull($crop);

        $parts = parse_url($crop['url']);
        parse_str($parts['query'] ?? '', $query);

        $this->assertArrayHasKey('s', $query, 'Local-disk crop URLs must carry a Glide signature.');
        $this->assertArrayHasKey('v', $query, 'The cache-busting "v" param must be preserved.');

        // The signature must actually validate against the route Glide will
        // receive the request on — this is what MediaGlideController checks
        // before it will serve the file.
        SignatureFactory::create(config('app.key'))->validateRequest(
            '/'.config('media.glide.route_path', 'media').'/'.$crop['path'],
            $query
        );
        $this->addToAssertionCount(1); // validateRequest() throws on failure.
    }

    public function test_cloud_disk_crop_url_is_unchanged_plain_disk_url(): void
    {
        config()->set('media.cloud_disks', ['r2']);
        config()->set('filesystems.disks.r2', [
            'driver' => 'local',
            'root' => storage_path('app/public/r2'),
            'url' => 'https://cdn.example-r2.com',
            'visibility' => 'public',
        ]);

        $sourcePath = 'uploads/source-r2.png';
        Storage::disk('r2')->put($sourcePath, $this->makeImage());
        $media = $this->makeSourceMedia($sourcePath, 'r2');

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

        $media->refresh();
        $crop = collect($media->crops)->last();
        $this->assertNotNull($crop);

        // Must resolve on the R2 domain, not the app's own "/media/..." route
        // — and it must never gain a Glide signature it doesn't need.
        $this->assertStringStartsWith('https://cdn.example-r2.com/', $crop['url']);
        $this->assertStringContainsString('?v=', $crop['url']);
        $this->assertStringNotContainsString('s=', $crop['url']);
    }

    public function test_signed_local_crop_url_is_rejected_without_the_signature(): void
    {
        $this->expectException(SignatureException::class);

        SignatureFactory::create(config('app.key'))->validateRequest(
            '/media/crops/some-crop.webp',
            ['v' => '123']
        );
    }
}
