/**
 * E2E — Expéditions & livraison : commande sur devis (awaiting-quote)
 *
 * Flux front implémenté dans `app/Livewire/CheckoutPage.php` +
 * `partials/checkout/payment.blade.php` :
 *  - `isQuoteOnlyCart` = true quand toutes les lignes sont `pko_port_mode='quote'`,
 *    ou quand le manifest ne contient que des options sentinelles « sur devis »
 *    (poids hors grille — cf. shipping.md §5.16).
 *  - L'étape paiement masque carte/espèces, affiche le bandeau « devis transport »
 *    et un bouton « Demander un devis ».
 *  - `checkout()` bifurque : createOrder() → statut `awaiting-quote`, sans paiement.
 *
 * Fixtures : catalogue de test expédition (shipping.md §5.17)
 *  - TX-14 : `pko_port_mode = 'quote'` (portail 8 m sur mesure)
 *  - TX-07 : 35 kg → hors grille Chronopost → sentinelle « Transport sur devis »
 */
import { test, expect } from '@playwright/test';
import {
  loginAsPro,
  clearCart,
  addSkuToCart,
  reachShippingStep,
  reachPaymentStep,
  shippingForm,
  optionRadios,
  waitForLivewire,
} from './helpers';

test.describe('Expéditions — commande sur devis', () => {
  test.beforeEach(async ({ page }) => {
    test.setTimeout(240_000);
    await loginAsPro(page);
    await clearCart(page);
  });

  test('un produit sur devis affiche le bandeau et le bouton « Demander un devis »', async ({ page }) => {
    await addSkuToCart(page, 'TX-14');
    // Panier 100 % devis : aucune option tarifable → l'étape « mode de
    // livraison » est court-circuitée, le tunnel s'ouvre directement au paiement.
    await reachPaymentStep(page);

    await expect(page.getByText(/nécessite un devis transport/i)).toBeVisible({
      timeout: 20_000,
    });
    await expect(
      page.getByRole('button', { name: /Demander un devis/i }),
    ).toBeVisible();
  });

  test('« Demander un devis » crée une commande sans paiement', async ({ page }) => {
    await addSkuToCart(page, 'TX-14');
    await reachPaymentStep(page);

    await page.getByRole('button', { name: /Demander un devis/i }).click();

    // Page de confirmation : le message devis est répété quand
    // order.status === 'awaiting-quote'.
    await expect(page.getByText(/devis/i).first()).toBeVisible({ timeout: 30_000 });
  });

  test('un colis hors grille bascule en « Transport sur devis »', async ({ page }) => {
    await addSkuToCart(page, 'TX-07'); // 35 kg — au-delà du dernier bracket (30 kg)
    await reachShippingStep(page);

    // Option unique, sentinelle : aucun tarif calculable.
    await expect(optionRadios(page)).toHaveCount(1, { timeout: 15_000 });
    await expect(
      shippingForm(page).getByText(/Transport sur devis/i).first(),
    ).toBeVisible();

    const lwShipping = waitForLivewire(page);
    await shippingForm(page).getByRole('button', { name: 'Continuer' }).click();
    await lwShipping;

    // Le paiement en ligne doit être indisponible (même traitement qu'un panier
    // 100 % devis) : bouton devis présent, pas de formulaire carte.
    await expect(page.getByRole('button', { name: /Demander un devis/i })).toBeVisible({
      timeout: 20_000,
    });
  });
});
