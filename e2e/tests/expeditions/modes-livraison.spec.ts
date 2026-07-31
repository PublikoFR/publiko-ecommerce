/**
 * E2E — Expéditions & livraison : sélection du mode de livraison
 *
 * Couvre le composant `ShippingOptions` (checkout, lot L5) sur le catalogue de
 * test expédition (SKU TX-*, cf. `docs/shipping.md` §5.17) :
 *  - les 3 services Chronopost sont proposés, Chrono 13 présélectionné
 *  - le point relais disparaît au-delà de 20 kg
 *  - bandeau franco atteint / bandeau de progression / bandeau d'exclusion
 *  - récap ventilé quand un forfait transport s'ajoute à la grille
 *  - panier 100 % « port inclus » → option unique « Livraison offerte »
 *  - la sélection d'un mode fait avancer le tunnel vers le paiement
 *
 * Le seed ne contient plus aucune méthode table-rate (`mde-*`) : toutes les
 * options viennent de la grille Chronopost + du ShippingCalculator.
 */
import { test, expect } from '@playwright/test';
import {
  loginAsPro,
  clearCart,
  addSkuToCart,
  reachShippingStep,
  shippingForm,
  optionRadios,
  waitForLivewire,
  SERVICE_RELAIS,
  SERVICE_STANDARD,
  SERVICE_EXPRESS,
} from './helpers';

