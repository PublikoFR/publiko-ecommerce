# pko/lunar-storefront-b2b — frontoffice B2B pro (phase 2)

Transformation complète du storefront (starter kit Livewire basique porté en §13.bis) en **frontoffice B2B pro-only** inspiré de Foussier. Catalogue navigable en libre accès, **prix masqués pour les visiteurs** (CTA "Connectez-vous pour voir vos prix"), achat/panier/compte réservés aux comptes pros vérifiés SIRET.

### 15.1 Décisions arbitrées

| Sujet | Choix | Pourquoi |
|---|---|---|
| Visibilité | Strict pro (Foussier-like) | Aligné B2B, SEO préservé sur catalogue + fiches produits |
| Inscription | Auto + vérif SIRET API INSEE V3 | DX pro instantanée, fallback validation manuelle si API indispo |
| Mono-user | 1 User ↔ 1 Customer | Simple phase 1, multi-user refactor si besoin ultérieur |
| Recherche | Scout driver `database` (DB LIKE) | Zero dépendance tierce ; Typesense documenté en follow-up |
| Charte | Palette bleu pro B2B (Tailwind primary=blue, neutral=slate) | Placeholder, remplaçable via 1 token quand logo/hex fournis |
| CMS | Tables simples + Livewire, Filament admin en follow-up | Time-to-market, UI admin ajoutable sans migration |

### 15.2 Architecture en 7 packages

| Package | Rôle |
|---|---|
| `packages/pko/storefront` | Design system (tokens Tailwind, Blade UI `<x-ui.*>`), layout `<x-layout.storefront>`, header Foussier-like (top contact bar + main bar + mega-menu collections cache 1h + info banner) + footer 4 colonnes CMS + USPs, `SearchAutocomplete` Livewire |
| `packages/pko/customer-auth` | `SireneClient` (INSEE Sirene 3.11, clé API en en-tête + fallback pending), `RegisterProCustomer` action, Livewire pages Login/Register/Forgot/Reset + layout auth dédié, middlewares `pro.customer` + `redirect.if.pro` |
| `packages/pko/account` | Layout sidebar `/compte`, 8 pages Livewire (dashboard, profil, société, adresses, commandes, commande-détail, fidélité, factures), `AccountContext` helper |
| `packages/pko/purchase-lists` | Tables `pko_purchase_lists` + `pko_purchase_list_items`, models, 3 Livewire (index, détail, picker modal) |
| `packages/pko/quick-order` | `QuickOrderPage` Livewire (table dynamique + coller-Excel), `SkuResolver` service |
| `packages/pko/storefront-cms` | Tables `pko_home_slides`, `pko_home_tiles`, `pko_home_offers`, `pko_posts`, `pko_pages`, `pko_newsletter_subscribers` ; 5 Livewire blocks home + 3 controllers (posts, pages, newsletter) |
| `packages/pko/store-locator` | Table `pko_stores`, routes `/magasins` + `/magasins/{slug}` avec carte Leaflet CDN + OpenStreetMap |

### 15.3 Design system — `<x-ui.*>` et `<x-layout.*>`

Tokens Tailwind (`tailwind.config.js`) :
```js
colors: { primary: blue, neutral: slate, success: emerald, warning: amber, danger: rose }
fontFamily: { sans: ['Inter', ...] }  // CDN Google Fonts
```

Composants UI : `button`, `input`, `select`, `textarea`, `checkbox`, `card`, `badge`, `alert`, `breadcrumb`, `dropdown` + `dropdown-item`, `modal`, `icon` (26 SVG inline, pas de Heroicons dep).

Composants layout : `header`, `footer`, `search-bar`, `logo` (SVG placeholder), `usps`, `storefront` (wrapper layout complet).

Composants storefront : `product-card` (Foussier-like avec marque + code + N variantes + price-gate + boutons Liste/Cart), `price-gate` (auth-aware wrapper), `add-to-cart` (gated + redirige vers product page si multi-variante).

### 15.4 Price gating

`<x-storefront.price-gate :product :variant size="md">` :
- Si `auth()->check()` (tout compte authentifié) → `Pricing::for($variant)->get()->matched->price->formatted()`.
- Sinon → `<x-ui.button href="/connexion" icon="user">Connectez-vous pour voir vos prix</x-ui.button>`.

