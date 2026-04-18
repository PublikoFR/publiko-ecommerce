# Cahier des Charges — Plateforme E-Commerce MDE Distribution
## Stack : Laravel + Lunar PHP + Filament

**Version** : 1.0  
**Date** : Avril 2026  
**Auteur** : Publiko  
**Périmètre** : Back-office uniquement — Front-office exclu de cette phase  

---

## 1. Contexte & Objectifs

### 1.1 Contexte

MDE Distribution est un distributeur B2B spécialisé dans les matériaux de construction, la domotique et les produits du bâtiment (portes, fenêtres, volets, portails, clôtures, automatismes). La clientèle se compose de particuliers et d'installateurs professionnels.

La plateforme PrestaShop 8 actuelle présente des limitations architecturales majeures rendant le développement et la maintenance coûteux. L'objectif est de migrer vers une stack Laravel moderne, maintenable, extensible et IA-ready.

### 1.2 Objectifs

- Remplacer le back-office PrestaShop par un back-office Filament/Lunar production-ready
- Conserver 100% des fonctionnalités e-commerce réellement utilisées par MDE Distribution
- Supprimer tout ce qui n'est pas utilisé (multistore, blog, modules inutiles)
- Préparer l'architecture pour l'intégration future des modules métier MDE (`packages/mde/*`)
- Garantir la non-régression lors des mises à jour Lunar/Filament

### 1.3 Ce qui est hors périmètre (phase 1)

- Front-office client (catalogue, panier, commande, compte client)
- Modules métier custom MDE (import FAB-DIS, validation SIRET, enrichissement IA, pricing B2B avancé)
- Paiement en ligne
- Emails transactionnels
- API publique

---

## 2. Stack Technique

### 2.1 Versions cibles

| Composant | Version |
|---|---|
| PHP | 8.3+ |
| Laravel | 11.x |
| Lunar Core | 1.x (lunarphp/core) |
| Lunar Admin | 1.x (lunarphp/lunar) |
| Filament | 3.x |
| Livewire | 3.x |
| Alpine.js | 3.x |
| MySQL | 8.0+ |
| Redis | 7.x (queues, cache) |

### 2.2 Conventions obligatoires

- **PSR-12** pour le style de code PHP
- **Strict typing** (`declare(strict_types=1)`) dans tous les fichiers
- **Laravel conventions** : nommage des modèles, migrations, factories, seeders
- **Lunar conventions** : ne jamais modifier les fichiers du vendor, utiliser exclusivement les mécanismes d'extension fournis
- **Filament conventions** : ResourceExtension pour étendre les ressources existantes, Filament Plugin pour les modules packagés
- Toute personnalisation du panel passe par `LunarPanel::panel()` dans `AppServiceProvider`
- Tous les modules MDE futurs vivront dans `packages/mde/` et s'enregistreront en tant que Filament Plugins

### 2.3 Structure des répertoires

```
app/
├── Providers/
│   └── AppServiceProvider.php      ← enregistrement LunarPanel + extensions
├── Models/                          ← extensions des modèles Lunar si nécessaire
└── Admin/
    └── Filament/
        └── Extensions/              ← ResourceExtensions MDE phase 2+

packages/                            ← modules MDE (phase 2+, hors périmètre)
└── mde/
    ├── fabdis-import/
    ├── b2b-pricing/
    ├── siret-validation/
    └── product-enrichment/

config/
└── lunar.php                        ← configuration Lunar

database/
├── migrations/                      ← uniquement les migrations hors Lunar
└── seeders/

resources/
└── views/
    └── vendor/lunar/                ← overrides Blade si nécessaire (à minimiser)
```

---

## 3. Architecture d'Extension

### 3.1 Principe fondamental

**Ne jamais modifier les fichiers du vendor Lunar.** Toute personnalisation utilise les mécanismes officiels :

```
Niveau 1 : LunarPanel::panel() → ajouter pages, resources, navigation groups
Niveau 2 : LunarPanel::extensions() → étendre resources existantes (ResourceExtension)
Niveau 3 : Filament Plugin → fonctionnalités packagées (modules MDE)
```

### 3.2 Enregistrement des extensions

```php
// app/Providers/AppServiceProvider.php
use Lunar\Admin\Support\Facades\LunarPanel;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(function ($panel) {
            return $panel
                ->path('admin')
                ->navigationGroups([
                    'Catalogue',
                    'Commandes',
                    'Clients',
                    'Marketing',
                    'Configuration',
                ]);
        })->register();
    }
}
```

### 3.3 Extension d'une ressource existante

```php
// Exemple : ajouter des champs à la fiche produit
class MdeProductExtension extends \Lunar\Admin\Support\Extending\ResourceExtension
{
    public function extendForm(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            ...$form->getComponents(withHidden: true),
            // champs additionnels MDE
        ]);
    }
}
```

