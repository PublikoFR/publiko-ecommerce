import { test, expect, Page } from '@playwright/test';
import { PRO_EMAIL } from './helpers';

// SIRET Luhn-valides synthétiques — aucune entreprise réelle.
// INSEE désactivé en E2E (aucune credential dans docker-compose.e2e.yml) →
// SireneClient::verify() retourne Status::Pending sans appel réseau.
const TEST_SIRET = '35600000000043'; // 14 chiffres, somme Luhn ≡ 0 (mod 10) ✓
const SIRET_LUHN_INVALIDE = '35600000000001'; // 14 chiffres mais Luhn ≠ 0 ✗

interface FormOptions {
  siret?: string;
  email?: string;
  password?: string;
  confirm?: string;
  terms?: boolean;
}

async function fillRegisterForm(page: Page, opts: FormOptions = {}): Promise<void> {
  const siret = opts.siret ?? TEST_SIRET;
  const email = opts.email ?? `test-pro-${Date.now()}-${Math.floor(Math.random() * 1e6)}@mde-distribution.test`;
  const password = opts.password ?? 'TestPro123!';
  const confirm = opts.confirm ?? password;
  const terms = opts.terms ?? true;

  await page.goto('/inscription');
  await page.getByLabel('SIRET (14 chiffres)').fill(siret);
  await page.getByLabel('E-mail pro').fill(email);
  await page.getByLabel('Mot de passe (min. 8 car.)').fill(password);
  await page.getByLabel('Confirmer').fill(confirm);
  if (terms) {
    await page.getByRole('checkbox').check();
  }
}

async function submit(page: Page): Promise<void> {
  await page.getByRole('button', { name: /Créer mon compte pro/i }).click();
}

/** Un message d'erreur de validation est affiché (rouge). */
async function expectValidationError(page: Page): Promise<void> {
  await expect(page.locator('.text-danger-600').first()).toBeVisible({ timeout: 15_000 });
  await expect(page).toHaveURL(/\/inscription/);
}

test.describe('Inscription pro', () => {
  test('affiche le formulaire d\'inscription', async ({ page }) => {
    await page.goto('/inscription');
    await expect(page.getByLabel('SIRET (14 chiffres)')).toBeVisible();
    await expect(page.getByLabel('E-mail pro')).toBeVisible();
    await expect(page.getByLabel('Mot de passe (min. 8 car.)')).toBeVisible();
    await expect(page.getByLabel('Confirmer')).toBeVisible();
    await expect(page.getByRole('button', { name: /Créer mon compte pro/i })).toBeVisible();
  });

  test('SIRET trop court déclenche une erreur', async ({ page }) => {
    await fillRegisterForm(page, { siret: '1234' });
    await submit(page);
    await expect(page.getByText(/SIRET invalide/i)).toBeVisible({ timeout: 15_000 });
    await expect(page).toHaveURL(/\/inscription/);
  });

  test('SIRET de 14 chiffres Luhn-invalide déclenche une erreur', async ({ page }) => {
    await fillRegisterForm(page, { siret: SIRET_LUHN_INVALIDE });
    await submit(page);
    await expect(page.getByText(/SIRET invalide/i)).toBeVisible({ timeout: 15_000 });
  });

  test('e-mail déjà utilisé déclenche une erreur de validation', async ({ page }) => {
    await fillRegisterForm(page, { email: PRO_EMAIL });
    await submit(page);
    await expectValidationError(page);
  });

  test('termes non acceptés déclenche une erreur', async ({ page }) => {
    await fillRegisterForm(page, { terms: false });
    await submit(page);
    await expectValidationError(page);
  });

  test('mot de passe trop court déclenche une erreur', async ({ page }) => {
    await fillRegisterForm(page, { password: 'court', confirm: 'court' });
    await submit(page);
    await expectValidationError(page);
  });

  test('confirmation de mot de passe divergente déclenche une erreur', async ({ page }) => {
    await fillRegisterForm(page, { password: 'TestPro123!', confirm: 'AutreMdp456!' });
    await submit(page);
    await expectValidationError(page);
  });

  test('inscription complète (SIRET valide, INSEE pending) crée le compte et redirige vers /connexion', async ({ page }) => {
    const uniqueEmail = `nouveau-pro-${Date.now()}-${Math.floor(Math.random() * 1e6)}@mde-distribution.test`;
    await fillRegisterForm(page, { email: uniqueEmail });
    await submit(page);

    // INSEE désactivé → sirene_status = pending. Le compte n'est PAS connecté,
    // redirection vers /connexion avec le message d'attente de validation.
    await page.waitForURL(/\/connexion/, { timeout: 20_000 });
    await expect(page.getByText(/vérification de votre SIRET|Compte créé/i)).toBeVisible({ timeout: 10_000 });
  });

  test('lien "Se connecter" depuis /inscription est fonctionnel', async ({ page }) => {
    await page.goto('/inscription');
    await page.getByRole('link', { name: /Se connecter/i }).click();
    await expect(page).toHaveURL(/\/connexion/);
  });
});
