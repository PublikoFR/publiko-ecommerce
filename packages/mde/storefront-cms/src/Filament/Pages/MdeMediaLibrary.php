<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

class MdeMediaLibrary extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Médiathèque';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'mediatheque';

    protected static string $view = 'storefront-cms::filament.pages.mde-media-library';

    #[Url(as: 'folder')]
    public ?int $currentFolderId = null;

    #[Url(as: 'media')]
    public ?int $selectedMediaId = null;

    public ?array $uploadState = [];

    public string $editName = '';

    public ?string $editTitle = null;

    public ?string $editAlt = null;

    public bool $showCreateFolderModal = false;

    public string $newFolderName = '';

    public string $newFolderCollection = 'default';

    public function mount(): void
    {
        $this->form->fill(['files' => []]);
        if ($this->selectedMediaId) {
            $this->loadSelectedMedia();
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('files')
                ->label('')
                ->multiple()
                ->appendFiles()
                ->previewable()
                ->panelLayout('grid')
                ->imagePreviewHeight('100')
                ->maxSize(20480)
                ->maxFiles(100)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'application/pdf', 'video/mp4'])
                ->disk('local')
                ->directory('livewire-tmp')
                ->visibility('private')
                ->storeFileNamesIn('original_filenames')
                ->columnSpanFull()
                ->live()
                ->afterStateUpdated(function ($state) {
                    if (! empty($state) && $this->currentFolderId !== null) {
                        $this->saveUploads();
                    }
                }),
        ])->statePath('uploadState');
    }

    // ------------------ Folder ------------------

    public function getFoldersTreeProperty(): array
    {
        return Folder::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Folder $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'collection' => $f->collection,
                'children' => [],
            ])
            ->toArray();
    }

    public function getCurrentFolderProperty(): ?Folder
    {
        return $this->currentFolderId ? Folder::find($this->currentFolderId) : null;
    }

    public function getMediasProperty()
    {
        $folder = $this->currentFolder;
        if ($folder === null) {
            return collect();
        }

        return Media::query()
            ->where('model_type', Folder::class)
            ->where('model_id', $folder->id)
            ->orderByDesc('id')
            ->get();
    }

    public function selectFolder(?int $id): void
    {
        $this->currentFolderId = $id;
        $this->selectedMediaId = null;
    }

    public function createFolder(): void
    {
        $this->validate([
            'newFolderName' => 'required|string|max:120',
            'newFolderCollection' => 'required|string|max:120',
        ]);

        $folder = Folder::create([
            'name' => $this->newFolderName,
            'collection' => Str::slug($this->newFolderCollection),
        ]);

        $this->newFolderName = '';
        $this->newFolderCollection = 'default';
        $this->showCreateFolderModal = false;
        $this->currentFolderId = $folder->id;

        Notification::make()->success()->title('Dossier « '.$folder->name.' » créé')->send();
    }

    public function deleteFolder(int $id): void
    {
        $folder = Folder::find($id);
        if ($folder === null) {
            return;
        }
        $folder->delete();
        if ($this->currentFolderId === $id) {
            $this->currentFolderId = null;
        }
        Notification::make()->success()->title('Dossier supprimé')->send();
    }

    // ------------------ Upload ------------------

    public function saveUploads(): void
    {
        $folder = $this->currentFolder;
        if ($folder === null) {
            Notification::make()->danger()->title('Sélectionnez d\'abord un dossier')->send();

            return;
        }

        $data = $this->form->getState();
        $files = (array) ($data['files'] ?? []);
        $names = (array) ($data['original_filenames'] ?? []);

        $added = 0;
        foreach ($files as $key => $relative) {
            try {
                $absolute = Storage::disk('local')->path($relative);
                if (! is_file($absolute)) {
                    continue;
                }

                $originalName = (string) ($names[$key] ?? basename($relative));
                $base = pathinfo($originalName, PATHINFO_FILENAME);
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);

                $folder->addMedia($absolute)
                    ->usingName($base)
                    ->usingFileName(Str::slug($base).'.'.$ext)
                    ->toMediaCollection((string) ($folder->collection ?: 'default'));

                $added++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->form->fill(['files' => []]);

        if ($added > 0) {
            Notification::make()->success()->title($added.' fichier'.($added > 1 ? 's ajoutés' : ' ajouté'))->send();
        }
    }

    // ------------------ Edit ------------------

    public function openMedia(int $id): void
    {
        $this->selectedMediaId = $id;
        $this->loadSelectedMedia();
    }

    public function closeMedia(): void
    {
        $this->selectedMediaId = null;
        $this->editName = '';
        $this->editTitle = null;
        $this->editAlt = null;
    }

    protected function loadSelectedMedia(): void
    {
        $media = $this->selectedMedia;
        if ($media === null) {
            $this->closeMedia();

            return;
        }

        $this->editName = pathinfo($media->file_name, PATHINFO_FILENAME);
        $this->editTitle = (string) ($media->getCustomProperty('title') ?? $media->name);
        $this->editAlt = (string) ($media->getCustomProperty('alt') ?? '');
    }

    public function getSelectedMediaProperty(): ?Media
    {
        return $this->selectedMediaId ? Media::find($this->selectedMediaId) : null;
    }

    public function saveSelectedMedia(): void
    {
        $media = $this->selectedMedia;
        if ($media === null) {
            return;
        }

        $this->validateOnly('editName');

        $newSlug = Str::slug($this->editName ?: 'media');
        $ext = pathinfo($media->file_name, PATHINFO_EXTENSION);
        $newFileName = $newSlug.'.'.$ext;

        if ($newFileName !== $media->file_name) {
            $this->renamePhysicalFile($media, $newFileName);
        }

        $media->name = $this->editTitle ?: $newSlug;
        $media->setCustomProperty('title', $this->editTitle);
        $media->setCustomProperty('alt', $this->editAlt);
        $media->save();

        Notification::make()->success()->title('Média mis à jour')->send();
    }

    public function deleteSelectedMedia(): void
    {
        $media = $this->selectedMedia;
        if ($media === null) {
            return;
        }
        $media->delete();
        $this->closeMedia();
        Notification::make()->success()->title('Média supprimé')->send();
    }

    protected function renamePhysicalFile(Media $media, string $newFileName): void
    {
        $disk = Storage::disk($media->disk);
        $dir = $media->id;
        $oldPath = $dir.'/'.$media->file_name;
        $newPath = $dir.'/'.$newFileName;

        if ($disk->exists($oldPath)) {
            $disk->move($oldPath, $newPath);
        }

        // Rename conversions folder entries if present
        $conversionsDir = $dir.'/conversions';
        if ($disk->exists($conversionsDir)) {
            foreach ($disk->files($conversionsDir) as $file) {
                $fileName = basename($file);
                if (preg_match('/^(.+)-([a-z0-9-]+)(\.[a-z0-9]+)$/i', $fileName, $m)) {
                    $oldBase = pathinfo($media->file_name, PATHINFO_FILENAME);
                    $newBase = pathinfo($newFileName, PATHINFO_FILENAME);
                    if ($m[1] === $oldBase) {
                        $newFile = $conversionsDir.'/'.$newBase.'-'.$m[2].$m[3];
                        $disk->move($file, $newFile);
                    }
                }
            }
        }

        $media->file_name = $newFileName;
    }

    public function getConversionsListProperty(): array
    {
        $media = $this->selectedMedia;
        if ($media === null) {
            return [];
        }

        $conversions = [];
        $generated = $media->generated_conversions ?? [];
        foreach ($generated as $name => $done) {
            if (! $done) {
                continue;
            }
            try {
                $conversions[] = [
                    'name' => $name,
                    'url' => $media->getUrl($name),
                ];
            } catch (\Throwable) {
                // conversion not served
            }
        }

        return $conversions;
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'editName' => ['required', 'string', 'min:1', 'max:180'],
            'editTitle' => ['nullable', 'string', 'max:200'],
            'editAlt' => ['nullable', 'string', 'max:500'],
        ];
    }
}
