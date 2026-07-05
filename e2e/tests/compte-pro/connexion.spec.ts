import { test, expect } from '@playwright/test';
import { loginAs, logout, PRO_EMAIL, PRO_PASSWORD } from './helpers';

test.describe('Connexion pro', () => {
  test('affiche le formulaire de connexion', async ({ page }) => {
    await page.goto('/connexion');
    await expect(page.getByLabel('Adresse e-mail')).toBeVisible();
    await expect(page.getByLabel('Mot de passe')).toBeVisible();
    await expect(page.getByRole('button', { name: /Se connecter/i })).toBeVisible();
  });

  test('connexion valide redirige vers le tableau de bord', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await expect(page).toHaveURL(/\/compte/);
    // Le dashboard affiche le nom de l'utilisateur : "Bonjour ..."
    await expect(page.getByRole('heading', { name: /Bonjour/i })).toBeVisible({ timeout: 10_000 });
  });

  test('mauvais mot de passe affiche une erreur', async ({ page }) => {
    await page.goto('/connexion');
    await page.getByLabel('Adresse e-mail').fill(PRO_EMAIL);
    await page.getByLabel('Mot de passe').fill('mauvais_mdp_incorrect');
    await page.getByRole('button', { name: /Se connecter/i }).click();
    await expect(page.getByText(/Identifiants incorrects/i)).toBeVisible({ timeout: 15_000 });
    await expect(page).toHaveURL(/\/connexion/);
  });

  test('e-mail inconnu affiche une erreur', async ({ page }) => {
    await page.goto('/connexion');
    await page.getByLabel('Adresse e-mail').fill('inconnu@nowhere.test');
    await page.getByLabel('Mot de passe').fill('nimportequoi');
    await page.getByRole('button', { name: /Se connecter/i }).click();
    await expect(page.getByText(/Identifiants incorrects/i)).toBeVisible({ timeout: 15_000 });
  });

  test('déconnexion redirige vers la page d\'accueil', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await logout(page);
    await expect(page).toHaveURL('/');
  });

  test('client non connecté accédant à /compte est redirigé vers /connexion', async ({ page }) => {
    await page.goto('/compte');
    await expect(page).toHaveURL(/\/connexion/);
  });

  test('client déjà connecté est redirigé depuis /connexion vers /compte', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.goto('/connexion');
    await expect(page).toHaveURL(/\/compte/);
  });

  test('invité accédant au /panier est redirigé vers /connexion', async ({ page }) => {
    // /panier est protégé par le middleware pro.customer (RequireProCustomer)
    await page.goto('/panier');
    await expect(page).toHaveURL(/\/connexion/);
  });
});
