<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Feature;

use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A Media row can outlive the file it points at (deleted or moved on disk
 * out from under the row). /media-proxy and /media-download read that file
 * with Storage::readStream(), which returns null rather than throwing when
 * the path is missing -- and passing null to fpassthru() is a TypeError,
 * not a handled 404. Bugsnag: "TypeError in GET /media-proxy/14939 -
 * fpassthru(): Argument #1 ($stream) must be of type resource, null given".
 */
class MediaProxyMissingFileTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFakeUser(): void
    {
        $user = new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return 'hashed';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        };

        $this->actingAs($user);
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
                'name' => 'missing',
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

    public function test_media_proxy_returns_404_instead_of_crashing_when_the_file_is_missing(): void
    {
        $this->actingAsFakeUser();

        // No Storage::put() -- the row exists, the file on disk does not.
        $media = $this->makeMedia('uploads/missing.png');

        $this->get(route('media.proxy', $media->id))
            ->assertNotFound();
    }

    public function test_media_download_returns_404_instead_of_crashing_when_the_file_is_missing(): void
    {
        $this->actingAsFakeUser();

        $media = $this->makeMedia('uploads/missing-download.png');

        $this->get(route('media.download', $media->id))
            ->assertNotFound();
    }
}