**Règle de visibilité des prix (depuis 2026-07)** : les prix (et l'achat) sont visibles dès qu'un client est **connecté**, sans condition supplémentaire de statut SIRET ni de groupe. Auparavant le gate exigeait `sirene_status='active'` + `CustomerGroup='installateurs'` ; la refonte SIRET ayant rendu `sirene_status` asynchrone (souvent `pending`/`null`), ce check strict privait de prix des pros pourtant approuvés (`pko_status='active'`) → régression. Le gate d'affichage est désormais découplé du statut de validation. L'**accès aux routes pro** (`/panier`, `/checkout`, `/compte`…) reste, lui, gardé par le middleware `pro.customer` (§15.6).

Même logique (`auth()->check()`) sur `<x-storefront.product-card>` et `<x-storefront.add-to-cart>`. Routes gated par middleware `pro.customer` : `/panier`, `/checkout*`, `/compte*`, `/achat-rapide`, `/compte/listes-achat*`.

#### Prix négociés par client (depuis 2026-07)

Tarifs HT contractuels propres à un client (équivalent des *« Prix spécifiques »* PrestaShop). Lunar ne price que par **groupe client**, jamais par client individuel → couche custom via le **pricing pipeline**.

- **Table** `pko_negotiated_prices` (`customer_id`, `product_variant_id`, `currency_id`, `price` en centimes HT ; unique `pko_negotiated_prices_unique`). Migration dans `packages/pko/customer-auth`.
- **Modèle** `Pko\CustomerAuth\Models\NegotiatedPrice`. Relation dynamique `Customer::negotiatedPrices` ajoutée sans subclasser le modèle Lunar via `Customer::resolveRelationUsing(...)` dans le ServiceProvider.
- **Application** : `Pko\CustomerAuth\Pricing\NegotiatedPricePipeline`, enregistré dans `config/lunar/pricing.php` → `pipelines`. Réécrit `pricing->matched` **uniquement si le prix négocié est strictement inférieur** au prix déjà résolu → un prix dégressif par quantité (ou toute règle Lunar) plus bas est préservé, et les promotions (discounts) s'appliquent ensuite dans le pipeline panier. Objectif : toujours le prix le plus avantageux. Un seul point d'application → cohérent fiche produit / price-gate / panier / checkout / commande.
- **Contexte client** : le pipeline lit `$manager->user` (auto-résolu depuis `Auth::user()` par le `PricingManager` s'il s'agit d'un utilisateur Lunar). En admin, l'utilisateur est un membre du staff (non-Lunar) → pipeline inactif ; une commande créée par un admin au nom d'un client passera par la future **impersonation** (qui posera un utilisateur Lunar).
- **UI** : onglet **« Prix négociés »** sur la fiche client (`NegotiatedPricesRelationManager` sur `PkoCustomerResource`) — recherche produit (nom/réf) + saisie du prix HT en €. Produits mono-variante → la recherche vise la variante par défaut.

### 15.5 Inscription pro + vérification SIRET

`Pko\CustomerAuth\Sirene\SireneClient` :
- `validateSiret(string): bool` — Luhn + 14 chiffres (statique). ⚠️ Luhn = on double un chiffre sur deux **depuis la droite** (longueur paire → index pairs depuis la gauche). Ce contrôle local tourne **toujours**, même vérification INSEE désactivée. La saisie est normalisée (espaces/séparateurs retirés) dans `RegisterPage::submit`, donc « 981 043 979 00021 » est accepté.
- `verify(string): SireneResult` — appelle `{base_url}/siret/{siret}` (nouveau portail INSEE Sirene 3.11) avec la **clé API unique** en en-tête `X-INSEE-Api-Key-Integration` (plus d'OAuth ni de token — l'ancien flux `api.insee.fr/token` client_credentials est déprécié). En-tête configurable via `INSEE_API_KEY_HEADER`.
- Retourne `Status::Active` (établissement actif), `Status::Inactive` (404 ou `etatAdministratifEtablissement ≠ A`), `Status::Pending` (API disabled, timeout, 5xx).
- ⚠️ **Gotcha parsing Sirene v3** : `etatAdministratifEtablissement` et `activitePrincipaleEtablissement` sont des variables **historisées** → elles vivent dans `etablissement.periodesEtablissement[]`, **pas** à la racine de `etablissement`. La période courante est celle dont `dateFin` est `null` (l'API trie du plus récent au plus ancien). Les lire à la racine renvoie toujours `null` → tout établissement actif était classé `Inactive` (« SIRET non valide » côté inscription). Couvert par `tests/Feature/CustomerAuth/SireneVerifyTest.php`.

`RegisterProCustomer::handle($dto)` :
- Transaction : crée `Lunar\Models\Customer` (raison, TVA FR depuis clé, meta.siret/naf/adresse INSEE), attache le **groupe par défaut** (`config('customer-auth.default_customer_group_handle')` = `nouveau-client`) + le **groupe métier** choisi à l'inscription (`customer_group_id`, uniquement si `pko_is_metier=true`), crée `User` lié via pivot `customer_user`. Retourne `['user', 'customer', 'sirene']`.
- Si `Status::Inactive` → `DomainException` bloquante.
- **`pko_status` = `pending` à la création (toujours)**, même SIRET actif : le compte ne devient `active` qu'à la **vérification de l'e-mail** (route `verification.verify`), et uniquement si `sirene_status='active'` (sinon reste `pending` pour validation manuelle). Voir §15.5 vérification e-mail.
- **`email_verified_at` reste `null`** : l'utilisateur confirme son adresse via un lien signé. Ne jamais le marquer vérifié à la création.
- **Notification admin** : après l'inscription, un `CustomerRegisteredAdminMail` récapitulant toutes les coordonnées est envoyé à `brand_setting('admin_email')` (fallback config `customer-auth.admin_notification_email`). Envoi isolé en try/catch (un échec SMTP ne compromet pas l'inscription).

**Groupes clients** (`lunar_customer_groups`) :
- Colonne custom `pko_is_metier` (bool, migration customer-auth) : un groupe « métier » est proposé dans la liste déroulante du formulaire d'inscription (`RegisterPage`), pour typer le nouveau client dès la création. Toggle éditable via `CustomerGroupFieldsExtension` (form + colonne Filament).
- Groupe par défaut `nouveau-client` (« Nouveau client ») : attribué d'office à toute inscription **et** cible de réattribution à la suppression d'un groupe.
- **Suppression d'un groupe** (`CustomerGroupGuard` + `CustomerGroupDeletionGuardExtension`) : les **clients** ne bloquent plus la suppression — ils sont détachés puis réattribués au groupe par défaut (`reassignCustomersToDefault()`), évitant la FK 1451 et les clients orphelins. Les autres références (collections, prix, produits, livraison, remises, taxes) restent bloquantes, ainsi que le groupe Lunar `default` et le groupe pro.

`RegisterPage` (Livewire) :
- **Vérif SIRET asynchrone** : au blur du champ SIRET (`wire:model.blur` → hook `updatedSiret`), appel INSEE avec loader (slot `trailing` du composant `<x-ui.input>`). Si actif → coche verte + préremplissage des champs société **encore vides** (raison sociale, activité/NAF, adresse). Si invalide/inactif → message d'erreur inline. La revalidation serveur au submit reste la source de vérité.
- **Code NAF / APE masqué** : le champ n'est plus affiché (donnée technique sans valeur ajoutée à la saisie). La propriété `activity` reste préremplie par `updatedSiret()` depuis l'INSEE et **persistée sur le customer** — seule une éventuelle erreur de validation est rendue en colonne société. Verrouillé par `RegisterPagePrefillTest::test_valid_siret_prefills_company_fields_server_side`.
- **Choix du métier** (colonne « Contact & accès », sous e-mail/téléphone pour équilibrer les deux colonnes) : combobox filtrable `<x-ui.searchable-select>` (Alpine, recherche insensible aux accents via `normalize('NFD')`, navigation clavier ↑/↓/Entrée/Échap, valeur `@entangle` sur `metierGroupId`). Remplace le `<select>` natif, devenu peu praticable avec un grand nombre de métiers. Le hint annonce l'intérêt métier : des **réductions dédiées au secteur d'activité**.
- **Pas de champ Pays** : la vérification SIRET (INSEE) ne peut valider qu'une entreprise française, le champ était donc redondant. Le pays est forcé à `FR` dans `RegisterPage::submit()` (et `RegisterProCustomer` conserve son défaut `FR`).
- Après création : `Status::Active` → `Auth::login()` + `JustRegistered::flag()` + redirection vers **l'accueil `/`** (avec message de bienvenue invitant à vérifier l'e-mail). Le compte reste `pending` tant que l'e-mail n'est pas vérifié, mais le flag `JustRegistered` lui accorde un **accès complet** le temps de sa session (cf. § « semi-connexion » plus bas) — pas de rebond vers `/connexion`. `Status::Pending`/`Inactive` → **on ne connecte PAS**.

**Vérification d'e-mail** (le compte reste `pending` jusqu'à vérification, bandeau de rappel) :
- Lien signé via `Pko\CustomerAuth\Support\EmailVerification::signedUrl()` (route `verification.verify`, middleware `signed`, valable 7 jours) — inclus dans le mail de bienvenue (`CustomerRegisteredMail`) et le renvoi (`EmailVerificationMail` + route `verification.send`, throttlée).
- La route de vérif fonctionne **sans session préalable** (clic depuis n'importe quel appareil) : valide signature + hash e-mail, `markEmailAsVerified()`, **promeut le(s) customer(s) `pending` → `active`** (si `sirene_status='active'`), puis auto-login.
- `ProAccess::denialReason()` distingue le motif `pending` : e-mail non vérifié (message invitant à cliquer sur le lien) vs validation SIRET manuelle.
- Bandeau de rappel dans le layout storefront tant que `! auth()->user()->hasVerifiedEmail()` **ET** que le customer est encore `pending` (bouton « Renvoyer le lien »). **Un compte déjà `active`** (activé en back-office, dont l'e-mail n'est pas « vérifié » au sens Laravel) **ne voit pas le bandeau** : pour lui la vérification n'active plus rien, le nagger était un bug. Verrouillé par `EmailVerificationBannerTest`.

**Anti-boucle de redirection (`ERR_TOO_MANY_REDIRECTS`)** — source unique de vérité :
`Pko\CustomerAuth\Support\ProAccess::isActivePro()` / `::denialReason()`. Un utilisateur
authentifié mais **non-actif** (SIRET pending, hors groupe, sans customer) qui accède à
`/compte` était renvoyé vers `/connexion` par `pro.customer`, que `redirect.if.pro`
renvoyait vers `/compte` (car authentifié) → boucle. La symétrie corrige les deux bouts :
- `RequireProCustomer` (gate `/compte`) refuse via `denialReason()` (message FR selon le motif).
- `RedirectIfProCustomer` (sur `/connexion`) ne redirige vers `/compte` **que** si `isActivePro()` — sinon laisse la page de connexion s'afficher.
- `LoginPage::authenticate` : après auth, un compte non-actif est redirigé vers l'accueil avec le message d'attente (jamais vers `/compte`).

Le piège se déclenchait surtout via la **connexion** (et non l'inscription) : `LoginPage`
authentifie l'utilisateur, donc la seule garde à l'inscription ne suffisait pas.

### Un compte non-actif n'est JAMAIS authentifié (pas de « semi-connexion »)

**Règle produit** : un compte `pending` (e-mail non vérifié, SIRET en attente, hors groupe, sans customer) ne doit pas dépasser le formulaire de connexion. Auparavant `LoginPage::authenticate()` appelait `Auth::attempt()` puis se contentait de **rediriger** : l'utilisateur restait authentifié sans accès à aucune page — son nom s'affichait sous le picto profil, ce qui laissait croire à une connexion réussie.

| Endroit | Comportement |
|---|---|
| `LoginPage::authenticate()` | Connexion **refusée** : `Auth::guard('web')->logout()` + `ValidationException` sur le champ e-mail, et **renvoi automatique du lien de vérification** si c'est le motif du blocage. |
| `RequireProCustomer` | Déconnecte tout utilisateur authentifié mais sans accès (`denialReason() !== null`), au lieu de le laisser à moitié connecté. Les cas volontaires (impersonation, inscription) sont déjà écartés en amont par `denialReason()` → jamais logués out ici. |

**Deux exceptions assumées**, seules situations où un compte `pending` peut rester connecté **avec un accès complet** :

1. **Impersonation admin** — `ProAccess::isImpersonating()` (cf. `docs/admin.md`).
2. **Auto-login juste après l'inscription** — flag `Pko\CustomerAuth\Support\JustRegistered` posé en session par `RegisterPage`, pour ne pas casser le parcours d'entrée. Le flag est purgé (`JustRegistered::clear()`) à la déconnexion **et au début de `LoginPage::authenticate()`** : une **connexion au formulaire** n'est jamais un auto-login d'inscription, donc un flag résiduel ne doit pas y faire passer un compte pending. Le SEUL moment où un pending reste connecté est bien l'auto-login qui suit la création du compte.

**Piège corrigé (régression « demi-connexion » n° 52)** : le flag étant consulté par `ProAccess::denialReason()`, un flag **résiduel** en session (inscription antérieure au nettoyage, session mal purgée) faisait passer `denialReason()` à `null` et **autorisait un compte pending à se connecter au formulaire**. `LoginPage` doit donc `clear()` le flag avant d'évaluer le gate. Verrouillé par `PendingAccountCannotLoginTest::test_a_stale_just_registered_flag_does_not_let_a_pending_account_login`.

**Le bypass se fait dans `ProAccess::denialReason()` (source unique), pas ailleurs.** `JustRegistered::isActive()` (lecture session directe, comme `isImpersonating()`) y renvoie `null` — donc accès accordé à **toutes** les routes pro (`/compte`, `/panier`, `/checkout`…) le temps de la session d'inscription. **Piège corrigé (régression « demi-connexion » n° 51)** : auparavant le flag n'était consulté que dans `RequireProCustomer` pour *suppimer le logout*, mais **pas la redirection** — `denialReason()` renvoyait quand même « confirmez votre e-mail », donc chaque route pro rebondissait vers `/connexion`. Résultat : l'utilisateur restait authentifié (nom affiché sous le profil) mais n'avait accès à rien = exactement la demi-connexion qu'on voulait éviter. Le flag doit accorder l'accès **au niveau de `denialReason()`**, sinon la moitié des gates l'ignore. Régression verrouillée par `ProAccessRedirectTest::test_freshly_registered_pending_user_keeps_full_access`. `RequireProCustomer` n'a donc plus à connaître `JustRegistered` : un compte flaggé n'atteint jamais sa branche logout.

**Piège CSRF (419 « This page has expired »)** : pour *refuser* une connexion, utiliser `logout()` **seul**. Un `session()->invalidate()` / `regenerateToken()` périme le token CSRF de la page de connexion encore affichée → la tentative suivante part en 419 et Livewire affiche « This page has expired ». `Auth::attempt()` ayant déjà régénéré l'id de session, il n'y a aucun risque de fixation à ne pas invalider. Même règle dans `RequireProCustomer` (pages Livewire déjà ouvertes). Régression verrouillée par `PendingAccountCannotLoginTest`.

`SESSION_LIFETIME` est passé à `10080` (7 jours) : une session de 2 h expirait en cours de journée de dev et produisait le même 419 sur les onglets ouverts.

### Déconnexion — logout simple (le lien est un `<form>` POST plein-page)

**Décision (2026-07)** : la déconnexion est une **route closure toute simple** (`routes/web.php`), logout Laravel standard, sans machinerie hors-session. Une itération précédente avait construit une révocation d'id de session dans le cache (`FrontSessionRevocation`) + un middleware `EnforceFrontLogout` sur tout le groupe web pour parer une « session zombie ». **Supprimé** : ça traitait un symptôme qui ne se manifeste pas avec le montage actuel. Pas de contrôleur dédié (une closure suffit, cohérent avec les routes `verification.*` du même fichier ; `route:cache` est de toute façon hors-jeu, ces routes étant des closures).

```php
// routes/web.php — hors impersonation admin
Auth::guard('web')->logout();
$request->session()->invalidate();
$request->session()->regenerateToken();
```

**Pourquoi c'est suffisant ici — et pourquoi une « session zombie » était crainte.** Une session Laravel est lue en début de requête puis réécrite en fin (« last write wins »). Une requête `POST /livewire/update` partie *avant* le clic « Se déconnecter » mais terminée *après* réécrit son snapshot, clé `login_web_*` comprise : **côté serveur** la session ressuscite bel et bien (reproduit contre redis). **Mais le navigateur ne l'adopte jamais** : le lien de déconnexion est un `<form method="POST" action="/deconnexion">` **plein-page** (header + layout compte), pas un lien `wire:navigate` ni une action Livewire. Une navigation dure **abandonne les XHR en vol** et **ignore leur `Set-Cookie`** — le navigateur garde donc la session vidée par `invalidate()`, la session ressuscitée reste orpheline (et expire seule). Le zombie n'est un risque que si la déconnexion se fait en **navigation SPA** (`wire:navigate`), ce qui n'est pas le cas.

**Règle à préserver** : le lien de déconnexion doit rester un `<form>` POST plein-page. Ne pas lui ajouter `wire:navigate`, ne pas le transformer en action Livewire — ce serait rouvrir la fenêtre de résurrection.

Détails d'implémentation :

- **Guard `web` visé explicitement**, jamais `Auth::logout()` sur le guard par défaut — il vaut `staff` en requête Filament (cf. `docs/admin.md`, impersonation).
- **Impersonation** : si un staff est connecté sur la même session, on ne fait **pas** d'`invalidate()` (qui éjecterait l'admin de son panel) — on retire seulement la clé du guard web + le marqueur `IMPERSONATOR_SESSION_KEY`.
- **POST uniquement** pour `/deconnexion` (un GET serait déclenchable par un prefetch). Route **exemptée de CSRF** (`bootstrap/app.php`) pour qu'un token périmé (page mise en cache par `wire:navigate`) ne renvoie pas 419.
- `JustRegistered::clear()` : la session n'étant pas toujours invalidée (branche impersonation), on retire explicitement le flag « fraîchement inscrit » pour qu'un compte pending ne se reconnecte pas dans la même session (cf. § semi-connexion).

Couvert par `tests/Feature/CustomerAuth/LogoutTest.php` (sans CSRF, idempotence, invalidation de session, flag JustRegistered nettoyé, impersonation staff préservée).

**Fiche client — onglets fusionnés** : `CustomerResource` est swappée par `Pko\CustomerAuth\Filament\Resources\PkoCustomerResource` (via `swapLunarResources`, slug `customers` conservé). `PkoViewCustomer::hasCombinedRelationManagerTabsWithContent() = true` fusionne l'infolist et les relations (Commandes/Adresses/Utilisateur) en un seul groupe d'onglets, 1er onglet = « Informations » (infos client). Comme pour tout swap Lunar (§3.2 CLAUDE.md), les 4 pages ont des sous-classes Pko redéclarant `$resource`, et les extensions sont re-keyées sur les classes Pko (`PkoCustomerResource` / `PkoCreateCustomer` / `PkoEditCustomer`). Nav `AdminNav\Builder` pointe sur `PkoCustomerResource`.

**SIRET éditable en back-office** (`CustomerSiretExtension`, `EditPageExtension` sur `PkoEditCustomer`) : une entreprise peut changer de SIRET. Le champ `siret` de la fiche client (édition seulement) est virtuel — chargé depuis `meta['siret']` en `beforeFill`, réécrit en `beforeUpdate` **par fusion** (préserve les autres clés meta). Si le SIRET change et est Luhn-valide, on relance la vérification INSEE à l'enregistrement et on rafraîchit `naf_code` / `meta.sirene_address` / `sirene_status` / `sirene_verified_at`. SIRET invalide → `ValidationException` (pas d'enregistrement).

