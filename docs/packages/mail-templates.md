# pko/lunar-mail-templates + pko/lunar-order-notifications

Socle des e-mails transactionnels et déclencheurs du parcours commande.
Périmètre : la bibliothèque de 18 modèles client fournie le 31/08/2026, plus
4 modèles **équipe** (inscription, nouvelle commande, virement, palier fidélité).

## Pourquoi deux packages

| Package | Rôle |
|---|---|
| `pko/lunar-mail-templates` | **Socle neutre** : stockage, rendu, édition back-office. Aucun métier, aucune marque. |
| `pko/lunar-order-notifications` | **Déclencheurs commande** : observer de statut, commandes planifiées, action Filament « retard ». |

Les e-mails rattachés à un domaine existant restent dans leur package
(`customer-auth` pour l'inscription, `shipping-common` pour l'expédition,
`loyalty` pour les paliers) et étendent simplement `TemplatedMail`.
Les 4 modèles `audience = admin` partent à `admin_notification_email()`
(Storefront → Paramètres, champ « E-mail notifications équipe »), via
`AdminRecipient::send()` + `OnceMailer`.

## Où vit un texte

1. **Base** — `pko_mail_templates` (`key`, `subject`, `content` JSON, `enabled`). Source de vérité, éditable en back-office.
2. **Fichier de contenus** — `packages/pko/mail-templates/database/content/fr.php`. Utilisé si la clé est absente en base.

Le fichier de contenus est de la **data** au sens du §3.0.4 du CLAUDE.md : c'est
le seul endroit du package qui porte le nom de la boutique et son discours
commercial. Réutiliser le back-office sur une autre enseigne = remplacer ce
fichier et rejouer le seeder. Aucune classe PHP ne contient de nom de marque.

## Envoyer un e-mail

```php
$mail = new OrderConfirmedMail($order);

if ($mail->shouldSend()) {              // clé connue ET modèle actif
    OnceMailer::send($mail, $recipient, $order);
}
```

`shouldSend()` est faux si la clé est inconnue ou le modèle désactivé en
back-office. **Toujours le vérifier** : `build()` lève une `LogicException`.

### OnceMailer — anti-doublon

`OnceMailer::send()` réserve le tuple `(clé, entité, destinataire)` dans
`pko_mail_dispatch_log` avant l'envoi, via une contrainte unique en base. Les
déclencheurs sont des observers de statut : ils se rejouent (reprise de webhook,
backfill, sauvegarde répétée) et enverraient sinon deux fois « paiement reçu ».

Pour un e-mail **récurrent**, passer un `$scope` — sans lui, la garde ne
laisserait passer que le premier envoi de la vie du client :

```php
OnceMailer::send($mail, $email, $customer, (string) now()->year);
```

En cas d'échec SMTP, la réservation est libérée : un rejeu ultérieur retente.

## Ajouter un e-mail

1. Déclarer la clé dans `MailTemplateRegistry` (placeholders disponibles + obligatoires).
2. Ajouter le contenu dans `database/content/fr.php`.
3. Créer la classe `Mailable` qui étend `TemplatedMail`.
4. Brancher le déclencheur.
5. `php artisan pko:mail-templates:sync`.

### Revoir le contenu par défaut d'un e-mail existant

La synchro sans `--force` ne touche pas une ligne déjà en base : modifier
`fr.php` ne suffit pas pour qu'un texte revu arrive en production. Le passage
se fait **par migration**, donc automatiquement au déploiement :

1. Modifier le contenu dans `database/content/fr.php`.
2. Recopier l'**ancienne** version (objet + contenu, telle qu'avant la
   modification) dans `database/content/upgrades/<date>_<sujet>.php`, indexée
   par clé. C'est de la DATA, comme `fr.php` (marque autorisée).
3. Créer une migration qui appelle
   `DefaultContentUpgrade::applyFile(__DIR__.'/../content/upgrades/<fichier>.php')`.

