<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Media;

use Intervention\Image\ImageManagerStatic as Image;

/**
 * Renders a single baked crop image from source file bytes and an
 * already-resolved geometry — the same pixel pipeline MediaCropperPanel
 * uses when a user saves a crop interactively, extracted so it can also
 * be replayed later (e.g. to regenerate a crop file that went missing
 * from disk without losing the original edit) from a Media record's
 * already-stored `crops[].geometry`.
 *
 * This intentionally does not clamp/sanitize the geometry the way
 * MediaCropperPanel::saveCrop() does for fresh, untrusted request input —
 * geometry passed here is expected to already be a valid, previously
 * computed rectangle (either just clamped by the caller, or read back
 * from a stored crop entry).
 */
class CropRenderer
{
    /**
     * @param  string  $sourceContents  Raw bytes of the source image.
     * @param  array{x?: int, y?: int, width?: int, height?: int, rotate?: float, scaleX?: float, scaleY?: float}  $geometry
     * @return array{bytes: string, width: int, height: int}
     */
    public function render(
        string $sourceContents,
        array $geometry,
        string $format,
        int $quality,
        int $targetWidth = 0,
        int $targetHeight = 0,
    ): array {
        $image = Image::make($sourceContents);
        $image->orientate();

        $scaleX = (float) ($geometry['scaleX'] ?? 1);
        $scaleY = (float) ($geometry['scaleY'] ?? 1);
        if ($scaleX < 0) {
            $image->flip('h');
        }
        if ($scaleY < 0) {
            $image->flip('v');
        }

        $rotate = (float) ($geometry['rotate'] ?? 0);
        if ($rotate !== 0.0) {
            $image->rotate(-$rotate, '#ffffff');
        }

        $cropX = (int) ($geometry['x'] ?? 0);
        $cropY = (int) ($geometry['y'] ?? 0);
        $cropW = max(1, (int) ($geometry['width'] ?? $image->width()));
        $cropH = max(1, (int) ($geometry['height'] ?? $image->height()));

        $imgW = $image->width();
        $imgH = $image->height();

        // Reconstruct the same whitespace-padding canvas saveCrop() bakes in
        // when the stored rectangle extends past the (post-rotation/flip)
        // image bounds, e.g. deliberate blank space added above a photo.
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

        $encoded = $image->encode($format, $quality);

        return [
            'bytes' => $encoded->getEncoded(),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }
}
