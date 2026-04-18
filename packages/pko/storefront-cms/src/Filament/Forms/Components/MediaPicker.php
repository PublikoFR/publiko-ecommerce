<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MediaPicker extends Field
{
    protected string $view = 'storefront-cms::forms.components.media-picker';

    protected bool $multiple = false;

    protected string $mediagroup = 'default';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);

        $this->afterStateHydrated(function (MediaPicker $component, ?Model $record): void {
            if (! $record || ! $record->exists) {
                $component->state($component->isMultiple() ? [] : null);

                return;
            }

            $ids = DB::table('pko_mediables')
                ->where('mediable_type', $record::class)
                ->where('mediable_id', $record->getKey())
                ->where('mediagroup', $component->getMediagroup())
                ->orderBy('position')
                ->pluck('media_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $component->state($component->isMultiple() ? $ids : ($ids[0] ?? null));
        });

        $this->saveRelationshipsUsing(function (MediaPicker $component, ?Model $record, $state): void {
            if (! $record || ! $record->exists) {
                return;
            }

            $ids = $component->isMultiple()
                ? array_values(array_filter(array_map('intval', (array) $state)))
                : ($state ? [(int) $state] : []);

            DB::table('pko_mediables')
                ->where('mediable_type', $record::class)
                ->where('mediable_id', $record->getKey())
                ->where('mediagroup', $component->getMediagroup())
                ->delete();

            $now = now();
            foreach ($ids as $position => $mediaId) {
                DB::table('pko_mediables')->insertOrIgnore([
                    'media_id' => $mediaId,
                    'mediable_type' => $record::class,
                    'mediable_id' => $record->getKey(),
                    'mediagroup' => $component->getMediagroup(),
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
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
