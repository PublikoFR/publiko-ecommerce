import { expect, Page } from '@playwright/test';

/**
 * Compte pro seedé par PkoCustomerSeeder (groupe "installateurs").
 * Seuls les clients pro reçoivent un User connectable ; les particuliers non.
 * Mot de passe commun : "testing123".
 */
export const PRO_EMAIL = 'thierry.leroy@weklo.test';
export const PRO_PASSWORD = 'testing123';
export const PRO_COMPANY = 'Leroy Fermetures';
export const PRO_SIRET = '12345678900015';

/** Second compte pro seedé, utile si un test a besoin d'un client distinct. */
export const PRO_EMAIL_ALT = 'sophie.girard@weklo.test';

/**
 * Connecte un client pro via le formulaire /connexion et attend l'arrivée
 * sur l'espace /compte (LoginPage redirige vers /compte par défaut).
 */
export async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/connexion');
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe').fill(password);
  await page.getByRole('button', { name: /Se connecter/i }).click();
  await page.waitForURL(/\/compte/, { timeout: 15_000 });
}

/**
 * Déconnexion via le bouton "Se déconnecter" de la sidebar espace-compte
 * (form POST /deconnexion). Requiert d'être sur une page /compte.
 */
export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.waitForURL('/', { timeout: 15_000 });
}

/** Assertion utilitaire : la page ne contient aucune trace d'erreur serveur. */
export async function expectNoServerError(page: Page): Promise<void> {
  const body = (await page.locator('body').textContent()) ?? '';
  expect(body).not.toContain('ErrorException');
  expect(body).not.toContain('SQLSTATE');
  expect(body).not.toMatch(/Whoops|500 SERVER ERROR/i);
}
