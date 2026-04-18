<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;

return new class extends Migration
{
    private const ATTRIBUTE_TYPE = 'collection';

    /**
     * @var array<int, array{handle: string, label: string, section: string, position: int}>
     */
    private array $attributes = [
        [
            'handle' => 'meta_title',
            'label' => 'Meta title',
            'section' => 'seo',
            'position' => 10,
        ],
        [
            'handle' => 'meta_description',
            'label' => 'Meta description',
            'section' => 'seo',
            'position' => 11,
        ],
    ];

    public function up(): void
    {
        $group = AttributeGroup::query()
            ->where('attributable_type', self::ATTRIBUTE_TYPE)
            ->orderBy('position')
            ->first();

        if ($group === null) {
            $group = AttributeGroup::query()->create([
                'attributable_type' => self::ATTRIBUTE_TYPE,
                'name' => ['en' => 'SEO', 'fr' => 'Référencement'],
                'handle' => 'collection_seo',
                'position' => 99,
            ]);
        }

        foreach ($this->attributes as $definition) {
            Attribute::query()->updateOrCreate(
                [
                    'attribute_type' => self::ATTRIBUTE_TYPE,
                    'handle' => $definition['handle'],
                ],
                [
                    'attribute_group_id' => $group->id,
                    'position' => $definition['position'],
                    'name' => ['en' => $definition['label'], 'fr' => $definition['label']],
                    'section' => $definition['section'],
                    'type' => TranslatedText::class,
                    'required' => false,
                    'default_value' => null,
                    'configuration' => ['richtext' => false],
                    'system' => false,
                    'description' => [
                        'en' => 'Tree Manager',
                        'fr' => 'Tree Manager',
                    ],
                ],
            );
        }
    }

    public function down(): void
    {
        Attribute::query()
            ->where('attribute_type', self::ATTRIBUTE_TYPE)
            ->whereIn('handle', array_column($this->attributes, 'handle'))
            ->delete();
    }
};
