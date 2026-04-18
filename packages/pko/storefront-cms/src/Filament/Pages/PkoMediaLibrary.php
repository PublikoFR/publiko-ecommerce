<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\CollectionResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Product;
use Pko\StorefrontCms\Filament\Resources\HomeOfferResource;
use Pko\StorefrontCms\Filament\Resources\HomeSlideResource;
use Pko\StorefrontCms\Filament\Resources\HomeTileResource;
use Pko\StorefrontCms\Filament\Resources\PageResource;
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Models\HomeOffer;
use Pko\StorefrontCms\Models\HomeSlide;
use Pko\StorefrontCms\Models\HomeTile;
use Pko\StorefrontCms\Models\Post;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

class PkoMediaLibrary extends Page
{
    use WithFileUploads;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Médiathèque';

    protected static ?string $title = 'Médiathèque';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'mediatheque';

    protected static string $view = 'storefront-cms::filament.pages.media-library';

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

    /**
     * List entities that reference the selected media via `pko_mediables`.
     *
     * @return array<int, array{label:string, title:string, url:?string, mediagroup:string}>
     */
    public function getUsagesProperty(): array
    {
        $media = $this->selectedMedia;
        if ($media === null) {
            return [];
        }

        $registry = $this->getMediableRegistry();

        $rows = DB::table('pko_mediables')
            ->where('media_id', $media->id)
            ->orderBy('mediable_type')
            ->orderBy('mediable_id')
            ->get();

        $usages = [];

        foreach ($rows as $row) {
            $type = (string) $row->mediable_type;
            $config = $registry[$type] ?? null;

            if ($config === null) {
                $usages[] = [
                    'label' => class_basename($type),
                    'title' => '#'.$row->mediable_id,
                    'url' => null,
                    'mediagroup' => (string) $row->mediagroup,
                ];

                continue;
            }

            /** @var Model|null $record */
            $record = $type::query()->find($row->mediable_id);

            if ($record === null) {
                continue;
            }

            $titleResolver = $config['title'];
            $title = is_callable($titleResolver)
                ? (string) ($titleResolver($record) ?: '#'.$record->getKey())
                : (string) ($record->{$titleResolver} ?? '#'.$record->getKey());

            $url = null;
            $resourceClass = $config['resource'] ?? null;
            if ($resourceClass && class_exists($resourceClass) && method_exists($resourceClass, 'getUrl')) {
                try {
                    $url = $resourceClass::getUrl('edit', ['record' => $record]);
                } catch (\Throwable) {
                    $url = null;
                }
            }

            $usages[] = [
                'label' => $config['label'],
                'title' => $title,
                'url' => $url,
                'mediagroup' => (string) $row->mediagroup,
            ];
        }

        return $usages;
    }

    /**
     * Map of known mediable_type → display config.
     *
     * @return array<class-string, array{label:string, title:string|callable, resource:?class-string}>
     */
    protected function getMediableRegistry(): array
    {
        return [
            Product::class => [
                'label' => 'Produit',
                'title' => fn (Model $r) => $r->translateAttribute('name'),
                'resource' => ProductResource::class,
            ],
            Collection::class => [
                'label' => 'Collection',
                'title' => fn (Model $r) => $r->translateAttribute('name'),
                'resource' => CollectionResource::class,
            ],
            Brand::class => [
                'label' => 'Marque',
                'title' => 'name',
                'resource' => BrandResource::class,
            ],
            Post::class => [
                'label' => 'Actualité',
                'title' => 'title',
                'resource' => PostResource::class,
            ],
            \Pko\StorefrontCms\Models\Page::class => [
                'label' => 'Page CMS',
                'title' => 'title',
                'resource' => PageResource::class,
            ],
            HomeSlide::class => [
                'label' => 'Slide accueil',
                'title' => 'title',
                'resource' => HomeSlideResource::class,
            ],
            HomeTile::class => [
                'label' => 'Tuile accueil',
                'title' => 'title',
                'resource' => HomeTileResource::class,
            ],
            HomeOffer::class => [
                'label' => 'Offre du moment',
                'title' => 'title',
                'resource' => HomeOfferResource::class,
            ],
        ];
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
