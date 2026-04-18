<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Mde\StorefrontCms\Concerns\HasMediaAttachments;

class MediaPicker extends Field
{
    protected string $view = 'storefront-cms::forms.components.media-picker';

    protected bool $multiple = false;

    protected string $mediagroup = 'default';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);

        // Hydrate from the model's media attachments on load.
        $this->afterStateHydrated(function (MediaPicker $component, ?Model $record): void {
            if (! $record || ! in_array(HasMediaAttachments::class, class_uses_recursive($record::class), true)) {
                $component->state($component->isMultiple() ? [] : null);

                return;
            }

            $ids = $record->mediaAttachments($component->getMediagroup())
                ->pluck('media.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $component->state($component->isMultiple() ? $ids : ($ids[0] ?? null));
        });

        // Persist selection after the parent record is saved.
        $this->saveRelationshipsUsing(function (MediaPicker $component, ?Model $record, $state): void {
            if (! $record || ! in_array(HasMediaAttachments::class, class_uses_recursive($record::class), true)) {
                return;
            }

            $ids = $component->isMultiple()
                ? array_values(array_filter(array_map('intval', (array) $state)))
                : ($state ? [(int) $state] : []);

            $record->syncMediaAttachments($ids, $component->getMediagroup());
        });
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function mediagroup(string $group): static
    {
        $this->mediagroup = $group;

        return $this;
    }

    public function getMediagroup(): string
    {
        return $this->mediagroup;
    }

    /**
     * IDs currently in state (always as int[] for convenience).
     *
     * @return array<int,int>
     */
    public function getSelectedIds(): array
    {
        $state = $this->getState();

        if (is_array($state)) {
            return array_values(array_map('intval', array_filter($state)));
        }

        return $state ? [(int) $state] : [];
    }
}
