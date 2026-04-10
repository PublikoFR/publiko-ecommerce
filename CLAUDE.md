# CLAUDE.md — MDE Distribution (back-office)

Guide à destination de Claude Code pour intervenir sur le projet `ecom-laravel`.

## Mission du projet

Back-office e-commerce remplaçant PrestaShop 8 pour **MDE Distribution** (distributeur B2B matériaux de construction, domotique, portails, volets, automatismes). Phase 1 = back-office uniquement. Le front-office, les emails, les paiements et les modules métier MDE (FAB-DIS, SIRET, pricing B2B, enrichissement IA) sont **hors périmètre** et arriveront en phase 2 sous `packages/mde/*`.

Cahier des charges complet : `cahier-des-charges-mde-laravel.md` à la racine — référence contractuelle.

## Stack

| Composant | Version |
|---|---|
| PHP | 8.3+ (conteneur Sail) |
| Laravel | 11.x |
| Lunar Core / Admin | 1.x (`lunarphp/lunar`) |
| Filament | 3.x (fourni par Lunar Admin) |
| Filament Shield | 3.x (`bezhansalleh/filament-shield`) |
| Livewire | 3.x |
| MySQL | 8.x (Sail) |
| Redis | 7.x (queue + cache + session) |
| Mailpit | dev mail catcher |
| Environnement | Laravel Sail (Docker) |

## Règles non-négociables

1. **Ne jamais modifier `vendor/lunarphp/*`.** Aucune exception. Les mises à jour Lunar doivent rester non-régressives. Toute personnalisation passe par les mécanismes officiels.
2. **Toujours `declare(strict_types=1);`** en tête de fichier PHP.
3. **PSR-12** appliqué via Laravel Pint (`make lint`).
4. **Conventions Laravel** pour nommage modèles, migrations, factories, seeders.
5. Les migrations custom MDE vivent dans `database/migrations/` et utilisent le préfixe de table `mde_` (en phase 2).
6. Les futurs modules métier vivent dans `packages/mde/*` et s'enregistrent comme **Filament Plugin**, pas en modifiant le core.

## Mécanismes d'extension

Trois niveaux autorisés, dans cet ordre de préférence (du plus léger au plus packagé) :

### Niveau 1 — `LunarPanel::panel()` dans `AppServiceProvider::register()`

Utiliser pour : ajouter des pages/resources/widgets custom, configurer navigation groups, brand, path admin, plugins.

Fichier concerné : `app/Providers/AppServiceProvider.php`.

```php
use Filament\Panel;
use Lunar\Admin\Support\Facades\LunarPanel;

LunarPanel::panel(function (Panel $panel): Panel {
    return $panel
        ->path('admin')
        ->brandName('MDE Distribution')
        ->navigationGroups([
            'Catalogue',
            'Commandes',
            'Clients',
            'Marketing',
            'Configuration',
        ])
        ->plugin(FilamentShieldPlugin::make());
})->register();
```

### Niveau 2 — `LunarPanel::extensions()` avec `ResourceExtension`

Utiliser pour : ajouter des champs à une ressource existante (form/table), ajouter relation managers ou pages.

Classes d'extension dans `app/Admin/Filament/Extensions/` (à créer quand nécessaire).

```php
LunarPanel::extensions([
    \Lunar\Admin\Filament\Resources\ProductResource::class => \App\Admin\Filament\Extensions\MdeProductExtension::class,
]);
```

### Niveau 3 — Filament Plugin dans `packages/mde/<module>/` (phase 2+)

Utiliser pour : fonctionnalité packagée réutilisable (ex : import FAB-DIS, validation SIRET). Le plugin expose un `FilamentPlugin` qui s'enregistre via `->plugin(new ModuleMdePlugin())` dans le panel.

## Arborescence clé

