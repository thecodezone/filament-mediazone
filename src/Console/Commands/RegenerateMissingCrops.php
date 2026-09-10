<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Console\Commands;

use Codezone\MediaZone\Media\CropRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Regenerates crop files that have gone missing from disk (e.g. a
 * MediaObserver::deleted() call — see the "shared crops directory" fix —
 * wiped out crops belonging to other, unrelated Media records) using each
 * crop's own stored geometry replayed against its still-present source
 * image via CropRenderer, the same pixel pipeline the interactive cropper
 * uses. This restores the exact original edit rather than requiring anyone
 * to manually re-crop affected images.
 *
 * A crop can only be regenerated if both its geometry and its source image
 * are still available; each is reported separately so a run's output is a
 * complete account of what could and couldn't be recovered.
 */
class RegenerateMissingCrops extends Command
{
    protected $signature = 'media:regenerate-missing-crops {--dry-run : Report what would be regenerated without writing or saving anything}';

    protected $description = 'Regenerate crop files missing from disk from their stored geometry and original source image.';

    public function handle(CropRenderer $renderer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $modelClass = config('media.model');

        $stats = [
            'already_present' => 0,
            'regenerated' => 0,
            'skipped_no_geometry' => 0,
            'skipped_source_missing' => 0,
            'failed' => 0,
        ];

        $modelClass::query()
            ->whereNotNull('crops')
            ->chunkById(100, function ($mediaItems) use ($renderer, $dryRun, &$stats) {
                foreach ($mediaItems as $media) {
                    $this->processMedia($media, $renderer, $dryRun, $stats);
                }
            });

        $this->newLine();
        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Already present: {$stats['already_present']}");
        $this->info(($dryRun ? "{$prefix}Would regenerate" : 'Regenerated').": {$stats['regenerated']}");

        if ($stats['skipped_no_geometry'] > 0) {
            $this->warn("Skipped, no stored geometry to regenerate from: {$stats['skipped_no_geometry']}");
        }

        if ($stats['skipped_source_missing'] > 0) {
            $this->error("Skipped, source image also missing: {$stats['skipped_source_missing']}");
        }

        if ($stats['failed'] > 0) {
            $this->error("Failed while regenerating: {$stats['failed']}");
        }

        return self::SUCCESS;
    }

    private function processMedia($media, CropRenderer $renderer, bool $dryRun, array &$stats): void
    {
        $crops = $media->crops ?? [];
        if (empty($crops)) {
            return;
        }

        $dirty = false;

        foreach ($crops as $i => $crop) {
            if (empty($crop['disk']) || empty($crop['path'])) {
                continue;
            }

            $cropDisk = Storage::disk($crop['disk']);
            if ($cropDisk->exists($crop['path'])) {
                $stats['already_present']++;

                continue;
            }

            $geometry = $crop['geometry'] ?? null;
            if (! $geometry) {
                $stats['skipped_no_geometry']++;
                $this->warn("Media #{$media->id} crop '{$crop['id']}' is missing and has no stored geometry ({$crop['disk']}:{$crop['path']}).");

                continue;
            }

            if (! $media->disk || ! $media->path || ! Storage::disk($media->disk)->exists($media->path)) {
                $stats['skipped_source_missing']++;
                $this->error("Media #{$media->id} crop '{$crop['id']}' is missing and its source image is also unavailable — cannot regenerate ({$crop['disk']}:{$crop['path']}).");

                continue;
            }

            if ($dryRun) {
                $stats['regenerated']++;
                $this->line("Would regenerate media #{$media->id} crop '{$crop['id']}' -> {$crop['disk']}:{$crop['path']}");

                continue;
            }

            try {
                $sourceContents = Storage::disk($media->disk)->get($media->path);
                $format = $crop['crop']['format'] ?? ($crop['ext'] ?? 'webp');
                $quality = (int) ($crop['crop']['quality'] ?? 90);
                $targetWidth = (int) ($crop['crop']['width'] ?? 0);
                $targetHeight = (int) ($crop['crop']['height'] ?? 0);

                $result = $renderer->render($sourceContents, $geometry, $format, $quality, $targetWidth, $targetHeight);

                $cropDisk->put($crop['path'], $result['bytes']);

                $crops[$i]['width'] = $result['width'];
                $crops[$i]['height'] = $result['height'];
                $crops[$i]['size'] = strlen($result['bytes']);
                $dirty = true;

                $stats['regenerated']++;
                $this->info("Regenerated media #{$media->id} crop '{$crop['id']}' -> {$crop['disk']}:{$crop['path']}");
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->error("Failed to regenerate media #{$media->id} crop '{$crop['id']}' ({$crop['disk']}:{$crop['path']}): {$e->getMessage()}");
            }
        }

        if ($dirty) {
            $media->crops = $crops;
            $media->timestamps = false;
            $media->saveQuietly();
            $media->timestamps = true;
        }
    }
}
