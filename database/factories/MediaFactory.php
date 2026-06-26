<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Database\Factories;

use Codezone\MediaZone\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $name = $this->faker->slug(2);
        $ext = $this->faker->randomElement(['jpg', 'png', 'webp']);
        $directory = 'uploads';

        return [
            'disk' => 'media',
            'directory' => $directory,
            'visibility' => 'public',
            'name' => $name,
            'path' => "{$directory}/{$name}.{$ext}",
            'url' => "https://example.com/media/{$directory}/{$name}.{$ext}",
            'width' => $this->faker->numberBetween(100, 4000),
            'height' => $this->faker->numberBetween(100, 4000),
            'size' => $this->faker->numberBetween(1024, 10485760),
            'type' => "image/{$ext}",
            'ext' => $ext,
            'alt' => null,
            'title' => null,
            'description' => null,
            'caption' => null,
            'exif' => null,
            'crops' => [],
        ];
    }
}
