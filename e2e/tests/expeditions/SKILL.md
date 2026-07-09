# Skill E2E — Expéditions & livraison

## Périmètre

Suite Playwright couvrant le domaine **expéditions & livraison** du storefront
Weklo : sélection du mode de livraison au checkout, franco (livraison offerte),
et documentation des volets non exposés au front en environnement e2e (commande
sur devis, multi-expédition V2).

| Fichier | Contenu |
|---|---|
| `modes-livraison.spec.ts` | Sélection du mode de livraison : liste des modes, présélection, retrait gratuit, franco masqué, avancée vers paiement |
| `commande-sur-devis.spec.ts` | Flux `awaiting-quote` — **skippé** (aucun produit `pko_quote_only` seedé en e2e) |
| `helpers.ts` | Réexporte les helpers panier/checkout + `reachShippingStep`, `shippingForm`, noms de méthodes |

## Compte utilisateur

| Rôle | Email | Mot de passe |
|---|---|---|
| Pro (installateur) | `thierry.leroy@mde-distribution.test` | `testing123` |

Créé par `PkoCustomerSeeder`. Le checkout exige une authentification (redirige
vers `/connexion` sinon — couvert dans `panier-checkout/checkout.spec.ts`).

## Fixtures & seed (PkoShippingSeeder)

Adresse de test : **75001 Paris** → zone `France métropolitaine`. Trois méthodes
table-rate seedées, toutes planifiées pour tous les groupes clients :

| Code | Nom UI | Driver | Prix | Visibilité |
|---|---|---|---|---|
| `mde-standard` | « Livraison standard » | `ship-by` (poids) | 690→1990 c selon paliers | toujours (FR métropole) |
| `mde-pickup` | « Retrait entrepôt » | `collection` | 0 € | toujours (FR métropole) |
| `mde-free` | « Livraison offerte » | `free-shipping` | 0 € | **uniquement si total ≥ 500 € HT** |

Les modifiers Chronopost / Colissimo (`packages/pko/shipping-*`) peuvent injecter
des options supplémentaires (Chrono 13, Colissimo…) selon la config/grille active.
Les tests n'assertent **pas** leur présence (dépend de credentials/grille) et se
limitent aux méthodes seedées garanties + au comptage `≥ 2`.

## Parcours front réel

Le storefront rend les options via le partial **générique Lunar**
`resources/views/partials/checkout/shipping_option.blade.php` : radios cachés
(`input.hidden.peer[name="shippingOption"]`) dans des `<label>`, heading
**« Shipping Options »**, bouton **« Choose Shipping »**. La 1re option est
présélectionnée par `CheckoutPage::determineCheckoutStep()`.

> ⚠️ La cible produit riche (« 3 cartes, défaut Chrono13, bandeaux
> franco/exclusion/multi-colis, badge dispo Weklo/fournisseur ») décrite dans le
> cahier des charges **n'est pas implémentée** dans ce partial générique. Les
> tests couvrent le front tel qu'il existe : nombre de modes, présélection,
> gratuité du retrait, masquage du franco. Quand le partial custom sera livré,
> enrichir `modes-livraison.spec.ts` (badges dispo, bandeaux).

Séquence menant à l'étape livraison (`reachShippingStep`) :
`/checkout` → adresse (heading « Shipping Details », bouton « Enregistrer
l'adresse ») → **« Shipping Options »**.

## Cas couverts

| Cas | Test |
|---|---|
| ≥ 2 modes listés (standard + retrait) | `modes-livraison.spec.ts` — « liste au moins deux modes » |
| Une option présélectionnée | `modes-livraison.spec.ts` — « une option est présélectionnée » |
| Retrait entrepôt gratuit (0 €) | `modes-livraison.spec.ts` — « le retrait entrepôt est affiché gratuit » |
| Franco masqué si panier < 500 € | `modes-livraison.spec.ts` — « la livraison offerte est masquée » |
| Sélection → avance vers paiement | `modes-livraison.spec.ts` — « sélectionner un mode et valider » |
| Commande sur devis | `commande-sur-devis.spec.ts` — **skippé** (voir ci-dessous) |

## Volets non testables en e2e (documentés)

### Commande sur devis (`awaiting-quote`)
Flux front implémenté (`CheckoutPage::isQuoteOnlyCart` + bandeau « devis
transport » + bouton « Demander un devis »), mais **aucun produit
`pko_quote_only = true` n'est seedé**. Les tests sont skippés avec le TODO
d'activation. Pour lever le skip : seeder un produit quote-only ou exposer un
helper e2e togglant `pko_quote_only` sur un produit connu, puis l'ajouter au
panier avant `reachShippingStep`.

### Multi-expédition V2 (découpage CarrierShipment par origine)
Logique **post-paiement, 100 % back-office** : `OrderShipmentObserver` →
`ShipmentSplitter::split()` crée un `CarrierShipment` par origine (weklo /
supplier_direct / supplier_via_weklo). **Aucune surface storefront** → non
couvrable en E2E navigateur. Couverture appropriée : tests Feature PHP
(`tests/Feature/Shipping/*`).

## Relancer la suite

```bash
npm run test:e2e -- expeditions          # domaine expéditions uniquement
npm run test:e2e -- modes-livraison      # un seul fichier
```

Le harness (`scripts/e2e-run.sh` + `e2e/global-setup.ts`) monte une stack Docker
isolée (`docker-compose.e2e.yml`, DB `pko_e2e`), `npm ci` + `migrate:fresh
--seed` automatiques. Cold-start ≈ 90–120 s ; chaque test livraison porte
`test.setTimeout(240_000)` pour l'absorber. Ne pas lancer en parallèle d'une
autre suite e2e (stack partagée).
