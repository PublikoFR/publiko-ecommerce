# pko/lunar-mail-templates + pko/lunar-order-notifications

Socle des e-mails transactionnels et déclencheurs du parcours commande.
Périmètre : la bibliothèque de 18 modèles fournie par le client (31/08/2026).

## Pourquoi deux packages

| Package | Rôle |
|---|---|
| `pko/lunar-mail-templates` | **Socle neutre** : stockage, rendu, édition back-office. Aucun métier, aucune marque. |
| `pko/lunar-order-notifications` | **Déclencheurs commande** : observer de statut, commandes planifiées, action Filament « retard ». |

Les e-mails rattachés à un domaine existant restent dans leur package
(`customer-auth` pour l'inscription, `shipping-common` pour l'expédition,
`loyalty` pour les paliers) et étendent simplement `TemplatedMail`.

## Où vit un texte

1. **Base** — `pko_mail_templates` (`key`, `subject`, `blocks` JSON, `enabled`). Source de vérité, éditable en back-office.
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

Les tests `MailTemplateRenderingTest` échouent si une clé n'a pas de contenu,
si un contenu n'a pas de clé, ou si un placeholder utilisé n'est pas déclaré.

## Blocs disponibles

`paragraph`, `heading`, `button` (`label`, `url`, `variant: primary|accent`),
`divider`, `signature`. Le rendu s'appuie sur
`storefront-cms::components.mail.layout` (logo, contact, mentions légales) et
échappe systématiquement le contenu.

## Édition back-office

**Paramètres → E-mails**. La création est désactivée (les modèles viennent du
seeder), seule l'édition est offerte. `ContentGuard` refuse l'enregistrement si
une variable **obligatoire** a disparu d'un modèle actif, ou si une variable
**inconnue** a été inventée. Un modèle désactivé tolère un contenu incomplet :
c'est l'état des e-mails dont le contenu n'est pas encore arrivé.

## Prévisualisation

`/_mail` liste les 18, `/_mail/{key}` rend le message avec des valeurs de
démonstration. Routes chargées **uniquement** en `local` et `testing`.

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

## Ce qui n'est pas couvert

- **14 et 15 (SAV)** — aucun module SAV n'existe. Modèles seedés désactivés.
- **07 et 08** — contenus non fournis par le client. Modèles seedés désactivés.
- **16 (avoir)** — modèle prêt, déclencheur non branché : reste à décider si
  l'avoir est exposé dans l'espace client (Pennylane gère `auto_credit_note_on_refund`).

## Points de vigilance

- Les mailables implémentent `ShouldQueue` : en test, `Mail::assertQueued`, pas `assertSent`.
- `TemplatedMail` ne peut pas déclarer de propriété `$locale` (déjà portée, non-readonly, par `Illuminate\Mail\Mailable`) — d'où `$templateLocale`.
- Les montants affichés sont des **HT** (`sub_total`) : les textes client annoncent explicitement « € HT ».
