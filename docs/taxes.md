# Taxes — une seule classe de TVA (20 %)

## Décision (2026-11-09, FEAT-003)

La boutique ne vend qu'en **TVA 20 %**. Le catalogue n'a donc qu'**une seule
`TaxClass`**, marquée `default`, portant un taux de 20 % dans la zone France.

Constat qui a déclenché la décision : `lunar:install` crée une « Default Tax
Class » **sans aucun taux** ; `PkoTaxSeeder` ajoutait ensuite TVA 20 / 10 / 5,5 %.
L'importeur IA retombait sur la classe d'id le plus bas quand
`tax_class_handle` était absent ou inconnu → 11 variantes importées sur la
classe sans taux, **vendues sans TVA**.

## Mise en œuvre

- **Migration** `2026_11_09_000001_keep_single_vat_20_tax_class` (données,
  idempotente) : identifie la classe à garder par ses **attributs** (une classe
  portant un montant à 20 %, de préférence `default`, sinon la plus ancienne),
  jamais par id — les ids diffèrent entre dev et prod. Elle réaffecte
  `lunar_product_variants.tax_class_id`, supprime les `lunar_tax_rate_amounts`
  des autres classes, les `lunar_tax_rates` devenus vides, puis les autres
  classes, et force `default = 1`. Sans classe à 20 %, elle ne supprime rien
  (warning dans le log). `down()` est vide : suppression de données.
- **Références** : les seules FK vers `lunar_tax_classes` sont
  `lunar_product_variants.tax_class_id` et `lunar_tax_rate_amounts.tax_class_id`.
  La livraison (`ShippingCalculator`, `UnifiedShippingModifier`,
  `ShippingTaxHelper`) lit `TaxClass::getDefault()` et ne stocke aucun id.
  Les `order_lines` historiques figent `tax_breakdown` et n'ont pas de classe :
  non touchées.
- **Importeur** (`LunarProductWriter::resolveTaxClassId`) : lookup par nom,
  insensible à la casse ; vide → classe `default` ; inconnu → classe `default`
  + warning `ImportLog` (une fois par valeur et par run). Plus de repli sur la
  « première » classe. Sans classe `default` → `RuntimeException` explicite.
- **Seeder** `PkoTaxSeeder` : ne crée que « TVA 20% » (`default`) et supprime
  les autres classes, dont celle de `lunar:install`. `make fresh` aboutit donc à
  une seule classe.

## Admin

Lunar Admin (Paramètres → Classes de taxe) permet toujours d'en créer d'autres :
on ne le bloque pas (ressource Lunar, cf. AGENTS.md §3.1.6). Créer une seconde
classe est un **choix métier explicite** à documenter ici ; la classe `default`
doit rester celle à 20 %, car l'importeur et la livraison s'appuient dessus.