Migration `2026_04_17_120000_add_sirene_columns_to_lunar_customers` : `sirene_status` (indexed), `sirene_verified_at`, `naf_code`.

### Suppression d'un client = anonymisation RGPD (jamais de delete physique)

Toutes les FK vers `lunar_customers` (orders, addresses, carts, pivots user/group/discount)
sont en `NO ACTION` et le modèle Lunar `Customer` n'a **ni SoftDeletes ni cascade** → un
`DeleteBulkAction` natif plante en `1451 FK constraint` dès qu'un client a une commande /
un compte lié. Décision (RGPD + compta/Pennylane) : **on n'efface jamais la ligne client**.

`App\Filament\Extensions\CustomerAnonymizeExtension` (extension sur `CustomerResource`)
remplace le bulk delete par une action **« Anonymiser (RGPD) »** →
`Pko\CustomerAuth\Actions\AnonymizeCustomer` :
- efface les données perso du client (`first_name`/`last_name`/`company_name`/`tax_identifier`/`meta`/`sirene_*`), **garde la fiche et ses commandes** ;
- supprime adresses + paniers du client, détache groupes/remises ;
- **supprime les comptes de connexion (`User`) liés** : les FK `NO ACTION` vers `users` sont dénouées avant (les commandes sont conservées, `lunar_orders.user_id` → `null` ; `discount_user`/`customer_user`/carts supprimés).

