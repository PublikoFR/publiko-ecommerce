# pko/lunar-ai-filament — actions Filament IA

Package `packages/pko/ai-filament/` (namespace `Pko\AiFilament\`), ServiceProvider : `AiFilamentServiceProvider` (sans migrations ni config).

### Rôle

Expose des actions Filament prêtes à l'emploi qui appellent `pko/ai-core`. Dépend de `filament/forms` + `pko/ai-core`. Réutilisable sur n'importe quel champ de formulaire Filament.

### `GenerateAiAction`

**API générique réutilisable `forField()`** — crée un bouton "Générer avec l'IA" pour n'importe quel champ Filament :

```php
GenerateAiAction::forField(
    targetField: 'seoTitle',              // clé du champ form
    prompt: 'Génère un meta title SEO…',  // prompt envoyé au LLM
    contextProperties: ['productName'],   // props Livewire injectées en contexte
    emptyCheckProperty: null,             // défaut : targetField
    llmConfigName: null,                  // null = LlmConfig::default()
    htmlMode: false,                      // true = toggle Aperçu/Code ; false = textarea brut
    label: 'Générer avec l\'IA',
    icon: 'heroicon-o-sparkles',
    modalHeading: 'Aperçu — Contenu généré par l\'IA',
    successTitle: 'Contenu généré',
    using: fn (Action $a) => $a->color('primary'),  // customizer optionnel (color, size, tooltip…)
): array  // 3 actions → passer à ->hintActions()
```

**Preset `descriptionActions()`** : appel pré-configuré de `forField()` pour la description produit HTML (inputs `productName/sku/shortDesc`, mode HTML).

### UX universelle (non-négociable)

- **Champ cible vide** → direct-replace sans modal (icône sparkles)
- **Champ cible non-vide** → modal preview (icône sparkles)
  - En `htmlMode: true` : toggle Aperçu/Code, aperçu rendu via `prose` avec scroll interne `max-height:60vh` (style inline pour contourner JIT Tailwind)
  - Bouton submit aligné à droite (`modalFooterActionsAlignment(Alignment::End)`)
  - Modal large (`5xl`)
- **Bouton crayon (toujours visible)** → modal d'édition avec :
  - Select **Modèle LLM** (peuplé depuis `LlmConfig::where('active', true)`, défaut = `LlmConfig::default()` ou `$llmConfigName`)
  - CheckboxList **Paramètres de la page transmis au prompt** (labels humanisés depuis `$contextProperties`, tous cochés par défaut — seuls les cochés sont injectés dans les inputs du LLM)
  - Textarea **Prompt** (pré-rempli avec le prompt par défaut)
  - Bouton "Générer avec l'IA" → appelle le LLM avec le config + le prompt + les paramètres sélectionnés
  - Preview + "Appliquer"
  - Tooltip "Modifier le prompt".
- **Rendu "split button"** : les 3 actions sont rendues comme un seul bouton scindé en deux via `extraAttributes` + CSS `packages/pko/ai-filament/resources/css/split-button.css` (enregistrée par `AiFilamentServiceProvider` via `FilamentAsset::register()`). Alignement à droite obtenu en déclarant un label réel sur le champ cible puis en appliquant `->hiddenLabel()` (déclenche le `justify-end` natif du field-wrapper Filament).
- Fences markdown ```html… stripées côté serveur avant affichage

### Détection d'emptiness

Le `->visible()` lit `$livewire->{$emptyCheckProperty}` (propriété Livewire publique de la page) **et non** l'état Filament. TipTap stocke sa state interne en JSON ProseMirror (array) ; seule la prop Livewire brute est une string fiable. Par défaut `$emptyCheckProperty = $targetField` → fonctionne tant que la page Livewire déclare une prop publique du même nom.

### Injection de contexte

Chaque nom listé dans `contextProperties` est lu sur `$livewire->{$property}` et transmis au LLM via `LlmManager::forConfig()->transform($prompt, $inputs)`. Les providers (Claude/OpenAI) sérialisent les inputs en JSON à la suite du prompt.

### Permission Shield

`generate_ai_content` (guard `staff`) — créée et assignée à `super_admin` via `AiPermissionsSeeder`. Vérifiée dans `->visible()` de chaque action.

### Intégration phase 1

`EditProductUnified::descriptionForm()` — `TiptapEditor::make('longDesc')->hintActions(GenerateAiAction::descriptionActions())`.

Pour ajouter un bouton ailleurs : utiliser `forField()` directement, ou créer un preset dédié dans la classe `GenerateAiAction` si le cas d'usage est récurrent.

---

