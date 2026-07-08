/**
 * Helpers partagés — parcours Paiement.
 *
 * Comptes / fixtures (seed e2e) :
 *  - Pro connecté : thierry.leroy@weklo.test / testing123
 *    (customer_id=4, groupe « installateurs », sirene_status=active).
 *  - Commandes seedées : ids 1..10, références WK-000001..WK-000010,
 *    statuts variés (awaiting-payment, payment-received, in-preparation,
 *    dispatched, delivered, cancelled). Aucune n'utilise `payment-offline`
 *    ni `awaiting-quote` → ces statuts identifient de façon unique une
 *    commande produite par le parcours checkout.
 *  - Provider de paiement par défaut : `cash-in-hand` (driver offline).
 *    Stripe est configuré avec des clés factices en e2e → le parcours carte
 *    (Stripe Elements) et le paiement devis réel ne sont pas pilotables.
 */
import { expect, type Page, type APIRequestContext, type APIResponse } from '@playwright/test';

export const PRO_EMAIL = 'thierry.leroy@weklo.test';
export const PRO_PASSWORD = 'testing123';

/** Statut d'une commande réglée hors-ligne (cash-in-hand → offline driver). */
export const OFFLINE_STATUS = 'payment-offline';

/** Attend la fin d'une requête Livewire (`/livewire/update`). */
export function waitForLivewire(page: Page): Promise<unknown> {
  return page.waitForResponse(r => r.url().includes('/livewire/update'), { timeout: 15_000 });
}

/**
 * GET résilient aux « socket hang up » transitoires : sur une stack e2e, un
 * worker PHP-FPM peut être recyclé et fermer la connexion en cours. Ces coupures
 * réseau ne sont pas l'objet du test (assertions de statut HTTP) → on retente.
 */
export async function getWithRetry(request: APIRequestContext, url: string, tries = 3): Promise<APIResponse> {
  let lastError: unknown;
  for (let attempt = 0; attempt < tries; attempt++) {
    try {
      return await request.get(url);
    } catch (error) {
      lastError = error;
      await new Promise(resolve => setTimeout(resolve, 500));
    }
  }
  throw lastError;
}

/**
 * Connecte le compte pro seedé.
 *
 * Le login est un composant Livewire (`wire:submit="authenticate"`). Deux
 * pièges de cold-start traités ici :
 *  - la toute première requête sur une stack fraîche est lente → un warmup
 *    `goto('/')` amorce opcache/session avant l'écran de connexion ;
 *  - le clic sur « Se connecter » peut partir AVANT que Livewire ait lié le
 *    handler `wire:submit` (clic perdu → on reste sur /connexion). On attend
 *    donc que Livewire soit monté, puis on retente clic+navigation.
 */
export async function loginAsPro(page: Page): Promise<void> {
  // Warmup tolérant : premier hit sur la stack (opcache/session). Sur une stack
  // e2e, un worker PHP recyclé peut renvoyer une réponse vide (ERR_EMPTY_RESPONSE)
  // ou fermer la connexion → best-effort, on retente.
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      await page.goto('/');
      break;
    } catch {
      await page.waitForTimeout(500);
    }
  }
  await page.goto('/connexion');

  // Livewire monté = handler wire:submit lié.
  await page.waitForFunction(() => Boolean((window as unknown as { Livewire?: unknown }).Livewire), null, {
    timeout: 30_000,
  });

  // `main` scope : le footer contient aussi un input e-mail (newsletter).
  await page.locator('main input[type="email"]').fill(PRO_EMAIL);
  await page.locator('main input[type="password"]').fill(PRO_PASSWORD);

  // Retry clic + navigation : robuste au 1er login à froid et au clic perdu
  // pendant l'hydratation. On ne reclique que si on est encore sur /connexion.
  await expect(async () => {
    if (!/\/compte/.test(page.url())) {
      await page.locator('main button[type="submit"]').click({ timeout: 10_000 }).catch(() => {});
    }
    await page.waitForURL(/\/compte/, { timeout: 20_000 });
  }).toPass({ timeout: 90_000 });
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
 * Ouvre (ou ré-ouvre) le formulaire d'adresse de livraison en mode édition.
 *
 * Le panier est persisté PAR CLIENT en base : il est partagé entre tous les
 * tests connectés en pro. Dès qu'un test enregistre une adresse, les tests
 * suivants retrouvent l'étape adresse « passée » (formulaire replié, résumé
 * affiché via le bouton « Modifier »). Ce helper garantit un formulaire
 * éditable quel que soit l'état hérité du panier partagé.
 */