### Suppression d'un groupe client = garde-fou (jamais de 1451)

Même schéma : toutes les FK vers `lunar_customer_groups` (customers, collections, prices,
products, shipping, tax, discounts) sont en `NO ACTION`. `App\Support\CustomerGroupGuard::blockReason()`
est la source unique de vérité et **bloque** (message FR) : le groupe **par défaut** Lunar
(`default=1`), le **groupe pro** (`config('customer-auth.default_customer_group_handle')`,
`installateurs`), et tout groupe **encore référencé** (liste des N clients/tarifs/produits…).
Un groupe custom sans aucune référence reste supprimable. `App\Filament\Extensions\CustomerGroupDeletionGuardExtension`
applique le garde-fou aux **deux** chemins : bulk delete de la liste (`extendTable`) et
delete de la page d'édition (`headerActions`, enregistrée aussi sur `EditCustomerGroup`).

Env requis pour INSEE : `INSEE_ENABLED=true` + `INSEE_API_KEY` (clé API unique du portail INSEE ; par défaut `INSEE_ENABLED=false` → fallback pending, admin valide manuellement).

**Page de configuration Back-office** (depuis 2026-07) : `Configuration → Réglages → Vérification SIRET` (`App\Filament\Pages\SireneConfig`). Permet d'**activer/désactiver** la vérification (toggle persisté dans le `Setting` `sirene.enabled`, indépendant de `.env`) et de **gérer les clés API via le système Secrets** (source `.env` **ou** base de données chiffrée, comme Stripe — module `insee`). Bouton « Tester la connexion INSEE » (requête token OAuth). Le `SireneClient` est re-liaisonné dans `AppServiceProvider::boot()` pour lire activation (Setting) + clés (Secrets) avec repli sur la config `.env`. Détails du système : `docs/packages/secrets.md`.

