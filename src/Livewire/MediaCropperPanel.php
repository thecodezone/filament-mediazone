<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Livewire;

use Codezone\MediaZone\Media\CropPreset;
use Codezone\MediaZone\Media\MediaLocation;
use Codezone\MediaZone\Services\MediaGlide;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Livewire\Component;

class MediaCropperPanel extends Component
{
    public ?int $mediaId = null;

    public string $statePath = '';

    public string $modalId = '';

    public ?array $mediaData = null;

    public ?string $defaultLocation = null;

    public function mount(
        int|array|null $media = null,
        string $statePath = '',
        string $modalId = '',
        array $presets = [],
        array $formats = [],
        ?string $defaultLocation = null,
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
    }

    protected function getMediaModel(): string
    {
        return config('media.model', \Codezone\MediaZone\Models\Media::class);
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

        $location = $data['location'] ?? null;
        $breakpoints = $data['breakpoints'] ?? ['mobile', 'tablet', 'desktop'];
        $key = trim($data['key'] ?? '') ?: ($location ?? 'custom');
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

        if ($scaleX < 0) {
            $image->flip('h');
        }
        if ($scaleY < 0) {
            $image->flip('v');
        }

        if ($rotate !== 0.0) {
            $image->rotate(-$rotate, '#ffffff');
        }

        if ($cropW > 0 && $cropH > 0) {
            $imgW = $image->width();
            $imgH = $image->height();

            $padLeft = $cropX < 0 ? abs($cropX) : 0;
            $padTop = $cropY < 0 ? abs($cropY) : 0;
            $padRight = max(0, ($cropX + $cropW) - $imgW);
            $padBottom = max(0, ($cropY + $cropH) - $imgH);

            if ($padLeft || $padTop || $padRight || $padBottom) {
                $newW = $imgW + $padLeft + $padRight;
                $newH = $imgH + $padTop + $padBottom;

                $canvas = Image::canvas($newW, $newH, '#ffffff');
                $canvas->insert($image, 'top-left', $padLeft, $padTop);
                $image = $canvas;

                $cropX += $padLeft;
                $cropY += $padTop;
            }

            $image->crop($cropW, $cropH, max(0, $cropX), max(0, $cropY));
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

        $cropId = (string) \Illuminate\Support\Str::uuid();
        $ext = $format;
        $directory = rtrim(dirname($media->path), '/').'/crops';
        $path = $directory.'/'.$cropId.'.'.$ext;

        $encoded = $image->encode($ext, $quality);
        Storage::disk($media->disk)->put($path, $encoded->getEncoded());

        $url = Storage::disk($media->disk)->url($path).'?v='.time();
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
            'updated_at' => now()->toISOString(),
        ];

        $crops = $media->crops ?? [];

        $filteredCrops = array_map(function ($existing) use ($key, $breakpoints) {
            if (($existing['key'] ?? ($existing['crop']['key'] ?? null)) === $key) {
                $existing['breakpoints'] = array_values(array_diff($existing['breakpoints'] ?? [], $breakpoints));
            }

            return $existing;
        }, $crops);

        $filteredCrops[] = $cropEntry;
        $media->crops = $filteredCrops;
        $media->timestamps = false;
        $media->saveQuietly();
        $media->timestamps = true;

        $media->removeBreakpointsFromSiblings($key, $breakpoints);

        $this->dispatch('add-crop', statePath: $this->statePath, mediaId: $media->id, cropId: $cropId, crop: $cropEntry);
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
