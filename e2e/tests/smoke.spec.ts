import { test, expect } from '@playwright/test';

test.describe('Smoke — storefront disponible', () => {
  test('page d\'accueil répond avec succès', async ({ page }) => {
    const response = await page.goto('/');
    // La page doit être accessible (pas de 500 ni d'erreur PHP)
    expect(response?.status()).toBeLessThan(500);
  });

  test('aucune exception PHP visible dans la page', async ({ page }) => {
    await page.goto('/');
    const bodyText = await page.locator('body').textContent();
    // Vérifier qu'il n'y a pas de stack trace Ignition/Whoops
    expect(bodyText).not.toContain('ErrorException');
    expect(bodyText).not.toContain('Whoops!');
    expect(bodyText).not.toContain('SQLSTATE');
  });

  test('admin login accessible', async ({ page }) => {
    const response = await page.goto('/admin/login');
    expect(response?.status()).toBeLessThan(500);
    // Filament affiche un champ email de connexion
    await expect(page.getByLabel(/email/i).first()).toBeVisible({ timeout: 10_000 });
  });
});
