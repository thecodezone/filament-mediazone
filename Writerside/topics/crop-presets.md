# Crop Presets

A crop preset defines a reusable output configuration — aspect ratio, output dimensions, format, and quality — that can be selected in the cropper sidebar.

## Creating a preset

Extend `Codezone\MediaZone\Media\CropPreset` and implement the required properties:

```php
namespace App\Media\Crops;

use Codezone\MediaZone\Media\CropPreset;

class HeroImagePreset extends CropPreset
{
    public string $key = 'hero';
    public string $label = 'Hero Image';
    public float $aspectRatio = 16 / 9;
    public int $targetWidth = 1680;
    public int $targetHeight = 945;
    public string $format = 'webp';
    public int $quality = 90;
}
```

## Registering presets

Add the class to `config/media.php`:

```php
'crop_presets' => [
    \App\Media\Crops\HeroImagePreset::class,
    \App\Media\Crops\ThumbnailPreset::class,
],
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$key` | string | Unique identifier used as the crop key |
| `$label` | string | Human-readable name shown in the preset dropdown |
| `$aspectRatio` | float | Aspect ratio to lock (e.g. `16/9`). Use `NAN` for free. |
| `$targetWidth` | int | Output width in pixels (0 = derive from crop) |
| `$targetHeight` | int | Output height in pixels (0 = derive from crop) |
| `$format` | string | Output format: `webp`, `jpg`, `png`, `avif` |
| `$quality` | int | Compression quality 1–100 |

<tip>
Presets and locations work well together. A location can reference a preset to auto-configure the cropper when that location is selected. See <a href="crop-locations.md">Crop Locations</a>.
</tip>

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>
