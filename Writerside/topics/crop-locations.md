# Crop Locations

A crop location represents a named placement in your frontend — a hero banner, a product thumbnail, a social share image — where a specific crop will be used. Selecting a location in the cropper auto-populates the crop key and links to a preset.

## Creating a location

Extend `Codezone\MediaZone\Media\MediaLocation` and implement the required properties:

```php
namespace App\Media\Locations;

use Codezone\MediaZone\Media\MediaLocation;
use App\Media\Crops\HeroImagePreset;

class HeroImageLocation extends MediaLocation
{
    public string $key = 'hero';
    public string $label = 'Hero Banner';
    public string $preset = HeroImagePreset::class;
    public array $breakpoints = ['desktop', 'tablet'];
    public array $guideLines = [];
}
```

## Registering locations

Add the class to `config/media.php`:

```php
'crop_locations' => [
    \App\Media\Locations\HeroImageLocation::class,
    \App\Media\Locations\MobileCarouselLocation::class,
],
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$key` | string | Unique crop key — matches the key stored in `crops` JSON |
| `$label` | string | Human-readable name shown in the location dropdown |
| `$preset` | string | Fully-qualified preset class to apply when this location is selected |
| `$breakpoints` | array | Default breakpoints pre-selected when location is chosen |
| `$guideLines` | array | Optional guide line overlays (see below) |

## Guide lines

Guide lines are visual overlays on the crop canvas that mark safe zones or bleed areas. Each line is an array with `axis` (`x` or `y`) and `position` (a fraction of the image dimension, 0–1):

```php
public array $guideLines = [
    ['axis' => 'x', 'position' => 0.1],  // 10% from left
    ['axis' => 'x', 'position' => 0.9],  // 10% from right
    ['axis' => 'y', 'position' => 0.2],  // 20% from top
];
```

Guide lines are rendered as coloured overlays on the canvas and do not affect the saved crop data.

## Using crops in templates

Retrieve a crop URL for a specific location key:

```php
$media->getCropUrl('hero');
// https://example.com/media/glide/123?w=1680&h=945&q=90&fm=webp&...
```

Check whether a crop exists for a location:

```php
if ($media->hasCrop('hero')) {
    // render the crop
}
```

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>
