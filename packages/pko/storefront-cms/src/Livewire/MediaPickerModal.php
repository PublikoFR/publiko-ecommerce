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
