/**
 * Helpers partagés — suite « Expéditions & livraison »
 *
 * Réutilise les helpers du domaine panier/checkout (login, ajout produit,
 * adresse) et ajoute la navigation jusqu'à l'étape « Shipping Options ».
 *
 * Compte pro : thierry.leroy@mde-distribution.test / testing123
 */
import { expect, type Page } from '@playwright/test';
import {
  loginAsPro,
  addE2EProductToCart,
  fillShippingAddress,
  waitForLivewire,
} from '../panier-checkout/helpers';

export {
  loginAsPro,
  addE2EProductToCart,
  fillShippingAddress,
  waitForLivewire,
};

export { PRO_EMAIL, PRO_PASSWORD, clearCart } from '../panier-checkout/helpers';

// Noms des méthodes de livraison seedées (PkoShippingSeeder) pour une adresse
// France métropolitaine. Le driver `free-shipping` (« Livraison offerte »)
// n'apparaît que si le total panier ≥ 500 € HT (franco).
export const METHOD_STANDARD = 'Livraison standard';
export const METHOD_PICKUP = 'Retrait entrepôt';
export const METHOD_FREE = 'Livraison offerte';

/**
 * Le formulaire des options de livraison. Sert de racine pour cibler les
 * cartes/radios sans capter d'autres <form> (adresse, paiement) de la page.
 */
export function shippingForm(page: Page) {
  return page.locator('form').filter({ hasText: 'Shipping Options' });
}

/**
 * Amène le tunnel jusqu'à l'étape de sélection du mode de livraison, avec les
 * radios INTERACTIFS (currentStep == shipping_option).
 *
 * Prérequis : produit déjà au panier, utilisateur pro connecté.
 *
 * ⚠️ État persistant : le panier Lunar (et donc `cart->shippingAddress` +
 * l'option choisie) est rattaché au user et **survit entre les tests** d'un
 * même run (migrate:fresh une seule fois par suite). Selon ce que les tests
 * précédents ont enregistré, `CheckoutPage::determineCheckoutStep()` peut :
 *   - currentStep 1 (aucune adresse)        → l'étape adresse est ouverte
 *   - currentStep 2 (adresse, pas d'option) → les radios sont déjà interactifs
 *   - currentStep 3 (adresse + option)      → les options sont collapsées
 * Ce helper normalise tous ces cas vers « radios interactifs ».
 */
export async function reachShippingStep(page: Page): Promise<void> {
  await page.goto('/checkout');
  // Attendre l'hydratation Livewire (cold-start Docker inclus) avant d'inspecter
  // quelle étape est rendue.
  await page.waitForLoadState('networkidle', { timeout: 150_000 });

  // 1. Étape adresse ouverte → remplir + enregistrer (fait avancer à l'étape 2/3).
  const firstName = page.getByRole('textbox', { name: /^Prénom/ }).first();
  if (await firstName.isEditable().catch(() => false)) {
    await fillShippingAddress(page);
    const lwAddress = waitForLivewire(page);
    await page.locator('button:has-text("Enregistrer l\'adresse")').click();
    await lwAddress;
  }

  // 2. Garantir que les radios d'options sont interactifs. Si l'étape livraison
  //    est collapsée (une option avait été enregistrée par un test précédent →
  //    currentStep 3), cliquer son bouton « Edit » (wire:click $set currentStep=2).
  const radios = shippingForm(page).locator('input[name="shippingOption"]');
  if ((await radios.count()) === 0) {
    const editBtn = shippingForm(page).getByRole('button', { name: 'Edit' });
    const lwEdit = waitForLivewire(page);
    await editBtn.click();
    await lwEdit;
  }

  await expect(page.getByText('Shipping Options')).toBeVisible({ timeout: 15_000 });
  await expect(radios.first()).toBeAttached({ timeout: 10_000 });
}
