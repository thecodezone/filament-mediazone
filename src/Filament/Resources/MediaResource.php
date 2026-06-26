<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Filament\Resources;

use Codezone\MediaZone\Filament\Resources\MediaResource\Pages;
use Codezone\MediaZone\Media\CropPreset;
use Codezone\MediaZone\Media\MediaLocation;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function getModel(): string
    {
        return config('media.model', \Codezone\MediaZone\Models\Media::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('media.resources.navigation_group', 'Content');
    }

    public static function getNavigationLabel(): string
    {
        return config('media.resources.navigation_label', 'Media');
    }

    public static function getModelLabel(): string
    {
        return config('media.resources.label', 'Media');
    }

    public static function getPluralModelLabel(): string
    {
        return config('media.resources.plural_label', 'Media');
    }

    public static function getNavigationSort(): ?int
    {
        return config('media.resources.navigation_sort', 3);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('media.should_register_navigation', true);
    }

    public static function form(Form $form): Form
    {
        $model = static::getModel();

        return $form->schema([

            Forms\Components\Section::make('File')
                ->schema([
                    Forms\Components\FileUpload::make('file')
                        ->label('Upload')
                        ->disk(config('media.disk', 'media'))
                        ->preserveFilenames(config('media.should_preserve_filenames', true))
                        ->maxSize(config('media.max_size', 102400))
                        ->acceptedFileTypes(config('media.accepted_file_types', []))
                        ->columnSpanFull()
                        ->hiddenOn('edit'),

                    Forms\Components\View::make('mediazone::media.forms.edit-preview')
                        ->hiddenOn('create'),

                    Forms\Components\FileUpload::make('replace_file')
                        ->label('Replace file')
                        ->disk(config('media.disk', 'media'))
                        ->preserveFilenames(config('media.should_preserve_filenames', true))
                        ->maxSize(config('media.max_size', 102400))
                        ->acceptedFileTypes(config('media.accepted_file_types', []))
                        ->hiddenOn('create')
                        ->hidden(fn ($get) => ! $get('_replace')),

                    Forms\Components\Hidden::make('_replace'),
                ]),

            Forms\Components\Section::make('Metadata')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Filename')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Filename without extension. Renaming will move the file on storage.')
                        ->hiddenOn('create'),

                    Forms\Components\TextInput::make('title')
                        ->label('Title')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('alt')
                        ->label('Alt Text')
                        ->maxLength(255)
                        ->helperText('Describes the image for accessibility and SEO.'),

                    Forms\Components\Textarea::make('caption')
                        ->label('Caption')
                        ->rows(2)
                        ->maxLength(500),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->columns(2),

            Forms\Components\Section::make('Crops')
                ->description('Saved crops of this image.')
                ->visible(fn ($record) => $record?->isCroppableImage())
                ->headerActions([
                    Forms\Components\Actions\Action::make('add_crop')
                        ->label('New Crop')
                        ->icon('heroicon-s-plus-circle')
                        ->iconButton()
                        ->color('warning')
                        ->size(\Filament\Support\Enums\ActionSize::ExtraLarge)
                        ->tooltip('New Crop')
                        ->visible(fn ($record) => $record?->isCroppableImage())
                        ->modalContent(fn ($record) => $record ? view('mediazone::media.actions.crop-action', static::cropActionViewData($record)) : null)
                        ->modalHeading('New Crop')
                        ->modalSubmitAction(false)
                        ->modalCancelAction(fn (StaticAction $action) => $action->label('Close'))
                        ->extraModalFooterActions(fn (): array => [
                            StaticAction::make('save_crop')
                                ->button()
                                ->label('Save crop')
                                ->color('primary')
                                ->alpineClickHandler("\$dispatch('mz-cropper__save')")
                                ->extraAttributes(['data-mz-cropper__save-btn' => 'true']),
                        ])
                        ->slideOver()
                        ->modalWidth('screen')
                        ->extraModalWindowAttributes(['style' => 'overflow:hidden;display:flex;flex-direction:column;']),
                ])
                ->schema([
                    Forms\Components\Placeholder::make('crops_list')
                        ->key('crops_list')
                        ->label('')
                        ->content(function ($record) {
                            if (! $record || empty($record->crops)) {
                                return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-400">No crops yet.</p>');
                            }

                            return view('mediazone::media.partials.crops-list', ['crops' => $record->crops]);
                        })
                        ->registerActions([
                            Forms\Components\Actions\Action::make('edit_crop')
                                ->label('Edit Crop')
                                ->modalHeading('Edit Crop')
                                ->modalSubmitActionLabel('Save')
                                ->fillForm(function (array $arguments, $record): array {
                                    $cropId = $arguments['id'] ?? null;
                                    $crop = $cropId ? collect($record?->crops ?? [])->first(fn ($c) => ($c['id'] ?? null) === $cropId) : null;

                                    return [
                                        'key' => $crop['key'] ?? '',
                                        'location' => $crop['location'] ?? '',
                                        'breakpoints' => $crop['breakpoints'] ?? [],
                                    ];
                                })
                                ->form(function (): array {
                                    $locationOptions = MediaLocation::allAsOptions();

                                    $fields = [];

                                    if (count($locationOptions) > 1) {
                                        $fields[] = Forms\Components\Select::make('location')
                                            ->label('Location')
                                            ->options($locationOptions)
                                            ->live();
                                        $fields[] = Forms\Components\TextInput::make('key')
                                            ->label('Key')
                                            ->hidden(fn (Forms\Get $get) => (bool) $get('location'))
                                            ->placeholder('my_custom_crop');
                                    } else {
                                        $fields[] = Forms\Components\TextInput::make('key')
                                            ->label('Key')
                                            ->required();
                                    }

                                    $fields[] = Forms\Components\CheckboxList::make('breakpoints')
                                        ->label('Breakpoints')
                                        ->options([
                                            'mobile' => 'Mobile',
                                            'tablet' => 'Tablet',
                                            'desktop' => 'Desktop',
                                        ])
                                        ->columns(3);

                                    return $fields;
                                })
                                ->action(function (array $arguments, array $data, \Livewire\Component $livewire): void {
                                    $cropId = $arguments['id'] ?? null;
                                    if ($cropId) {
                                        $livewire->updateCrop($cropId, $data);
                                    }
                                }),
                        ]),
                ])
                ->hiddenOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $model = static::getModel();

        return $table
            ->deferLoading()
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->width(72)
                    ->height(72)
                    ->defaultImageUrl(null)
                    ->extraImgAttributes(['class' => 'object-contain rounded'])
                    ->getStateUsing(fn ($r) => $r->thumbnail_url),

                Tables\Columns\TextColumn::make('pretty_name')
                    ->label('Name')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('alt', 'like', "%{$search}%")
                    ))
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('name', $direction))
                    ->description(fn ($r) => $r->alt ?? '')
                    ->wrap(),

                Tables\Columns\TextColumn::make('ext')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => match (strtolower($state)) {
                        'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg' => 'success',
                        'mp4', 'mov', 'webm' => 'info',
                        'pdf' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('size_for_humans')
                    ->label('Size')
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('size', $direction)),

                Tables\Columns\TextColumn::make('width')
                    ->label('Dimensions')
                    ->formatStateUsing(fn ($r) => $r->width && $r->height
                        ? "{$r->width} × {$r->height}"
                        : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('directory')
                    ->label('Folder')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('disk')
                    ->label('Disk')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('ext')
                    ->label('Type')
                    ->options(fn () => $model::distinct()
                        ->orderBy('ext')
                        ->pluck('ext', 'ext')
                        ->filter()
                        ->toArray()),

                Tables\Filters\SelectFilter::make('directory')
                    ->label('Folder')
                    ->options(fn () => $model::distinct()
                        ->orderBy('directory')
                        ->pluck('directory', 'directory')
                        ->filter()
                        ->toArray()),

                Tables\Filters\SelectFilter::make('disk')
                    ->label('Disk')
                    ->options(fn () => $model::distinct()->pluck('disk', 'disk')->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('crop')
                    ->label('Crop')
                    ->icon('heroicon-o-scissors')
                    ->color('gray')
                    ->visible(fn ($r) => $r->isCroppableImage())
                    ->modalContent(fn ($r) => view('mediazone::media.actions.crop-action', static::cropActionViewData($r)))
                    ->modalHeading('Crop Image')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->slideOver()
                    ->modalWidth('screen'),

                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn ($r) => $r->url)
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::can('update', $record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function cropActionViewData($media): array
    {
        return [
            'statePath' => 'crops',
            'modalId' => 'crop-'.$media->id,
            'media' => $media->toArray(),
            'presets' => CropPreset::allAsArray(),
            'formats' => config('media.crop_formats', ['webp', 'jpg', 'png']),
        ];
    }
}