`DefaultContentUpgrade` ne remplace le contenu en base **que s'il est encore
identique à l'ancienne version** (comparaison sur l'arbre page-builder
normalisé, identifiants retirés : un modèle ouvert puis réenregistré sans
changement compte comme non retouché). Un texte ou un objet retouché en
back-office est conservé et signalé par un `warning` dans les logs
(`Mail template kept: customized in back-office`). `enabled` n'est jamais
modifié. Sans ligne en base, rien n'est fait : la synchro la créera avec le
nouveau contenu.

Exemple : `2026_09_17_000100_upgrade_loyalty_tier_unlocked_mail_content`
(encart cadeau). Test : `DefaultContentUpgradeTest`.

Hors déploiement, pour forcer un seul modèle à la main (écrase les retouches) :
`php artisan pko:mail-templates:sync --force --key=loyalty.tier_unlocked`
(`--key` répétable).

Les tests `MailTemplateRenderingTest` échouent si une clé n'a pas de contenu,
si un contenu n'a pas de clé, ou si un placeholder utilisé n'est pas déclaré.

## Format du contenu et rendu

Le contenu suit l'**arbre du page-builder** (`{heading, sections:[{layout,
columns:[{blocks:[]}]}]}`), le même que les pages et articles — schéma :
`packages/pko/page-builder/resources/schema/content.schema.json`.

Blocs rendus en e-mail : `text`, `title`, `button`, `separator`, `callout`,
`list`, `image`, `quote`. Les blocs `video`, `accordion`, `gallery` et `code`
sont **ignorés silencieusement** : ils n'ont pas d'équivalent utilisable dans un
client mail.

**Le rendu du page-builder n'est pas réutilisé.** Il produit du Tailwind et des
classes CSS, que Gmail et Outlook ignorent. Chaque bloc est redécliné en
`<table>` + styles inline dans `resources/views/blocks/`, assemblés par
`resources/views/message.blade.php` au-dessus de
`storefront-cms::components.mail.layout` (logo, contact, mentions légales).

### Colonnes : fluid hybrid, sans media query

Jusqu'à **3 colonnes** côte à côte (`EmailLayout::MAX_SIDE_BY_SIDE`), au-delà
chaque colonne prend toute la largeur et s'empile.

Le rendu n'utilise **pas** de media query : l'application Gmail Android les
ignore, or c'est justement le client où le repli compte. À la place, chaque
colonne est un `inline-block` borné par une `max-width` en pixels — quand la
fenêtre devient trop étroite pour les aligner, elles passent d'elles-mêmes les
unes sous les autres. C'est pour cette raison que les largeurs sont calculées en
pixels et non en pourcentages : un pourcentage resterait proportionnel et
n'empilerait jamais rien.

Outlook (moteur Word) ne connaît pas `inline-block` : des commentaires
conditionnels `[if mso]` lui fournissent une vraie table, invisible ailleurs.

Le conteneur porte `font-size:0` pour supprimer le blanc que les navigateurs
insèrent entre deux éléments inline ; chaque colonne rétablit sa taille.

Géométrie dans `EmailLayout` : gabarit 600 px, padding 32 px, soit 536 px utiles ;
2 colonnes → 260 px, 3 colonnes → 168 px, gouttière de 16 px.

### Sections encadrées et blocs vides

Une section dont `background_color` ou `padding` est renseigné (mêmes champs que
dans l'éditeur de pages) est rendue dans une table à fond coloré, coins arrondis
12 px. La largeur des colonnes est alors calculée sur la largeur utile **moins**
le padding horizontal (`EmailLayout::columnWidth($n, $available)`) : 2 colonnes
dans un encart de 20 px → 240 px.

Un bloc qui ne rendrait rien une fois les variables remplacées (image sans
source, texte ou titre vides) est retiré **avant** de compter les colonnes
(`EmailLayout::blockIsEmpty()`). Sans ce filtre, une colonne ne contenant qu'une
image absente laisserait un trou à côté du texte.

Le champ `url` du bloc `image` accepte un placeholder (`:gift_image_url`). La
valeur doit être une URL **absolue** : un client mail ne résout pas un chemin
relatif.

Exemple : `loyalty.tier_unlocked` présente le cadeau dans un encart lime clair
(`#f6faea`), photo à gauche (`:gift_image_url`), intitulé et description à
droite (`:gift_title`, `:gift_description`). Sans photo, le texte prend toute la
largeur de l'encart.

