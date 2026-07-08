/**
 * E2E — panier : gestion des articles
 *
 * Couverture :
 *  - ajout d'un produit depuis la fiche produit
 *  - incrément / décrément de quantité (boutons + / -)
 *  - suppression d'une ligne individuelle (bouton corbeille)
 *  - vidage complet du panier (wire:confirm)
 *  - persistance panier après rechargement de page (session)
 *
 * Compte pro : thierry.leroy@weklo.test / testing123
 * Produit    : premier produit disponible sur /recherche (stock ≥ 5, mono-variant)
 */
import { test, expect } from '@playwright/test';
import { loginAsPro, addE2EProductToCart, waitForLivewire } from './helpers';

test.describe('Panier — gestion des articles', () => {
  let productName: string;

  test.beforeEach(async ({ page }) => {
    // Cold-start Docker : 150s max wait [wire:id] + opérations → 300s total
    test.setTimeout(300_000);
    await loginAsPro(page);
    productName = await addE2EProductToCart(page);
  });

  // ─── Ajout ───────────────────────────────────────────────────────────────

  test('ajoute un produit au panier depuis la fiche produit', async ({ page }) => {
    await page.goto('/panier');

    await expect(
      page.getByRole('main').getByText(productName).first(),
    ).toBeVisible({ timeout: 10_000 });
    await expect(page.getByRole('main').getByText('Votre panier est vide')).not.toBeVisible();
  });

  // ─── Quantité ────────────────────────────────────────────────────────────

  test('incrémente la quantité via le bouton +', async ({ page }) => {
    await page.goto('/panier');

    // La ligne du produit = <li> contenant son nom
    const line = page.locator('li').filter({ hasText: productName });
    await expect(line).toBeVisible({ timeout: 10_000 });

    // Quantité initiale = 1
    await expect(line.locator('span.w-10')).toHaveText('1');

    // Bouton + = dernier bouton wire:click updateQuantity de la ligne
    const plusBtn = line.locator('button[wire\\:click*="updateQuantity"]').last();
    const lw = waitForLivewire(page);
    await plusBtn.click();
    await lw;

    await expect(line.locator('span.w-10')).toHaveText('2');
  });

  test('décrémente la quantité via le bouton -', async ({ page }) => {
    await page.goto('/panier');

    const line    = page.locator('li').filter({ hasText: productName });
    const plusBtn  = line.locator('button[wire\\:click*="updateQuantity"]').last();
    const minusBtn = line.locator('button[wire\\:click*="updateQuantity"]').first();

    // Passer à 3 via deux clics "+"
    for (let i = 0; i < 2; i++) {
      const lw = waitForLivewire(page);
      await plusBtn.click();
      await lw;
    }
    await expect(line.locator('span.w-10')).toHaveText('3');

    // Décrémenter une fois
    const lw = waitForLivewire(page);
    await minusBtn.click();
    await lw;

    await expect(line.locator('span.w-10')).toHaveText('2');
  });

  // ─── Suppression ─────────────────────────────────────────────────────────

  test('supprime une ligne via le bouton corbeille', async ({ page }) => {
    await page.goto('/panier');

    const line = page.locator('li').filter({ hasText: productName });
    await expect(line).toBeVisible({ timeout: 10_000 });

    const lw = waitForLivewire(page);
    // title="Retirer" placé sur le bouton de suppression
    await line.getByTitle('Retirer').click();
    await lw;

    await expect(
      page.getByText('Votre panier est vide'),
    ).toBeVisible({ timeout: 8_000 });
  });

  test('vide le panier entier via "Vider le panier"', async ({ page }) => {
    await page.goto('/panier');

    // wire:confirm déclenche window.confirm — accepter la boîte native
    page.once('dialog', dialog => dialog.accept());

    const lw = waitForLivewire(page);
    await page.getByRole('button', { name: 'Vider le panier' }).click();
    await lw;

    await expect(
      page.getByText('Votre panier est vide'),
    ).toBeVisible({ timeout: 8_000 });
  });

  // ─── Persistance ─────────────────────────────────────────────────────────

  test('le panier persiste après rechargement de page (session)', async ({ page }) => {
    await page.goto('/panier');
    await expect(
      page.getByRole('main').getByText(productName).first(),
    ).toBeVisible({ timeout: 10_000 });

    await page.reload();

    await expect(
      page.getByRole('main').getByText(productName).first(),
    ).toBeVisible({ timeout: 10_000 });
  });
});
