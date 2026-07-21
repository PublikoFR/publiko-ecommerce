<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Lunar\Admin\Support\Pages\BasePage;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Models\FeatureValue;
use Pko\LunarMediaCore\Services\MediaLibraryImporter;
use Pko\Storefront\StorefrontServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TreeManager extends BasePage implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Catalogue';

    protected static string $view = 'filament.pages.tree-manager';

    protected const LOCALE = 'fr';

    /** Slug du dossier médiathèque recevant les visuels de catégorie. */
    protected const CATEGORY_MEDIA_FOLDER = 'categories';

    public ?int $collectionGroupId = null;

    public string $activeTab = 'both';

    /**
     * IDs des catégories cochées pour l'export (piloté côté client, synchronisé
     * juste avant l'export). Vide = exporter toute l'arborescence.
     *
     * @var array<int, int>
     */
    public array $collectionExportSelection = [];

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['categories', 'features', 'both'], true) ? $tab : 'both';
        $this->cachedHeaderActions = [];
        $this->cacheHeaderActions();
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $baseUrl = static::getUrl();

        return [
            NavigationItem::make('Catégories')
                ->group('Catalogue')
                ->icon('heroicon-o-rectangle-stack')
                ->sort(4)
                ->url($baseUrl.'?tab=categories')
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getNavigationItemActiveRoutePattern())
                    && request()->query('tab') === 'categories'),
            NavigationItem::make('Caractéristiques')
                ->group('Catalogue')
                ->icon('heroicon-o-tag')
                ->sort(5)
                ->url($baseUrl.'?tab=features')
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getNavigationItemActiveRoutePattern())
                    && request()->query('tab') === 'features'),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return match ($this->activeTab) {
            'categories' => 'Catégories',
            'features' => 'Caractéristiques',
            default => 'Catégories & Caractéristiques',
        };
    }

    public function mount(): void
    {
        $tab = request()->query('tab', 'both');
        $this->activeTab = in_array($tab, ['categories', 'features', 'both'], true)
            ? $tab
            : 'both';

        $group = CollectionGroup::query()->orderBy('id')->first();

        abort_if($group === null, 500, 'Aucun CollectionGroup configuré.');

        $this->collectionGroupId = $group->id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function collectionsTree(): array
    {
        /** @var EloquentCollection<int, LunarCollection> $collections */
        $collections = LunarCollection::query()
            ->where('collection_group_id', $this->collectionGroupId)
            // `media` eager-loadé : l'arbre affiche la vignette de chaque nœud,
            // soit ~500 requêtes de plus sans ça.
            ->with('media')
            ->withCount('products')
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
                    'product_count' => $node->products_count ?? 0,
                    'pko_enabled' => (bool) $node->pko_enabled,
                    'image_url' => pko_media_url($node->getFirstMedia('images'), 'small'),
                    'children' => $build($node->id),
                ];
            })->values()->all();
        };

        return $build(null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function featureFamilies(): array
    {
        /** @var EloquentCollection<int, FeatureFamily> $families */
        $families = FeatureFamily::query()
            ->with(['values' => fn ($q) => $q->ordered()])
            ->ordered()
            ->get();

        return $families->map(fn (FeatureFamily $family): array => [
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

        $this->skipRender();
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

        $this->skipRender();
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

        $this->skipRender();
    }

    // =========================================================================
    // Collection enable/disable toggle
    // =========================================================================

    /**
     * Flip pko_enabled on a collection.
     *
     * Disabling cascades to all nestedset descendants (children, grandchildren…).
     * Re-enabling only affects the node itself — descendants keep their own state.
     */
    public function toggleCollectionEnabled(int $id): void
    {
        /** @var LunarCollection $node */
        $node = LunarCollection::query()->findOrFail($id);
        $enabling = ! $node->pko_enabled;

        DB::transaction(function () use ($node, $enabling): void {
            $node->pko_enabled = $enabling;
            $node->save();

            if (! $enabling) {
                // Cascade disable to all descendants via nestedset
                LunarCollection::query()
                    ->where('_lft', '>', $node->_lft)
                    ->where('_rgt', '<', $node->_rgt)
                    ->update(['pko_enabled' => false]);
            }
        });

        Cache::forget(StorefrontServiceProvider::NAV_CACHE_KEY);

        unset($this->collectionsTree);

        Notification::make()
            ->title($enabling ? 'Catégorie activée' : 'Catégorie désactivée')
            ->success()
            ->send();
    }

    /**
     * Tous les IDs de catégories du groupe courant. Sert à initialiser la
     * sélection d'export côté client (« tout coché » par défaut).
     *
     * @return array<int, int>
     */
    public function allCollectionIds(): array
    {
        return LunarCollection::query()
            ->where('collection_group_id', $this->collectionGroupId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
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

                unset($this->collectionsTree);
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
            ->form(fn (array $arguments): array => $this->collectionFormSchema(
                isset($arguments['id']) ? (int) $arguments['id'] : null,
            ))
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

                unset($this->collectionsTree);
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

                unset($this->collectionsTree);
                Notification::make()->success()->title('Catégorie supprimée')->send();
            });
    }

    /**
     * @param  int|null  $collectionId  Renseigné en édition : permet d'afficher
     *                                  l'image actuellement liée à la catégorie.
     * @return array<int, Component>
     */
    protected function collectionFormSchema(?int $collectionId = null): array
    {
        return [
            ...$this->currentImagePreview($collectionId),
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
                ->label(fn (): string => $collectionId !== null ? 'Remplacer l\'image' : 'Image de catégorie')
                ->image()
                ->imageEditor()
                ->directory('tree-manager/uploads')
                ->disk('local')
                ->helperText('Laisser vide pour conserver l\'image actuelle.'),
        ];
    }

    /**
     * Aperçu de l'image actuellement liée à la catégorie (édition uniquement).
     * `pko_media_url()` retombe sur l'original quand la conversion demandée n'a
     * pas encore été générée — les conversions partent en queue (redis), donc
     * elles sont absentes juste après un import.
     *
     * @return array<int, Component>
     */
    protected function currentImagePreview(?int $collectionId): array
    {
        if ($collectionId === null) {
            return [];
        }

        $collection = LunarCollection::query()->find($collectionId);
        $media = $collection?->getFirstMedia('images');

        if ($media === null) {
            return [
                Placeholder::make('current_image')
                    ->label('Image actuelle')
                    ->content('Aucune image pour cette catégorie.'),
            ];
        }

        $url = pko_media_url($media, 'small');

        return [
            Placeholder::make('current_image')
                ->label('Image actuelle')
                ->content(new HtmlString(sprintf(
                    '<img src="%s" alt="" style="max-height:8rem;width:auto;border-radius:.5rem;'
                    .'box-shadow:0 0 0 1px rgba(0,0,0,.08);object-fit:contain;" />'
                    .'<span style="display:block;margin-top:.35rem;font-size:.75rem;color:#6b7280;">%s</span>',
                    e($url),
                    e($media->file_name),
                ))),
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

                unset($this->featureFamilies);
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

                unset($this->featureFamilies);
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

                unset($this->featureFamilies);
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

                unset($this->featureFamilies);
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

                unset($this->featureFamilies);
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

                unset($this->featureFamilies);
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
            Action::make('tabCategories')
                ->label('Catégories')
                ->icon('heroicon-o-rectangle-stack')
                ->color(fn (): string => $this->activeTab === 'categories' ? 'primary' : 'gray')
                ->action(fn () => $this->switchTab('categories')),
            Action::make('tabFeatures')
                ->label('Caractéristiques')
                ->icon('heroicon-o-tag')
                ->color(fn (): string => $this->activeTab === 'features' ? 'primary' : 'gray')
                ->action(fn () => $this->switchTab('features')),
            Action::make('tabBoth')
                ->label('Les deux')
                ->icon('heroicon-o-squares-2x2')
                ->color(fn (): string => $this->activeTab === 'both' ? 'primary' : 'gray')
                ->action(fn () => $this->switchTab('both')),
            ActionGroup::make([
                Action::make('maintenanceFixTree')
                    ->label('Réparer l\'arbre')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->action(function (): void {
                        LunarCollection::fixTree();
                        unset($this->collectionsTree);
                        Notification::make()->success()->title('Arbre recalculé')->send();
                    }),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->tooltip('Maintenance'),
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
                unset($this->collectionsTree);
                Notification::make()->success()->title('Arbre recalculé')->send();
            });
    }

    public function exportCollectionsAction(): Action
    {
        return Action::make('exportCollections')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->modalHeading('Exporter les catégories')
            ->modalSubmitActionLabel('Télécharger JSON')
            ->form([
                Textarea::make('json_data')
                    ->label('Données')
                    ->rows(15)
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'font-mono text-xs']),
            ])
            ->fillForm(fn (): array => [
                'json_data' => json_encode(
                    $this->serializeCollectionsTree(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ])
            ->action(fn (): StreamedResponse => $this->streamJson(
                'categories-'.now()->format('Ymd-His').'.json',
                $this->serializeCollectionsTree(),
            ))
            ->extraModalFooterActions(fn (): array => [
                Action::make('copyCollectionsJson')
                    ->label('Copier')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->alpineClickHandler(self::clipboardJs()),
            ]);
    }

    public function importCollectionsAction(): Action
    {
        return Action::make('importCollections')
            ->label('Import')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Importer les catégories')
            ->modalSubmitActionLabel('Importer')
            ->form([
                Radio::make('mode')
                    ->label('Mode d\'import')
                    ->options([
                        'append' => 'Ajouter à la suite des catégories existantes',
                        'replace' => 'Écraser : remplacer toutes les catégories existantes',
                    ])
                    ->descriptions([
                        'append' => 'Les catégories importées sont ajoutées comme nouvelles entrées, les existantes sont conservées.',
                        'replace' => 'Supprime d\'abord toutes les catégories du groupe, puis importe. Action irréversible.',
                    ])
                    ->default('append')
                    ->required()
                    ->inline(false),
                Textarea::make('json_data')
                    ->label('Données JSON')
                    ->placeholder('Collez vos données JSON ici…')
                    ->rows(10)
                    ->extraInputAttributes(['class' => 'font-mono text-xs']),
                FileUpload::make('file')
                    ->label('Ou importez un fichier')
                    ->acceptedFileTypes(['application/json'])
                    ->disk('local')
                    ->directory('tree-manager/imports'),
            ])
            ->action(function (array $data): void {
                $payload = $this->resolveImportPayload($data);
                abort_if(! is_array($payload), 422, 'JSON invalide.');

                $payload = ['tree' => $this->resolveCollectionsTreePayload($payload)];

                $mode = ($data['mode'] ?? 'append') === 'replace' ? 'replace' : 'append';
                $stats = $this->importCollectionsPayload($payload, $mode);

                unset($this->collectionsTree);
                Notification::make()
                    ->success()
                    ->title($mode === 'replace' ? 'Catégories remplacées' : 'Catégories ajoutées')
                    ->body($this->formatImageImportStats($stats))
                    ->send();
            });
    }

    public function exportFeaturesAction(): Action
    {
        return Action::make('exportFeatures')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->modalHeading('Exporter les caractéristiques')
            ->modalSubmitActionLabel('Télécharger JSON')
            ->form([
                Textarea::make('json_data')
                    ->label('Données')
                    ->rows(15)
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'font-mono text-xs']),
            ])
            ->fillForm(fn (): array => [
                'json_data' => json_encode(
                    $this->serializeFeaturesTree(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ])
            ->action(fn (): StreamedResponse => $this->streamJson(
                'features-'.now()->format('Ymd-His').'.json',
                $this->serializeFeaturesTree(),
            ))
            ->extraModalFooterActions(fn (): array => [
                Action::make('copyFeaturesJson')
                    ->label('Copier')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->alpineClickHandler(self::clipboardJs()),
            ]);
    }

    public function importFeaturesAction(): Action
    {
        return Action::make('importFeatures')
            ->label('Import')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Importer les caractéristiques')
            ->modalSubmitActionLabel('Importer')
            ->form([
                Textarea::make('json_data')
                    ->label('Données JSON')
                    ->placeholder('Collez vos données JSON ici…')
                    ->rows(10)
                    ->extraInputAttributes(['class' => 'font-mono text-xs']),
                FileUpload::make('file')
                    ->label('Ou importez un fichier')
                    ->acceptedFileTypes(['application/json'])
                    ->disk('local')
                    ->directory('tree-manager/imports'),
            ])
            ->action(function (array $data): void {
                $payload = $this->resolveImportPayload($data);
                abort_if(! is_array($payload), 422, 'JSON invalide.');

                if (! isset($payload['families'])) {
                    $payload = ['families' => $this->flatMapToFamilies($payload)];
                }

                $this->importFeaturesPayload($payload);

                unset($this->featureFamilies);
                Notification::make()->success()->title('Caractéristiques importées')->send();
            });
    }

    private static function clipboardJs(): string
    {
        return <<<'JS'
            const textarea = $el.closest('.fi-modal').querySelector('textarea');
            if (textarea) {
                navigator.clipboard.writeText(textarea.value);
                const label = $el.querySelector('.fi-btn-label');
                if (label) {
                    const prev = label.textContent;
                    label.textContent = 'Copié !';
                    setTimeout(() => label.textContent = prev, 2000);
                }
            }
        JS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function resolveImportPayload(array $data): ?array
    {
        if (filled($data['file'] ?? null)) {
            $path = Storage::disk('local')->path((string) $data['file']);

            return json_decode((string) file_get_contents($path), true);
        }

        if (filled($data['json_data'] ?? null)) {
            return json_decode((string) $data['json_data'], true);
        }

        abort(422, 'Veuillez coller du JSON ou sélectionner un fichier.');
    }

    /**
     * URL réutilisable du visuel de catégorie pour l'export : on privilégie
     * l'URL source d'origine (import distant) et on retombe sur l'URL publique
     * du média local sinon. Chaîne vide si la catégorie n'a pas d'image.
     */
    protected function collectionImageSource(LunarCollection $collection): string
    {
        $media = $collection->getFirstMedia('images');
        if ($media === null) {
            return '';
        }

        return (string) ($media->getCustomProperty('source_url') ?: $media->getFullUrl());
    }

    /**
     * Normalise les formats d'entrée acceptés vers une liste de nœuds
     * `[{name, img_src?, children: [...]}]` :
     *  - `{"tree": [...]}`        → format d'export natif
     *  - `{"categories": [...]}`  → export PrestaShop (porte `img_src`)
     *  - `[{"name": …}, …]`       → liste de nœuds nue
     *  - `{"Parent": ["Enfant"]}` → map plate (legacy)
     *
     * @param  array<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function resolveCollectionsTreePayload(array $payload): array
    {
        foreach (['tree', 'categories'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        // Liste de nœuds nue : au moins une entrée possédant une clé `name`.
        if (array_is_list($payload)) {
            foreach ($payload as $node) {
                if (is_array($node) && array_key_exists('name', $node)) {
                    return array_values($payload);
                }
            }
        }

        return $this->flatMapToTree($payload);
    }

    /**
     * Convert {"Parent": ["Child1", "Child2"], "Child1": ["GrandChild"]} into
     * [{"name": "Parent", "children": [{"name": "Child1", "children": [...]}]}].
     *
     * @param  array<string, list<string>>  $map
     * @return list<array{name: string, children: list<mixed>}>
     */
    private function flatMapToTree(array $map): array
    {
        $allChildren = [];
        foreach ($map as $children) {
            if (is_array($children)) {
                foreach ($children as $child) {
                    $allChildren[(string) $child] = true;
                }
            }
        }

        $build = function (string $name) use (&$build, $map): array {
            $node = ['name' => $name, 'children' => []];
            if (isset($map[$name]) && is_array($map[$name])) {
                foreach ($map[$name] as $child) {
                    $node['children'][] = $build((string) $child);
                }
            }

            return $node;
        };

        $roots = [];
        foreach (array_keys($map) as $key) {
            if (! isset($allChildren[$key])) {
                $roots[] = $build((string) $key);
            }
        }

        return $roots;
    }

    /**
     * Convert {"Marque": ["Bosch", "Makita"]} into the families import format.
     *
     * @param  array<string, list<string>>  $map
     * @return list<array{handle: string, name: string, values: list<array{handle: string, name: string}>}>
     */
    private function flatMapToFamilies(array $map): array
    {
        $families = [];
        foreach ($map as $familyName => $values) {
            $family = [
                'handle' => Str::slug((string) $familyName),
                'name' => (string) $familyName,
                'multi_value' => true,
                'searchable' => false,
                'values' => [],
            ];
            if (is_array($values)) {
                foreach ($values as $valueName) {
                    $family['values'][] = [
                        'handle' => Str::slug((string) $valueName),
                        'name' => (string) $valueName,
                    ];
                }
            }
            $families[] = $family;
        }

        return $families;
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

        // Sélection d'export : vide = tout exporter, sinon on ne garde que les
        // catégories cochées (et leurs ancêtres, pour préserver l'arborescence).
        $selected = array_map('intval', $this->collectionExportSelection);
        $selectAll = $selected === [];

        $serialize = function (?int $parentId) use (&$serialize, $byParent, $selected, $selectAll): array {
            /** @var SupportCollection<int, LunarCollection> $group */
            $group = $byParent->get($parentId, collect());

            return $group->reduce(function (array $carry, LunarCollection $c) use (&$serialize, $selected, $selectAll): array {
                $children = $serialize($c->id);

                // On garde le nœud s'il est coché, ou si un de ses descendants l'est.
                if (! $selectAll && ! in_array((int) $c->id, $selected, true) && $children === []) {
                    return $carry;
                }

                $carry[] = [
                    'id' => $c->id,
                    'name' => (string) ($c->translateAttribute('name', self::LOCALE) ?? ''),
                    'img_src' => $this->collectionImageSource($c),
                    'description' => $c->translateAttribute('description', self::LOCALE),
                    'meta_title' => $c->translateAttribute('meta_title', self::LOCALE),
                    'meta_description' => $c->translateAttribute('meta_description', self::LOCALE),
                    'children' => $children,
                ];

                return $carry;
            }, []);
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
    /**
     * @param  array<string, mixed>  $payload
     * @return array{imported:int, skipped:int, errors:int}
     */
    public function importCollectionsPayload(array $payload, string $mode = 'append'): array
    {
        /** @var list<array{id:int, url:string, name:string}> $pendingImages */
        $pendingImages = [];

        DB::transaction(function () use ($payload, $mode, &$pendingImages): void {
            // Mode « écraser » : on purge d'abord toutes les catégories du groupe
            // (pivots détachés au préalable pour éviter la FK 1451), puis on importe.
            if ($mode === 'replace') {
                $this->purgeCollectionsForCurrentGroup();
            }

            // En modes « ajouter » comme « écraser », on crée systématiquement de
            // NOUVEAUX nœuds (les IDs entrants sont ignorés) : cela évite d'écraser
            // par effet de bord des catégories d'un autre groupe et garantit un
            // comportement additif prévisible.
            $walk = function (array $nodes, ?int $parentId) use (&$walk, &$pendingImages): void {
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

                    $collection = new LunarCollection([
                        'collection_group_id' => $this->collectionGroupId,
                        'type' => 'static',
                        'sort' => 'custom',
                        'attribute_data' => collect(),
                    ]);

                    $this->persistCollectionAttributes($collection, $data);

                    if ($parentId === null) {
                        $collection->saveAsRoot();
                    } else {
                        /** @var LunarCollection $parent */
                        $parent = LunarCollection::query()->findOrFail($parentId);
                        $collection->appendToNode($parent)->save();
                    }

                    // Les téléchargements distants sont différés APRÈS le commit :
                    // une centaine d'appels HTTP dans la transaction la garderait
                    // ouverte plusieurs minutes (locks + risque de timeout).
                    $imgSrc = trim((string) ($node['img_src'] ?? ''));
                    if ($imgSrc !== '') {
                        $pendingImages[] = [
                            'id' => (int) $collection->id,
                            'url' => $imgSrc,
                            'name' => $data['name'],
                        ];
                    }

                    if (! empty($node['children']) && is_array($node['children'])) {
                        $walk($node['children'], $collection->id);
                    }
                }
            };

            $walk($payload['tree'] ?? [], null);
            LunarCollection::fixTree();
        });

        $stats = $this->importCollectionImages($pendingImages);

        // Import de masse : vide le cache de nav une fois après commit
        // (l'arbre a pu changer massivement, hors events unitaires).
        Cache::forget(StorefrontServiceProvider::NAV_CACHE_KEY);

        return $stats;
    }

    /**
     * Télécharge les `img_src` des catégories importées dans la médiathèque
     * (dossier « Catégories », créé au besoin) puis rattache le fichier à la
     * catégorie Lunar.
     *
     * La déduplication est portée par MediaLibraryImporter : `source_url` +
     * `sha1` en `custom_properties`. Ré-importer le même JSON ne recrée donc
     * pas de doublon dans la médiathèque — le média existant est réutilisé.
     *
     * @param  list<array{id:int, url:string, name:string}>  $pending
     * @return array{imported:int, skipped:int, errors:int}
     */
    protected function importCollectionImages(array $pending): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        if ($pending === []) {
            return $stats;
        }

        $importer = app(MediaLibraryImporter::class);
        $folder = $importer->resolveFolder(self::CATEGORY_MEDIA_FOLDER, 'Catégories');

        foreach ($pending as $item) {
            try {
                $media = $importer->importIntoFolder($folder, $item['url'], $item['name']);
                if ($media === null) {
                    $stats['errors']++;

                    continue;
                }

                /** @var LunarCollection|null $collection */
                $collection = LunarCollection::query()->find($item['id']);
                if ($collection === null) {
                    $stats['errors']++;

                    continue;
                }

                if ($this->attachLibraryMediaToCollection($collection, $media, $item['url'])) {
                    $stats['imported']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                report($e);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Copie le fichier du média bibliothèque dans la collection Spatie `images`
     * de la catégorie — c'est celle que lit le storefront
     * (`getFirstMediaUrl('images', 'small')`), et elle seule génère les
     * conversions Lunar. Idempotent via `source_url`.
     */
    protected function attachLibraryMediaToCollection(LunarCollection $collection, Media $media, string $sourceUrl): bool
    {
        $already = $collection->getMedia('images')
            ->contains(fn (Media $m): bool => (string) $m->getCustomProperty('source_url') === $sourceUrl);

        if ($already) {
            return false;
        }

        $path = $media->getPath();
        if (! is_file($path)) {
            return false;
        }

        $collection->thumbnail?->delete();

        $collection
            ->addMedia($path)
            ->preservingOriginal()
            ->usingFileName($media->file_name)
            ->withCustomProperties(['primary' => true, 'source_url' => $sourceUrl])
            ->toMediaCollection('images');

        return true;
    }

    /**
     * @param  array{imported:int, skipped:int, errors:int}  $stats
     */
    protected function formatImageImportStats(array $stats): ?string
    {
        if (array_sum($stats) === 0) {
            return null;
        }

        $parts = ["{$stats['imported']} image(s) importée(s)"];
        if ($stats['skipped'] > 0) {
            $parts[] = "{$stats['skipped']} déjà présente(s)";
        }
        if ($stats['errors'] > 0) {
            $parts[] = "{$stats['errors']} en échec";
        }

        return implode(' · ', $parts).'.';
    }

    /**
     * Supprime toutes les catégories du groupe courant (mode import « écraser »).
     * On détache d'abord les pivots (FK NO ACTION → sinon 1451), puis on efface
     * en masse. Cf. App\Observers\CollectionDeleteObserver pour le même problème
     * côté suppression unitaire.
     */
    private function purgeCollectionsForCurrentGroup(): void
    {
        $ids = LunarCollection::query()
            ->where('collection_group_id', $this->collectionGroupId)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        foreach ([
            'lunar_collection_customer_group',
            'lunar_collection_product',
            'lunar_collection_discount',
            'lunar_brand_collection',
            'pko_feature_family_collection',
        ] as $table) {
            DB::table($table)->whereIn('collection_id', $ids)->delete();
        }

        LunarCollection::query()
            ->where('collection_group_id', $this->collectionGroupId)
            ->delete();
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
        $rootCats = count($this->collectionsTree);
        $totalCats = $this->countNodes($this->collectionsTree);
        $families = count($this->featureFamilies);
        $totalValues = $this->countValues($this->featureFamilies);

        $catLabel = "{$rootCats} racine, {$totalCats} au total";
        $featLabel = "{$families} familles, {$totalValues} valeurs au total";

        return match ($this->activeTab) {
            'categories' => $catLabel,
            'features' => $featLabel,
            default => "Catégories : {$catLabel} — Caractéristiques : {$featLabel}",
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function countNodes(array $nodes): int
    {
        $count = count($nodes);
        foreach ($nodes as $node) {
            $count += $this->countNodes($node['children'] ?? []);
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $families
     */
    private function countValues(array $families): int
    {
        return array_sum(array_map(fn (array $f): int => count($f['values'] ?? []), $families));
    }
}