### Texte riche

Le bloc `text` contient du HTML issu de l'éditeur, sémantique et sans style. Un
client mail n'a pas de feuille de styles : `EmailHtml::inlineStyles()` pose donc
les styles manquants sur `p`, `a`, `ul`, `ol`, `li`, titres, `blockquote`, `hr`,
tableaux et images. Sans lui, les marges de paragraphe varient d'un client à
l'autre et les liens s'affichent en bleu système au lieu de la couleur de marque.

Les styles déjà présents sur l'élément sont conservés et placés **après** les
nôtres : à déclarations égales dans un même attribut `style`, la dernière
l'emporte, donc la mise en forme choisie par le rédacteur gagne toujours.

La conversion depuis l'ancien format plat est assurée par `LegacyBlocksConverter`
et la migration `2026_09_02_000100_convert_pko_mail_templates_to_page_builder_content`.

## Édition back-office

**Configuration → Réglages → E-mails** (déclaré dans
`lunar-admin-nav/src/Navigation/Builder.php` — une resource absente de ce fichier
n'apparaît pas dans la sidebar, quel que soit son `navigationGroup`).

L'écran d'édition combine deux mécanismes :

- **Réglages** (objet, activation) : form Filament classique.
- **Contenu** : le composant Livewire `pko-page-builder`, le même éditeur que les
  pages et articles, monté avec `withMeta: false` (pas de titre, slug, SEO ni
  couverture). Il écrit lui-même la colonne `content`.

La création est désactivée : les modèles viennent du seeder.

`ContentGuard` refuse le contenu si une variable **obligatoire** a disparu d'un
modèle actif, ou si une variable **inconnue** a été inventée. La garde est posée
dans **`MailTemplate::saving()`** et non dans la page : c'est le seul point de
passage commun à l'éditeur Filament, au PageBuilder, au seeder et à tinker. Elle
lève une `ContentGuardException`, que `EditMailTemplate` traduit en erreur de
formulaire. Un modèle désactivé tolère un contenu incomplet.

### Liste des modèles

Colonne **Destinataire** (client / équipe / les deux), issue du champ `audience`
de `MailTemplateRegistry` — c'est du code, pas une colonne en base, d'où un
filtre qui traduit la valeur en liste de clés. Les 18 modèles d'origine partent
au client ; les clés `*_admin` partent à l'équipe.

Trois actions, en icônes seules, chacune porteuse d'une infobulle Alpine/Tippy
(`tooltip()`, rendu `x-tooltip`) et non d'un attribut `title` : le picto étant le
seul repère visuel, l'infobulle est le libellé de l'action. Un `title` natif
n'est ni stylable, ni immédiat, ni accessible au tactile.

- **Œil** — panneau latéral affichant le mail rendu, dans une iframe isolée (le
  HTML d'un e-mail porte ses propres styles, qui déborderaient sur le
  back-office). Le rendu passe par `TemplatedMail`, donc par le chemin réel
  d'envoi : l'aperçu montre ce que le client recevra.
- **Crayon** — édition du modèle.
- **Avion en papier** — envoie le modèle à une adresse, pré-remplie avec celle
  de l'utilisateur connecté, pour juger le rendu dans un vrai client mail.
  L'objet est préfixé `[TEST]`, sans quoi le message serait indiscernable d'un
  vrai envoi. L'envoi contourne `OnceMailer` : la garde anti-doublon
  n'autoriserait qu'un seul test par modèle et par destinataire. L'action est
  masquée sur un modèle désactivé ou sans contenu.

En local, ces envois arrivent dans le Mailpit partagé : **http://mail.localhost**.

## Prévisualisation

Deux chemins, une seule source : `MailPreview` (valeurs de démonstration + rendu).

- **Back-office** : action œil dans la liste (voir ci-dessus).
- **Route locale** : `/_mail` liste les modèles, `/_mail/{key}` rend un message.
  Chargées **uniquement** en `local` et `testing`.

## Expéditeur

`BrandMailFrom` aligne `mail.from` sur le `Setting` storefront
(`contact.email_sender`, à défaut `contact.email`), avec repli sur `MAIL_FROM_*`.

## Commandes planifiées

| Commande | Heure | E-mail |
|---|---|---|
| `pko:mails:loyalty-anniversary` | 09:00 | 18 — anniversaire |
| `pko:mails:abandoned-carts` | 10:00 | 11 — panier non finalisé |
| `pko:mails:quote-reminders` | 10:30 | 10 — relance devis |
| `pko:mails:review-requests` | 11:00 | 13 — demande d'avis |

Toutes acceptent `--dry-run`. Horaires ouvrés volontaires : ces messages sont
commerciaux, les envoyer en pleine nuit dessert le propos.

## Variables d'environnement

| Variable | Défaut | Rôle |
|---|---|---|
| `ORDER_REVIEW_URL` | `''` | Destination du bouton « Donner mon avis ». **Vide = e-mail 13 jamais envoyé.** |
| `ORDER_REVIEW_DELAY_DAYS` | `7` | Délai après livraison avant la demande d'avis |
| `ABANDONED_CART_HOURS` | `24` | Inactivité avant relance panier |
| `QUOTE_REMINDER_DAYS` | `5` | Attente avant relance d'un devis |

## E-mails équipe

Destinataire unique : `admin_notification_email()` lit `Setting::get('admin_email')`
(Storefront → Paramètres), puis `ADMIN_NOTIFICATION_EMAIL` / `CONTACT_EMAIL`,
puis `LOYALTY_ADMIN_EMAIL`. Si vide, l'envoi est sauté (log info).

| Clé | Déclencheur | Package |
|---|---|---|
| `account.registered_admin` | `RegisterProCustomer` | customer-auth |
| `order.placed_admin` | `placed_at` renseigné | order-notifications |
| `order.payment_offline_admin` | statut `payment-offline` | order-notifications |
| `loyalty.tier_unlocked_admin` | déblocage de palier | loyalty |

L'inscription équipe porte un `Reply-To` vers l'e-mail du client, pour
répondre d'un clic. Le bouton « Appeler » n'est rendu que si un téléphone
a été saisi (`PhoneLink::href()`, ignoré si l'URL est vide).

## Ce qui n'est pas couvert

- **14 et 15 (SAV)** — aucun module SAV n'existe. Modèles seedés désactivés.
- **07 et 08** — contenus non fournis par le client. Modèles seedés désactivés.
- **16 (avoir)** — modèle prêt, déclencheur non branché : reste à décider si
  l'avoir est exposé dans l'espace client (Pennylane gère `auto_credit_note_on_refund`).

## Points de vigilance

- Les mailables implémentent `ShouldQueue` : en test, `Mail::assertQueued`, pas `assertSent`.
- `TemplatedMail` ne peut pas déclarer de propriété `$locale` (déjà portée, non-readonly, par `Illuminate\Mail\Mailable`) — d'où `$templateLocale`.
- **Aucune propriété `readonly` ni `private` dans `TemplatedMail`** (2026-09-17). Un mail en file est restauré par `SerializesModels::__unserialize`, qui réaffecte les propriétés par réflexion depuis la portée de la **classe fille** : PHP refuse d'initialiser une `readonly` déclarée dans le parent (« Cannot initialize readonly property … from scope … »), et ignore les `private` du parent (« must not be accessed before initialization » dans `build()`). Conséquence : les 12 mails en `ShouldQueue` échouaient tous dès qu'une vraie file (redis, database) était branchée. La suite tourne en `QUEUE_CONNECTION=sync` et ne sérialise rien — `QueuedTemplatedMailSerializationTest` fait l'aller-retour `serialize`/`unserialize` pour couvrir ce trou. Les `readonly` déclarées dans les classes filles, elles, ne posent pas de problème.
- Les montants affichés sont des **HT** (`sub_total`) : les textes client annoncent explicitement « € HT ».
- Après un changement de format de contenu, penser à `make artisan CMD='migrate'` sur la base de dev : les tests (`RefreshDatabase`) rejouent toutes les migrations et restent verts même si la base de dev est en retard, ce qui masque le décalage jusqu'à l'ouverture de l'écran.