### 15.6 Routes publiques + gated

```
Public :
  /  (home Livewire refondue)
  /collections/{slug}  (faceted filters via catalog-features)
  /produits/{slug}
  /recherche  (DB LIKE, multi-champs)
  /magasins  /magasins/{slug}  (Leaflet CDN)
  /actualites  /actualites/{slug}
  /pages/{slug}
  POST /newsletter
  /connexion  /inscription  /mot-de-passe-oublie  /reinitialisation/{token}

Pro gated (middleware pro.customer = auth + CustomerGroup installateurs + sirene_status active) :
  /panier  /checkout  /checkout/success
  /achat-rapide
  /compte  /compte/profil  /compte/societe  /compte/adresses
  /compte/commandes  /compte/commandes/{order}
  /compte/listes-achat  /compte/listes-achat/{list}
  /compte/fidelite  /compte/factures
  POST /deconnexion

Admin :
  /admin  (Filament, Shield, intact)
```

**Déconnexion exemptée de CSRF** (depuis 2026-07, `bootstrap/app.php` → `validateCsrfTokens(except: ['deconnexion'])`) : le storefront étant une SPA Livewire (`wire:navigate`), une page ouverte longtemps ou restaurée depuis le cache back/forward porte un token `@csrf` périmé ; le `POST /deconnexion` renvoyait alors un 419, l'utilisateur restait connecté sans pouvoir se déconnecter. La déconnexion étant idempotente et non destructive (risque CSRF négligeable), on l'exempte pour qu'elle aboutisse toujours.