```
app/
├── Models/User.php                    ← trait LunarUser + LunarUserInterface
├── Providers/AppServiceProvider.php   ← LunarPanel::panel() + Shield
└── Admin/Filament/Extensions/         ← ResourceExtensions MDE (phase 2+)

config/
├── lunar/*.php                        ← configs Lunar publiées (rarement modifiées)
├── lunar.php                          ← config principale Lunar
└── filament-shield.php                ← config Shield

database/
├── migrations/                        ← Lunar publiées + permission_tables
└── seeders/
    ├── DatabaseSeeder.php
    └── Mde*Seeder.php                 ← 10 seeders thématiques MDE

packages/                              ← modules MDE (phase 2+)
└── mde/

resources/views/vendor/lunar/          ← overrides Blade — à minimiser
```

## Commandes Make (raccourcis Sail)

| Commande | Effet |
|---|---|
| `make install` | Première installation complète (build + migrate + lunar:install + shield + seed) |
| `make up` / `make down` | Démarrer / arrêter Sail |
| `make shell` | Shell dans le conteneur applicatif |
| `make migrate` | Exécuter migrations |
| `make fresh` | `migrate:fresh --seed` (reset DB complet) |
| `make seed` | Lancer les seeders MDE |
| `make test` | Suite PHPUnit |
| `make lint` | Laravel Pint (PSR-12) |
| `make artisan CMD='...'` | Commande artisan arbitraire |
| `make composer CMD='...'` | Commande composer arbitraire |

## Lunar : points d'attention

- **Prix en cents** : tous les prix sont stockés en entier (cents/centimes). `19900` = 199,00 €.
- **`attribute_data`** : champs i18n des produits/collections stockés en JSON via `Lunar\FieldTypes\*` (`Text`, `TranslatedText`, `Number`, `Dropdown`…). Toujours passer une collection de FieldTypes, jamais de strings bruts.
- **Staff vs User** : le staff admin est une table séparée (`lunar_staff`) — **ne pas confondre** avec la table `users` (clients / customers futurs).
- **`php artisan lunar:install`** : crée le premier staff + seed Lunar de base. À ré-exécuter après un `migrate:fresh`.
- **`kalnoy/nestedset`** est épinglé à `6.0.7` — les versions ultérieures utilisent `whenBooted()` qui n'existe pas en Laravel 11.

## Filament Shield

- Panel ID Lunar = `admin`
- Installation : `make install` lance déjà `shield:install admin` et `shield:generate --all --panel=admin`
- Rôles suggérés : `super_admin`, `catalogue_manager`, `sav`, `lecture_seule`
- Les policies sont générées automatiquement pour toutes les ressources Lunar découvertes

## Tests

- PHPUnit 11. Suite : `make test`
- Tests feature minimaux fournis :
  - `tests/Feature/AdminPanelAccessTest.php` — guard redirect, login page OK
  - `tests/Feature/SeedersTest.php` — vérifie 50 produits / ≥3 collections / 2 groupes clients / 10 commandes / ≥5 marques
- Ajouter des tests pour tout pipeline critique : calcul de prix, stock, cycle de vie commande.

## Ce qu'il faut **éviter**

- Créer des Filament Resources custom en phase 1 : Lunar en fournit déjà pour produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff.
- Toucher aux fichiers `config/lunar/*.php` sans raison — les conserver proches du default facilite les mises à jour.
- Modifier les migrations Lunar publiées — si besoin de champs additionnels, créer une migration MDE dédiée qui ajoute des colonnes (`Schema::table`).

## Documentation externe

- Lunar : <https://docs.lunarphp.io>
- Filament : <https://filamentphp.com/docs/3.x>
- Laravel 11 : <https://laravel.com/docs/11.x>
- Filament Shield : <https://filamentphp.com/plugins/bezhansalleh-shield>

## Conventions Git

- Branches : `main` (prod), `develop` (intégration), `feature/*` par module
- Conventional Commits recommandés (`feat:`, `fix:`, `refactor:`, `chore:`…)
- Pas de mention IA dans les messages de commit
