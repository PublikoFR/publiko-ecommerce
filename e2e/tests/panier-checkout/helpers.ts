/**
 * Helpers partagés — suite panier & checkout
 *
 * Compte pro : thierry.leroy@mde-distribution.test / testing123
 * Produit : premier produit visible sur /recherche (mono-variant, stock ≥ 5)
 */
import { expect, type Page } from '@playwright/test';

export const PRO_EMAIL    = 'thierry.leroy@mde-distribution.test';
export const PRO_PASSWORD = 'testing123';

// ---------------------------------------------------------------------------
// Livewire
// ---------------------------------------------------------------------------

export async function waitForLivewire(page: Page): Promise<void> {
  await page.waitForResponse(
    r => r.url().includes('/livewire/update'),
    { timeout: 15_000 },
  );
}

// ---------------------------------------------------------------------------
// Panier — isolation inter-tests
// ---------------------------------------------------------------------------

/**
 * Vide le panier si non-vide. À appeler avant chaque test qui ajoute un produit.
 * Lunar réutilise le même panier pour un user (user_id) entre sessions.
 */
export async function clearCart(page: Page): Promise<void> {
  await page.goto('/panier');
  const emptyMsg = page.getByRole('main').getByText('Votre panier est vide');
  const alreadyEmpty = await emptyMsg.isVisible().catch(() => false);
  if (alreadyEmpty) return;

  // wire:confirm déclenche window.confirm — accepter avant le clic
  page.once('dialog', d => d.accept());
  const lw = page.waitForResponse(
    r => r.url().includes('/livewire/update'),
    { timeout: 15_000 },
  );
  await page.getByRole('button', { name: 'Vider le panier' }).click();
  await lw;
  await expect(emptyMsg).toBeVisible({ timeout: 8_000 });
}

// ---------------------------------------------------------------------------
// Authentification
// ---------------------------------------------------------------------------

export async function loginAsPro(page: Page): Promise<void> {
  await page.goto('/connexion');

  // Si déjà connecté, /connexion redirige immédiatement → sortir.
  if (!page.url().includes('/connexion')) {
    return;
  }

  // Attendre que toutes les ressources soient chargées (dont livewire.js)
  // avant d'interagir avec le formulaire. Cold-start Docker : ~60-120 s.
  // networkidle garantit que livewire.js a été chargé ET exécuté.
  await page.waitForLoadState('networkidle', { timeout: 150_000 });

  await page.locator('input[type="email"]').first().fill(PRO_EMAIL);
  await page.locator('input[type="password"]').first().fill(PRO_PASSWORD);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.waitForURL(/\/compte/, { timeout: 30_000 });
}

// ---------------------------------------------------------------------------
// Panier — ajout produit
// ---------------------------------------------------------------------------

/**
 * Navigue vers le premier produit disponible sur /recherche et l'ajoute au panier.
 * Retourne la description de la ligne telle qu'elle apparaît dans le panier.
 *
 * Prérequis : utilisateur pro déjà connecté.
 * Garanties seed : mono-variant, stock ≥ 5, pas de quote_only.
 */
export async function addE2EProductToCart(page: Page): Promise<string> {
  await clearCart(page);
  await page.goto('/recherche');
  const link = page.locator('article a[href*="/produits/"]').first();
  await expect(link).toBeVisible({ timeout: 10_000 });
  const href = (await link.getAttribute('href')) ?? '';

  await page.goto(href);
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 10_000 });
  const productName = (await page.getByRole('heading', { level: 1 }).textContent())?.trim() ?? '';

  const addBtn = page.locator('button:has-text("Ajouter au panier")').first();
  await expect(addBtn).toBeVisible({ timeout: 10_000 });

  const lwUpdate = page.waitForResponse(
    r => r.url().includes('/livewire/update'),
    { timeout: 15_000 },
  );
  await addBtn.click();
  await lwUpdate;

  return productName;
}

// ---------------------------------------------------------------------------
// Checkout — adresse de livraison
// ---------------------------------------------------------------------------

/**
 * Remplit le formulaire d'adresse de livraison (étape 1 du checkout).
 *
 * Structure DOM : x-input.group enveloppe l'input dans un <label> →
 * getByRole('textbox', { name: /^Label/ }) résout correctement l'accessible name.
 *
 * Le select Pays ne porte pas d'aria-label propre : on le cible par son
 * texte de première option ("Sélectionnez un pays") avec locator('select').
 */
export async function fillShippingAddress(page: Page): Promise<void> {
  const firstNameInput = page.getByRole('textbox', { name: /^Prénom/ }).first();
  await expect(firstNameInput).toBeEditable({ timeout: 12_000 });

  await firstNameInput.fill('Thierry');
  await page.getByRole('textbox', { name: /^Nom/ }).first().fill('Leroy');
  await page.getByRole('textbox', { name: /^E-mail de contact/ }).first().fill(PRO_EMAIL);
  await page.getByRole('textbox', { name: /^Adresse ligne 1/ }).first().fill('12 rue de la Paix');
  await page.getByRole('textbox', { name: /^Ville/ }).first().fill('Paris');
  await page.getByRole('textbox', { name: /^Code postal/ }).first().fill('75001');

  // Select pays : premier <select> du formulaire d'adresse de livraison.
  // Valeur = country.id, option label = country.native ("France" en français).
  const countrySelect = page.locator('select').first();
  await countrySelect.selectOption({ label: 'France' });

  // Attendre les mises à jour wire:model.live (country_id déclenche un update)
  await page.waitForLoadState('networkidle');
}