export async function openShippingAddressForm(page: Page): Promise<void> {
  const lineOne = page.locator('[wire\\:model\\.live="shipping.line_one"]');
  if (await lineOne.count()) {
    return; // formulaire déjà en mode édition
  }

  // Adresse déjà enregistrée → rouvrir via « Modifier » de la section Shipping.
  const shippingForm = page.locator('form:has(h3:has-text("Shipping Details"))');
  const modifier = shippingForm.getByRole('button', { name: 'Modifier' });
  if (await modifier.count()) {
    const livewire = waitForLivewire(page);
    await modifier.first().click();
    await livewire;
  }
  await expect(lineOne).toBeVisible({ timeout: 10_000 });
}

/**
 * Remplit et enregistre l'adresse de livraison sur /checkout.
 * `shippingIsBilling` est vrai par défaut → l'adresse de facturation est
 * dupliquée automatiquement, l'étape passe directement au choix du transport.
 *
 * Idempotent : si le panier partagé (persisté par client en base) porte déjà
 * une adresse d'un test précédent, l'étape est passée et le formulaire est
 * absent → no-op, on laisse le flux avancer.
 */
export async function fillShippingAddress(page: Page): Promise<void> {
  const lineOne = page.locator('[wire\\:model\\.live="shipping.line_one"]');
  if (!(await lineOne.count())) {
    return; // adresse déjà enregistrée sur le panier partagé
  }

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

  const save = page.locator('button:has-text("Enregistrer l\'adresse")');
  if (!(await save.count())) {
    return; // l'étape a basculé entre-temps
  }
  const livewire = waitForLivewire(page);
  await save.first().click();
  await livewire;
}

/**
 * Sélectionne l'option de transport proposée (première par défaut) et valide.
 *
 * Idempotent : attend que l'étape option se stabilise, puis clique le bouton
 * s'il est là. Si l'étape est déjà passée (auto-sélection ou panier partagé
 * déjà arrivé au paiement), l'attente se résout sur l'en-tête « Paiement » et
 * on ne clique rien.
 */
export async function chooseShipping(page: Page): Promise<void> {
  const shipBtn = page.locator('button:has-text("Choose Shipping")');
  const paymentHeading = page.getByRole('heading', { name: 'Paiement' });

  // Soit le bouton apparaît (à cliquer), soit l'étape paiement est déjà là.
  await expect(shipBtn.or(paymentHeading).first()).toBeVisible({ timeout: 15_000 });

  if (await shipBtn.count()) {
    const livewire = waitForLivewire(page);
    await shipBtn.first().click();
    await livewire;
  }
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
  // L'action `checkout()` (Livewire) crée la commande côté serveur puis émet
  // un redirect. On attend la réponse Livewire (≠ networkidle qui résout sur
  // tout le trafic) : à sa résolution, la commande est garantie créée en base.
  const livewire = waitForLivewire(page);
  await validate.first().click();
  await livewire;

  // Le redirect Livewire déclenche une navigation pleine page (vers `/` d'après
  // le bug UI connu) APRÈS la réponse. On attend qu'on ait quitté /checkout,
  // sinon la navigation suivante du test (`goto('/compte/commandes')`) est
  // interrompue par ce redirect en cours (« interrupted by another navigation »).
  await page.waitForURL(url => !new URL(url).pathname.startsWith('/checkout'), { timeout: 15_000 });
  await page.waitForLoadState('domcontentloaded');
}
