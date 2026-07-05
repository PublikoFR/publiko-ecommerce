import { test, expect } from '@playwright/test';
import { loginAs, logout, PRO_COMPANY, PRO_EMAIL, PRO_PASSWORD, PRO_SIRET, expectNoServerError } from './helpers';

// Accès aux données & conditions réservées au compte pro (société, SIRET, TVA).
test.describe('Espace pro — société & conditions', () => {
  test('la sidebar affiche la société du compte pro connecté', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    // Le layout compte affiche "Connecté·e en tant que <raison sociale>"
    await expect(page.getByText(PRO_COMPANY).first()).toBeVisible({ timeout: 10_000 });
  });

  test('la page Société expose SIRET et TVA intra du compte pro', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.getByRole('link', { name: 'Ma société' }).click();
    await expect(page).toHaveURL(/\/compte\/societe/);
    await expectNoServerError(page);
    await expect(page.getByText('SIRET', { exact: true })).toBeVisible();
    await expect(page.getByText(PRO_SIRET)).toBeVisible();
    await expect(page.getByText('TVA intra-communautaire')).toBeVisible();
  });

  test('la sidebar liste les rubriques pro (commandes, société, fidélité)', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await expect(page.getByRole('link', { name: 'Mes commandes' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ma société' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Programme fidélité' })).toBeVisible();
  });

  test('accès direct à /compte/societe en invité redirige vers /connexion', async ({ page }) => {
    await page.goto('/compte/societe');
    await expect(page).toHaveURL(/\/connexion/);
  });

  test('déconnexion depuis la page société coupe l\'accès pro', async ({ page }) => {
    await loginAs(page, PRO_EMAIL, PRO_PASSWORD);
    await page.goto('/compte/societe');
    await logout(page);
    await page.goto('/compte/societe');
    await expect(page).toHaveURL(/\/connexion/);
  });
});
