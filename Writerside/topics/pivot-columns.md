# Pivot Columns

When media is attached to a model via a `BelongsToMany` relationship, you can store and retrieve extra data on the pivot table — for example, which crop key to use at each breakpoint.

## 1. Add columns to the pivot migration

```php
Schema::create('model_media', function (Blueprint $table) {
    $table->foreignId('model_id')->constrained()->cascadeOnDelete();
    $table->foreignId('media_id')->constrained()->cascadeOnDelete();
    $table->string('crop_key')->nullable();
    $table->string('mobile_crop_key')->nullable();
    $table->timestamps();
});
```

## 2. Declare the pivot columns in config

```php
// config/media.php
'picker' => [
    'pivot_columns' => ['crop_key', 'mobile_crop_key'],
],
```

The picker's sync logic reads this list and includes those keys when calling `sync()` on the relationship.

## 3. Expose the pivot data in `toMediaArray()`

Override `pivotMediaArray()` in your custom model so the picker can read them back when dehydrating selected media:

```php
// app/Models/Media.php
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

## 4. Declare `withPivot` on the relationship

```php
// app/Models/Page.php
public function media()
{
    return $this->belongsToMany(Media::class)
        ->withPivot('crop_key', 'mobile_crop_key')
        ->withTimestamps();
}
```

<tip>
The base <code>pivotMediaArray()</code> returns an empty array, so if you don't override it nothing breaks — pivot columns are simply absent from the serialised data.
</tip>