### 15.7 Catalogue faceté (CollectionPage)

`CollectionPage` refondu :
- `#[Url(as: 'f')]` pour préservation des filtres, `#[Url(as: 'sort')]` pour tri, `WithPagination`.
- Sidebar filtres via `Pko\CatalogFeatures\Facades\Features::countsFor($collection)` + checkboxes.
- `Features::productsWith($selectedValueIds)` pour filter Product IDs, JOIN avec `whereHas('collections')`.
- Tri : nouveautés (default), prix asc/desc, nom A-Z.
- Pagination 24 items/page, grid 3 cols desktop.

### 15.8 Homepage refondue

`resources/views/livewire/home.blade.php` (utilisé par `App\Livewire\Home`) :
- `<livewire:storefront-cms.home-hero>` — carrousel slides Alpine auto-play 6s + dots + prev/next (cache 15min)
- `<livewire:storefront-cms.home-tiles>` — 4 tuiles portails/volets/automatismes/motorisations
- `<livewire:storefront-cms.home-featured>` — 6 produits collection `config('storefront.home.featured_collection_slug')` ou fallback latest
- `<livewire:storefront-cms.home-offers>` — 4 offres du moment
- Pitch SEO-friendly (paragraphe keyword-riche)
- `<livewire:storefront-cms.home-posts>` — 4 dernières actus publiées