---

## 4. Fonctionnalités Back-Office

### 4.1 Gestion du Catalogue Produits

#### 4.1.1 Produits

**Inclus :**
- Création, édition, duplication, archivage de produits
- Statut (publié / brouillon / archivé)
- Nom, description courte, description longue (rich text WYSIWYG)
- Référence SKU, code EAN/GTIN, MPN
- Prix HT, prix de référence (barré), coût d'achat
- TVA : taux applicable par produit (5.5%, 10%, 20%)
- Poids, dimensions (longueur, largeur, hauteur) pour le calcul de livraison
- Images : upload multiple, réordonnancement drag & drop, image principale
- Statut de stock : en stock / rupture / sur commande
- Quantité en stock
- Seuil d'alerte stock
- Associations produits : produits similaires, accessoires, pièces détachées

**Exclu :**
- Produits téléchargeables
- Produits virtuels
- Abonnements

#### 4.1.2 Variantes

- Gestion des options de variante (ex : couleur, dimension, finition)
- Valeurs par option (ex : Blanc, Gris anthracite, RAL sur demande)
- Génération automatique des combinaisons
- Prix spécifique par variante (supplément ou prix fixe)
- Stock indépendant par variante
- SKU/EAN par variante
- Image par variante

#### 4.1.3 Attributs personnalisés

- Création d'attributs libres sans migration de schéma (feature native Lunar)
- Types disponibles : texte court, texte long, nombre, liste déroulante, case à cocher, date
- Assignation des attributs par type de produit
- Affichage des attributs dans la fiche produit admin

#### 4.1.4 Types de produits

- Définition de types produits (ex : Portail coulissant, Volet roulant, Automatisme)
- Chaque type porte sa propre configuration d'attributs
- Assignation du type lors de la création du produit

#### 4.1.5 Collections & Catégories

- Arborescence de collections hiérarchique (illimitée)
- Groupes de collections (ex : "Navigation principale", "Promotions")
- Assignation multiple d'un produit à plusieurs collections
- Image de collection, description
- Position/ordre des produits dans une collection
- URLs SEO-friendly par collection

#### 4.1.6 Marques (Brands)

- Création et gestion des marques (SOMFY, FAAC, BFT, etc.)
- Logo de marque
- Description
- Assignation à un produit

#### 4.1.7 Tags

- Création libre de tags
- Assignation multiple à un produit
- Filtrage des produits par tag en back-office

### 4.2 Gestion des Prix

#### 4.2.1 Structure tarifaire

- Prix de base HT par produit et par variante
- Prix multi-devises (configurables, au minimum EUR)
- Prix de vente conseillé (MSRP/Prix barré)
- Coût d'achat (pour calcul de marge, visible en back-office uniquement)

#### 4.2.2 Groupes clients et tarification

- Création et gestion des groupes clients (ex : Particuliers, Installateurs, Revendeurs)
- Prix spécifique par groupe client et par produit/variante
- Remise en pourcentage par groupe et par marque ou collection (base pour les modules MDE phase 2)
- Tarification par paliers (ex : prix dégressif selon quantité)

#### 4.2.3 Canaux de vente

- Gestion d'un canal de vente principal (mde-distribution.fr)
- Activation/désactivation des produits par canal
- Prix indépendants par canal si nécessaire

### 4.3 Gestion des Stocks

- Suivi des niveaux de stock par produit et par variante
- Mise à jour manuelle du stock en back-office
- Historique des mouvements de stock
- Paramétrage : autoriser ou non les commandes en rupture de stock (back-order)
- Seuil d'alerte stock configurable par produit
- Vue globale : liste des produits sous le seuil d'alerte

### 4.4 Gestion des Commandes

#### 4.4.1 Liste des commandes

- Vue tabulaire avec filtres : statut, date, client, montant, référence
- Recherche full-text
- Tri par colonnes
- Export CSV

#### 4.4.2 Cycle de vie d'une commande

Statuts gérés :
- En attente de paiement
- Paiement validé
- En préparation
- Expédiée (partiellement ou totalement)
- Livrée
- Annulée
- Remboursée (partiellement ou totalement)

#### 4.4.3 Détail d'une commande

- Informations client (nom, adresse de livraison, adresse de facturation)
- Lignes de commande : produit, variante, quantité, prix unitaire HT/TTC, remise
- Sous-total HT, TVA par taux, total TTC
- Frais de livraison
- Remises appliquées
- Mode de paiement
- Historique des changements de statut (log horodaté)
- Notes internes (non visibles client)
- Gestion des retours / remboursements partiels

#### 4.4.4 Création de commande manuelle

