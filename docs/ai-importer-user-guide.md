# AI Importer — guide staff back-office

Ce document s'adresse aux équipes **back-office** qui gèrent l'import de catalogues fournisseurs. Il décrit **comment** utiliser le module AI Importer au quotidien, pas comment il est construit (voir `docs/technical-choices.md` et `docs/ai-importer-migration-plan.md` pour la partie développeur).

Accès : admin → **Imports**.

---

## 1. Les 3 écrans

| Écran | URL | À quoi ça sert |
|---|---|---|
| **Imports** | `/admin/import-jobs` | Liste des imports en cours, historique, création d'un nouveau job |
| **Configurations import** | `/admin/importer-configs` | Gérer les mappings par fournisseur (Somfy, Bubendorff, etc.) |
| **Configurations LLM** | `/admin/llm-configs` | Clés API des modèles IA (Claude, OpenAI) utilisés par certaines actions |

---

## 2. Créer une configuration fournisseur

Une **configuration** décrit comment lire un fichier Excel d'un fournisseur : quelles feuilles, quelles colonnes mapper vers quels champs Lunar, quelles transformations appliquer.

1. *Imports → Configurations import → Nouveau*.
2. **Nom** : `somfy` (court, unique). **Fournisseur** : libre.
3. Onglet **Éditeur visuel** :
   - Indique **Feuille principale** (ex. `B01_COMMERCE`).
   - Ajoute les **Feuilles** secondaires si le fichier a des données 1-N (images, logistique…). Coche « Ligne 1 = en-têtes » si la première ligne contient les noms de colonnes.
   - Dans **Colonnes mappées**, ajoute une entrée par champ produit à produire. Chaque entrée a :
     - **Clé de sortie** : un des mots-clés reconnus (`reference`, `name`, `price_cents`, `stock`, `ean`, `brand_name`, `collections`, `features`, `images`, `videos`…). La liste complète est dans `docs/technical-choices.md` §7.quinquies.9.
     - **Colonne source** : nom d'en-tête ou lettre (`M`, `AA`).
     - **Feuille** : nom de la feuille (sinon feuille principale).
     - **Pipeline d'actions** : suite de transformations appliquées à la valeur lue (ex. `math ×1.2` → `round 2` pour passer du HT au TTC arrondi).
4. Onglet **JSON brut** : si tu préfères coller/modifier directement le JSON, la même config est éditable en texte.
5. **Exporter** une config depuis la liste (bouton *Exporter JSON*) produit un fichier téléchargeable, utile pour versionner ou partager.

### Migrer une config PrestaShop existante

Les configs du module PS `publikoaiimporter` sont importables telles quelles :

```bash
make artisan CMD='ai-importer:import-ps-config /chemin/config.json --name=somfy --supplier=Somfy'
```

Le format JSON est déjà compatible (pipeline `actions[]` v1). La commande lifte automatiquement les vieilles configs `action:{}` (objet unique) vers `actions:[{}]`.

---

## 3. Tester une config sans lancer d'import

Avant de créer un vrai job sur 50 000 lignes, vérifie le mapping sur les 5 premières lignes :

```bash
make artisan CMD='ai-importer:preview-config somfy /chemin/catalog.xlsx --rows=5'
```

La commande affiche un tableau des valeurs mappées. Utile pour valider qu'un `multiply`, un `template` ou un `feature_build` produit bien ce qu'on attend.

---

## 4. Les 18 actions disponibles

Chaque action lit la valeur courante (ou certaines colonnes du row source), retourne une nouvelle valeur, et passe à la suivante.

