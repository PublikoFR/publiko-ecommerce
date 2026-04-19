<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

class MediaPickerModal extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public string $statePath = '';

    public bool $multiple = false;

    public string $mediagroup = 'default';

    /** @var array<int,int> */
    public array $preselected = [];

    /** @var array<int,int> */
    public array $selected = [];

    public ?int $currentFolderId = null;

    public string $search = '';

    /** @var mixed */
    public $pendingUpload = null;

    public string $importUrl = '';

    public string $importName = '';

    public ?string $importError = null;

    public ?int $focusedId = null;

    public string $editName = '';

    public string $editTitle = '';

    public string $editAlt = '';

    #[On('open-media-picker-modal')]
    public function openModal(string $statePath, bool $multiple = false, array $preselected = [], string $mediagroup = 'default'): void
    {
        $this->statePath = $statePath;
        $this->multiple = $multiple;
        $this->mediagroup = $mediagroup;
        $this->preselected = array_map('intval', $preselected);
        $this->selected = $this->preselected;
        $this->search = '';

        if ($this->currentFolderId === null) {
            $this->currentFolderId = Folder::query()->orderBy('name')->value('id');
        }

        $this->open = true;
        $this->dispatch('media-picker-opened');
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->selected = [];
        $this->statePath = '';
        $this->pendingUpload = null;
        $this->clearFocus();
        $this->dispatch('media-picker-closed');
    }

    public function selectFolder(?int $id): void
    {
        $this->currentFolderId = $id;
    }

    public function toggle(int $id): void
    {
        if ($this->multiple) {
            if (in_array($id, $this->selected, true)) {
                $this->selected = array_values(array_diff($this->selected, [$id]));
            } else {
                $this->selected[] = $id;
            }
        } else {
            $this->selected = [$id];
        }

        // Cliquer une tuile ouvre aussi le volet latéral sur ce média.
        $this->focusMedia($id);
    }

    public function focusMedia(int $id): void
    {
        $media = Media::query()->find($id);
        if (! $media) {
            $this->focusedId = null;

            return;
        }

        $this->focusedId = (int) $media->id;

        $name = pathinfo((string) $media->file_name, PATHINFO_FILENAME);
        $this->editName = $name !== '' ? $name : (string) $media->name;
        $this->editTitle = (string) ($media->getCustomProperty('title') ?? $media->name ?? '');
        $this->editAlt = (string) ($media->getCustomProperty('alt') ?? '');
    }

    public function clearFocus(): void
    {
        $this->focusedId = null;
        $this->editName = '';
        $this->editTitle = '';
        $this->editAlt = '';
    }

    public function saveFocusedMeta(): void
    {
        if (! $this->focusedId) {
            return;
        }

        $media = Media::query()->find($this->focusedId);
        if (! $media) {
            return;
        }

        $media->setCustomProperty('alt', $this->editAlt);
        $media->setCustomProperty('title', $this->editTitle !== '' ? $this->editTitle : null);
        if ($this->editName !== '') {
            $media->name = $this->editName;
        }
        $media->save();

        $this->dispatch('media-focused-updated', id: $this->focusedId);
    }

    public function confirm(): void
    {
        if (! $this->statePath) {
            $this->closeModal();

            return;
        }

        $ids = array_values(array_map('intval', $this->selected));

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

        // Preserve the order selected by the user.
        $orderedMedias = array_map(fn ($id) => $medias[$id] ?? ['id' => $id, 'url' => null, 'alt' => '', 'fileName' => '#'.$id], $ids);

        $this->dispatch('media-picked', statePath: $this->statePath, ids: $ids, medias: $orderedMedias);
        $this->closeModal();
    }

    public function persistPendingUpload(string $originalName): ?int
    {
        $folder = $this->currentFolderId ? Folder::find($this->currentFolderId) : null;
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

            // Auto-select the freshly uploaded media
            $this->toggle((int) $added->id);

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

        $folder = $this->currentFolderId ? Folder::find($this->currentFolderId) : null;
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

            // Fallback : sniff par contenu si extension absente/non reconnue.
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

            // Auto-sélection du média fraîchement importé.
            $this->toggle((int) $added->id);
        } catch (\Throwable $e) {
            report($e);
            $this->importError = 'Erreur lors de l\'import : '.$e->getMessage();
        }
    }

    public function getFocusedMediaProperty(): ?Media
    {
        return $this->focusedId ? Media::query()->find($this->focusedId) : null;
    }

    public function getFoldersProperty()
    {
        return Folder::query()->orderBy('name')->get(['id', 'name', 'collection']);
    }

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

    public function render()
    {
        return view('storefront-cms::livewire.media-picker-modal');
    }
}
