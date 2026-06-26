# Filament MediaZone

A full-featured media manager for [Laravel Filament](https://filamentphp.com/). Upload, browse, crop, and serve images and files from any Laravel disk — all within your Filament admin panel.

![MediaZone](cz-lines-orange-dark.svg){ width=100 }

by [CodeZone](https://codezone.io)

![Media library](screenshot-1.png){ thumbnail="true" }
![List view](screenshot-2.png){ thumbnail="true" }
![Cropper](screenshot-3.png){ thumbnail="true" }
![Saved crops](screenshot-4.png){ thumbnail="true" }
![MediaPicker field](screenshot-5.png){ thumbnail="true" }
![Item actions](screenshot-6.png){ thumbnail="true" }
![MediaPicker list](screenshot-7.png){ thumbnail="true" }

## Features

- **Media library**: Grid and list browsing with search, folder, and type filters.
- **File uploads**: Drag-and-drop or click-to-upload with configurable accepted types and size limits.
- **Image cropping**: Interactive cropper with presets, locations, aspect ratio, format, and quality controls.
- **Glide integration**: On-the-fly image transformations served via [League Glide](https://glide.thephpleague.com/).
- **Filament form field**: Drop-in `MediaPicker` component for single or multi-select media on any Filament form.
- **Configurable model**: Bring your own `Media` model — the package derives the table name from it automatically.
- **Tenant-aware**: Works with Filament's multi-tenancy; edit URLs resolve with the correct tenant context.

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament 3.2+

## Installation

See [Installation](installation.md) for setup instructions.
