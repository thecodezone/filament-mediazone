<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Media;

abstract class CropPreset
{
    abstract public function getKey(): string;

    abstract public function getLabel(): string;

    abstract public function getWidth(): int;

    abstract public function getHeight(): int;

    public function getFormat(): string
    {
        return 'webp';
    }

    public function getQuality(): int
    {
        return 90;
    }

    /**
     * Guide lines to overlay on the crop canvas.
     * Each entry: ['axis' => 'x'|'y', 'percent' => 0-100]
     */
    public function getGuideLines(): array
    {
        return [];
    }

    public function toArray(): array
    {
        return [
            'key' => $this->getKey(),
            'label' => $this->getLabel(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'format' => $this->getFormat(),
            'quality' => $this->getQuality(),
            'guide_lines' => $this->getGuideLines(),
        ];
    }

    public static function allAsArray(): array
    {
        return collect(config('media.crop_presets', []))
            ->filter(fn ($c) => class_exists($c))
            ->map(fn ($c) => (new $c)->toArray())
            ->values()
            ->all();
    }
}