test.describe('Expéditions — sélection du mode de livraison', () => {
  // Absorbe UNE fois le cold-start Docker (global-setup se termine sur
  // `optimize:clear` → la 1re requête navigateur recompile config/routes/views
  // + 1er rendu Livewire de toute la stack Lunar/Filament).
  test.beforeAll(async ({ browser }) => {
    test.setTimeout(90_000);
    const port = process.env.E2E_PORT ?? '18080';
    const page = await browser.newPage({ baseURL: `http://localhost:${port}` });
    try {
      await page.goto('/connexion', { waitUntil: 'domcontentloaded', timeout: 60_000 });
      await page
        .waitForFunction(() => (window as { Livewire?: unknown }).Livewire !== undefined, {
          timeout: 30_000,
        })
        .catch(() => undefined);
    } catch {
      // Warm infra insuffisant : le 1er test retentera dans son propre budget.
    } finally {
      await page.close();
    }
  });

  test.beforeEach(async ({ page }) => {
    // Login (~90 s Livewire à froid) + ajout + adresse → marge 240 s.
    test.setTimeout(240_000);
    await loginAsPro(page);
    await clearCart(page);
  });

  test('un colis léger propose les 3 services, Chrono 13 présélectionné', async ({ page }) => {
    await addSkuToCart(page, 'TX-01'); // 1,5 kg
    await reachShippingStep(page);

    await expect(optionRadios(page)).toHaveCount(3, { timeout: 15_000 });

    const form = shippingForm(page);
    await expect(form.getByText(SERVICE_RELAIS).first()).toBeVisible();
    await expect(form.getByText(SERVICE_STANDARD).first()).toBeVisible();
    await expect(form.getByText(SERVICE_EXPRESS).first()).toBeVisible();

    // Défaut métier : Chrono 13 (cf. ShippingCalculator::DEFAULT_OPTION_IDENTIFIER).
    await expect(
      form.locator('input[type="radio"][value="chronopost.chrono13"]'),
    ).toBeChecked({ timeout: 10_000 });
  });

  test('au-delà de 20 kg le point relais disparaît', async ({ page }) => {
    await addSkuToCart(page, 'TX-05'); // 25 kg
    await reachShippingStep(page);

    await expect(optionRadios(page)).toHaveCount(2, { timeout: 15_000 });
    await expect(shippingForm(page).getByText(SERVICE_RELAIS)).toHaveCount(0);
  });

  test('franco atteint : tous les services offerts et bandeau affiché', async ({ page }) => {
    await addSkuToCart(page, 'TX-08'); // 600 € HT
    await reachShippingStep(page);

    const form = shippingForm(page);
    await expect(form.getByText(/livraison standard offerte/i)).toBeVisible({ timeout: 15_000 });

    // Règle métier : au-delà du seuil, le port est offert quel que soit le service.
    for (const service of [SERVICE_RELAIS, SERVICE_STANDARD, SERVICE_EXPRESS]) {
      await expect(
        form.locator('label').filter({ hasText: service }).getByText('Offert'),
      ).toBeVisible();
    }
  });

  test('sous le seuil : bandeau de progression du franco', async ({ page }) => {
    await addSkuToCart(page, 'TX-02', 3); // 3 × 150 € = 450 € HT → reste 50 €

    await reachShippingStep(page);

    await expect(
      shippingForm(page).getByText(/Plus que.*pour bénéficier de la livraison standard offerte/i),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('une ligne exclue du franco affiche le bandeau d\'exclusion', async ({ page }) => {
    await addSkuToCart(page, 'TX-08'); // franco-éligible, 600 € HT
    await addSkuToCart(page, 'TX-09'); // exclu du franco
    await reachShippingStep(page);

    const form = shippingForm(page);
    await expect(
      form.getByText(/frais de transport complémentaires/i),
    ).toBeVisible({ timeout: 15_000 });

    // Franco annulé : plus aucune carte « Offert ».
    await expect(form.getByText('Offert')).toHaveCount(0);
  });

  test('un forfait transport produit un récap ventilé', async ({ page }) => {
    await addSkuToCart(page, 'TX-10'); // mode flat, forfait 25 € HT
    await reachShippingStep(page);

    const form = shippingForm(page);
    // Le récap ventilé est un tableau : ligne « + 25,00 € » (forfait) et pied
    // « Total livraison HT ». Cibler les cellules évite de matcher aussi les
    // prix affichés sur les 3 cartes de service.
    await expect(form.getByText(/Total livraison HT/i)).toBeVisible({ timeout: 15_000 });
    await expect(form.getByRole('cell', { name: '+ 25,00 €' })).toBeVisible();
    await expect(form.getByRole('cell', { name: '25,00 €', exact: true })).toBeVisible();
  });

  test('panier 100 % port inclus : option unique « Livraison offerte »', async ({ page }) => {
    await addSkuToCart(page, 'TX-13'); // pko_port_mode = free
    await reachShippingStep(page);

    await expect(optionRadios(page)).toHaveCount(1, { timeout: 15_000 });
    await expect(shippingForm(page).getByText('Livraison offerte').first()).toBeVisible();
  });

  test('sélectionner un autre mode et continuer fait avancer vers le paiement', async ({ page }) => {
    await addSkuToCart(page, 'TX-01');
    await reachShippingStep(page);

    const form = shippingForm(page);
    // Chrono 10 (express) : toujours présent et jamais présélectionné.
    const lwSelect = waitForLivewire(page);
    await form.locator('input[type="radio"][value="chronopost.chrono10"]').check();
    await lwSelect;

    const lwSubmit = waitForLivewire(page);
    await form.getByRole('button', { name: 'Continuer' }).click();
    await lwSubmit;

    await expect(page.getByRole('heading', { name: 'Paiement' })).toBeVisible({
      timeout: 15_000,
    });
  });
});

test.describe('Expéditions — disponibilité produit', () => {
  test('un produit en rupture ne peut pas être ajouté au panier', async ({ page }) => {
    test.setTimeout(240_000);
    await loginAsPro(page);
    await clearCart(page);

    // TX-20 : stock 0, purchasable = in_stock. Le clic part quand même (le
    // bouton n'est pas désactivé) mais AddToCart::addToCart() refuse la ligne.
    await addSkuToCart(page, 'TX-20');

    await expect(page.getByText(/dépasse le stock disponible/i)).toBeVisible({
      timeout: 15_000,
    });

    await page.goto('/panier');
    await expect(
      page.getByRole('main').getByText('Votre panier est vide'),
    ).toBeVisible({ timeout: 15_000 });
  });
});
