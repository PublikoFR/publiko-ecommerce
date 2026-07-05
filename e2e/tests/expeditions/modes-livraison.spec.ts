/**
 * E2E — Expéditions & livraison : sélection du mode de livraison
 *
 * Couverture (front réel du storefront) :
 *  - l'étape « Shipping Options » liste les modes seedés pour une adresse FR
 *  - au moins deux modes disponibles (standard + retrait entrepôt)
 *  - une option est présélectionnée (radio checked)
 *  - le retrait entrepôt est affiché gratuit (0 €)
 *  - le franco « Livraison offerte » (≥ 500 € HT) est masqué pour un petit panier
 *  - la sélection d'un mode fait avancer le tunnel vers le paiement
 *
 * Hypothèses seed (PkoShippingSeeder, zone « France métropolitaine ») :
 *  - `mde-standard` (ship-by au poids)   → « Livraison standard »
 *  - `mde-pickup`   (collection, 0 c)    → « Retrait entrepôt »
 *  - `mde-free`     (free-shipping 500€) → « Livraison offerte » (masqué si < 500 €)
 *
 * Note UI : le storefront rend les options via le partial générique Lunar
 * (`partials/checkout/shipping_option.blade.php`) — radios `.hidden peer` dans
 * des <label>, heading « Shipping Options », bouton « Choose Shipping ».
 */
import { test, expect } from '@playwright/test';
import {
  loginAsPro,
  addE2EProductToCart,
  reachShippingStep,
  shippingForm,
  waitForLivewire,
  METHOD_STANDARD,
  METHOD_PICKUP,
  METHOD_FREE,
} from './helpers';

test.describe('Expéditions — sélection du mode de livraison', () => {
  test.beforeEach(async ({ page }) => {
    // Cold-start Docker : login (~90 s Livewire) + ajout + adresse → 240 s
    test.setTimeout(240_000);
    await loginAsPro(page);
    await addE2EProductToCart(page);
    await reachShippingStep(page);
  });

  test('liste au moins deux modes de livraison sélectionnables', async ({ page }) => {
    const radios = shippingForm(page).locator('input[name="shippingOption"]');
    expect(await radios.count()).toBeGreaterThanOrEqual(2);

    // Les deux méthodes toujours disponibles pour une adresse FR métropole.
    await expect(shippingForm(page).getByText(METHOD_STANDARD)).toBeVisible();
    await expect(shippingForm(page).getByText(METHOD_PICKUP)).toBeVisible();
  });

  test('une option est présélectionnée (radio checked)', async ({ page }) => {
    // CheckoutPage::determineCheckoutStep() pré-sélectionne la 1re option.
    const checked = shippingForm(page).locator('input[name="shippingOption"]:checked');
    await expect(checked).toHaveCount(1, { timeout: 8_000 });
  });

  test('le retrait entrepôt est affiché gratuit (0 €)', async ({ page }) => {
    // La carte « Retrait entrepôt » (seed 0 cent) porte un prix formaté à 0.
    const card = shippingForm(page)
      .locator('label')
      .filter({ hasText: METHOD_PICKUP });
    await expect(card).toBeVisible();
    await expect(card).toContainText(/0[.,]00|gratuit/i);
  });

  test('la livraison offerte (franco 500 €) est masquée pour un petit panier', async ({ page }) => {
    // Un seul produit standard < 500 € HT → le driver free-shipping n'injecte
    // aucune option. Le bandeau franco n'apparaît donc pas.
    await expect(shippingForm(page).getByText(METHOD_FREE)).toHaveCount(0);
  });

  test('sélectionner un autre mode et valider fait avancer vers le paiement', async ({ page }) => {
    const form = shippingForm(page);
    const radios = form.locator('input[name="shippingOption"]');

    // Sélectionner une option DIFFÉRENTE de la présélection garantit un event
    // `change` → un update wire:model.live (cliquer l'option déjà cochée n'émet
    // aucune requête et ferait timeout le waitForLivewire).
    const checkedValue = await form
      .locator('input[name="shippingOption"]:checked')
      .getAttribute('value');

    const count = await radios.count();
    let targetId: string | null = null;
    for (let i = 0; i < count; i++) {
      const value = await radios.nth(i).getAttribute('value');
      if (value && value !== checkedValue) {
        targetId = value;
        break;
      }
    }
    expect(targetId, 'au moins deux options distinctes attendues').toBeTruthy();

    // Le radio est caché (.hidden peer) : cliquer son <label for="{id}">.
    const lwSelect = waitForLivewire(page);
    await form.locator(`label[for="${targetId}"]`).click();
    await lwSelect;

    const lwSubmit = waitForLivewire(page);
    await form.locator('button:has-text("Choose Shipping")').click();
    await lwSubmit;

    await expect(
      page.getByRole('heading', { name: 'Paiement' }),
    ).toBeVisible({ timeout: 12_000 });
  });
});
