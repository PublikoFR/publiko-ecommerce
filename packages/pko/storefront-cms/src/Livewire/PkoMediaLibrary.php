<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
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
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Models\HomeOffer;
use Pko\StorefrontCms\Models\HomeSlide;
use Pko\StorefrontCms\Models\HomeTile;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\Setting;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

/**
 * Composant unifié médiathèque — gère à la fois :
 *  - le mode page (browse + bulk + CRUD dossiers + drawer édition slide-over),
 *  - le mode picker modale (sélection single/multiple, déclenché par l'event `open-media-picker-modal`).
 *
 * Le mode est piloté par la prop `pickerMode` : null = page, 'single' | 'multiple' = picker.
 */
class PkoMediaLibrary extends Component
{
    use WithFileUploads;

    // ------------------ Mode & picker contract ------------------

    /** null = page mode ; 'single' | 'multiple' = picker modal. */
    public ?string $pickerMode = null;

    public bool $open = false;

    public string $statePath = '';

    public string $mediagroup = 'default';

    /** @var array<int,int> */
    public array $preselected = [];

    // ------------------ Selection & focus ------------------

    /** Cases cochées (bulk page OU sélection picker). */
    /** @var array<int,int> */
    public array $selectedMediaIds = [];

    /** Média ciblé par le drawer d'édition. */
    public ?int $selectedMediaId = null;

    // ------------------ Browse state ------------------

    public ?int $currentFolderId = null;

    public string $search = '';

    // ------------------ Upload ------------------

    /** Slot pour un upload temporaire via $wire.upload('pendingUpload', file, ...) */
    public mixed $pendingUpload = null;

    // ------------------ URL import ------------------

    public string $importUrl = '';

    public string $importName = '';

    public ?string $importError = null;

    // ------------------ Edit form ------------------

    public string $editName = '';

    public ?string $editTitle = null;

    public ?string $editAlt = null;

    // ------------------ Folder CRUD (page) ------------------

    public bool $showCreateFolderModal = false;

    public string $newFolderName = '';

    public string $newFolderCollection = 'default';

    // ------------------ Bulk move (page) ------------------

    public bool $showBulkMoveModal = false;

    public ?int $bulkMoveTargetFolderId = null;

    // ------------------ Lifecycle ------------------

    /**
     * Une seule instance porte le layout complet de la page médiathèque (`asPage=true`).
     * Les autres instances (montées globalement via render hook) démarrent en mode latent
     * et ne s'affichent que lorsque l'event `open-media-picker-modal` les active.
     */
    public bool $asPage = false;

    public function mount(bool $asPage = false): void
    {
        $this->asPage = $asPage;

        if ($asPage && $this->currentFolderId === null) {
            $this->currentFolderId = $this->resolveDefaultFolderId() ?? Folder::query()->orderBy('id')->value('id');
        }
    }

    /**
     * ID du dossier marqué comme « par défaut » — setting `media.default_folder_id` (int) ou,
     * à défaut, le dossier dont le slug `collection` vaut `products` (bootstrap historique).
     */
    public function getDefaultFolderIdProperty(): ?int
    {
        return $this->resolveDefaultFolderId();
    }

    protected function resolveDefaultFolderId(): ?int
    {
        $configured = Setting::get('media.default_folder_id');
        if (is_numeric($configured)) {
            $exists = Folder::query()->whereKey((int) $configured)->exists();
            if ($exists) {
                return (int) $configured;
            }
        }

        $fallback = Folder::query()->where('collection', 'products')->value('id');

        return $fallback ? (int) $fallback : null;
    }

    public function setDefaultFolder(int $id): void
    {
        if (! Folder::query()->whereKey($id)->exists()) {
            return;
        }

        Setting::set('media.default_folder_id', $id);
        Notification::make()->success()->title('Dossier par défaut mis à jour')->send();
    }

    public function isPicker(): bool
    {
        return $this->pickerMode !== null;
    }

    public function isMultiple(): bool
    {
        return $this->pickerMode === 'multiple';
    }

    // ------------------ Picker open / close / confirm ------------------

    #[On('open-media-picker-modal')]
    public function openModal(string $statePath, bool $multiple = false, array $preselected = [], string $mediagroup = 'default', ?string $folder = null): void
    {
        $this->statePath = $statePath;
        $this->pickerMode = $multiple ? 'multiple' : 'single';
        $this->mediagroup = $mediagroup;
        $this->preselected = array_map('intval', $preselected);
        $this->selectedMediaIds = $this->preselected;
        $this->search = '';

        // Priorité : slug explicite passé par le dispatcher > dossier par défaut configuré > premier dossier.
        $resolved = null;
        if ($folder) {
            $resolved = Folder::query()->where('collection', $folder)->value('id');
        }
        $resolved ??= $this->resolveDefaultFolderId();
        $resolved ??= Folder::query()->orderBy('id')->value('id');
        $this->currentFolderId = $resolved ? (int) $resolved : null;

        $this->open = true;
        $this->dispatch('media-picker-opened');
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->selectedMediaIds = [];
        $this->statePath = '';
        $this->pendingUpload = null;
        $this->closeMedia();
        $this->pickerMode = null;
        $this->dispatch('media-picker-closed');
    }

