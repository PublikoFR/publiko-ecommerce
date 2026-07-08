import { test, expect } from '@playwright/test';
import { loginAs, logout, PRO_EMAIL, PRO_PASSWORD, expectNoServerError } from './helpers';

// NOTE FIXTURES : PkoOrderSeeder crée 20 commandes (réf. WK-XXXXXX) réparties
// ALÉATOIREMENT sur les 5 clients seedés (3 particuliers + 2 pro). Le compte pro
// de test peut donc avoir 0 commande lors d'un run donné : les tests gèrent les
// deux cas (liste vide OU peuplée) pour rester déterministes.

test.describe('Historique commandes pro', () => {
  test('client non connecté est redirigé vers /connexion', async ({ page }) => {
    await page.goto('/compte/commandes');
    await expect(page).toHaveURL(/\/connexion/);
  });

  test('page commandes accessible après connexion pro', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.goto('/compte/commandes');
    await expect(page).toHaveURL(/\/compte\/commandes/);
    await expect(page.getByRole('heading', { name: 'Mes commandes' })).toBeVisible({ timeout: 10_000 });
    await expectNoServerError(page);
  });

  test('la page affiche les commandes ou le message vide', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.goto('/compte/commandes');
    await page.getByRole('heading', { name: 'Mes commandes' }).waitFor({ timeout: 15_000 });

    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    if (count > 0) {
      await expect(rows.first()).toBeVisible();
      const firstRef = (await rows.first().textContent()) ?? '';
      expect(firstRef).toMatch(/#|WK-/);
    } else {
      await expect(page.getByText(/Aucune commande/i)).toBeVisible();
    }
  });

  test('le lien "Détails" ouvre la page de détail si des commandes existent', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.goto('/compte/commandes');
    await page.getByRole('heading', { name: 'Mes commandes' }).waitFor({ timeout: 15_000 });

    const rows = page.locator('table tbody tr');
    if ((await rows.count()) === 0) {
      test.skip(true, 'Aucune commande attribuée au compte pro dans ce run (attribution aléatoire du seeder).');
      return;
    }

    await rows.first().getByRole('link', { name: /Détails/i }).click();
    await expect(page).toHaveURL(/\/compte\/commandes\/\d+/);
    await expectNoServerError(page);
  });

  test('navigation depuis le tableau de bord vers les commandes', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    // Carte "Commandes récentes" : lien "Voir tout →" vers /compte/commandes
    await page.getByRole('link', { name: /Voir tout/i }).first().click();
    await expect(page).toHaveURL(/\/compte\/commandes/);
  });

  test('navigation via la sidebar "Mes commandes"', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.getByRole('link', { name: 'Mes commandes' }).click();
    await expect(page).toHaveURL(/\/compte\/commandes/);
    await expect(page.getByRole('heading', { name: 'Mes commandes' })).toBeVisible();
  });

  test('déconnexion depuis la page commandes coupe l\'accès', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.goto('/compte/commandes');
    await logout(page);
    await expect(page).toHaveURL('/');
    await page.goto('/compte/commandes');
    await expect(page).toHaveURL(/\/connexion/);
  });
});
