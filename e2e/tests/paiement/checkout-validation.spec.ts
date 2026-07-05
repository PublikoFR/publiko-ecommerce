/**
 * E2E — Échec « en amont » du paiement : validation d'adresse.
 *
 * Le seul provider pilotable en e2e (offline) n'a pas de scénario de refus de
 * transaction. Le cas d'échec honnête et déterministe est le blocage de la
 * commande quand l'adresse de livraison est incomplète : l'étape ne passe pas
 * et le paiement reste inatteignable.
 */
import { test, expect } from '@playwright/test';
import { loginAsPro, addFirstProductToCart } from './helpers';

test.describe('Checkout — garde de validation', () => {
  test('une adresse de livraison incomplète bloque l\'accès au paiement', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAsPro(page);
    await addFirstProductToCart(page);
    await page.goto('/checkout');
    await page.waitForLoadState('networkidle');

    // Adresse pro pré-remplie partiellement mais line_one / city / postcode
    // sont vides (pas d'adresse SIRENE seedée) → on soumet en l'état.
    const lineOne = page.locator('[wire\\:model\\.live="shipping.line_one"]');
    await expect(lineOne).toBeVisible();
    await lineOne.fill('');

    await page.locator('button:has-text("Enregistrer l\'adresse")').first().click();
    await page.waitForTimeout(2_000);

    // L'étape adresse reste active (le formulaire n'a pas basculé en récap) :
    // le champ d'adresse est toujours éditable → paiement non atteint.
    await expect(lineOne).toBeVisible();
    await expect(page.getByRole('button', { name: 'Valider la commande' })).toHaveCount(0);
  });
});