    public function confirm(): void
    {
        if (! $this->statePath) {
            $this->closeModal();

            return;
        }

        $ids = array_values(array_map('intval', $this->selectedMediaIds));

        $medias = Media::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Media $m) => [
                'id' => (int) $m->id,
                'url' => $m->getUrl(),
                'alt' => (string) ($m->getCustomProperty('alt') ?? ''),
                'fileName' => $m->file_name,
            ])
            ->keyBy('id')
            ->all();

        // Préserver l'ordre de sélection
        $orderedMedias = array_map(
            fn ($id) => $medias[$id] ?? ['id' => $id, 'url' => null, 'alt' => '', 'fileName' => '#'.$id],
            $ids
        );

        $this->dispatch('media-picked', statePath: $this->statePath, ids: $ids, medias: $orderedMedias);
        $this->closeModal();
    }

    // ------------------ Folders ------------------

    public function getFoldersProperty()
    {
        $defaultId = $this->resolveDefaultFolderId();

        // Ordre de création : les dossiers les plus anciens d'abord.
        $folders = Folder::query()->orderBy('id')->get(['id', 'name', 'collection']);

        // Épingle le dossier par défaut en tête de liste.
        if ($defaultId !== null) {
            $folders = $folders->sortByDesc(fn (Folder $f) => $f->id === $defaultId ? 1 : 0)->values();
        }

        return $folders;
    }

    public function getCurrentFolderProperty(): ?Folder
    {
        return $this->currentFolderId ? Folder::find($this->currentFolderId) : null;
    }

    public function getOtherFoldersProperty()
    {
        return Folder::query()
            ->when($this->currentFolderId, fn ($q) => $q->where('id', '!=', $this->currentFolderId))
            ->orderBy('id')
            ->get(['id', 'name', 'collection']);
    }

    public function selectFolder(?int $id): void
    {
        $this->currentFolderId = $id;
        $this->selectedMediaId = null;
        // En mode picker on conserve la sélection entre dossiers ; en mode page on reset le bulk.
        if (! $this->isPicker()) {
            $this->selectedMediaIds = [];
        }
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

    // ------------------ Medias list ------------------

    public function getMediasProperty()
    {
        if (! $this->currentFolderId) {
            return collect();
        }

        $q = Media::query()
            ->where('model_type', Folder::class)
            ->where('model_id', $this->currentFolderId)
            ->orderByDesc('id');

        if ($this->search !== '') {
            $needle = '%'.$this->search.'%';
            $q->where(function ($q) use ($needle) {
                $q->where('file_name', 'like', $needle)
                    ->orWhere('name', 'like', $needle);
            });
        }

        return $q->get();
    }

    // ------------------ Selection ------------------

    public function toggleMediaSelection(int $id): void
    {
        // Picker single : remplace toujours la sélection et ouvre le drawer latéral inline.
        if ($this->pickerMode === 'single') {
            $this->selectedMediaIds = [$id];
            $this->openMedia($id);

            return;
        }

        // Picker multiple + page mode : toggle.
        if (in_array($id, $this->selectedMediaIds, true)) {
            $this->selectedMediaIds = array_values(array_diff($this->selectedMediaIds, [$id]));
        } else {
            $this->selectedMediaIds[] = $id;
        }

        // En mode picker multiple, on ouvre aussi le drawer inline sur le dernier clic.
        if ($this->pickerMode === 'multiple') {
            $this->openMedia($id);
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

    // ------------------ Focus / edit ------------------

    public function openMedia(int $id): void
    {
        $media = Media::query()->find($id);
        if (! $media) {
            $this->closeMedia();

            return;
        }

        $this->selectedMediaId = (int) $media->id;
        $this->editName = pathinfo((string) $media->file_name, PATHINFO_FILENAME);
        $this->editTitle = (string) ($media->getCustomProperty('title') ?? $media->name ?? '');
        $this->editAlt = (string) ($media->getCustomProperty('alt') ?? '');
    }

    public function closeMedia(): void
    {
        $this->selectedMediaId = null;
        $this->editName = '';
        $this->editTitle = null;
        $this->editAlt = null;
    }

    public function getSelectedMediaProperty(): ?Media
    {
        return $this->selectedMediaId ? Media::find($this->selectedMediaId) : null;
    }

    public function saveFocusedMeta(): void
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

        $this->dispatch('media-focused-updated', id: $this->selectedMediaId);
        // Pas de toast : feedback inline dans le drawer (x-data autoSaveIndicator).
    }

    /**
     * Autosave des métadonnées : chaque blur sur editName/editTitle/editAlt
     * déclenche `saveFocusedMeta` côté serveur (via wire:model.blur).
     */
    public function updated(string $name): void
    {
        if (! in_array($name, ['editName', 'editTitle', 'editAlt'], true)) {
            return;
        }
        if ($this->selectedMediaId === null) {
            return;
        }

        $this->saveFocusedMeta();
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

    // ------------------ Upload ------------------

    public function persistPendingUpload(string $originalName): ?int
    {
        $folder = $this->currentFolder;
        if ($folder === null || $this->pendingUpload === null) {
            $this->pendingUpload = null;

            return null;
        }

        try {
            $upload = $this->pendingUpload;
            $base = pathinfo($originalName, PATHINFO_FILENAME);
            $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: $upload->getClientOriginalExtension();

            $added = $folder->addMedia($upload->getRealPath())
                ->usingName($base)
                ->usingFileName(Str::slug($base).'.'.$ext)
                ->toMediaCollection((string) ($folder->collection ?: 'default'));

            $this->pendingUpload = null;

            // En mode picker, auto-sélection du média fraîchement uploadé.
            if ($this->isPicker()) {
                $this->toggleMediaSelection((int) $added->id);
            }

            return (int) $added->id;
        } catch (\Throwable $e) {
            report($e);
            $this->pendingUpload = null;

            return null;
        }
    }

    public function importFromUrl(): void
    {
        $this->importError = null;

        $url = trim($this->importUrl);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            $this->importError = 'URL invalide (http:// ou https:// requis).';

            return;
        }

        $folder = $this->currentFolder;
        if ($folder === null) {
            $this->importError = 'Sélectionne d\'abord un dossier cible.';

            return;
        }

        try {
            $context = stream_context_create([
                'http' => ['timeout' => 15, 'user_agent' => 'pko-media-importer/1.0'],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $binary = @file_get_contents($url, false, $context);
            if ($binary === false || strlen($binary) < 8) {
                $this->importError = 'Téléchargement impossible depuis cette URL.';

                return;
            }

            $tmpDir = storage_path('app/tmp');
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $basename = $this->importName !== ''
                ? Str::slug($this->importName)
                : (Str::slug(pathinfo(parse_url($url, PHP_URL_PATH) ?? 'image', PATHINFO_FILENAME)) ?: 'image');

            $extFromUrl = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

            if (! in_array($extFromUrl, $allowed, true)) {
                $sig = substr($binary, 0, 12);
                $extFromUrl = match (true) {
                    str_starts_with($sig, "\xFF\xD8\xFF") => 'jpg',
                    str_starts_with($sig, "\x89PNG\r\n\x1a\n") => 'png',
                    str_starts_with($sig, 'GIF8') => 'gif',
                    str_contains($sig, 'WEBP') => 'webp',
                    str_contains($sig, '<svg') || str_contains($sig, '<?xml') => 'svg',
                    default => null,
                };
                if ($extFromUrl === null) {
                    $this->importError = 'Format d\'image non supporté.';

                    return;
                }
            }

            $tmpPath = $tmpDir.'/'.uniqid('url-import-').'.'.$extFromUrl;
            file_put_contents($tmpPath, $binary);

            $added = $folder->addMedia($tmpPath)
                ->usingName($this->importName !== '' ? $this->importName : $basename)
                ->usingFileName($basename.'.'.$extFromUrl)
                ->toMediaCollection((string) ($folder->collection ?: 'default'));

            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }

            $this->importUrl = '';
            $this->importName = '';

            if ($this->isPicker()) {
                $this->toggleMediaSelection((int) $added->id);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->importError = 'Erreur lors de l\'import : '.$e->getMessage();
        }
    }

    // ------------------ Usages / conversions (drawer page) ------------------

    /**
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
                'label' => 'Contenu',
                'title' => 'title',
                'resource' => PostResource::class,
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

    // ------------------ Validation ------------------

    protected function rules(): array
    {
        return [
            'editName' => ['required', 'string', 'min:1', 'max:180'],
            'editTitle' => ['nullable', 'string', 'max:200'],
            'editAlt' => ['nullable', 'string', 'max:500'],
        ];
    }

    // ------------------ Render ------------------

    public function render()
    {
        return view('storefront-cms::livewire.pko-media-library');
    }
}
