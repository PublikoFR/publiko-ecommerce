<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lunar\Admin\Support\Pages\BasePage;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Mde\CatalogFeatures\Models\FeatureFamily;
use Mde\CatalogFeatures\Models\FeatureValue;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TreeManager extends BasePage implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Publiko Tree Manager';

    protected static ?string $title = 'Publiko Tree Manager';

    protected static string $view = 'filament.pages.tree-manager';

    protected static ?int $navigationSort = 1;

    protected const LOCALE = 'fr';

    public ?int $collectionGroupId = null;

    /** @var array<int, array<string, mixed>> */
    public array $collectionsTree = [];

    /** @var array<int, array<string, mixed>> */
    public array $featureFamilies = [];

    public string $collectionSearch = '';

    public string $featureSearch = '';

    public function mount(): void
    {
        $group = CollectionGroup::query()->orderBy('id')->first();

        abort_if($group === null, 500, 'Aucun CollectionGroup configuré.');

        $this->collectionGroupId = $group->id;
        $this->rehydrate();
    }

    public function rehydrate(): void
    {
        $this->hydrateCollections();
        $this->hydrateFeatures();
    }

    protected function hydrateCollections(): void
    {
        /** @var EloquentCollection<int, LunarCollection> $collections */
        $collections = LunarCollection::query()
            ->where('collection_group_id', $this->collectionGroupId)
            ->defaultOrder()
            ->get();

        $byParent = $collections->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $byParent): array {
            /** @var SupportCollection<int, LunarCollection> $group */
            $group = $byParent->get($parentId, collect());

            return $group->map(function (LunarCollection $node) use ($build): array {
                return [
                    'id' => $node->id,
                    'name' => (string) ($node->translateAttribute('name', self::LOCALE) ?? '—'),
                    'thumbnail' => $node->getThumbnailImage() ?: null,
                    'product_count' => $node->products()->count(),
                    'children' => $build($node->id),
                ];
            })->values()->all();
        };

        $this->collectionsTree = $build(null);
    }

    protected function hydrateFeatures(): void
    {
        /** @var EloquentCollection<int, FeatureFamily> $families */
        $families = FeatureFamily::query()
            ->with(['values' => fn ($q) => $q->ordered()])
            ->ordered()
            ->get();

        $this->featureFamilies = $families->map(fn (FeatureFamily $family): array => [
            'id' => $family->id,
            'name' => $family->name,
            'handle' => $family->handle,
            'multi_value' => (bool) $family->multi_value,
            'searchable' => (bool) $family->searchable,
            'values' => $family->values->map(fn (FeatureValue $value): array => [
                'id' => $value->id,
                'name' => $value->name,
                'handle' => $value->handle,
            ])->values()->all(),
        ])->values()->all();
    }

    // =========================================================================
    // Drag & drop handlers
    // =========================================================================

    public function moveCollection(int $id, ?int $newParentId, int $newIndex): void
    {
        DB::transaction(function () use ($id, $newParentId, $newIndex): void {
            /** @var LunarCollection $node */
            $node = LunarCollection::query()->findOrFail($id);

            if ($newParentId === null) {
                $node->saveAsRoot();
            } else {
                /** @var LunarCollection $parent */
                $parent = LunarCollection::query()->findOrFail($newParentId);
                $node->appendToNode($parent)->save();
            }

            $siblingsQuery = LunarCollection::query()
                ->where('collection_group_id', $this->collectionGroupId);

            if ($newParentId === null) {
                $siblingsQuery->whereNull('parent_id');
            } else {
                $siblingsQuery->where('parent_id', $newParentId);
            }

            /** @var EloquentCollection<int, LunarCollection> $siblings */
            $siblings = $siblingsQuery->defaultOrder()->get();

            $others = $siblings->reject(fn (LunarCollection $s) => $s->id === $id)->values();
            $targetIndex = max(0, min($newIndex, $others->count()));

            $target = $others->get($targetIndex);

            if ($target !== null) {
                $node->refresh();
                $target->refresh();
                $node->insertBeforeNode($target);
                $node->save();
            }
        });

        $this->hydrateCollections();
    }

    public function moveFeatureFamily(int $id, int $newIndex): void
    {
        DB::transaction(function () use ($id, $newIndex): void {
            /** @var EloquentCollection<int, FeatureFamily> $families */
            $families = FeatureFamily::query()->ordered()->get();

            $moving = $families->firstWhere('id', $id);
            if ($moving === null) {
                return;
            }

            $others = $families->reject(fn (FeatureFamily $f) => $f->id === $id)->values();
            $target = max(0, min($newIndex, $others->count()));

            $reordered = $others->toArray();
            array_splice($reordered, $target, 0, [$moving->toArray()]);

            foreach ($reordered as $position => $family) {
                FeatureFamily::query()
                    ->where('id', $family['id'])
                    ->update(['position' => $position]);
            }
        });

        $this->hydrateFeatures();
    }

    public function moveFeatureValue(int $id, int $newFamilyId, int $newIndex): void
    {
        DB::transaction(function () use ($id, $newFamilyId, $newIndex): void {
            /** @var FeatureValue $value */
            $value = FeatureValue::query()->findOrFail($id);
            $oldFamilyId = (int) $value->feature_family_id;

            if ($oldFamilyId !== $newFamilyId) {
                $value->feature_family_id = $newFamilyId;
                $value->save();
            }

            /** @var EloquentCollection<int, FeatureValue> $siblings */
            $siblings = FeatureValue::query()
                ->where('feature_family_id', $newFamilyId)
                ->where('id', '!=', $id)
                ->ordered()
                ->get();

            $reordered = $siblings->values()->all();
            $target = max(0, min($newIndex, count($reordered)));
            array_splice($reordered, $target, 0, [$value]);

            foreach ($reordered as $position => $sibling) {
                FeatureValue::query()
                    ->where('id', $sibling->id)
                    ->update(['position' => $position]);
            }

            if ($oldFamilyId !== $newFamilyId) {
                FeatureValue::query()
                    ->where('feature_family_id', $oldFamilyId)
                    ->ordered()
                    ->get()
                    ->values()
                    ->each(fn (FeatureValue $s, int $i) => FeatureValue::query()
                        ->where('id', $s->id)
                        ->update(['position' => $i]));
            }
        });

        $this->hydrateFeatures();
    }

    // =========================================================================
    // Collection CRUD actions
    // =========================================================================

    public function createCollectionAction(): Action
    {
        return Action::make('createCollection')
            ->label('Nouvelle catégorie')
            ->icon('heroicon-o-plus')
            ->modalHeading('Nouvelle catégorie')
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->form($this->collectionFormSchema())
            ->fillForm(fn (array $arguments): array => [
                'parent_id' => $arguments['parent_id'] ?? null,
                'name' => '',
                'description' => null,
                'meta_title' => '',
                'meta_description' => '',
                'image' => null,
            ])
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments): void {
                    $collection = new LunarCollection([
                        'collection_group_id' => $this->collectionGroupId,
                        'type' => 'static',
                        'sort' => 'custom',
                        'attribute_data' => collect(),
                    ]);
                    $this->persistCollectionAttributes($collection, $data);

                    $parentId = $arguments['parent_id'] ?? null;
                    if ($parentId === null) {
                        $collection->saveAsRoot();
                    } else {
                        /** @var LunarCollection $parent */
                        $parent = LunarCollection::query()->findOrFail($parentId);
                        $collection->appendToNode($parent)->save();
                    }

                    $this->attachImageIfPresent($collection, $data['image'] ?? null);
                });

                $this->hydrateCollections();

                Notification::make()->success()->title('Catégorie créée')->send();
            });
    }

    public function editCollectionAction(): Action
    {
        return Action::make('editCollection')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Modifier la catégorie')
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->form($this->collectionFormSchema())
            ->fillForm(function (array $arguments): array {
                /** @var LunarCollection $collection */
                $collection = LunarCollection::query()->findOrFail($arguments['id']);

                return [
                    'name' => (string) ($collection->translateAttribute('name', self::LOCALE) ?? ''),
                    'description' => $collection->translateAttribute('description', self::LOCALE),
                    'meta_title' => (string) ($collection->translateAttribute('meta_title', self::LOCALE) ?? ''),
                    'meta_description' => (string) ($collection->translateAttribute('meta_description', self::LOCALE) ?? ''),
                    'image' => null,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments): void {
                    /** @var LunarCollection $collection */
                    $collection = LunarCollection::query()->findOrFail($arguments['id']);
                    $this->persistCollectionAttributes($collection, $data);
                    $collection->save();

                    $this->attachImageIfPresent($collection, $data['image'] ?? null);
                });

                $this->hydrateCollections();

                Notification::make()->success()->title('Catégorie mise à jour')->send();
            });
    }

    public function deleteCollectionAction(): Action
    {
        return Action::make('deleteCollection')
            ->label('Supprimer')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer la catégorie ?')
            ->modalDescription('Les sous-catégories seront également supprimées (soft delete).')
            ->action(function (array $arguments): void {
                /** @var LunarCollection $collection */
                $collection = LunarCollection::query()->findOrFail($arguments['id']);
                $collection->delete();

                $this->hydrateCollections();

                Notification::make()->success()->title('Catégorie supprimée')->send();
            });
    }

    /**
     * @return array<int, Component>
     */
    protected function collectionFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Titre (affiché sur la page)')
                ->required()
                ->maxLength(191),
            RichEditor::make('description')
                ->label('Description (affichée sur la page)')
                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3']),
            TextInput::make('meta_title')
                ->label('Meta title (SEO)')
                ->maxLength(70)
                ->helperText('Recommandé : 50–60 caractères.'),
            Textarea::make('meta_description')
                ->label('Meta description (SEO)')
                ->maxLength(180)
                ->rows(3)
                ->helperText('Recommandé : 150–160 caractères.'),
            FileUpload::make('image')
                ->label('Image de catégorie')
                ->image()
                ->imageEditor()
                ->directory('tree-manager/uploads')
                ->disk('local')
                ->helperText('Laisser vide pour conserver l\'image actuelle.'),
        ];
    }

    protected function persistCollectionAttributes(LunarCollection $collection, array $data): void
    {
        $attributeData = $collection->attribute_data instanceof SupportCollection
            ? $collection->attribute_data
            : collect();

        foreach (['name', 'description', 'meta_title', 'meta_description'] as $key) {
            $value = $data[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $current = $attributeData->get($key);
            $translations = $current instanceof TranslatedText
                ? collect($current->getValue() ?? [])
                : collect();

            $translations->put(self::LOCALE, new LunarText((string) $value));
            $attributeData->put($key, new TranslatedText($translations));
        }

        $collection->attribute_data = $attributeData;

        if (! $collection->exists) {
            // the caller persists via saveAsRoot / appendToNode after this
            return;
        }

        $collection->save();
    }

    protected function attachImageIfPresent(LunarCollection $collection, mixed $image): void
    {
        if (blank($image)) {
            return;
        }

        $path = is_array($image) ? reset($image) : $image;
        if ($path instanceof UploadedFile) {
            $absolute = $path->getRealPath();
        } else {
            $absolute = Storage::disk('local')->path((string) $path);
        }

        if (! is_file($absolute)) {
            return;
        }

        $collection->thumbnail?->delete();

        $collection
            ->addMedia($absolute)
            ->preservingOriginal()
            ->withCustomProperties(['primary' => true])
            ->toMediaCollection('images');
    }

    // =========================================================================
    // Feature family CRUD
    // =========================================================================

    public function createFamilyAction(): Action
    {
        return Action::make('createFamily')
            ->label('Nouvelle famille')
            ->icon('heroicon-o-plus')
            ->modalHeading('Nouvelle famille de caractéristique')
            ->form($this->familyFormSchema())
            ->fillForm(fn (): array => [
                'name' => '',
                'handle' => '',
                'multi_value' => true,
                'searchable' => false,
            ])
            ->action(function (array $data): void {
                $maxPosition = (int) FeatureFamily::query()->max('position');
                FeatureFamily::query()->create([
                    'name' => $data['name'],
                    'handle' => $data['handle'],
                    'multi_value' => (bool) $data['multi_value'],
                    'searchable' => (bool) $data['searchable'],
                    'position' => $maxPosition + 1,
                ]);

                $this->hydrateFeatures();

                Notification::make()->success()->title('Famille créée')->send();
            });
    }

    public function editFamilyAction(): Action
    {
        return Action::make('editFamily')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Modifier la famille')
            ->form($this->familyFormSchema())
            ->fillForm(function (array $arguments): array {
                /** @var FeatureFamily $family */
                $family = FeatureFamily::query()->findOrFail($arguments['id']);

                return [
                    'name' => $family->name,
                    'handle' => $family->handle,
                    'multi_value' => (bool) $family->multi_value,
                    'searchable' => (bool) $family->searchable,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                FeatureFamily::query()->where('id', $arguments['id'])->update([
                    'name' => $data['name'],
                    'handle' => $data['handle'],
                    'multi_value' => (bool) $data['multi_value'],
                    'searchable' => (bool) $data['searchable'],
                ]);

                $this->hydrateFeatures();

                Notification::make()->success()->title('Famille mise à jour')->send();
            });
    }

    public function deleteFamilyAction(): Action
    {
        return Action::make('deleteFamily')
            ->label('Supprimer')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer la famille ?')
            ->modalDescription('Toutes les valeurs rattachées seront également supprimées.')
            ->action(function (array $arguments): void {
                FeatureFamily::query()->where('id', $arguments['id'])->delete();

                $this->hydrateFeatures();

                Notification::make()->success()->title('Famille supprimée')->send();
            });
    }

    /**
     * @return array<int, Component>
     */
    protected function familyFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(191),
            TextInput::make('handle')
                ->label('Identifiant technique (handle)')
                ->required()
                ->alphaDash()
                ->maxLength(64)
                ->helperText('Lettres minuscules, chiffres et tirets uniquement.'),
            Toggle::make('multi_value')
                ->label('Multi-valeurs')
                ->helperText('Un produit peut porter plusieurs valeurs de cette famille.')
                ->default(true),
            Toggle::make('searchable')
                ->label('Indexée pour la recherche')
                ->default(false),
        ];
    }

    // =========================================================================
    // Feature value CRUD
    // =========================================================================

    public function createValueAction(): Action
    {
        return Action::make('createValue')
            ->label('Nouvelle valeur')
            ->icon('heroicon-o-plus')
            ->modalHeading('Nouvelle valeur')
            ->form($this->valueFormSchema())
            ->fillForm(fn (array $arguments): array => [
                'feature_family_id' => $arguments['family_id'] ?? null,
                'name' => '',
                'handle' => '',
            ])
            ->action(function (array $data, array $arguments): void {
                $familyId = (int) ($data['feature_family_id'] ?? $arguments['family_id'] ?? 0);
                abort_if($familyId === 0, 422, 'Famille manquante.');

                $maxPosition = (int) FeatureValue::query()
                    ->where('feature_family_id', $familyId)
                    ->max('position');

                FeatureValue::query()->create([
                    'feature_family_id' => $familyId,
                    'name' => $data['name'],
                    'handle' => $data['handle'],
                    'position' => $maxPosition + 1,
                ]);

                $this->hydrateFeatures();

                Notification::make()->success()->title('Valeur créée')->send();
            });
    }

    public function editValueAction(): Action
    {
        return Action::make('editValue')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Modifier la valeur')
            ->form($this->valueFormSchema())
            ->fillForm(function (array $arguments): array {
                /** @var FeatureValue $value */
                $value = FeatureValue::query()->findOrFail($arguments['id']);

                return [
                    'feature_family_id' => $value->feature_family_id,
                    'name' => $value->name,
                    'handle' => $value->handle,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                FeatureValue::query()->where('id', $arguments['id'])->update([
                    'feature_family_id' => $data['feature_family_id'],
                    'name' => $data['name'],
                    'handle' => $data['handle'],
                ]);

                $this->hydrateFeatures();

                Notification::make()->success()->title('Valeur mise à jour')->send();
            });
    }

    public function deleteValueAction(): Action
    {
        return Action::make('deleteValue')
            ->label('Supprimer')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer la valeur ?')
            ->action(function (array $arguments): void {
                FeatureValue::query()->where('id', $arguments['id'])->delete();

                $this->hydrateFeatures();

                Notification::make()->success()->title('Valeur supprimée')->send();
            });
    }

    /**
     * @return array<int, Component>
     */
    protected function valueFormSchema(): array
    {
        return [
            Select::make('feature_family_id')
                ->label('Famille')
                ->options(fn (): array => FeatureFamily::query()
                    ->ordered()
                    ->pluck('name', 'id')
                    ->all())
                ->required(),
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(191),
            TextInput::make('handle')
                ->label('Identifiant technique (handle)')
                ->required()
                ->alphaDash()
                ->maxLength(64),
        ];
    }

    // =========================================================================
    // JSON import / export
    // =========================================================================

    protected function getHeaderActions(): array
    {
        return [
            $this->fixTreeAction(),
            $this->exportCollectionsAction(),
            $this->importCollectionsAction(),
            $this->exportFeaturesAction(),
            $this->importFeaturesAction(),
        ];
    }

    public function fixTreeAction(): Action
    {
        return Action::make('fixTree')
            ->label('Réparer l\'arbre')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('gray')
            ->action(function (): void {
                LunarCollection::fixTree();
                $this->hydrateCollections();
                Notification::make()->success()->title('Arbre recalculé')->send();
            });
    }

    public function exportCollectionsAction(): Action
    {
        return Action::make('exportCollections')
            ->label('Exporter catégories')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn (): StreamedResponse => $this->streamJson(
                'categories-'.now()->format('Ymd-His').'.json',
                $this->serializeCollectionsTree(),
            ));
    }

    public function importCollectionsAction(): Action
    {
        return Action::make('importCollections')
            ->label('Importer catégories')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->form([
                FileUpload::make('file')
                    ->label('Fichier JSON')
                    ->acceptedFileTypes(['application/json'])
                    ->required()
                    ->disk('local')
                    ->directory('tree-manager/imports'),
            ])
            ->action(function (array $data): void {
                $path = Storage::disk('local')->path((string) $data['file']);
                $payload = json_decode((string) file_get_contents($path), true);
                abort_if(! is_array($payload) || ! isset($payload['tree']), 422, 'Format invalide.');

                $this->importCollectionsPayload($payload);
                $this->hydrateCollections();

                Notification::make()->success()->title('Catégories importées')->send();
            });
    }

    public function exportFeaturesAction(): Action
    {
        return Action::make('exportFeatures')
            ->label('Exporter caractéristiques')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn (): StreamedResponse => $this->streamJson(
                'features-'.now()->format('Ymd-His').'.json',
                $this->serializeFeaturesTree(),
            ));
    }

    public function importFeaturesAction(): Action
    {
        return Action::make('importFeatures')
            ->label('Importer caractéristiques')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->form([
                FileUpload::make('file')
                    ->label('Fichier JSON')
                    ->acceptedFileTypes(['application/json'])
                    ->required()
                    ->disk('local')
                    ->directory('tree-manager/imports'),
            ])
            ->action(function (array $data): void {
                $path = Storage::disk('local')->path((string) $data['file']);
                $payload = json_decode((string) file_get_contents($path), true);
                abort_if(! is_array($payload) || ! isset($payload['families']), 422, 'Format invalide.');

                $this->importFeaturesPayload($payload);
                $this->hydrateFeatures();

                Notification::make()->success()->title('Caractéristiques importées')->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCollectionsTree(): array
    {
        /** @var EloquentCollection<int, LunarCollection> $collections */
        $collections = LunarCollection::query()
            ->where('collection_group_id', $this->collectionGroupId)
            ->defaultOrder()
            ->get();

        $byParent = $collections->groupBy('parent_id');

        $serialize = function (?int $parentId) use (&$serialize, $byParent): array {
            /** @var SupportCollection<int, LunarCollection> $group */
            $group = $byParent->get($parentId, collect());

            return $group->map(fn (LunarCollection $c): array => [
                'id' => $c->id,
                'name' => (string) ($c->translateAttribute('name', self::LOCALE) ?? ''),
                'description' => $c->translateAttribute('description', self::LOCALE),
                'meta_title' => $c->translateAttribute('meta_title', self::LOCALE),
                'meta_description' => $c->translateAttribute('meta_description', self::LOCALE),
                'children' => $serialize($c->id),
            ])->values()->all();
        };

        return [
            'version' => 1,
            'group_id' => $this->collectionGroupId,
            'tree' => $serialize(null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeFeaturesTree(): array
    {
        /** @var EloquentCollection<int, FeatureFamily> $families */
        $families = FeatureFamily::query()
            ->with(['values' => fn ($q) => $q->ordered()])
            ->ordered()
            ->get();

        return [
            'version' => 1,
            'families' => $families->map(fn (FeatureFamily $family): array => [
                'handle' => $family->handle,
                'name' => $family->name,
                'multi_value' => (bool) $family->multi_value,
                'searchable' => (bool) $family->searchable,
                'values' => $family->values->map(fn (FeatureValue $value): array => [
                    'handle' => $value->handle,
                    'name' => $value->name,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function importCollectionsPayload(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $walk = function (array $nodes, ?int $parentId) use (&$walk): void {
                foreach ($nodes as $node) {
                    $data = [
                        'name' => (string) ($node['name'] ?? ''),
                        'description' => $node['description'] ?? null,
                        'meta_title' => $node['meta_title'] ?? null,
                        'meta_description' => $node['meta_description'] ?? null,
                        'image' => null,
                    ];

                    if (blank($data['name'])) {
                        continue;
                    }

                    $existing = isset($node['id'])
                        ? LunarCollection::query()->find($node['id'])
                        : null;

                    if ($existing === null) {
                        $collection = new LunarCollection([
                            'collection_group_id' => $this->collectionGroupId,
                            'type' => 'static',
                            'sort' => 'custom',
                            'attribute_data' => collect(),
                        ]);
                    } else {
                        $collection = $existing;
                    }

                    $this->persistCollectionAttributes($collection, $data);

                    if ($parentId === null) {
                        $collection->saveAsRoot();
                    } else {
                        /** @var LunarCollection $parent */
                        $parent = LunarCollection::query()->findOrFail($parentId);
                        $collection->appendToNode($parent)->save();
                    }

                    if (! empty($node['children']) && is_array($node['children'])) {
                        $walk($node['children'], $collection->id);
                    }
                }
            };

            $walk($payload['tree'] ?? [], null);
            LunarCollection::fixTree();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function importFeaturesPayload(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            foreach (array_values($payload['families'] ?? []) as $familyIndex => $familyDef) {
                $handle = (string) ($familyDef['handle'] ?? '');
                if ($handle === '') {
                    continue;
                }

                $family = FeatureFamily::query()->updateOrCreate(
                    ['handle' => $handle],
                    [
                        'name' => (string) ($familyDef['name'] ?? Str::headline($handle)),
                        'multi_value' => (bool) ($familyDef['multi_value'] ?? true),
                        'searchable' => (bool) ($familyDef['searchable'] ?? false),
                        'position' => $familyIndex,
                    ],
                );

                foreach (array_values($familyDef['values'] ?? []) as $valueIndex => $valueDef) {
                    $valueHandle = (string) ($valueDef['handle'] ?? '');
                    if ($valueHandle === '') {
                        continue;
                    }

                    FeatureValue::query()->updateOrCreate(
                        [
                            'feature_family_id' => $family->id,
                            'handle' => $valueHandle,
                        ],
                        [
                            'name' => (string) ($valueDef['name'] ?? Str::headline($valueHandle)),
                            'position' => $valueIndex,
                        ],
                    );
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function streamJson(string $filename, array $payload): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        $categoryCount = count($this->collectionsTree);
        $familyCount = count($this->featureFamilies);

        return "Gérez les catégories produits et les caractéristiques filtrables. {$categoryCount} catégorie(s) racine, {$familyCount} famille(s) de caractéristique.";
    }
}
