# Chantier E-mails v2 — brief d'exécution

> **Terminé le 2026-09-02.** Les 5 volets sont livrés.
> Documentation à jour : `docs/packages/mail-templates.md`.
> Ce fichier est conservé comme trace du périmètre demandé ; il peut être
> supprimé une fois le chantier validé par Rom.

Document autoportant. Tu reprends un chantier déjà commencé par une autre session.
Lis-le en entier avant de coder. Projet : `~/webdev/projects/ecom-laravel`
(Laravel 11 + Lunar 1.x + Filament 3). Respecte `CLAUDE.md` à la racine.

## 1. Ce qui existe déjà (ne pas refaire)

Un socle d'e-mails transactionnels est livré et committé (`6abd8f8`, branche
`feat/mail-templates-18`). Documentation complète : **`docs/packages/mail-templates.md`**
(à lire), état par mail : `docs/mails-plan.md`.

En résumé :

- `packages/pko/mail-templates/` — socle neutre : table `pko_mail_templates`
  (`key`, `subject`, `blocks` JSON, `enabled`), `TemplatedMail`, `TemplateResolver`,
  `Placeholders`, `ContentGuard`, `OnceMailer` (anti-doublon), Resource Filament,
  preview `/_mail`, `BrandMailFrom`.
- `packages/pko/order-notifications/` — déclencheurs commande : `OrderMailObserver`,
  4 commandes planifiées, action Filament « Signaler un retard ».
- 18 contenus dans `packages/pko/mail-templates/database/content/fr.php` (**data**,
  seul fichier portant la marque — cf. §3.0 du CLAUDE.md, ne jamais mettre de nom
  de marque dans du code PHP).
- 23 tests dans `tests/Feature/MailTemplates/`.

Menu admin : **Configuration → Réglages → E-mails**, déclaré dans
`packages/pko/lunar-admin-nav/src/Navigation/Builder.php` (méthode `configuration()`).

## 2. Ce qui est demandé (le chantier)

Rom a validé ces 5 volets, dans cet ordre de dépendance. 1 et 2 conditionnent 3.
Les volets 4 et 5 sont indépendants et peuvent être faits à tout moment.

### Volet 1 — Stockage au format page-builder

Aujourd'hui `blocks` est une **liste plate** de blocs typés. Il faut passer à
l'arbre du page-builder (`{heading, sections:[{layout, columns:[{blocks:[]}]}]}`),
schéma : `packages/pko/page-builder/resources/schema/content.schema.json`.

- Ajouter une colonne `content` (JSON) sur `pko_mail_templates`.
- Migrer les 18 contenus de `database/content/fr.php` vers le nouveau format.
- Adapter `TemplatedMail`, `Placeholders::applyToBlocks()`, `Placeholders::found()`
  et `ContentGuard` : ils itèrent aujourd'hui sur une liste plate, ils doivent
  parcourir l'arbre (sections → colonnes → blocs).
