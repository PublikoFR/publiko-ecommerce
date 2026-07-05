/**
 * Helpers partagés — parcours Paiement.
 *
 * Comptes / fixtures (seed e2e) :
 *  - Pro connecté : thierry.leroy@mde-distribution.test / testing123
 *    (customer_id=4, groupe « installateurs », sirene_status=active).
 *  - Commandes seedées : ids 1..10, références MDE-000001..MDE-000010,
 *    statuts variés (awaiting-payment, payment-received, in-preparation,
 *    dispatched, delivered, cancelled). Aucune n'utilise `payment-offline`
 *    ni `awaiting-quote` → ces statuts identifient de façon unique une
 *    commande produite par le parcours checkout.
 *  - Provider de paiement par défaut : `cash-in-hand` (driver offline).
 *    Stripe est configuré avec des clés factices en e2e → le parcours carte
 *    (Stripe Elements) et le paiement devis réel ne sont pas pilotables.
 */
import { expect, type Page } from '@playwright/test';

export const PRO_EMAIL = 'thierry.leroy@mde-distribution.test';
export const PRO_PASSWORD = 'testing123';

/** Statut d'une commande réglée hors-ligne (cash-in-hand → offline driver). */
export const OFFLINE_STATUS = 'payment-offline';

/** Attend la fin d'une requête Livewire (`/livewire/update`). */
export function waitForLivewire(page: Page): Promise<unknown> {
  return page.waitForResponse(r => r.url().includes('/livewire/update'), { timeout: 15_000 });
}

/**
 * Connecte le compte pro seedé. La toute première connexion pro peut être
 * lente (bootstrap session/customer) → timeout large + networkidle.
 */
export async function loginAsPro(page: Page): Promise<void> {
  await page.goto('/connexion');
  // `main` scope : le footer contient aussi un input e-mail (newsletter).
  await page.locator('main input[type="email"]').fill(PRO_EMAIL);
  await page.locator('main input[type="password"]').fill(PRO_PASSWORD);
  await page.locator('main button[type="submit"]').click();
  await page.waitForURL(/\/compte/, { timeout: 60_000 });
  await page.waitForLoadState('networkidle');
}

/**
 * Ajoute le premier produit trouvé (via la recherche) au panier.
 * Renvoie l'URL de la fiche produit utilisée.
 */
export async function addFirstProductToCart(page: Page): Promise<string> {
  await page.goto('/recherche');
  const href = await page.locator('article a[href*="/produits/"]').first().getAttribute('href');
  if (!href) throw new Error('Aucun produit trouvé sur /recherche pour le parcours paiement.');

  await page.goto(href);
  const addBtn = page.locator('button:has-text("Ajouter au panier")').first();
  await expect(addBtn).toBeVisible({ timeout: 10_000 });

  const livewire = waitForLivewire(page);
  await addBtn.click();
  await livewire;

  return href;
}

/**
 * Remplit et enregistre l'adresse de livraison sur /checkout.
 * `shippingIsBilling` est vrai par défaut → l'adresse de facturation est
 * dupliquée automatiquement, l'étape passe directement au choix du transport.
 */
export async function fillShippingAddress(page: Page): Promise<void> {
  const fields: Record<string, string> = {
    first_name: 'Thierry',
    last_name: 'Leroy',
    contact_email: PRO_EMAIL,
    line_one: '12 rue des Tests',
    city: 'Paris',
    postcode: '75001',
  };

  for (const [field, value] of Object.entries(fields)) {
    const input = page.locator(`[wire\\:model\\.live="shipping.${field}"]`);
    if (await input.count()) {
      await input.fill(value);
    }
  }
  // Laisse le dernier update `wire:model.live` se propager avant la soumission.
  await page.waitForTimeout(1_000);

  await page.locator('button:has-text("Enregistrer l\'adresse")').first().click();
  await waitForLivewire(page);
}

/**
 * Sélectionne l'option de transport proposée (première par défaut) et valide.
 * Tolère l'auto-sélection : si le bouton n'apparaît pas, l'étape est déjà passée.
 */
export async function chooseShipping(page: Page): Promise<void> {
  const shipBtn = page.locator('button:has-text("Choose Shipping")');
  await expect(shipBtn.first()).toBeVisible({ timeout: 10_000 });
  await shipBtn.first().click();
  await waitForLivewire(page);
}

/**
 * Parcours complet jusqu'à la validation d'un paiement offline.
 * Laisse la page où l'app l'emmène après `checkout()` (redirection variable
 * selon le bug UI connu). L'effet de bord fiable = la commande créée en base.
 */
export async function placeOfflineOrder(page: Page): Promise<void> {
  await addFirstProductToCart(page);

  await page.goto('/checkout');
  await page.waitForLoadState('networkidle');

  await fillShippingAddress(page);
  await chooseShipping(page);

  // Étape paiement : `cash-in-hand` est le mode par défaut → bouton offline.
  const validate = page.locator('button:has-text("Valider la commande")');
  await expect(validate.first()).toBeVisible({ timeout: 10_000 });
  await validate.first().click();
  // La commande est créée côté serveur ; l'URL d'arrivée n'est pas garantie.
  await page.waitForLoadState('networkidle');
}