- Création d'une commande depuis le back-office (commande téléphonique, devis accepté)
- Recherche client existant ou création à la volée
- Ajout de produits avec prix modifiable
- Sélection adresse de livraison

### 4.5 Gestion des Clients

#### 4.5.1 Liste des clients

- Vue tabulaire avec filtres : groupe, statut, date d'inscription, pays
- Recherche par nom, email, téléphone
- Export CSV

#### 4.5.2 Fiche client

- Informations personnelles : nom, prénom, email, téléphone
- Type de compte : particulier / professionnel
- Pour les professionnels : raison sociale, SIRET (champ libre, validation module phase 2), TVA intracommunautaire
- Adresses multiples (livraison et facturation)
- Groupe client assigné
- Statut : actif / inactif / en attente de validation
- Historique des commandes
- Notes internes

#### 4.5.3 Groupes clients

- Création et gestion des groupes (Particuliers, Installateurs, Revendeurs, etc.)
- Description du groupe
- Assignation manuelle d'un client à un groupe
- Vue des clients par groupe

### 4.6 Gestion des Remises & Promotions

- Codes promo : montant fixe ou pourcentage
- Conditions d'application : montant minimum de commande, groupe client, produit/collection spécifique, période de validité, limite d'utilisation
- Remises automatiques sur panier (sans code, basées sur des règles)
- Historique d'utilisation par code promo

### 4.7 Gestion de la Livraison

- Création de transporteurs (Colissimo, DPD, TNT, retrait en agence...)
- Zones géographiques de livraison
- Règles tarifaires : prix fixe, par tranche de poids, par tranche de montant commande, gratuit au-delà d'un montant
- Délais de livraison estimés par transporteur
- Activation/désactivation par transporteur

### 4.8 Gestion de la TVA

- Création des taux de TVA (5.5%, 10%, 20%)
- Zones fiscales (France métropolitaine, DOM-TOM, export UE, export hors UE)
- Assignation du taux de TVA par produit
- Calcul automatique TVA sur les commandes

### 4.9 Gestion des Devises

- Devise principale : EUR
- Possibilité d'ajouter des devises secondaires
- Taux de change manuel ou automatique
- Affichage des prix en devise sélectionnée

### 4.10 Gestion des Canaux de Vente

- Canal principal : mde-distribution.fr
- Configuration : nom, URL, devise par défaut, langue par défaut, timezone
- Activation/désactivation de produits par canal

### 4.11 Gestion des URLs & SEO

- URL personnalisée par produit et par collection
- Gestion des redirections (301, 302)
- Méta-titre et méta-description par produit et collection
- Détection des doublons d'URL

### 4.12 Gestion des Médias

- Bibliothèque de médias centralisée
- Upload d'images (JPEG, PNG, WebP)
- Génération automatique de thumbnails et conversions configurables
- Réordonnancement des images produit par drag & drop
- Suppression des médias orphelins

### 4.13 Configuration Générale

- Informations de la boutique : nom, adresse, email de contact, téléphone, logo
- Paramètres de commande : stock minimum pour achat, commandes en rupture autorisées ou non
- Paramètres d'affichage : nombre de produits par page, tri par défaut
- Gestion des langues (FR par défaut, extensible)
- Gestion des devises
- Paramètres de sécurité admin

### 4.14 Gestion des Utilisateurs Admin

- Création de comptes staff (administrateurs, gestionnaires de catalogue, SAV)
- Rôles et permissions granulaires (RBAC via Filament Shield ou équivalent)
- Historique des actions (activity log)
- Authentification sécurisée (2FA optionnel)

### 4.15 Tableau de Bord

- KPI principaux : chiffre d'affaires du jour/mois/année, nombre de commandes, panier moyen, taux de conversion (calculable en phase 2)
- Graphique des ventes sur les 30 derniers jours
- Dernières commandes (tableau)
- Produits en rupture ou sous seuil d'alerte
- Nouveaux clients

---

## 5. Fonctionnalités Exclues (par rapport à PrestaShop)

Les fonctionnalités suivantes ne sont **pas développées** dans cette phase, car non utilisées par MDE Distribution ou remplacées par des modules custom phase 2 :

| Fonctionnalité PrestaShop | Raison d'exclusion |
|---|---|
| Multistore | Non utilisé |
| Blog / CMS natif | Non utilisé (Creative Elements remplacé) |
| Marketplace / multi-vendeurs | Non utilisé |
| Programmes de fidélité / points | Non utilisé |
| Avis clients | Non utilisé en natif |
| Chat / support intégré | Non utilisé |
| Import/export PrestaShop natif | Remplacé par pipeline FAB-DIS custom (phase 2) |
| Modules marketplace PS | Non applicable |
| Gestion des retours automatisée | Phase ultérieure |
| Multi-entrepôts | Phase ultérieure |
| Devis B2B | Module custom MDE phase 2 |
| Validation SIRET | Module custom MDE phase 2 |
| Pricing rules B2B avancées | Module custom MDE phase 2 |
| Enrichissement IA produits | Module custom MDE phase 2 |
| Front-office | Phase 2 |