Toutes les requêtes home cachées 15min (clé versionnée à ajouter via observer sur events Post/Slide/Tile/Offer `saved` dans un follow-up).

### 15.9 Nouvelles dépendances

- Aucun ajout Composer (tout construit sur l'existant Laravel 11 + Lunar + Livewire + Spatie Permission déjà présents).
- CDN Leaflet 1.9.4 (`/magasins`, `/magasins/{slug}`) — chargé `@push('head')` / `@push('scripts')` local à ces pages.
- CDN Google Fonts Inter (layout principal).

### 15.10 Variables d'environnement ajoutées

```env
# Inscription pro INSEE (désactivé par défaut → fallback validation manuelle)
# Nouveau portail : clé API unique en en-tête (plus d'OAuth consumer key/secret)
INSEE_ENABLED=false
INSEE_BASE_URL=https://api.insee.fr/api-sirene/3.11
INSEE_API_KEY=
# INSEE_API_KEY_HEADER=X-INSEE-Api-Key-Integration  # défaut, à surcharger si besoin
INSEE_TIMEOUT=5

# Contact front (config/storefront.php)
CONTACT_PHONE="02 XX XX XX XX"
CONTACT_EMAIL=contact@example.fr
CONTACT_TAGLINE="Besoin d'un conseil ?"

# Réseaux sociaux (optionnel)
SOCIAL_FACEBOOK=
SOCIAL_INSTAGRAM=
SOCIAL_LINKEDIN=
SOCIAL_YOUTUBE=

# Bannière info + shipping
BANNER_ENABLED=true
BANNER_TEXT="Livraison offerte dès 125 € HT"
MIN_FREE_SHIPPING_CENTS=12500

# Home (optionnel)
HOME_FEATURED_COLLECTION=
```

### 15.11 Nouveaux tests de référence

Compte de test pro (seeded par `PkoCustomerSeeder`) :
- Email : `thierry.leroy@example.test`
- Password : `testing123`
- Customer `Leroy Fermetures`, SIRET `12345678900015`, group `installateurs`, `sirene_status=active`

Compte admin Filament (seeded par `PkoAdminUserSeeder`) :
- Email : `admin@example.fr`
- Password : `testing123`

### 15.12 Administration CMS (Filament)

Nouveau groupe de navigation **Storefront** dans l'admin Filament avec 7 resources et 1 page :

| Resource / Page | Modèle | Fonctionnalités |
|---|---|---|
| **Slides accueil** | `HomeSlide` | CRUD modal, reorder drag-n-drop (`position`), color pickers fond/texte, dates début/fin, CTA. Cache `pko.home.slides.v1` flush au save. |
| **Tuiles accueil** | `HomeTile` | 4 cards promotionnelles (titre, sous-titre, image, CTA, reorder). Cache `pko.home.tiles.v1`. |
| **Offres du moment** | `HomeOffer` | Badge (ex. -25%), image, date fin, CTA. Cache `pko.home.offers.v1`. |
| **Actualités** | `Post` | RichEditor Filament, slug auto depuis titre, cover, extrait, status draft/published, date publication. Cache `pko.home.posts.v1`. |
| **Pages CMS** | `Page` | RichEditor, slug unique, status (published/draft). Routes `/pages/{slug}` (CGV, mentions, FAQ, politique…). |
| **Abonnés newsletter** | `NewsletterSubscriber` | Liste read-only (pas de create), bulk delete, search + sort. |
| **Magasins** | `Store` | Sections Identité / Adresse / Contact / Horaires (`KeyValue` jour→plage), slug auto, coordonnées lat/lng. |
| **Paramètres** (page) | `Setting` | Contact (tél, e-mail, accroche), bannière info (toggle + texte + icône select), seuil livraison offerte cents, social links (FB/IG/LI/YT), USPs via `Repeater` icône+titre+sous-titre, slug collection vedette home. |

Plugins Filament : `Pko\StorefrontCms\Filament\StorefrontCmsPlugin` et `Pko\StoreLocator\Filament\StoreLocatorPlugin` enregistrés dans `AppServiceProvider`. Nav group inséré avant `Commandes`.

**Source de vérité config** : table `pko_storefront_settings` (key/value JSON) → modèle `Pko\StorefrontCms\Models\Setting` (helper static `get/set/forget`, cache Redis 1h). `StorefrontCmsServiceProvider::mergeDbSettingsIntoConfig()` au boot surcharge les valeurs de `config('storefront.*')` quand la table est peuplée (guard `Schema::hasTable` pour install/CI). Résultat : l'admin peut éditer tous les réglages frontoffice sans toucher au `.env`.

Policies Shield régénérées (`make artisan CMD='shield:generate --all --panel=admin --no-interaction'`) après ajout des nouveaux modèles.

### 15.13 Follow-up documentés (hors scope phase 2)

- **Scout Typesense** : upgrade du driver `database` → Typesense self-hosted pour performance + typo-tolerance sur 60k+ références.
- **Pays checkout** : `CheckoutPage::getCountriesProperty()` hardcodé `[GBR, USA]` (hérité starter kit) à remplacer par France/UE.
- **Cache Navigation versionné** : observer `Collection::saved` qui invalide `pko.storefront.nav.roots.v1`.
- **Factures PDF** : page `/compte/factures` = placeholder. Génération via Spatie Browsershot ou équivalent.
- **Loyalty UI storefront** : `/compte/fidelite` est branché sur `LoyaltyManager::getCustomerSnapshot()` mais le rendu barre/cadeaux est minimal — design à finaliser.
- **Adresses CRUD** : `/compte/adresses` liste les adresses mais le bouton Ajouter est désactivé (follow-up Livewire create/edit).
- **Multi-user société** : 1:1 User↔Customer phase 1. Upgrade = pivot roles + invitation flow.
- **Reviews** : `/home` prévoyait un bloc reviews (non inclus — intégration Avis Vérifiés en follow-up).
- **SEO** : sitemap XML, schema.org Product/Organization/Store, meta tags dynamiques (scope phase suivante).

