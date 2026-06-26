<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Unit;

use Codezone\MediaZone\Media\MediaLocation;
use Codezone\MediaZone\Tests\TestCase;

class MediaLocationTest extends TestCase
{
    private function makeLocation(string $key, string $label, ?string $description = null): MediaLocation
    {
        return new class($key, $label, $description) extends MediaLocation
        {
            public function __construct(
                private string $k,
                private string $l,
                private ?string $d = null,
            ) {}

            public function getKey(): string
            {
                return $this->k;
            }

            public function getLabel(): string
            {
                return $this->l;
            }

            public function getDescription(): ?string
            {
                return $this->d;
            }
        };
    }

    public function test_all_as_options_returns_empty_label_and_configured_classes(): void
    {
        $className = new class extends MediaLocation
        {
            public function getKey(): string
            {
                return 'hero';
            }

            public function getLabel(): string
            {
                return 'Hero Image';
            }
        };
        $className = get_class($className);

        config(['media.crop_locations' => [$className]]);

        $options = MediaLocation::allAsOptions();

        $this->assertArrayHasKey('', $options);
        $this->assertSame('— None —', $options['']);
        $this->assertArrayHasKey('hero', $options);
        $this->assertSame('Hero Image', $options['hero']);
    }

    public function test_all_as_options_custom_empty_label(): void
    {
        config(['media.crop_locations' => []]);

        $options = MediaLocation::allAsOptions('Select location...');

        $this->assertArrayHasKey('', $options);
        $this->assertSame('Select location...', $options['']);
    }

    public function test_all_as_options_no_empty_label_when_empty_string(): void
    {
        config(['media.crop_locations' => []]);

        $options = MediaLocation::allAsOptions('');

        $this->assertArrayNotHasKey('', $options);
    }

    public function test_all_as_array_returns_instances(): void
    {
        $className = new class extends MediaLocation
        {
            public function getKey(): string
            {
                return 'silo';
            }

            public function getLabel(): string
            {
                return 'Silo Image';
            }
        };
        $className = get_class($className);

        config(['media.crop_locations' => [$className]]);

        $result = MediaLocation::allAsArray();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(MediaLocation::class, $result[0]);
        $this->assertSame('silo', $result[0]->getKey());
    }

    public function test_all_as_array_skips_nonexistent_classes(): void
    {
        config(['media.crop_locations' => ['Nonexistent\\Location\\Class']]);

        $result = MediaLocation::allAsArray();

        $this->assertSame([], $result);
    }
}
