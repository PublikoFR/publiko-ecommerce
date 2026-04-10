<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;

class MdeCollectionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const COLLECTIONS = [
        'Portails coulissants',
        'Portails battants',
        'Volets roulants',
        'Motorisations',
        'Clôtures',
    ];

    public function run(): void
    {
        $group = CollectionGroup::query()->updateOrCreate(
            ['handle' => 'navigation-principale'],
            ['name' => 'Navigation principale'],
        );

        foreach (self::COLLECTIONS as $name) {
            $exists = Collection::query()
                ->where('collection_group_id', $group->id)
                ->whereJsonContains('attribute_data->name->value', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            Collection::query()->create([
                'collection_group_id' => $group->id,
                'attribute_data' => collect([
                    'name' => new Text($name),
                    'description' => new Text("Catégorie {$name} MDE Distribution"),
                ]),
            ]);
        }
    }
}
