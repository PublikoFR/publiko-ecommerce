# Bibliothèque de 18 e-mails — plan d'intégration

Source : document client « Bibliothèque d'emails », version du 31 août 2026.
Statut : **implémenté** le 2026-09-01 (branche `feat/mail-templates-18`).
Implémentation : `docs/packages/mail-templates.md`. Questions encore ouvertes en fin de fichier.

## Architecture retenue

Textes stockés en base (`pko_mail_templates`), seedés depuis le code, éditables
plus tard depuis une page Filament. Décision et alternatives : cf. § Socle.

Le code reste **neutre de marque** (§3.0 du CLAUDE.md) : « WEKLO », la signature
« La fermeture pour les pros. » et le discours commercial vivent dans le seeder
et en base, jamais dans une classe PHP ni un Blade. Une autre boutique reseede.

## Mapping contre l'existant

| # | Mail | État | Cible |
|---|---|---|---|
| 01 | Bienvenue / inscription | ✅ livré | `CustomerRegisteredMail` — bascule sur le socle |
| 02 | Activation du compte | ✅ livré | `AccountActivatedMail`, à la vérification de l'e-mail |
| 03 | Première commande + cadeau | ✅ livré | `FirstOrderGiftMail`, 1re commande payée |
| 04 | Confirmation de commande | ✅ livré | `OrderConfirmedMail` — comble le trou critique |
| 05 | Paiement confirmé | ✅ livré | `OrderPaymentReceivedMail` |
| 06 | Commande expédiée | ✅ livré | `ShipmentCreatedMail` — bascule sur le socle |
| 07 | Disponible au retrait | **en attente** | contenu à venir — clé seedée `enabled=false` |
| 08 | Expédié par le partenaire | **en attente** | contenu à venir — clé seedée `enabled=false` |
| 09 | Retard de commande | ✅ livré | action « Signaler un retard » sur la fiche commande |
| 10 | Relance devis | ✅ livré | `pko:mails:quote-reminders`, 10h30 |
| 11 | Panier non finalisé | ✅ livré | `pko:mails:abandoned-carts`, 10h00 |
| 12 | Commande livrée | ✅ livré | statut `delivered` — reste à savoir qui le pose |
| 13 | Demande d'avis | ⚠️ livré, inactif | attend `ORDER_REVIEW_URL` |
| 14 | Demande SAV reçue | **bloqué** | aucun module SAV dans le projet |
| 15 | Retour SAV accepté | **bloqué** | aucun module SAV ni étiquette retour |
| 16 | Avoir disponible | ⚠️ modèle prêt | déclencheur non branché |
| 17 | Fidélité / nouveau palier | ✅ livré | `TierUnlockedMail` — bascule sur le socle |
| 18 | Anniversaire fidélité | ✅ livré | `pko:mails:loyalty-anniversary`, 09h00 |

Récap : **13 livrés et actifs**, 1 livré mais inactif (13, attend son URL), 1 modèle prêt
sans déclencheur (16), 2 en attente de contenu client (07, 08), 2 bloqués faute de module
SAV (14, 15).

Les mails en attente ou bloqués sont **seedés désactivés** (`enabled=false`) : la clé
existe, le déclencheur est câblé, seul le contenu manque. Les activer plus tard ne
demande aucune modification de code.

## Socle livré

1. ✅ Table `pko_mail_templates` + modèle + résolveur avec repli sur le fichier de contenus
2. ✅ Template Blade générique par blocs, au-dessus de `storefront-cms::components.mail.layout`
3. ✅ Seeder des 18 contenus (`pko:mail-templates:sync`), branché au `DatabaseSeeder`
4. ✅ Placeholders déclarés par mail et validés à l'enregistrement (`ContentGuard`)
5. ✅ Preview locale `/_mail` et `/_mail/{key}`
6. ✅ Expéditeur lu depuis le `Setting` storefront (`BrandMailFrom`)
7. ✅ Page Filament d'édition (Paramètres → E-mails)
8. ✅ Garde anti-doublon `OnceMailer` + table `pko_mail_dispatch_log`

Le point 7 du plan initial (brancher les `mailers` de `config/lunar/orders.php`) a été
écarté : un observer dédié (`OrderMailObserver`) donne le même résultat en gardant la
logique dans un package, sans toucher à la config Lunar (§6 du CLAUDE.md).

## Questions ouvertes

- ~~07 et 08~~ : **tranché le 2026-09-01** — contenus communiqués plus tard, clés seedées désactivées.
- ~~02~~ : **répondu par le code** — l'activation se fait à la vérification de l'e-mail
  (`customer-auth/routes/web.php`), il n'y a aucune validation SIRET manuelle. 01 et 02
  s'enchaînent donc correctement. ⚠️ Mais le texte client du 02 affirme « votre SIRET a été
  validé », ce qui est faux : le SIRET n'est pas vérifié (INSEE peut être coupé, la valeur
  peut être nulle). Mention retirée des deux contenus — **à faire confirmer au client**.
- **03** : « un petit cadeau glissé dans le colis » — geste logistique manuel. Le mail
  l'annonce ; qui garantit que le cadeau est réellement mis ?
- **13** : le CTA « Donner mon avis » pointe vers quoi ? (Google, Trustpilot, formulaire interne)
- **14/15** : le SAV est-il dans le périmètre du projet ? C'est un module complet, pas un mail.
- **16** : l'avoir est-il exposé au client dans son espace ? Le texte dit « depuis votre espace client ».
- **12** : le statut « livré » est-il poussé manuellement, ou lu depuis le tracking transporteur ?