- Décider et documenter : on garde `blocks` en parallèle pour rétrocompat, ou on
  bascule franchement. Une bascule franche est préférable (rien n'est en prod),
  mais alors la migration doit convertir les lignes existantes en base.

### Volet 2 — Renderer e-mail table-based

**Point critique.** Le rendu du page-builder est du HTML web : Tailwind, `flex`,
classes CSS (voir `packages/pko/page-builder/resources/views/partials/block-button.blade.php`).
C'est **inutilisable en e-mail** — Gmail et Outlook ignorent les CSS externes.

Il faut un renderer **dédié** qui parcourt le même arbre JSON et produit des
`<table>` avec styles **inline**, au-dessus du layout existant
`packages/pko/storefront-cms/resources/views/components/mail/layout.blade.php`
(qui fournit déjà logo, contact, réseaux, mentions légales, et lit la marque
depuis les `Setting` — ne pas le dupliquer).

Blocs à supporter : `text`, `title`, `button`, `separator`, `callout`, `list`,
`image`, `quote`.
Blocs à **exclure** (aucun sens ou cassés en e-mail) : `video`, `accordion`,
`gallery`, `code`.

Multi-colonnes : **1 et 2 colonnes uniquement** (tables imbriquées). Au-delà,
illisible sur mobile et mal géré par Outlook. Si Rom demande davantage, le lui
faire confirmer explicitement.

Le rendu actuel `packages/pko/mail-templates/resources/views/message.blade.php`
est un bon point de départ (styles inline, échappement) mais ne gère qu'une liste plate.

### Volet 3 — Intégration de l'éditeur PageBuilder

Le Repeater Filament actuel est jugé illisible par Rom. Le remplacer par le
composant Livewire `Pko\PageBuilder\Livewire\PageBuilder`.

- `mount(string $modelClass, int $recordId, bool $withMeta = false, ?string $indexUrl = null)`.
  **`withMeta: false`** désactive déjà tout le bagage CMS (titre, slug, SEO,
  post type, cover) — c'est exactement ce qu'il faut ici.
- Il lit et écrit `$record->content` via `PageBuilderManager::normalize()`.
- Conserver les réglages propres au mail : **objet** et **actif** (toggle), ainsi
  que le rappel des variables disponibles (aujourd'hui `MailTemplateResource::placeholderHint()`).
- Restreindre la palette aux blocs supportés par le renderer du volet 2.
- Voir `packages/pko/page-builder/resources/views/filament/edit-with-builder.blade.php`
  pour le pattern d'intégration dans une page Filament.

### Volet 4 — Aperçu en sidepanel

- Ajouter une action **œil** dans la liste des e-mails (`MailTemplateResource::table()`).
- Elle ouvre un **panneau latéral** (slide-over Filament) affichant le mail **rendu
  complet** : layout, logo, pied de page, avec des valeurs de démonstration.
- Réutiliser le **rendu réel** (celui du volet 2), pas une approximation : l'intérêt
  est de voir ce que le client recevra.
- Des valeurs de démonstration existent déjà :
  `MailPreviewController::sampleValues()` — les factoriser plutôt que les dupliquer.

### Volet 5 — Colonne destinataire

- Ajouter un champ `audience` par mail dans `MailTemplateRegistry` :
  `customer` | `admin` | `both`.
- L'afficher en **colonne avec badge** dans la liste, et le rendre **filtrable**.
- Les 18 modèles actuels partent tous au client. Les mails admin existants
  (`CustomerRegisteredAdminMail`, `TierUnlockedAdmin`) sont hors de ce lot — ne pas
  les embarquer, mais le champ doit pouvoir les décrire si on les migre un jour.

## 3. Contraintes

- `CLAUDE.md` fait foi. En particulier : `declare(strict_types=1)`, PSR-12,
  packages sous `packages/pko/*`, **jamais** de nom de marque dans du code PHP
  (uniquement dans les fichiers de contenu/seed), jamais toucher `vendor/`.
- **MCP obligatoires** : `laravel-boost` pour toute question Laravel/Filament/Livewire,
  `lunar-docs` pour Lunar. Ne pas répondre de mémoire sur ces sujets.
- Après toute création de Resource/Page Filament : **`make shield-sync`**.
- `make lint` doit être vert avant de rendre.
- Boucle de test : **`make test-only T=tests/Feature/MailTemplates`** (~30 s),
  PAS `make test` (7 min). La suite complète une seule fois, à la fin.
- **Ne jamais committer sans l'accord explicite de Rom.** Proposer, ne pas faire.
- Mettre à jour `docs/packages/mail-templates.md` **dans le même commit** que le code.

## 4. Pièges déjà rencontrés (ne pas les refaire)

- Les mailables implémentent `ShouldQueue` → en test, `Mail::assertQueued`,
  **jamais** `Mail::assertSent` (qui ne voit rien et fait croire à un bug).
- `TemplatedMail` ne peut pas déclarer de propriété `$locale` : `Illuminate\Mail\Mailable`
  en a déjà une, non-readonly → fatal error au chargement. D'où `$templateLocale`.
- `Livewire::fillForm()` **ne remplace pas** les items d'un Repeater Filament : les
  valeurs initiales survivent et le test ment. C'est pourquoi la validation vit dans
  `ContentGuard` (classe testable directement) et non dans la page.
- Le `DatabaseSeeder` seede les 18 modèles → dans les tests, `MailTemplate::create()`
  sur une clé existante viole la contrainte unique. Récupérer la ligne et l'`update()`.
- Une resource absente de `Builder.php` **n'apparaît pas** dans la sidebar, quel que
  soit son `navigationGroup` : `AdminNavPlugin` reconstruit tout le menu.
- `Order::factory()` ne crée ni adresse ni destinataire : un test d'envoi doit créer
  une `OrderAddress` avec `contact_email` **et** `country_id` (un observer Lunar
  tiers lève une TypeError sans pays).

## 5. État git au moment du transfert

Branche `feat/mail-templates-18`. Le socle est committé (`6abd8f8`).
**Modifications non committées** (à conserver, elles sont valides) :

- `packages/pko/lunar-admin-nav/` — ajout de l'entrée de menu E-mails
- `packages/pko/mail-templates/database/content/fr.php` — textes SIRET restaurés
- `packages/pko/mail-templates/lang/fr/admin.php` — groupe « Configuration »
- `docs/mails-plan.md`

⚠️ Un fichier non suivi `tests/Unit/MailTemplateModelTest.php` (stub vide
`assertTrue(true)`) ne vient pas de cette session — ne pas le supprimer sans
demander à Rom.

⚠️ La suite complète `make test` n'a jamais pu être menée à terme (processus
interrompus). Le dernier run signalait « au moins un chunk a échoué » sans
identifier lequel. **À investiguer** : lancer `make test > /tmp/make-test-full.log 2>&1`
puis chercher `FAILED` dans le log complet, et déterminer si l'échec est lié à ce
chantier ou préexistant.

## 6. Questions ouvertes côté client (ne pas trancher seul)

- Mail 13 « demande d'avis » : inactif tant que `ORDER_REVIEW_URL` est vide.
- Mails 07 et 08 : contenus non fournis, modèles seedés `enabled=false`.
- Mails 14 et 15 (SAV) : aucun module SAV dans le projet.
- Mail 16 (avoir) : modèle prêt, déclencheur non branché.
