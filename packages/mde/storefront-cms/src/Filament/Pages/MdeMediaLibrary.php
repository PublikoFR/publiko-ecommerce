<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

class MdeMediaLibrary extends Page
{
    use WithFileUploads;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Médiathèque';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'mediatheque';

    protected static string $view = 'storefront-cms::filament.pages.mde-media-library';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    #[Url(as: 'folder')]
    public ?int $currentFolderId = null;

    #[Url(as: 'media')]
    public ?int $selectedMediaId = null;

    /** @var mixed Slot unique pour un upload temporaire en cours via.upload() */
    public $pendingUpload = null;

    public string $editName = '';

    public ?string $editTitle = null;

    public ?string $editAlt = null;

    public bool $showCreateFolderModal = false;

    public string $newFolderName = '';

    public string $newFolderCollection = 'default';

    /** @var array<int,int> IDs des médias cochés */
    public array $selectedMediaIds = [];

    public bool $showBulkMoveModal = false;

    public ?int $bulkMoveTargetFolderId = null;

    public function mount(): void
    {
        if ($this->currentFolderId === null) {
            $this->currentFolderId = Folder::query()->orderBy('name')->value('id');
        }

        if ($this->selectedMediaId) {
            $this->loadSelectedMedia();
        }
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
        $this->selectedMediaIds = [];
    }

    // ------------------ Bulk selection ------------------

    public function toggleMediaSelection(int $id): void
    {
        if (in_array($id, $this->selectedMediaIds, true)) {
            $this->selectedMediaIds = array_values(array_diff($this->selectedMediaIds, [$id]));
        } else {
            $this->selectedMediaIds[] = $id;
        }
    }

    public function selectAllMedias(): void
    {
        $this->selectedMediaIds = $this->medias->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    public function clearMediaSelection(): void
    {
        $this->selectedMediaIds = [];
    }

    public function bulkDeleteMedias(): void
    {
        if (empty($this->selectedMediaIds)) {
            return;
        }

        $medias = Media::query()
            ->whereIn('id', $this->selectedMediaIds)
            ->where('model_type', Folder::class)
            ->get();

        $count = 0;
        foreach ($medias as $media) {
            try {
                $media->delete();
                $count++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->selectedMediaIds = [];

        Notification::make()
            ->success()
            ->title($count.' média'.($count > 1 ? 's supprimés' : ' supprimé'))
            ->send();
    }

    public function openBulkMoveModal(): void
    {
        if (empty($this->selectedMediaIds)) {
            return;
        }
        $this->bulkMoveTargetFolderId = null;
        $this->showBulkMoveModal = true;
    }

    public function confirmBulkMove(): void
    {
        $this->validate([
            'bulkMoveTargetFolderId' => 'required|integer|exists:folders,id',
        ]);

        if ($this->bulkMoveTargetFolderId === $this->currentFolderId) {
            Notification::make()->warning()->title('Dossier identique au courant')->send();

            return;
        }

        $target = Folder::find($this->bulkMoveTargetFolderId);
        if ($target === null) {
            return;
        }

        $medias = Media::query()
            ->whereIn('id', $this->selectedMediaIds)
            ->where('model_type', Folder::class)
            ->get();

        $count = 0;
        foreach ($medias as $media) {
            $media->model_id = $target->id;
            $media->collection_name = (string) ($target->collection ?: 'default');
            $media->save();
            $count++;
        }

        $this->selectedMediaIds = [];
        $this->showBulkMoveModal = false;
        $this->bulkMoveTargetFolderId = null;

        Notification::make()
            ->success()
            ->title($count.' média'.($count > 1 ? 's déplacés' : ' déplacé').' vers « '.$target->name.' »')
            ->send();
    }

    public function getOtherFoldersProperty()
    {
        return Folder::query()
            ->when($this->currentFolderId, fn ($q) => $q->where('id', '!=', $this->currentFolderId))
            ->orderBy('name')
            ->get(['id', 'name', 'collection']);
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

        $mediaCount = Media::query()
            ->where('model_type', Folder::class)
            ->where('model_id', $folder->id)
            ->count();

        if ($mediaCount > 0) {
            Notification::make()
                ->danger()
                ->title('Dossier non vide')
                ->body("Ce dossier contient {$mediaCount} média".($mediaCount > 1 ? 's' : '').'. Supprime ou déplace les médias avant de supprimer le dossier.')
                ->send();

            return;
        }

        $folder->delete();
        if ($this->currentFolderId === $id) {
            $this->currentFolderId = null;
        }
        Notification::make()->success()->title('Dossier supprimé')->send();
    }

    // ------------------ Upload (WP-style) ------------------

    /**
     * Persiste un fichier uploadé via $wire.upload('pendingUpload', file, ...).
     * Retourne l'id du média créé (utilisé côté JS pour retirer la tuile optimiste).
     */
    public function persistPendingUpload(string $originalName): ?int
    {
        $folder = $this->currentFolder;
        if ($folder === null) {
            Notification::make()->danger()->title('Sélectionnez d\'abord un dossier')->send();

            return null;
        }

        $upload = $this->pendingUpload;
        if ($upload === null) {
            return null;
        }

        try {
            $base = pathinfo($originalName, PATHINFO_FILENAME);
            $ext = pathinfo($originalName, PATHINFO_EXTENSION)
                ?: $upload->getClientOriginalExtension();

            $added = $folder->addMedia($upload->getRealPath())
                ->usingName($base)
                ->usingFileName(Str::slug($base).'.'.$ext)
                ->toMediaCollection((string) ($folder->collection ?: 'default'));

            $this->pendingUpload = null;

            return (int) $added->id;
        } catch (\Throwable $e) {
            report($e);
            $this->pendingUpload = null;

            return null;
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
