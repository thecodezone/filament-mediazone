# Media Picker

`MediaPicker` is a Filament form component for attaching one or more media items to a model.

![MediaPicker field](screenshot-5.png)

## Basic usage

```php
use Codezone\MediaZone\Forms\Components\MediaPicker;

MediaPicker::make('media')
    ->label('Images')
    ->multiple()
    ->relationship('media', 'name'),
```

## Single file

```php
MediaPicker::make('hero_image_id')
    ->label('Hero Image'),
```

## Options

### `multiple(bool $multiple = true)`

Allows selecting more than one file. Defaults to single-select.

### `relationship(string $relationship, string $titleAttribute)`

Wires the picker to an Eloquent relationship (e.g. a `BelongsToMany`). The selected IDs are synced automatically on save.

### `disk(string $disk)`

Restricts the picker to files on a specific disk.

### `directory(string $directory)`

Restricts browsing to a specific directory.

### `acceptedFileTypes(array $types)`

Overrides the global `config('media.accepted_file_types')` for this field.

### `maxSize(int $kilobytes)`

Maximum upload size for files selected or uploaded through this picker.

## Pivot columns

When using a `BelongsToMany` relationship with extra pivot data, declare the columns in config:

```php
// config/media.php
'picker' => [
    'pivot_columns' => ['crop_key', 'mobile_crop_key'],
],
```

These columns are passed through to `sync()` when media is attached. See [Pivot Columns](pivot-columns.md).

![MediaPicker list view with mixed media](screenshot-7.png)

## Edit link

When a media item is selected, the picker shows an edit button that links to the `MediaResource` edit page. This uses `$resource::getUrl('edit', ['record' => $id])` and works correctly in multi-tenant Filament panels.
