/**
 * E2E — Expéditions & livraison : commande sur devis (awaiting-quote)
 *
 * Flux front implémenté dans `app/Livewire/CheckoutPage.php` +
 * `partials/checkout/payment.blade.php` :
 *  - `isQuoteOnlyCart` = true dès qu'une ligne porte un produit `pko_quote_only`.
 *  - L'étape paiement masque carte/espèces, affiche le bandeau « devis transport »
 *    et un bouton « Demander un devis » (au lieu de « Valider la commande »).
 *  - `checkout()` bifurque : createOrder() → statut `awaiting-quote`, sans paiement.
 *
 * ── SKIP : aucun produit `pko_quote_only = true` n'est seedé en e2e ──
 * Le parcours client ne peut donc pas être atteint via le storefront sans
 * fixture dédiée. Deux options d'activation (à demander à l'infra, cf.
 * done_comment / SKILL.md) :
 *   1. seeder un produit `pko_quote_only = true` (garanti mono-variant, stock ≥ 1) ;
 *   2. exposer un helper e2e togglant `pko_quote_only` sur un produit connu.
 * Une fois la fixture disponible, retirer les `test.skip` et ajouter l'ajout
 * de ce produit précis au panier (addE2EProductToCart cible le 1er produit
 * générique, non quote-only).
 */
import { test, expect } from '@playwright/test';
import { loginAsPro, reachShippingStep, waitForLivewire } from './helpers';

test.describe('Expéditions — commande sur devis', () => {
  test.skip('affiche le bandeau « devis transport » à l\'étape paiement (skip: pas de produit quote_only seedé)', async ({ page }) => {
    test.setTimeout(240_000);
    await loginAsPro(page);
    // TODO(fixture): ajouter au panier un produit pko_quote_only=true.
    await reachShippingStep(page);

    const lwShipping = waitForLivewire(page);
    await page.locator('button:has-text("Choose Shipping")').click();
    await lwShipping;

    await expect(page.getByText(/nécessite un devis transport/i)).toBeVisible();
    await expect(
      page.getByRole('button', { name: /Demander un devis/i }),
    ).toBeVisible();
  });

  test.skip('« Demander un devis » crée une commande awaiting-quote sans paiement (skip: pas de produit quote_only seedé)', async ({ page }) => {
    test.setTimeout(240_000);
    await loginAsPro(page);
    // TODO(fixture): panier avec produit pko_quote_only=true.
    await reachShippingStep(page);

    const lwShipping = waitForLivewire(page);
    await page.locator('button:has-text("Choose Shipping")').click();
    await lwShipping;

    await page.getByRole('button', { name: /Demander un devis/i }).click();
    // La page de succès répète le message quand order.status === 'awaiting-quote'.
    await expect(page.getByText(/devis transport/i)).toBeVisible({ timeout: 20_000 });
  });
});
