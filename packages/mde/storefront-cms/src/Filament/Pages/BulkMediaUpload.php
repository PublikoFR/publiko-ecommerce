<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TomatoPHP\FilamentMediaManager\Models\Folder;

class BulkMediaUpload extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Upload massif médias';

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'mde-bulk-media-upload';

    protected static string $view = 'storefront-cms::filament.pages.bulk-media-upload';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'folder_id' => null,
            'optimize' => true,
            'files' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Destination')->schema([
                Select::make('folder_id')
                    ->label('Dossier de destination')
                    ->options(fn () => Folder::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->helperText('Les fichiers seront rangés dans ce dossier. Créez-en un depuis « Medias » si besoin.'),
                Toggle::make('optimize')
                    ->label('Convertir en WebP automatiquement (si image)')
                    ->default(true)
                    ->disabled()
                    ->helperText('Géré par Spatie MediaLibrary côté Lunar — conversions appliquées à l\'upload.'),
            ]),
            Section::make('Fichiers à uploader')->schema([
                FileUpload::make('files')
                    ->label('')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->previewable()
                    ->openable()
                    ->downloadable()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('120')
                    ->uploadingMessage('Upload en cours…')
                    ->maxSize(20480)
                    ->maxFiles(50)
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                        'image/svg+xml',
                        'application/pdf',
                        'video/mp4',
                    ])
                    ->disk('local')
                    ->directory('livewire-tmp')
                    ->visibility('private')
                    ->storeFileNamesIn('original_filenames')
                    ->columnSpanFull()
                    ->required()
                    ->hint('Glissez-déposez ou cliquez pour sélectionner. Max 50 fichiers · 20 Mo chacun.')
                    ->hintIcon('heroicon-m-information-circle'),
                Placeholder::make('hint')
                    ->label('')
                    ->content('Formats acceptés : JPG, PNG, WebP, GIF, SVG, PDF, MP4. Les noms originaux sont conservés.'),
            ]),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        /** @var Folder|null $folder */
        $folder = Folder::query()->find($data['folder_id'] ?? null);
        if ($folder === null) {
            Notification::make()->danger()->title('Dossier introuvable')->send();

            return;
        }

        $files = (array) ($data['files'] ?? []);
        $names = (array) ($data['original_filenames'] ?? []);

        $added = 0;
        $errors = [];

        foreach ($files as $key => $relative) {
            try {
                $absolute = Storage::disk('local')->path($relative);
                if (! is_file($absolute)) {
                    $errors[] = (string) ($names[$key] ?? $relative);

                    continue;
                }

                $originalName = $names[$key] ?? basename($relative);
                $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);

                $folder
                    ->addMedia($absolute)
                    ->usingName($nameWithoutExt)
                    ->usingFileName(Str::slug($nameWithoutExt).'.'.pathinfo($originalName, PATHINFO_EXTENSION))
                    ->toMediaCollection((string) ($folder->collection ?: 'default'));

                $added++;
            } catch (\Throwable $e) {
                $errors[] = (string) ($names[$key] ?? $relative).' — '.$e->getMessage();
            }
        }

        // Reset form
        $this->form->fill([
            'folder_id' => $data['folder_id'],
            'optimize' => true,
            'files' => [],
        ]);

        if ($added > 0) {
            Notification::make()
                ->success()
                ->title($added.' fichier'.($added > 1 ? 's ajoutés' : ' ajouté').' dans « '.$folder->name.' »')
                ->send();
        }

        if ($errors !== []) {
            Notification::make()
                ->warning()
                ->title(count($errors).' erreur'.(count($errors) > 1 ? 's' : '').' d\'upload')
                ->body(implode("\n", array_slice($errors, 0, 5)).(count($errors) > 5 ? "\n… et autres" : ''))
                ->persistent()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
