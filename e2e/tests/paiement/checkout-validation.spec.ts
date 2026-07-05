/**
 * E2E — Échec « en amont » du paiement : validation d'adresse.
 *
 * Le seul provider pilotable en e2e (offline) n'a pas de scénario de refus de
 * transaction. Le cas d'échec honnête et déterministe est le blocage de la
 * commande quand l'adresse de livraison est incomplète : l'étape ne passe pas
 * et le paiement reste inatteignable.
 */
import { test, expect } from '@playwright/test';
import { loginAsPro, addFirstProductToCart, openShippingAddressForm, waitForLivewire } from './helpers';

test.describe('Checkout — garde de validation', () => {
  test('une adresse de livraison incomplète bloque l\'accès au paiement', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAsPro(page);
    await addFirstProductToCart(page);
    await page.goto('/checkout');
    // Attend le montage du checkout (section adresse rendue).
    await expect(page.getByRole('heading', { name: 'Shipping Details' })).toBeVisible({ timeout: 15_000 });

    // Panier partagé (persisté par client en base) : garantir un formulaire
    // éditable même si un test précédent a déjà enregistré une adresse.
    await openShippingAddressForm(page);

    // On vide line_one → l'adresse devient incomplète (champ requis).
    const lineOne = page.locator('[wire\\:model\\.live="shipping.line_one"]');
    await expect(lineOne).toBeVisible();
    await lineOne.fill('');

    const livewire = waitForLivewire(page);
    await page.locator('button:has-text("Enregistrer l\'adresse")').first().click();
    await livewire;

    // L'étape adresse reste active (le formulaire n'a pas basculé en récap) :
    // le champ d'adresse est toujours éditable → paiement non atteint.
    await expect(lineOne).toBeVisible();
    await expect(page.getByRole('button', { name: 'Valider la commande' })).toHaveCount(0);
  });
});