| Catégorie | Actions | Note |
|---|---|---|
| **Calcul** | `math`, `round` | `math` = ×/÷/+/−, paramètre `operation` + `value` |
| **Texte** | `change_case` (upper/lower/capitalize), `trim`, `truncate`, `slugify`, `replace`, `regex_replace` | |
| **Combinaison** | `concat`, `template`, `copy` | `template` = chaîne avec placeholders `{key}` |
| **Lookup** | `map` | Table source → cible, `multi_value: true` pour traiter CSV |
| **Date** | `date_format` | Formats `Y-m-d`, `d/m/Y`… |
| **Validation** | `validate_ean13` | Retourne la chaîne si checksum EAN valide, sinon vide |
| **Relation 1-N** | `multiline_aggregate` | Lit une feuille secondaire, agrège selon `method` (concat/count/first/last/json_array) |
| **IA** | `llm_transform` | Prompt + colonnes → réponse Claude/OpenAI |
| **Caractéristiques catalogue** | `feature_build` | Construit le hash `{family_handle: [value_handle]}` attendu par le writer. Les features atterrissent dans `pko_feature_values` (pas dans `attribute_data` Lunar) |

### Exemple de pipeline price HT → TTC

```json
"price_cents": {
  "col": "PRIX_NET",
  "actions": [
    { "type": "math", "operation": "multiply", "value": 1.2 },
    { "type": "math", "operation": "multiply", "value": 100 },
    { "type": "round", "decimals": 0 }
  ]
}
```

Le writer attend les prix en **cents entiers** : on multiplie par 1,2 (TVA 20 %), puis par 100 pour passer en centimes, puis on arrondit.

### Exemple `feature_build`

```json
"features": {
  "actions": [
    {
      "type": "feature_build",
      "families": {
        "marque":       { "col": "MARQUE" },
        "applications": { "col": "USAGE", "multi_value": true, "separator": "|" },
        "matiere":      { "col": "MATERIAL", "values_map": { "alu": "aluminium", "pvc": "pvc" } }
      }
    }
  ]
}
```

Les handles sortis (`somfy`, `residentiel`, `aluminium`…) doivent **exister au préalable** dans *Catalogue → Caractéristiques* (Publiko Tree Manager). Le writer associe le produit aux valeurs correspondantes via `Features::syncByHandles()`.

---

## 5. Lancer un import

1. *Imports → Nouveau* (un gros bouton "Nouvel import" en haut à droite de la liste).
2. **Configuration** : choisir celle du fournisseur.
3. **Fichier** : glisser un XLSX/XLS/CSV (max 100 Mo).
4. **Taille chunk** : 500 suffit dans la plupart des cas. Augmenter (2000+) pour accélérer sur gros fichiers si le serveur a la RAM.
5. **Limite de lignes** : laisser vide pour tout importer. Mettre 50 pour un test.
6. **Politique d'erreur** :
   - *Ignorer* — skip la ligne en erreur, continue (par défaut).
   - *Arrêter* — stoppe l'import, les lignes déjà importées restent.
   - *Rollback* — restaure un snapshot pris avant import, comme si rien ne s'était passé.
7. **Programmer l'import** (optionnel) : l'écriture Lunar attend cette date/heure (dispatché toutes les 5 min par le scheduler). Laisser vide pour lancer immédiatement le parse.
8. Valider → le **parse** démarre en file d'attente.

### Flux complet

1. **Parse** → lit le fichier, applique le pipeline, remplit la table de staging. Statut `parsing` → `parsed`.
2. **Preview** (écran détail du job, onglet *Staging*) : vérifier les lignes, filtrer par statut, éditer une ligne si besoin, marquer bulk comme *validées* ou *ignorées*.
3. **Lancer l'import Lunar** → bouton vert en haut à droite. Écrit les produits dans Lunar, les associe aux collections et caractéristiques. Statut `importing` → `imported`.
4. **Rollback** (si besoin) → bouton rouge. Restaure les produits à leur état avant l'import.

### Que fait chaque statut ?

| Statut parse | Signification |
|---|---|
| `pending` | Pas encore démarré |
| `parsing` | En cours (barre de progression live) |
| `paused` / `error` | Interrompu — bouton *Relancer le parse* |
| `parsed` | Parse terminé, staging prêt |
| `cancelled` | Annulé manuellement |

| Statut import | Signification |
|---|---|
| `pending` | Parse terminé, en attente de lancement |
| `scheduled` | Programmé — sera lancé par le scheduler |
| `importing` | Écriture Lunar en cours |
| `imported` | Import terminé — rollback possible |
| `error` | Erreur — bouton *Reprendre l'import* (continue sur les lignes restantes) |
| `rolled_back` | Rollback effectué |

