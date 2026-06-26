# Custom Model

You can replace the default `Media` model with your own to add relationships, columns, scopes, or observer hooks specific to your application.

## Create your model

Extend the package base model:

```php
namespace App\Models;

use Codezone\MediaZone\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    // Add a custom relationship
    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
```

## Register it

Point the config at your model:

```php
// config/media.php
'model' => \App\Models\Media::class,
```

## Custom table name

Override `$table` on your model. The migration uses `app(config('media.model'))->getTable()`, so it will automatically create the correct table name:

```php
class Media extends BaseMedia
{
    protected $table = 'site_media';
}
```

<note>
Set <code>config('media.model')</code> before running migrations so the table name resolves correctly.
</note>

## Adding pivot data to `toMediaArray()`

When media is attached via a `BelongsToMany` pivot, you may need extra pivot columns in the serialised array returned to the picker. Override `pivotMediaArray()`:

```php
protected function pivotMediaArray(): array
{
    if (! $this->pivot) {
        return [];
    }

    return [
        'crop_key'        => $this->pivot->crop_key ?? null,
        'mobile_crop_key' => $this->pivot->mobile_crop_key ?? null,
    ];
}
```

See [Pivot Columns](pivot-columns.md) for the full setup.

## Custom observer logic

The package registers `MediaObserver` automatically. To add your own observer behaviour without conflicting, register a second observer in your `AppServiceProvider`:

```php
use App\Models\Media;
use App\Observers\AppMediaObserver;

public function boot(): void
{
    Media::observe(AppMediaObserver::class);
}
```

Both observers will fire — the package observer handles URL generation and cache invalidation; yours handles application-specific logic.

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>

<tip>
Filament MediaZone is open source. <a href="https://github.com/thecodezone/filament-mediazone">View the repository on GitHub</a> to report issues, contribute, or browse the source.
</tip>
