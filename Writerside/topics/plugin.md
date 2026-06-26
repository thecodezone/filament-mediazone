# Plugin

`MediaZonePlugin` is the Filament plugin class that registers the media resource, assets, and Livewire components with your panel.

## Registration

```php
use Codezone\MediaZone\MediaZonePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            MediaZonePlugin::make(),
        ]);
}
```

## What the plugin registers

- The `MediaResource` Filament resource (media listing and edit pages)
- The `MediaPickerPanel` and `MediaCropperPanel` Livewire components
- CSS and JavaScript assets (cropper.js, picker styles, listing styles, cropper styles)

## Navigation

Navigation label, group, icon, and sort order are all driven by `config('media.resources')`. Override them in your published config:

```php
'resources' => [
    'navigation_group' => 'Assets',
    'navigation_icon'  => 'heroicon-o-film',
    'navigation_sort'  => 10,
],
```

To hide the resource from navigation entirely:

```php
'should_register_navigation' => false,
```

## Custom resource class

To replace the `MediaResource` with your own (e.g. to add custom columns or actions to the edit form), set:

```php
'resources' => [
    'resource' => \App\Filament\Resources\MediaResource::class,
],
```

Your class should extend `Codezone\MediaZone\Filament\Resources\MediaResource`.

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>

<tip>
Filament MediaZone is open source. <a href="https://github.com/thecodezone/filament-mediazone">View the repository on GitHub</a> to report issues, contribute, or browse the source.
</tip>
