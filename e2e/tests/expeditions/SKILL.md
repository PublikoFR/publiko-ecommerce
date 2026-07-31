# Skill E2E — Expéditions & livraison

## Périmètre

Suite Playwright couvrant le domaine **expéditions & livraison** du storefront :
sélection du mode de livraison au checkout (composant `ShippingOptions`, lot L5),
franco de port, forfaits transport, et flux « commande sur devis ».

| Fichier | Contenu |
|---|---|
| `modes-livraison.spec.ts` | 3 services Chronopost, défaut Chrono 13, masquage du relais > 20 kg, bandeaux franco/exclusion, récap ventilé, panier port inclus, avancée vers paiement, rupture de stock |
| `commande-sur-devis.spec.ts` | Flux `awaiting-quote` : produit sur devis, création de commande sans paiement, colis hors grille |
| `helpers.ts` | Login pro, `addSkuToCart`, `reachShippingStep`, `reachPaymentStep`, `shippingForm`, `optionRadios`, table `TX_SLUGS` |

## Compte utilisateur

| Rôle | Email | Mot de passe |
|---|---|---|
| Pro (installateur) | `thierry.leroy@weklo.test` | `testing123` |

Créé par `PkoCustomerSeeder`, qui attache **aussi** le groupe par défaut
(`nouveau-client`) et pose `email_verified_at` — sans ça `ProAccess::denialReason()`
refuse la connexion storefront (« Accès réservé aux comptes professionnels ») et
toute la suite authentifiée tombe.

## Fixtures — catalogue de test expédition

`PkoShippingCasesProductSeeder` (cf. `docs/shipping.md` §5.17) seede 20 produits
`TX-01` → `TX-20` aux poids/prix/stocks **déterministes**. Les SKU utilisés ici :

| SKU | Ce qu'il déclenche |
|---|---|
| `TX-01` | 1,5 kg → 3 services, Chrono 13 présélectionné |
| `TX-02` | 7 kg / 150 € — ×3 = 450 € HT → bandeau de progression franco |
| `TX-05` | 25 kg → point relais masqué |
| `TX-07` | 35 kg → hors grille → sentinelle « Transport sur devis » |
| `TX-08` | 600 € HT → franco atteint (Chrono 13 offert) |
| `TX-09` | exclu du franco → bandeau d'exclusion |
| `TX-10` | mode `flat` (25 € HT) → récap ventilé |
| `TX-13` | mode `free` → option unique « Livraison offerte » |
| `TX-14` | mode `quote` → flux devis complet |
| `TX-20` | stock 0 + `purchasable=in_stock` → refus d'ajout au panier |

Les anciennes méthodes table-rate (`mde-standard` / `mde-pickup` / `mde-free`) ne
sont **plus seedées du tout** : toutes les options viennent de la grille Chronopost.

`TX_SLUGS` (dans `helpers.ts`) mappe SKU → slug produit. Les slugs sont dérivés de
« marque + nom + MPN » : un renommage dans le seeder impose de régénérer la table.

## Parcours front réel

Composant `ShippingOptions` (`resources/views/livewire/components/shipping-options.blade.php`) :
- en-tête **« Mode de livraison »**, cartes `<label>` avec radio `wire:model.live="chosenOption"`
  (pas d'attribut `name` → cibler par `value`, cf. `optionRadios()`) ;
- bouton de validation **« Continuer »** ;
- bandeaux franco / progression / exclusion / multi-colis en tête de formulaire ;
- récap ventilé (tableau) dès qu'un forfait ou un supplément s'ajoute à la grille.

Séquence : `/checkout` → « Adresse de livraison » (bouton « Enregistrer l'adresse »)
→ « Mode de livraison » → « Paiement ».

**Panier 100 % devis** : aucune option n'étant calculable, `determineCheckoutStep()`
saute l'étape livraison et ouvre directement le paiement → utiliser
`reachPaymentStep()` et non `reachShippingStep()`. Le cas hors-grille (`TX-07`)
garde en revanche son étape livraison, avec la sentinelle « Transport sur devis ».

## Volets non couverts en E2E (documentés)

### Multi-expédition (découpage `CarrierShipment` par origine)
Logique **post-paiement, 100 % back-office** (`OrderShipmentObserver` →
`ShipmentSplitter::split()`). Aucune surface storefront → couverture par tests
Feature PHP (`tests/Feature/Shipping/*`).

### Scission de commande (panier mixte devis + payable)
Couverte côté PHP par `tests/Feature/Shipping/OrderSplitTest.php` (5 scénarios).
Le parcours navigateur demande un paiement Stripe réel côté branche payable → non
rejouable en E2E sans stub de paiement dans la stack jetable.

### Calcul des frais de port
`tests/Feature/Shipping/ShippingCasesTest.php` joue les 20 scénarios de tarification
(grille, franco, flat, free, quote, Corse) sur un vrai panier Lunar — beaucoup plus
rapide et déterministe qu'un parcours navigateur. L'E2E ne vérifie que le **rendu**.

## Relancer la suite

```bash
npm run test:e2e -- expeditions          # domaine expéditions uniquement
npm run test:e2e -- modes-livraison      # un seul fichier
```

Le harness (`scripts/e2e-run.sh` + `e2e/global-setup.ts`) monte une stack Docker
isolée (`docker-compose.e2e.yml`, base `pko_e2e`) et joue `migrate:fresh --seed`.
La base `pko_e2e` est explicitement autorisée par `DestructiveCommandGuard` — sans
cette exception, la garde anti-wipe bloque le setup et tous les tests échouent.

Cold-start ≈ 90–120 s ; chaque test porte `test.setTimeout(240_000)`.
Ne pas lancer en parallèle d'une autre suite e2e (stack partagée).
