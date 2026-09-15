<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Livewire;

use Codezone\MediaZone\Media\CropPreset;
use Codezone\MediaZone\Media\MediaLocation;
use Codezone\MediaZone\Models\Media;
use Codezone\MediaZone\Services\MediaGlide;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use Livewire\Component;

class MediaCropperPanel extends Component
{
    public ?int $mediaId = null;

    public string $statePath = '';

    public string $modalId = '';

    public ?array $mediaData = null;

    public ?string $defaultLocation = null;

    public ?string $editingCropId = null;

    public ?array $initialGeometry = null;

    public ?string $initialKey = null;

    public ?string $initialLocation = null;

    public ?array $initialBreakpoints = null;

    public ?string $initialFormat = null;

    public ?int $initialQuality = null;

    public ?int $initialTargetWidth = null;

    public ?int $initialTargetHeight = null;

    public ?string $initialLabel = null;

    public function mount(
        int|array|null $media = null,
        string $statePath = '',
        string $modalId = '',
        array $presets = [],
        array $formats = [],
        ?string $defaultLocation = null,
        ?string $editingCropId = null,
        ?array $initialGeometry = null,
        ?string $initialKey = null,
        ?string $initialLocation = null,
        ?array $initialBreakpoints = null,
        ?string $initialFormat = null,
        ?int $initialQuality = null,
        ?int $initialTargetWidth = null,
        ?int $initialTargetHeight = null,
        ?string $initialLabel = null,
    ): void {
        if (is_array($media)) {
            $this->mediaId = $media['id'] ?? null;
            $this->mediaData = $media;
        } elseif (is_int($media)) {
            $this->mediaId = $media;
        }

        $this->statePath = $statePath;
        $this->modalId = $modalId;
        $this->defaultLocation = $defaultLocation;
        $this->editingCropId = $editingCropId;
        $this->initialGeometry = $initialGeometry;
        $this->initialKey = $initialKey;
        $this->initialLocation = $initialLocation;
        $this->initialBreakpoints = $initialBreakpoints;
        $this->initialFormat = $initialFormat;
        $this->initialQuality = $initialQuality;
        $this->initialTargetWidth = $initialTargetWidth;
        $this->initialTargetHeight = $initialTargetHeight;
        $this->initialLabel = $initialLabel;
    }

    protected function getMediaModel(): string
    {
        return config('media.model', Media::class);
    }

    public function getMediaProperty()
    {
        $model = $this->getMediaModel();

        return $this->mediaId ? $model::find($this->mediaId) : null;
    }

    public function getPresetsProperty(): array
    {
        return CropPreset::allAsArray();
    }

    public function getFormatsProperty(): array
    {
        return config('media.crop_formats', ['jpg', 'jpeg', 'webp', 'png', 'avif']);
    }

    public function getLocationsProperty(): array
    {
        $presetKeys = array_column(CropPreset::allAsArray(), 'key');

        return collect(MediaLocation::allAsArray())
            ->map(function ($loc) use ($presetKeys) {
                $locKey = $loc->getKey();

                return [
                    'key' => $locKey,
                    'label' => $loc->getLabel(),
                    'description' => $loc->getDescription(),
                    'default_preset' => in_array($locKey, $presetKeys, true) ? $locKey : null,
                ];
            })
            ->values()
            ->all();
    }

