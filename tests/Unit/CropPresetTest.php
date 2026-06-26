<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Unit;

use Codezone\MediaZone\Media\CropPreset;
use Codezone\MediaZone\Tests\TestCase;

class CropPresetTest extends TestCase
{
    private function makePreset(string $key, string $label, int $width, int $height, string $format = 'webp', int $quality = 90): CropPreset
    {
        return new class($key, $label, $width, $height, $format, $quality) extends CropPreset
        {
            public function __construct(
                private string $k,
                private string $l,
                private int $w,
                private int $h,
                private string $f,
                private int $q,
            ) {}

            public function getKey(): string
            {
                return $this->k;
            }

            public function getLabel(): string
            {
                return $this->l;
            }

            public function getWidth(): int
            {
                return $this->w;
            }

            public function getHeight(): int
            {
                return $this->h;
            }

            public function getFormat(): string
            {
                return $this->f;
            }

            public function getQuality(): int
            {
                return $this->q;
            }
        };
    }

    public function test_to_array_returns_all_keys(): void
    {
        $preset = $this->makePreset('silo_image', 'Silo Image', 1000, 1000, 'webp', 90);

        $array = $preset->toArray();

        $this->assertSame([
            'key' => 'silo_image',
            'label' => 'Silo Image',
            'width' => 1000,
            'height' => 1000,
            'format' => 'webp',
            'quality' => 90,
            'guide_lines' => [],
        ], $array);
    }

    public function test_to_array_uses_default_format_and_quality(): void
    {
        $preset = new class extends CropPreset
        {
            public function getKey(): string
            {
                return 'test';
            }

            public function getLabel(): string
            {
                return 'Test';
            }

            public function getWidth(): int
            {
                return 800;
            }

            public function getHeight(): int
            {
                return 600;
            }
        };

        $array = $preset->toArray();

        $this->assertSame('webp', $array['format']);
        $this->assertSame(90, $array['quality']);
    }

    public function test_all_as_array_returns_empty_when_no_presets_configured(): void
    {
        config(['media.crop_presets' => []]);

        $this->assertSame([], CropPreset::allAsArray());
    }

    public function test_all_as_array_skips_nonexistent_classes(): void
    {
        config(['media.crop_presets' => [
            'Nonexistent\\Preset\\Class',
        ]]);

        $result = CropPreset::allAsArray();

        $this->assertSame([], $result);
    }

    public function test_all_as_array_with_inline_preset(): void
    {
        $presetClass = new class extends CropPreset
        {
            public function getKey(): string
            {
                return 'inline_test';
            }

            public function getLabel(): string
            {
                return 'Inline Test';
            }

            public function getWidth(): int
            {
                return 400;
            }

            public function getHeight(): int
            {
                return 300;
            }
        };

        $className = get_class($presetClass);
        config(['media.crop_presets' => [$className]]);

        $result = CropPreset::allAsArray();

        $this->assertCount(1, $result);
        $this->assertSame('inline_test', $result[0]['key']);
    }
}