---

## 6. Modèle de Données

Lunar fournit nativement les tables suivantes (ne pas redéfinir) :

```
lunar_products                  lunar_product_variants
lunar_product_types             lunar_product_option_values
lunar_product_options           lunar_collections
lunar_collection_groups         lunar_prices
lunar_customer_groups           lunar_customers
lunar_orders                    lunar_order_lines
lunar_addresses                 lunar_channels
lunar_currencies                lunar_languages
lunar_tax_classes               lunar_tax_rates
lunar_tax_zones                 lunar_shipping_zones
lunar_shipping_rates            lunar_discount_types
lunar_discounts                 lunar_brands
lunar_tags                      lunar_urls
lunar_attributes                lunar_attribute_groups
lunar_media (via Spatie)        lunar_staff
```

Les tables customs MDE seront préfixées `mde_` et définies dans les packages `packages/mde/*` lors de la phase 2.

---

## 7. Sécurité

- Authentification admin via Filament (email + mot de passe)
- CSRF protection native Laravel
- Politiques d'accès via Laravel Policies + Filament Shield
- Rate limiting sur les routes d'authentification
- Logs d'audit sur toutes les modifications critiques (produits, commandes, clients, prix)
- Pas de données sensibles en clair en base (tokens hashés)
- Headers de sécurité HTTP (via middleware)
- Variables d'environnement pour toutes les credentials

---

## 8. Performance

- Cache Redis pour les configurations et les résultats de requêtes fréquentes
- Queue Redis pour les traitements asynchrones (imports, emails phase 2)
- Eager loading systématique pour éviter le N+1 dans les listings
- Pagination sur toutes les listes back-office
- Indexes MySQL sur les colonnes de filtrage et de tri fréquents

---

## 9. Environnement de Développement

### 9.1 Local

- Docker Compose : PHP 8.3, MySQL 8, Redis, MailHog
- Laravel Sail ou configuration Docker custom
- `.env` non versionné, `.env.example` versionné
- Makefile pour les commandes courantes (`make migrate`, `make seed`, `make test`)

### 9.2 Versioning

- Git : toute la codebase versionnée y compris Docker
- Branches : `main` (production), `develop` (intégration), feature branches par module
- Conventional Commits recommandés

### 9.3 Tests

- PHPUnit / Pest pour les tests unitaires et feature
- Tests sur les pipelines critiques : calcul de prix, gestion du stock, cycle de vie commande
- Factories et seeders pour les données de test

---

## 10. Plan de Migration depuis PrestaShop

### 10.1 Données à migrer (script de migration dédié, hors périmètre phase 1)

- Catalogue produits + variantes + images
- Catégories → Collections Lunar
- Clients + adresses + groupes clients
- Commandes historiques (lecture seule)
- Marques, tags

### 10.2 Données non migrées

- Modules PS custom (refactorisés en packages MDE phase 2)
- Templates Creative Elements (remplacés par Blade/Livewire phase 2)
- Configurations PS spécifiques à l'ancienne plateforme

---

## 11. Livrables Phase 1

1. Application Laravel installée avec Lunar + Filament fonctionnel
2. Admin panel accessible sur `/admin` avec authentification
3. Toutes les fonctionnalités listées en section 4 opérationnelles
4. Données de démonstration (seeders) : 50 produits, 3 collections, 2 groupes clients, 10 commandes
5. Documentation CLAUDE.md pour Claude Code (conventions, structure, patterns)
6. Docker Compose pour l'environnement local
7. README d'installation

---

## 12. Critères de Recette

- [ ] Connexion admin fonctionnelle
- [ ] Création d'un produit avec variantes, images, attributs, prix par groupe client
- [ ] Navigation dans l'arborescence des collections
- [ ] Création manuelle d'une commande
- [ ] Changement de statut d'une commande avec log horodaté
- [ ] Création d'un client avec adresses multiples et groupe assigné
- [ ] Création d'un code promo avec conditions
- [ ] Ajout d'un utilisateur staff avec rôle restreint
- [ ] Mise à jour du stock d'un produit
- [ ] Configuration d'un transporteur avec règle tarifaire
- [ ] Export CSV d'une liste de commandes
- [ ] Aucune modification de fichier dans `vendor/lunarphp/`

---

*Document destiné à Claude Code pour la mise en œuvre technique. Toute évolution du périmètre doit être validée avant développement.*
