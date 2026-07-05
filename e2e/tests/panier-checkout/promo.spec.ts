/**
 * E2E — codes promo — SKIPPED
 *
 * L'UI de saisie de coupon n'est pas encore livrée :
 *  - CartPage n'expose ni champ coupon_code ni action applyCoupon/removeCoupon
 *  - Aucun seeder de discount n'est disponible pour les tests E2E
 *
 * Pour activer ces tests :
 *  1. Ajouter un champ coupon_code + méthodes applyCoupon/removeCoupon sur CartPage
 *  2. Créer PkoE2eDiscountSeeder (ex. "PROMO10" → 10 % off, tous groupes clients)
 *  3. Appeler PkoE2eDiscountSeeder depuis DatabaseSeeder
 *  4. Supprimer les test.skip et écrire les assertions
 */
import { test } from '@playwright/test';

test.skip('code promo valide — applique la réduction sur le sous-total', async () => {
  // Prérequis :
  //  - PkoE2eDiscountSeeder : coupon "PROMO10", 10 % off, tous groupes clients
  //  - Ajouter produit E2E au panier (addE2EProductToCart)
  //  - Saisir "PROMO10" dans le champ coupon (à créer sur CartPage)
  //  - Vérifier que le sous-total diminue de 10 %
  //  - Vérifier qu'une ligne de réduction apparaît dans le récapitulatif
});

test.skip('code promo invalide — affiche un message d\'erreur', async () => {
  // Prérequis :
  //  - Ajouter produit E2E au panier
  //  - Saisir "INVALID999" dans le champ coupon
  //  - Vérifier l'affichage d'un message d'erreur
  //  - Vérifier que le sous-total est inchangé
});

test.skip('retrait d\'un code promo — restaure le prix initial', async () => {
  // Prérequis :
  //  - Appliquer un code promo valide
  //  - Cliquer sur "Retirer" à côté du code appliqué
  //  - Vérifier que le sous-total revient au montant initial
});
