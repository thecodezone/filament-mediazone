<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Models;

use Codezone\MediaZone\Database\Factories\MediaFactory;
use Codezone\MediaZone\Services\MediaGlide;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Media extends Model
{
    use HasFactory, HasSlug;

    protected $table = 'media';

    protected $guarded = [];

    protected $casts = [
        'crops' => 'array',
        'exif' => 'array',
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
    ];

    protected $appends = [
        'url',
        'thumbnail_url',
        'medium_url',
        'large_url',
        'resizable',
        'size_for_humans',
        'pretty_name',
    ];

    protected static function newFactory()
    {
        return MediaFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    /**
     * Remove the given breakpoints from all sibling media crops with the same key
     * in the same directory. Does not bump updated_at.
     */
    public function removeBreakpointsFromSiblings(string $cropKey, array $breakpoints): void
    {
        if (empty($breakpoints)) {
            return;
        }

        static::withoutGlobalScopes()
            ->where('id', '!=', $this->id)
            ->where('directory', $this->directory)
            ->whereNotNull('crops')
            ->each(function (self $sibling) use ($cropKey, $breakpoints) {
                $changed = false;
                $updatedCrops = array_map(function ($crop) use ($cropKey, $breakpoints, &$changed) {
                    if (($crop['key'] ?? null) !== $cropKey) {
                        return $crop;
                    }
                    $remaining = array_values(array_diff($crop['breakpoints'] ?? [], $breakpoints));
                    if (count($remaining) !== count($crop['breakpoints'] ?? [])) {
                        $crop['breakpoints'] = $remaining;
                        $changed = true;
                    }

                    return $crop;
                }, $sibling->crops);

                if ($changed) {
                    $sibling->crops = $updatedCrops;
                    $sibling->timestamps = false;
                    $sibling->saveQuietly();
                    $sibling->timestamps = true;
                }
            });
    }

    /**
     * Resolve a single media item from a picker's stored JSON state.
     */
    public static function resolve(mixed $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $id = is_array($raw) ? ($raw['id'] ?? null) : $raw;
        $cropKey = is_array($raw) ? ($raw['crop_key'] ?? null) : null;

        $media = static::withoutGlobalScopes()->forView()->find((int) $id);
        if (! $media) {
            return null;
        }

        $arr = $media->toMediaArray();
        if ($cropKey) {
            $arr['crop_key'] = $cropKey;
        }

        return $arr;
    }

    /**
     * Resolve an array of media items from a picker's stored JSON state.
     */
    public static function resolveMany(array $raw): array
    {
        return collect($raw)->map(function ($item) {
            $id = is_array($item) ? ($item['id'] ?? null) : $item;
            $cropKey = is_array($item) ? ($item['crop_key'] ?? null) : null;

            if (! $id) {
                return null;
            }

            $media = static::withoutGlobalScopes()->forView()->find((int) $id);
            if (! $media) {
                return null;
            }

            $arr = $media->toMediaArray();
            if ($cropKey) {
                $arr['crop_key'] = $cropKey;
            }

            return $arr;
        })->filter()->values()->all();
    }

    public function scopeForView($query)
    {
        return $query->select('id', 'disk', 'path', 'url', 'name', 'width', 'height', 'type', 'title', 'caption', 'crops', 'directory');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) => $q->where(
            fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('alt', 'like', "%{$search}%")
        ));
    }

    protected function prettyName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->title) {
                    return $this->title;
                }

                return pathinfo($this->name, PATHINFO_FILENAME);
            },
        );
    }

    protected function sizeForHumans(): Attribute
    {
        return Attribute::make(
            get: function () {
                $size = $this->size ?? 0;

                if ($size <= 0) {
                    return '—';
                }

                if ($size >= 1073741824) {
                    return number_format($size / 1073741824, 2).' GB';
                }

                if ($size >= 1048576) {
                    return number_format($size / 1048576, 2).' MB';
                }

                if ($size >= 1024) {
                    return number_format($size / 1024, 2).' KB';
                }

                return $size.' B';
            },
        );
    }

    public function getSignedUrl(array $params = []): string
    {
        $cloudDisks = config('media.cloud_disks', []);

        if (in_array($this->disk, $cloudDisks, true) || ! MediaGlide::isResizable($this->type ?? '')) {
            return $this->url ?? Storage::disk($this->disk)->url($this->path);
        }

        $path = MediaGlide::signedUrl($this->path, $params);

        if (str_starts_with($path, '/')) {
            $path = rtrim(config('app.url'), '/').$path;
        }

        return $path;
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getSignedUrl(['w' => 200, 'h' => 200, 'fit' => 'crop', 'fm' => 'webp', 'q' => 60]),
        );
    }

    protected function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getSignedUrl(['w' => 640, 'h' => 640, 'fit' => 'contain', 'fm' => 'webp', 'q' => 75]),
        );
    }

    protected function largeUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getSignedUrl(['w' => 1024, 'h' => 1024, 'fit' => 'contain', 'fm' => 'webp', 'q' => 80]),
        );
    }

    protected function resizable(): Attribute
    {
        return Attribute::make(
            get: fn () => MediaGlide::isResizable($this->type ?? ''),
        );
    }

    public function isCroppableImage(): bool
    {
        $type = $this->type ?? '';

        return str_contains($type, 'image') && ! str_contains($type, 'svg');
    }

    public function getCropById(string $id): ?array
    {
        foreach ($this->crops ?? [] as $crop) {
            if (($crop['id'] ?? null) === $id) {
                return $crop;
            }
        }

        return null;
    }

    public function getCrop(string $key): ?array
    {
        foreach ($this->crops ?? [] as $crop) {
            $cKey = $crop['key'] ?? ($crop['crop']['key'] ?? null);
            if ($cKey === $key) {
                return $crop;
            }
        }

        return null;
    }

    public function hasCrop(string $key): bool
    {
        return $this->getCrop($key) !== null;
    }

    public function getCropForLocation(string $locationKey, ?string $breakpoint = null): ?array
    {
        $candidates = [];
        foreach ($this->crops ?? [] as $crop) {
            $cropLocation = $crop['location'] ?? null;
            $cropKey = $crop['key'] ?? ($crop['crop']['key'] ?? null);
            if ($cropLocation === $locationKey || $cropKey === $locationKey) {
                $candidates[] = $crop;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        if ($breakpoint && count($candidates) > 1) {
            foreach ($candidates as $c) {
                $bps = $c['breakpoints'] ?? ['mobile', 'tablet', 'desktop'];
                if (in_array($breakpoint, $bps, true)) {
                    return $c;
                }
            }
        }

        return $candidates[0];
    }

    public function hasCropForLocation(string $locationKey, ?string $breakpoint = null): bool
    {
        return $this->getCropForLocation($locationKey, $breakpoint) !== null;
    }

    public function toMediaArray(): array
    {
        $sortedCrops = $this->crops ?? [];
        usort($sortedCrops, fn ($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

        $cropOptions = [];
        $cropsByLocation = [];
        foreach ($sortedCrops as $crop) {
            $cropId = $crop['id'] ?? null;
            $key = $crop['key'] ?? ($crop['crop']['key'] ?? null);
            $label = $crop['crop']['label'] ?? $key;
            if ($cropId) {
                $breakpoints = $crop['breakpoints'] ?? [];
                $bpSuffix = ! empty($breakpoints) ? ' ('.implode(', ', array_map('ucfirst', $breakpoints)).')' : '';
                $cropOptions[$cropId] = ($label ?? $key).$bpSuffix;
            }
            $location = $crop['location'] ?? $key;
            if ($location && ! isset($cropsByLocation[$location])) {
                $cropsByLocation[$location] = $crop;
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'medium_url' => $this->medium_url,
            'large_url' => $this->large_url,
            'alt' => $this->alt,
            'name' => $this->name,
            'pretty_name' => $this->pretty_name,
            'size_for_humans' => $this->size_for_humans,
            'ext' => $this->ext,
            'width' => $this->width,
            'height' => $this->height,
            'resizable' => $this->resizable,
            'disk' => $this->disk,
            'directory' => $this->directory,
            'crops' => $sortedCrops,
            'crop_options' => $cropOptions,
            'crops_by_location' => $cropsByLocation,
        ] + $this->pivotMediaArray();
    }

    /**
     * Hook for subclasses to merge extra pivot/relationship data into toMediaArray().
     * Override in the consuming app's Media model instead of touching base model.
     */
    protected function pivotMediaArray(): array
    {
        return [];
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: function () {
                $url = $this->getRawOriginal('url');
                if ($url) {
                    return $url;
                }

                return $this->refreshUrl();
            },
        );
    }

    public function isPrivate(): bool
    {
        try {
            $isPrivate = Storage::disk($this->disk)->getVisibility($this->path) === 'private';
        } catch (\Throwable) {
            $isPrivate = config(sprintf('filesystems.disks.%s.visibility', $this->disk)) !== 'public';
        }

        return $isPrivate;
    }

    public function generateUrl(): string
    {
        $url = Storage::disk($this->disk)->url($this->path);

        if ($this->exists) {
            $this->url = $url;
            $this->saveQuietly(['url']);
        }

        return $url;
    }

    public function refreshUrl(): string
    {
        return $this->generateUrl();
    }

    public function withoutNameExtension(): void
    {
        $extension = pathinfo($this->name, PATHINFO_EXTENSION);
        if ($extension) {
            $this->name = pathinfo($this->name, PATHINFO_FILENAME);
        }
    }

    public function removeNameExtension(): void
    {
        $this->withoutNameExtension();
        if ($this->isDirty()) {
            $this->save();
        }
    }
}
