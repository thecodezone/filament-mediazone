# Media Model

The `Codezone\MediaZone\Models\Media` Eloquent model is the core of the package. It stores all uploaded file metadata and crop data.

## Table columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `disk` | string | Laravel filesystem disk name |
| `directory` | string | Folder path within the disk |
| `visibility` | string | `public` or `private` |
| `name` | string | Original filename without extension |
| `slug` | string | URL-safe unique slug |
| `path` | string | Full path on disk |
| `file` | string | Filename with extension |
| `width` | integer | Image width in pixels |
| `height` | integer | Image height in pixels |
| `size` | bigint | File size in bytes |
| `type` | string | MIME type category (e.g. `image`, `video`) |
| `ext` | string | File extension |
| `alt` | string | Alt text |
| `title` | string | Title |
| `description` | text | Long description |
| `caption` | text | Caption |
| `exif` | json | Raw EXIF metadata |
| `crops` | json | Saved crop data keyed by crop key |
| `url` | string | Public URL (generated on save) |

## Key accessors

**`thumbnail_url`** — returns a Glide-transformed thumbnail URL suitable for use in `<img>` tags or CSS `background-image`.

**`size_for_humans`** — formats `size` as a human-readable string (e.g. `1.4 MB`).

**`pretty_name`** — the filename without extension, with underscores and hyphens replaced by spaces.

## `toMediaArray()`

Returns a normalised array representation of the media item, used by the picker and form field when serialising selected media:

```php
$media->toMediaArray();
// [
//   'id'     => 1,
//   'url'    => 'https://...',
//   'name'   => 'hero-image',
//   'ext'    => 'webp',
//   'width'  => 1920,
//   'height' => 1080,
//   'crops'  => [...],
//   ...
// ]
```

To add application-specific keys (e.g. pivot columns), override `pivotMediaArray()` in your custom model:

```php
protected function pivotMediaArray(): array
{
    if (! $this->pivot) {
        return [];
    }
    return [
        'crop_key' => $this->pivot->crop_key ?? null,
    ];
}
```

See [Pivot Columns](pivot-columns.md) for the full pattern.

## Scopes

**`scopeSearch($query, $term)`** — searches `name`, `title`, and `alt` columns.

## URL generation

`generateUrl()` is called automatically by `MediaObserver` on save. It constructs the public URL from the disk, path, and configured Glide route. For cloud disks listed in `config('media.cloud_disks')`, the original storage URL is used directly — Glide is bypassed.
