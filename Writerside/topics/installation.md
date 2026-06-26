# Installation

## Require the package

```bash
composer require codezone/filament-mediazone
```

## Publish the config

```bash
php artisan vendor:publish --tag=mediazone-config
```

This creates `config/media.php` in your application. See [Configuration](configuration.md) for all available options.

## Publish and run the migration

```bash
php artisan vendor:publish --tag=mediazone-migrations
php artisan migrate
```

The migration creates a `media` table. The table name is derived from your configured model's `getTable()` method — override `$table` on a custom model to rename it.

## Publish assets

```bash
php artisan filament:assets
```

This copies CSS and JavaScript to `public/css/codezone/filament-mediazone/`. Run this once after install, and again after upgrading the package.

## Register the plugin

Add `MediaZonePlugin` to your Filament panel provider:

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

See [Plugin](plugin.md) for plugin configuration options.

<note>
If you are using a custom Media model, set <code>config('media.model')</code> before running migrations so the table name resolves correctly.
</note>

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>

<tip>
Filament MediaZone is open source. <a href="https://github.com/thecodezone/filament-mediazone">View the repository on GitHub</a> to report issues, contribute, or browse the source.
</tip>
