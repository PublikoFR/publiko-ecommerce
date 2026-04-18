<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Mde\StorefrontCms\Models\Mediable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Universal polymorphic media attachments via `mde_mediables` table.
 *
 * A model can have multiple media, grouped by `mediagroup` (e.g. 'gallery', 'cover', 'thumbnail'),
 * ordered by `position`.
 *
 * @mixin Model
 */
trait HasMediaAttachments
{
    /**
     * Morph-to-many relation to Spatie Media via the `mde_mediables` pivot.
     *
     * When $group is provided, the relation is scoped to that mediagroup.
     */
    public function mediaAttachments(?string $group = null): MorphToMany
    {
        $relation = $this->morphToMany(
            related: Media::class,
            name: 'mediable',
            table: 'mde_mediables',
            foreignPivotKey: 'mediable_id',
            relatedPivotKey: 'media_id',
        )
            ->using(Mediable::class)
            ->withPivot(['mediagroup', 'position'])
            ->withTimestamps()
            ->orderBy('mde_mediables.position');

        if ($group !== null) {
            $relation->wherePivot('mediagroup', $group);
        }

        return $relation;
    }

    /**
     * First media attached in a given group (lowest position).
     */
    public function firstMedia(string $group = 'default'): ?Media
    {
        return $this->mediaAttachments($group)->first();
    }

    /**
     * URL of the first media in a group, optionally for a named conversion.
     */
    public function firstMediaUrl(string $group = 'default', string $conversion = ''): ?string
    {
        return $this->firstMedia($group)?->getUrl($conversion) ?: null;
    }

    /**
     * Replace all attachments of the given group with the provided ordered ids.
     *
     * @param  array<int, int>  $mediaIds  Ordered list (position = array index).
     */
    public function syncMediaAttachments(array $mediaIds, string $group = 'default'): void
    {
        $this->mediaAttachments($group)->detach();

        $position = 0;
        foreach ($mediaIds as $id) {
            if (! $id) {
                continue;
            }

            $this->mediaAttachments()->attach($id, [
                'mediagroup' => $group,
                'position' => $position++,
            ]);
        }
    }

    /**
     * Attach a single media to a group at a given position (appended if null).
     */
    public function attachMedia(int $mediaId, string $group = 'default', ?int $position = null): void
    {
        $position ??= ((int) $this->mediaAttachments($group)->max('position')) + 1;

        $this->mediaAttachments()->attach($mediaId, [
            'mediagroup' => $group,
            'position' => $position,
        ]);
    }

    /**
     * Detach a media (optionally scoped to a group).
     */
    public function detachMedia(int $mediaId, ?string $group = null): void
    {
        $relation = $this->mediaAttachments($group);
        $relation->detach($mediaId);
    }
}
