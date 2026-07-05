/**
 * E2E — Étape paiement du checkout : choix du mode de règlement.
 *
 * Vérifie la présence des deux modes (« Pay by card » Stripe / « Pay with
 * cash » offline) et le basculement entre eux. Le montage réel de Stripe
 * Elements n'est PAS asservi (clés Stripe factices en e2e) : on se contente
 * de vérifier que sélectionner la carte remplace le formulaire offline.
 */
import { test, expect } from '@playwright/test';
import { loginAsPro, addFirstProductToCart, fillShippingAddress, chooseShipping } from './helpers';

test.describe('Checkout — étape paiement', () => {
  test.beforeEach(async ({ page }) => {
    test.setTimeout(120_000);
    await loginAsPro(page);
    await addFirstProductToCart(page);
    await page.goto('/checkout');
    await page.waitForLoadState('networkidle');
    await fillShippingAddress(page);
    await chooseShipping(page);
    // On est désormais à l'étape paiement.
    await expect(page.getByRole('heading', { name: 'Paiement' })).toBeVisible({ timeout: 10_000 });
  });

  test('propose le paiement par carte et le paiement hors-ligne', async ({ page }) => {
    await expect(page.getByRole('button', { name: 'Pay by card' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Pay with cash' })).toBeVisible();

    // Mode par défaut = cash-in-hand : notice + bouton de validation offline.
    await expect(page.locator('text=Paiement hors ligne, aucune carte requise.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Valider la commande' })).toBeVisible();
  });

  test('basculer sur « Pay by card » remplace le formulaire offline', async ({ page }) => {
    await expect(page.getByRole('button', { name: 'Valider la commande' })).toBeVisible();

    await page.getByRole('button', { name: 'Pay by card' }).click();
    await page.waitForTimeout(1_500);

    // Le formulaire offline (bouton + notice) disparaît au profit du bloc carte.
    await expect(page.getByRole('button', { name: 'Valider la commande' })).toBeHidden();
    await expect(page.locator('text=Paiement hors ligne, aucune carte requise.')).toBeHidden();
  });
});
