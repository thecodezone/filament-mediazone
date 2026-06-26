# Tenant-Aware Setup

MediaZone works with Filament's built-in multi-tenancy. The key requirement is that all edit URLs are generated using Filament's resource URL builder rather than Laravel's `route()` helper, so the current tenant context is included automatically.

## How edit URLs are resolved

`getEditUrl()` in both `MediaListing` and `ListMedia` uses:

```php
$resource::getUrl('edit', ['record' => $id]);
```

Filament's `getUrl()` reads the current tenant from the panel context and injects it into the route parameters automatically. You do not need to pass the tenant manually.

## Scoping media to a tenant

The package does not add tenant scoping to the `Media` model by default. To scope the media library to the current tenant, override `getMediaModel()` in a custom subclass of `ListMedia` or add a global scope to your custom model.

### Global scope example

```php
// app/Models/Media.php
protected static function booted(): void
{
    static::addGlobalScope('tenant', function (Builder $query) {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $query->where('site_id', $tenant->id);
        }
    });
}
```

### Assigning tenant on create

To automatically assign the current tenant when a media record is created:

```php
protected static function booted(): void
{
    static::creating(function (Media $media) {
        if (! $media->site_id) {
            $media->site_id = Filament::getTenant()?->id;
        }
    });
}
```

## Tenant-aware resource

If you have registered the `MediaResource` under a tenanted panel, the resource's navigation and URLs will include the tenant slug automatically — no extra configuration is required.

<warning>
Do not use Laravel's <code>route()</code> helper to build media edit URLs manually. It does not know about the current Filament tenant and will throw a missing-parameter error. Always use <code>$resource::getUrl()</code>.
</warning>

<seealso>
    <category ref="support">
        <a href="https://www.paypal.com/donate/?hosted_button_id=T2TCWZXD7J97E">Support Filament MediaZone development</a>
    </category>
</seealso>
