@props([
    'media' => null,
    'hover' => false,
    'crop' => null,
    'location' => null,
    'srcset' => null,
    'sizes' => null,
    'class' => '',
    'pictureClass' => null,
    'imgClass' => null
])

@php
    if (is_int($media) || is_numeric($media)) {
        $mediaModel = cache()->tags(['media'])->remember('media_' . $media, 3600, fn() => App\Models\Media::query()->forView()->find((int) $media));
        $media = $mediaModel ? $mediaModel->toMediaArray() : null;
    } elseif ($media instanceof App\Models\Media) {
        $media = $media->toMediaArray();
    } elseif (!is_array($media)) {
        $media = null;
    }

    $src = null;
    $alt = '';
    $imgWidth = null;
    $imgHeight = null;
    $sources = [];
    $pictureClass = $pictureClass ?? $class;
    $imgClass = $imgClass ?: $class;

    if ($media) {
        $alt = $media['alt'] ?? $media['name'] ?? '';
        $crops = $media['crops'] ?? [];
        $cropsByLocation = $media['crops_by_location'] ?? [];

        $findById = function (string $id) use ($crops): ?array {
            foreach ($crops as $c) {
                if (($c['id'] ?? null) === $id) return $c;
            }
            return null;
        };

        $findByKey = function (string $key) use ($crops): ?array {
            foreach ($crops as $c) {
                if (($c['key'] ?? ($c['crop']['key'] ?? null)) === $key) return $c;
            }
            return null;
        };

        // Build <picture> sources — prefer per-breakpoint crop ids, fall back to crop_key breakpoint resolution
        $mobileCrop  = ($k = $media['mobile_crop_key']  ?? null) ? $findById($k) : null;
        $tabletCrop  = ($k = $media['tablet_crop_key']  ?? null) ? $findById($k) : null;
        $desktopCrop = ($k = $media['desktop_crop_key'] ?? null) ? $findById($k) : null;

        if (($ck = ($media['crop_key'] ?? $crop)) && (!$mobileCrop || !$tabletCrop || !$desktopCrop)) {
            if (is_array($ck)) $ck = $ck[0] ?? null;
            if ($ck) {
                $keyMatches = array_filter($crops, fn($c) => ($c['key'] ?? ($c['crop']['key'] ?? null)) === $ck);
                foreach ($keyMatches as $c) {
                    $bps = $c['breakpoints'] ?? ['mobile', 'tablet', 'desktop'];
                    if (!$mobileCrop  && in_array('mobile',  $bps, true)) $mobileCrop  = $c;
                    if (!$tabletCrop  && in_array('tablet',  $bps, true)) $tabletCrop  = $c;
                    if (!$desktopCrop && in_array('desktop', $bps, true)) $desktopCrop = $c;
                }
            }
        }

        $bpMobile = config('media.breakpoints.mobile', 767);
        $bpTablet = config('media.breakpoints.tablet', 1174);

        if ($mobileCrop)  $sources[] = ['media' => "(max-width: {$bpMobile}px)",                                    'srcset' => $mobileCrop['url']];
        if ($tabletCrop)  $sources[] = ['media' => "(min-width: " . ($bpMobile + 1) . "px) and (max-width: {$bpTablet}px)", 'srcset' => $tabletCrop['url']];
        if ($desktopCrop) $sources[] = ['media' => "(min-width: " . ($bpTablet + 1) . "px)",                        'srcset' => $desktopCrop['url']];

        // When sources are partial (not all breakpoints covered), the <img> fallback inside
        // <picture> must be the original image — not a crop — so uncovered viewports don't
        // inadvertently receive a crop meant for a different breakpoint.
        $allBreakpointsCovered = $mobileCrop && $tabletCrop && $desktopCrop;

        // Resolve default/fallback src: location first, then legacy crop key.
        // Only used when all breakpoints are covered (crop shown at every size) or no sources exist.
        $cropEntry = null;
        if ($allBreakpointsCovered || count($sources) === 0) {
            if ($location) {
                $cropsByLocation = $media['crops_by_location'] ?? [];
                $cropEntry = $cropsByLocation[$location] ?? null;
                if (!$cropEntry) {
                    foreach ($crops as $c) {
                        $cLoc = $c['location'] ?? null;
                        $cKey = $c['key'] ?? ($c['crop']['key'] ?? null);
                        if ($cLoc === $location || $cKey === $location) { $cropEntry = $c; break; }
                    }
                }
            }
            if (!$cropEntry && $crop) {
                foreach (is_array($crop) ? $crop : [$crop] as $k) {
                    if ($cropEntry = $findByKey($k)) break;
                }
            }
        }

        if ($cropEntry) {
            $src = $cropEntry['url'] ?? null;
            $imgWidth = $cropEntry['width'] ?? null;
            $imgHeight = $cropEntry['height'] ?? null;
        }

        if (!$src) {
            $src = $media['medium_url'] ?? $media['url'] ?? null;
            $imgWidth = $media['width'] ?? null;
            $imgHeight = $media['height'] ?? null;
        }
    }
@endphp

@if ($src)
    @if (count($sources) > 0)
        <picture class="{{ $pictureClass }}">
            @foreach ($sources as $source)
                <source media="{{ $source['media'] }}" srcset="{{ $source['srcset'] }}">
            @endforeach
            <img
                src="{{ $src }}"
                alt="{{ $alt }}"
                @if ($imgWidth && $imgHeight) width="{{ $imgWidth }}" height="{{ $imgHeight }}" @endif
                @if ($srcset) srcset="{{ $srcset }}" @endif
                @if ($sizes) sizes="{{ $sizes }}" @endif
                class="{{ $imgClass ?: 'w-full h-auto' }}"
            />
        </picture>
    @else
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            @if ($imgWidth && $imgHeight) width="{{ $imgWidth }}" height="{{ $imgHeight }}" @endif
            @if ($srcset) srcset="{{ $srcset }}" @endif
            @if ($sizes) sizes="{{ $sizes }}" @endif
            class="{{ $class }}"
        />
    @endif
@endif
