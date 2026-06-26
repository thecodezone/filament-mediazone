<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Media;

abstract class MediaLocation
{
    abstract public function getKey(): string;

    abstract public function getLabel(): string;

    public function getDescription(): ?string
    {
        return null;
    }

    public static function allAsOptions(string $emptyLabel = '— None —'): array
    {
        $options = $emptyLabel ? ['' => $emptyLabel] : [];
        foreach (config('media.crop_locations', []) as $class) {
            if (class_exists($class)) {
                $loc = new $class;
                $options[$loc->getKey()] = $loc->getLabel();
            }
        }

        return $options;
    }

    public static function allAsArray(): array
    {
        return collect(config('media.crop_locations', []))
            ->filter(fn ($c) => class_exists($c))
            ->map(fn ($c) => new $c)
            ->values()
            ->all();
    }
}
