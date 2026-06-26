<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Unit;

use Codezone\MediaZone\Services\MediaGlide;
use Codezone\MediaZone\Tests\TestCase;

class MediaGlideTest extends TestCase
{
    public function test_is_resizable_returns_true_for_image_types(): void
    {
        foreach (['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'] as $type) {
            $this->assertTrue(MediaGlide::isResizable($type), "Expected {$type} to be resizable");
        }
    }

    public function test_is_resizable_returns_true_for_bare_image_type(): void
    {
        $this->assertTrue(MediaGlide::isResizable('image'));
    }

    public function test_is_resizable_returns_false_for_non_image_types(): void
    {
        foreach (['application/pdf', 'video/mp4', 'image/svg+xml', 'text/plain', ''] as $type) {
            $this->assertFalse(MediaGlide::isResizable($type), "Expected {$type} to not be resizable");
        }
    }

    public function test_signed_url_contains_path_and_signature(): void
    {
        MediaGlide::$urlBuilder = null; // Reset cached builder between tests

        $url = MediaGlide::signedUrl('silo_images/test.jpg', ['w' => 200, 'h' => 200]);

        $this->assertStringContainsString('test.jpg', $url);
        $this->assertStringContainsString('s=', $url);
        $this->assertStringContainsString('w=200', $url);
        $this->assertStringContainsString('h=200', $url);
    }

    public function test_signed_url_is_deterministic_for_same_params(): void
    {
        MediaGlide::$urlBuilder = null;

        $url1 = MediaGlide::signedUrl('test.jpg', ['w' => 100]);
        $url2 = MediaGlide::signedUrl('test.jpg', ['w' => 100]);

        $this->assertSame($url1, $url2);
    }

    public function test_signed_urls_differ_for_different_params(): void
    {
        MediaGlide::$urlBuilder = null;

        $url1 = MediaGlide::signedUrl('test.jpg', ['w' => 100]);
        $url2 = MediaGlide::signedUrl('test.jpg', ['w' => 200]);

        $this->assertNotSame($url1, $url2);
    }
}