---

## 6. Les images et les vidéos

### Images

- Clé staging `images` : array d'URLs (ou CSV).
- Le writer télécharge chaque URL via Spatie MediaLibrary dans la collection media par défaut (Lunar).
- Idempotent : si l'URL a déjà été téléchargée (via la propriété custom `source_url`), elle est skippée.
- La première URL devient automatiquement la **miniature** (`primary=true`).

Exemple de pipeline :

```json
"images": {
  "col": "IMAGE_URL",
  "sheet": "B02_LOGISTIQUE",
  "actions": [
    { "type": "multiline_aggregate", "sheet": "B02_LOGISTIQUE", "method": "json_array",
      "filter_type": "CODE_IMAGE", "columns": ["URL"] }
  ]
}
```

(Le writer accepte aussi un simple CSV d'URLs.)

### Vidéos (phase préliminaire)

- Clé staging `videos` : array d'URLs YouTube/Vimeo.
- Les URLs sont stockées pour l'instant dans `Product.attribute_data.videos` sous forme de chaîne CSV.
- Une table custom dédiée (titre, thumbnail custom, ordre, provider) est prévue pour une phase ultérieure — le format actuel est volontairement minimal.

---

## 7. Les clés API LLM

- *Imports → Configurations LLM → Nouveau*.
- **Provider** : Claude (Anthropic) ou OpenAI.
- **Clé API** : la clé est **chiffrée** en base (cast Laravel `encrypted` avec `APP_KEY`). Pas de clair en DB, ni dans les dumps.
- Coche **Configuration par défaut** sur la clé principale — les actions `llm_transform` sans `llm_config_id` l'utiliseront.

---

## 8. Rollback et snapshots

À chaque import, un **snapshot JSON gzippé** est stocké dans `storage/app/ai-importer/backups/`. Il contient l'état *avant* import des produits touchés (+ leurs variants, prix, pivots collections).

- Bouton **Rollback** sur un job `imported` → restore en transaction.
- Les backups restent sur disque tant que tu ne les supprimes pas à la main — prévoir un nettoyage périodique si ça s'accumule.
- Le format est volontairement lisible : tu peux l'inspecter avec `zcat storage/app/ai-importer/backups/job_xxx.json.gz | jq .` avant de déclencher un rollback.

---

## 9. Dépannage

| Symptôme | Piste |
|---|---|
| Parse fini mais `staging_count = 0` | `primary_sheet` ne correspond à aucune feuille du fichier |
| Beaucoup de `error` sur staging avec "reference manquante" | La clé de sortie `reference` n'est pas mappée (ou sa colonne source est vide) |
| Features non associées au produit | Vérifier que les **handles** produits par `feature_build` existent dans Tree Manager *avant* l'import |
| Prix affichés divisés par 100 | Oublié de multiplier par 100 dans le pipeline pour passer en cents |
| Images pas téléchargées | Vérifier que les URLs sont HTTPS valides + que le serveur peut sortir (firewall / proxy) |
| Progress bar bloquée à 0 % | Vérifier que le worker queue tourne : `make shell` puis `php artisan queue:work ai-importer-parse` |
| Parse OOM sur gros XLSX | Augmenter `chunk_size` du job à 2000+ (le parser bascule en mode streaming dès qu'un XLSX dépasse 5 Mo) |

---

## 10. Commandes utiles

```bash
# Tester une config (dry-run)
make artisan CMD='ai-importer:preview-config somfy /tmp/catalog.xlsx --rows=10'

# Migrer une config PrestaShop
make artisan CMD='ai-importer:import-ps-config /tmp/ps-config.json --name=somfy --supplier=Somfy --replace'

# Lancer manuellement le scheduler (normalement automatique toutes les 5 min)
make artisan CMD='ai-importer:run-scheduled --dry'   # liste ce qui tournerait
make artisan CMD='ai-importer:run-scheduled'         # exécute

# Workers queue (dans le conteneur app)
php artisan queue:work ai-importer-parse
php artisan queue:work ai-importer-import
```