    public function saveCrop(array $data): void
    {
        $media = $this->getMediaProperty();
        if (! $media) {
            return;
        }

        // When editing an existing crop, reuse its id (and, where possible,
        // its baked file path) instead of minting a new one, so consumers
        // referencing this crop by id (e.g. a location's stored crop_key)
        // keep resolving to the same crop after it's re-baked. If the crop
        // being edited was deleted out from under this request, there's
        // nothing to replace, so it degrades to creating a new crop entry
        // rather than erroring.
        $editingCropId = $data['id'] ?? null;
        $existingCrop = $editingCropId
            ? collect($media->crops ?? [])->first(fn ($c) => ($c['id'] ?? null) === $editingCropId)
            : null;

        $location = $data['location'] ?? null;
        $breakpoints = $data['breakpoints'] ?? ['mobile', 'tablet', 'desktop'];
        // A location-less save with no explicit key has no real "slot" identity
        // to share with any other media, so the fallback is suffixed with this
        // media's own id rather than a shared literal - otherwise two unrelated
        // Media records would collide on the same implicit key.
        $key = trim($data['key'] ?? '') ?: ($location ?? ('custom-'.$media->id));
        $label = $data['label'] ?? $key;
        $format = $data['format'] ?? 'webp';
        $quality = max(1, min(100, (int) ($data['quality'] ?? 90)));
        $targetWidth = (int) ($data['targetWidth'] ?? 0);
        $targetHeight = (int) ($data['targetHeight'] ?? 0);
        $cropX = (int) ($data['x'] ?? 0);
        $cropY = (int) ($data['y'] ?? 0);
        $cropW = max(1, (int) ($data['width'] ?? 0));
        $cropH = max(1, (int) ($data['height'] ?? 0));
        $rotate = (float) ($data['rotate'] ?? 0);
        $scaleX = (float) ($data['scaleX'] ?? 1);
        $scaleY = (float) ($data['scaleY'] ?? 1);

        $fileContents = Storage::disk($media->disk)->get($media->path);
        if (! $fileContents) {
            return;
        }

        $image = Image::make($fileContents);
        $image->orientate();

        // Natural dimensions of the source file at the time of this crop, used
        // later to detect whether the source has since been replaced at a
        // different resolution (see Media::getCrop() consumers).
        $sourceWidth = $image->width();
        $sourceHeight = $image->height();

        if ($scaleX < 0) {
            $image->flip('h');
        }
        if ($scaleY < 0) {
            $image->flip('v');
        }

        if ($rotate !== 0.0) {
            $image->rotate(-$rotate, '#ffffff');
        }

        // Geometry captured for persistence, defaulting to "no crop applied"
        // (the full, post-transform image) when the request didn't specify one.
        $geometryX = 0;
        $geometryY = 0;
        $geometryWidth = $image->width();
        $geometryHeight = $image->height();

        if ($cropW > 0 && $cropH > 0) {
            $imgW = $image->width();
            $imgH = $image->height();

            // Clamp the requested rectangle to a bounded region around the
            // (post-rotation/flip) image - it may extend past the image edges
            // (baked as whitespace padding below, e.g. for adding blank space
            // above a photo), but only by a bounded, size-proportional amount.
            // This defends against both stale geometry (the source file was
            // replaced at a different, smaller resolution since this crop was
            // last saved) and crafted payloads (a client sending extreme
            // values directly to this endpoint) forcing an unbounded canvas
            // allocation, while still allowing genuine editorial padding.
            [$cropX, $cropY, $cropW, $cropH] = $this->clampCropRectangle($cropX, $cropY, $cropW, $cropH, $imgW, $imgH);

            $geometryX = $cropX;
            $geometryY = $cropY;
            $geometryWidth = $cropW;
            $geometryHeight = $cropH;

            $padLeft = $cropX < 0 ? abs($cropX) : 0;
            $padTop = $cropY < 0 ? abs($cropY) : 0;
            $padRight = max(0, ($cropX + $cropW) - $imgW);
            $padBottom = max(0, ($cropY + $cropH) - $imgH);

            if ($padLeft || $padTop || $padRight || $padBottom) {
                $paddedWidth = $imgW + $padLeft + $padRight;
                $paddedHeight = $imgH + $padTop + $padBottom;

                $canvas = Image::canvas($paddedWidth, $paddedHeight, '#ffffff');
                $canvas->insert($image, 'top-left', $padLeft, $padTop);
                $image = $canvas;

                $cropX += $padLeft;
                $cropY += $padTop;
            }

            $image->crop($cropW, $cropH, $cropX, $cropY);
        }

        if ($targetWidth > 0 && $targetHeight > 0) {
            $image->fit($targetWidth, $targetHeight);
            $image->resizeCanvas($targetWidth, $targetHeight, 'center', false, '#ffffff');
        } elseif ($targetWidth > 0) {
            $image->resize($targetWidth, null, function ($constraint) {
                $constraint->aspectRatio();
            });
        } elseif ($targetHeight > 0) {
            $image->resize(null, $targetHeight, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        $cropId = $existingCrop['id'] ?? (string) Str::uuid();
        $ext = $format;
        // dirname() returns "." for a root-level path (no directory
        // component), which would otherwise get baked into $directory as a
        // literal "./" prefix. League\Glide\Urls\UrlBuilder only trims
        // leading/trailing slashes when signing, so that "./" survives into
        // the signed hash — but browsers normalize "./" out of the request
        // URL before sending it, so the signature the server recomputes
        // never matches and the file 403s no matter how it's re-signed.
        $sourceDirectory = dirname($media->path);
        $directory = ($sourceDirectory === '.' ? '' : rtrim($sourceDirectory, '/').'/').'crops';
        $path = $directory.'/'.$cropId.'.'.$ext;

        $encoded = $image->encode($ext, $quality);
        Storage::disk($media->disk)->put($path, $encoded->getEncoded());

        // Clean up the previous baked output if this edit changed its
        // extension (a format change), so it doesn't linger as an orphan.
        if ($existingCrop && ! empty($existingCrop['path']) && $existingCrop['path'] !== $path) {
            Storage::disk($media->disk)->delete($existingCrop['path']);
        }

        // Local-disk crop URLs share the same "/media/{path}" prefix as the
        // signed Glide route, so an unsigned URL here gets rejected with a
        // 403 whenever the request reaches Laravel instead of being served
        // as a static file (e.g. the baked file is momentarily missing).
        // Cloud disks (e.g. R2) serve from their own domain and never hit
        // that route, so they keep the plain, cache-busted disk URL — same
        // split Media::getSignedUrl() already uses for the source image.
        if (in_array($media->disk, config('media.cloud_disks', []), true)) {
            $url = Storage::disk($media->disk)->url($path).'?v='.time();
        } else {
            $url = MediaGlide::signedUrl($path, ['v' => (string) time()]);
            if (str_starts_with($url, '/')) {
                $url = rtrim(config('app.url'), '/').$url;
            }
        }
        $size = strlen($encoded->getEncoded());

        $cropEntry = [
            'id' => $cropId,
            'crop' => [
                'key' => $key,
                'label' => $label,
                'format' => $format,
                'quality' => $quality,
                'width' => $targetWidth,
                'height' => $targetHeight,
            ],
            'key' => $key,
            'location' => $location,
            'breakpoints' => $breakpoints,
            'disk' => $media->disk,
            'directory' => $directory,
            'visibility' => 'public',
            'name' => $key,
            'path' => $path,
            'url' => $url,
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => $size,
            'type' => 'image/'.$ext,
            'ext' => $ext,
            'geometry' => [
                'x' => $geometryX,
                'y' => $geometryY,
                'width' => $geometryWidth,
                'height' => $geometryHeight,
                'rotate' => $rotate,
                'scaleX' => $scaleX,
                'scaleY' => $scaleY,
                'source_width' => $sourceWidth,
                'source_height' => $sourceHeight,
            ],
            'updated_at' => now()->toISOString(),
        ];

        $crops = $media->crops ?? [];

        $replacedExisting = false;
        $filteredCrops = array_map(function ($existing) use ($key, $breakpoints, $cropId, $cropEntry, &$replacedExisting) {
            if (($existing['id'] ?? null) === $cropId) {
                $replacedExisting = true;

                return $cropEntry;
            }

            if (($existing['key'] ?? ($existing['crop']['key'] ?? null)) === $key) {
                $existing['breakpoints'] = array_values(array_diff($existing['breakpoints'] ?? [], $breakpoints));
            }

            return $existing;
        }, $crops);

        if (! $replacedExisting) {
            $filteredCrops[] = $cropEntry;
        }

        $media->crops = $filteredCrops;
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;

        $media->removeBreakpointsFromSiblings($location, $key, $breakpoints);

        $this->dispatch('add-crop', statePath: $this->statePath, mediaId: $media->id, cropId: $cropId, crop: $cropEntry);
    }

    /**
     * How far a crop rectangle may extend beyond the source image on each
     * side, as a multiple of that axis's source dimension. Baked as
     * whitespace padding (e.g. deliberately adding blank space above a
     * photo) - a real, wanted editorial workflow - so this isn't clamped to
     * zero. It's bounded to a small, size-proportional multiple rather than
     * left unbounded, so a stale request (source replaced at a smaller
     * resolution) or a crafted one (extreme x/y/width/height sent directly
     * to this endpoint) can't force an arbitrarily large canvas allocation.
     */
    private const MAX_PADDING_RATIO = 1.0;

    /**
     * Clamp a requested crop rectangle to a bounded region around
     * [0, 0, $boundsWidth, $boundsHeight] - it may extend past the bounds
     * (see MAX_PADDING_RATIO) but not arbitrarily far. Malformed input
     * (non-positive width/height, an origin or extent far beyond the
     * bounds) is corrected rather than rejected outright, so a stale or
     * crafted request still produces a safe crop instead of failing the
     * whole save.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} [x, y, width, height]
     */
    protected function clampCropRectangle(int $x, int $y, int $width, int $height, int $boundsWidth, int $boundsHeight): array
    {
        $boundsWidth = max(1, $boundsWidth);
        $boundsHeight = max(1, $boundsHeight);

        $padX = (int) round($boundsWidth * self::MAX_PADDING_RATIO);
        $padY = (int) round($boundsHeight * self::MAX_PADDING_RATIO);

        $width = max(1, min($width, $boundsWidth + (2 * $padX)));
        $height = max(1, min($height, $boundsHeight + (2 * $padY)));

        $x = max(-$padX, min($x, $boundsWidth + $padX - $width));
        $y = max(-$padY, min($y, $boundsHeight + $padY - $height));

        return [$x, $y, $width, $height];
    }

    public function deleteCrop(string $id): void
    {
        $media = $this->getMediaProperty();
        if (! $media) {
            return;
        }

        $crop = collect($media->crops ?? [])->first(fn ($c) => ($c['id'] ?? null) === $id);
        if ($crop && ! empty($crop['path'])) {
            Storage::disk($media->disk)->delete($crop['path']);
        }

        $media->crops = array_values(
            array_filter($media->crops ?? [], fn ($c) => ($c['id'] ?? null) !== $id)
        );
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;

        $this->dispatch('crop-deleted', statePath: $this->statePath, cropId: $id);
    }

    public function render(): View
    {
        $media = $this->getMediaProperty();
        $cropperImageUrl = null;

        if ($media && $media->path && MediaGlide::isResizable($media->type ?? '')) {
            $cropperImageUrl = MediaGlide::signedUrl($media->path);
        } elseif ($media) {
            $cropperImageUrl = $media->url;
        }

        return view('mediazone::livewire.media-cropper-panel', [
            'media' => $media,
            'cropperImageUrl' => $cropperImageUrl,
            'presets' => $this->getPresetsProperty(),
            'formats' => $this->getFormatsProperty(),
            'locations' => $this->getLocationsProperty(),
        ]);
    }
}
