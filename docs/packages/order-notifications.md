# Package `pko/lunar-order-notifications`

Envoi des e-mails transactionnels déclenchés par le parcours commande et les relances commerciales.

## Responsabilités

- Observer de statut commande (`OrderMailObserver`) → déclenche les mails post-achat
- Commandes artisan planifiées → relances autonomes (panier, devis, avis, anniversaire)
- Chaque envoi passe par `OnceMailer` (idempotence via `pko_mail_dispatch_log`)

## Commandes planifiées

| Commande | Horaire | Déclencheur |
|---|---|---|
| `pko:mails:abandoned-carts` | 10h00 | Paniers sans commande depuis N jours |
| `pko:mails:quote-reminders` | 10h30 | Devis sans suite depuis N jours |
| `pko:mails:review-requests` | 11h00 | Commandes livrées depuis N jours |
| `pko:mails:loyalty-anniversaries` | 09h00 | Anniversaires de fidélité |

## Délai de relance panier (`cart.abandoned`)

**Valeur par défaut : 5 jours** (configurable sans redéploiement).

### Priorité de lecture

1. **Base de données** : `pko_mail_templates.settings['delay_days']` pour la ligne `key = 'cart.abandoned'`
   — modifiable depuis **Paramètres → E-mails** (champ « Délai de relance » visible uniquement sur ce modèle).
2. **Env var** : `ABANDONED_CART_HOURS` (en heures, divisé par 24 en interne). Défaut config = 120 h (5 j).

### Fenêtre de sélection

La commande cherche les paniers dont `updated_at` est entre `now - delay` et `now - (delay + 14 j)`.
La fenêtre de 14 jours garantit qu'un panier n'est pas manqué si la commande ne tourne pas exactement à l'heure.
`OnceMailer` empêche tout double envoi (contrainte unique en base sur `(mail_key, entity_type, entity_id, recipient)`).

### Modification du délai

Back-office : **Paramètres → E-mails → Panier non finalisé → modifier → Délai de relance**.
Aucun redéploiement nécessaire. La commande de 10h00 du lendemain utilise la nouvelle valeur.

## Configuration

```php
// config/order-notifications.php
'abandoned_cart_hours' => env('ABANDONED_CART_HOURS', 120), // fallback si pas de valeur en base
'quote_reminder_days'  => env('QUOTE_REMINDER_DAYS', 5),
'review_delay_days'    => env('ORDER_REVIEW_DELAY_DAYS', 7),
'review_url'           => env('ORDER_REVIEW_URL', ''),
```

## Idempotence

`OnceMailer::send()` pose un `insertOrIgnore` sur `pko_mail_dispatch_log` avant chaque envoi.
La contrainte unique `(mail_key, entity_type, entity_id, recipient)` garantit qu'une commande rejouée
ou un double déclencheur scheduler n'envoie pas deux fois le même mail au même destinataire.
En cas d'échec SMTP, la ligne est supprimée pour permettre un rejeu ultérieur.
