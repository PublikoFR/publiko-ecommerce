/**
 * Helpers partagés — suite « Expéditions & livraison »
 *
 * Réutilise les helpers PROUVÉS VERTS du domaine panier/checkout
 * (login, ajout produit, adresse, waitForLivewire) plutôt que d'en
 * réimplémenter des variantes divergentes. La seule logique propre à
 * cette suite est `reachShippingStep` (navigation jusqu'aux radios du
 * mode de livraison, avec normalisation de l'état persistant du panier).
 *
 * Compte pro : thierry.leroy@mde-distribution.test / testing123
 */
import { expect, type Page } from '@playwright/test';
import {
  PRO_EMAIL,
  PRO_PASSWORD,
  clearCart,
  addE2EProductToCart,
  waitForLivewire,
} from '../panier-checkout/helpers';

export {
  PRO_EMAIL,
  PRO_PASSWORD,
  clearCart,
  addE2EProductToCart,
  waitForLivewire,
};

/**
 * Remplit le formulaire d'adresse de livraison (étape 1 du checkout).
 *
 * Variante locale de la version panier/checkout : le changement de pays
 * (`wire:model.live`) est attendu via la RÉPONSE `/livewire/update` plutôt que
 * par `networkidle` — ce storefront n'atteint pas `networkidle` de façon fiable.
 */
export async function fillShippingAddress(page: Page): Promise<void> {
  const firstNameInput = page.getByRole('textbox', { name: /^Prénom/ }).first();
  await expect(firstNameInput).toBeEditable({ timeout: 20_000 });

  await firstNameInput.fill('Thierry');
  await page.getByRole('textbox', { name: /^Nom/ }).first().fill('Leroy');
  await page.getByRole('textbox', { name: /^E-mail de contact/ }).first().fill(PRO_EMAIL);
  await page.getByRole('textbox', { name: /^Adresse ligne 1/ }).first().fill('12 rue de la Paix');
  await page.getByRole('textbox', { name: /^Ville/ }).first().fill('Paris');
  await page.getByRole('textbox', { name: /^Code postal/ }).first().fill('75001');

  // Le select Pays est `wire:model.live` → selectOption déclenche un update.
  const countryUpdate = page
    .waitForResponse(r => r.url().includes('/livewire/update'), { timeout: 15_000 })
    .catch(() => undefined);
  await page.locator('select').first().selectOption({ label: 'France' });
  await countryUpdate;
}

// La suite « expéditions » peut tourner SEULE (`test:e2e -- expeditions`) : le
// tout premier login frappe alors une stack Docker froide, où `livewire.js`
// est chargé mais pas encore initialisé au moment du clic. Le formulaire de
// connexion étant un composant Livewire (`wire:submit="authenticate"`), un clic
// pré-hydratation déclenche un submit HTML natif → on reste sur `/connexion?`.
// D'où un login robuste (warmup + attente d'init réelle + retry du clic), plus
// tolérant que la variante panier/checkout qui, elle, bénéficie d'un warmup
// implicite par les tests non authentifiés qui la précèdent.
let coldStartWarmed = false;

async function waitForLivewireReady(page: Page): Promise<void> {
  // NE PAS utiliser `networkidle` : ce storefront ne l'atteint pas de façon
  // fiable (requêtes de fond) → l'attente consomme jusqu'à son timeout et épuise
  // le budget du beforeEach. `window.Livewire` n'existe qu'une fois le runtime
  // INITIALISÉ → signal d'hydratation déterministe, résolu en ~ms une fois le JS
  // exécuté (donc aussi une garantie que wire:submit est branché).
  await page
    .waitForFunction(() => (window as { Livewire?: unknown }).Livewire !== undefined, {
      timeout: 60_000,
    })
    .catch(() => undefined);
}

export async function loginAsPro(page: Page): Promise<void> {
  // Warmup unique : première requête sur stack froide (opcache/Livewire à froid).
  // On chauffe directement `/connexion` — la page dont on a besoin — pour que le
  // login réel qui suit tombe sur un rendu déjà compilé. `domcontentloaded`
  // uniquement — surtout pas `networkidle` (cf. supra).
  if (!coldStartWarmed) {
    await page.goto('/connexion', { waitUntil: 'domcontentloaded', timeout: 150_000 });
    coldStartWarmed = true;
  }

  await page.goto('/connexion', { waitUntil: 'domcontentloaded', timeout: 90_000 });

  // Déjà connecté → /connexion redirige immédiatement vers l'espace compte.
  if (!page.url().includes('/connexion')) {
    await page.waitForURL(/\/compte/, { timeout: 30_000 }).catch(() => undefined);
    return;
  }

  await waitForLivewireReady(page);

  const emailInput = page.locator('input[type="email"]').first();
  await expect(emailInput).toBeEditable({ timeout: 30_000 });

  // Retry du clic : sur stack froide, le 1er « Se connecter » peut partir avant
  // que le handler wire:submit soit branché (submit natif → reste sur /connexion).
  for (let attempt = 1; attempt <= 4; attempt++) {
    await emailInput.fill(PRO_EMAIL);
    await page.locator('input[type="password"]').first().fill(PRO_PASSWORD);

    const redirected = page
      .waitForURL(/\/compte/, { timeout: 20_000 })
      .then(() => true)
      .catch(() => false);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    if (await redirected) {
      return;
    }

    // Toujours sur le formulaire (submit natif avorté) → re-stabiliser puis retry.
    if (!page.url().includes('/connexion')) {
      return; // redirigé ailleurs (ex. /compte différé) : considérer OK.
    }
    await waitForLivewireReady(page);
  }

  // Dernière tentative : laisser l'assertion remonter un échec parlant.
  await page.waitForURL(/\/compte/, { timeout: 20_000 });
}

// Libellés des 3 services Chronopost (seed `2026_06_26_120000`), tels qu'affichés
// par le composant ShippingOptions. Les anciennes méthodes table-rate
// (`mde-standard` / `mde-pickup` / `mde-free`) ne sont plus seedées du tout.
export const SERVICE_RELAIS = 'Livraison économique';
export const SERVICE_STANDARD = 'Livraison standard';
export const SERVICE_EXPRESS = 'Livraison express';

/**
 * Catalogue de test expédition (`PkoShippingCasesProductSeeder`, cf. shipping.md §5.17).
 * Les slugs sont dérivés de « marque + nom + MPN » : déterministes tant que le
 * seeder n'est pas modifié. Un changement de nom produit dans le seeder impose
 * de régénérer cette table.
 */
export const TX_SLUGS: Record<string, string> = {
  'TX-01': 'somfy-telecommande-4-canaux-bi-directionnelle-tx-01-mpn',
  'TX-02': 'faac-motorisation-a-bras-droits-24v-tx-02-mpn',
  'TX-04': 'somfy-volet-roulant-monobloc-1400x1200-tx-04-mpn',
  'TX-05': 'came-portail-battant-acier-2-vantaux-3-m-tx-05-mpn',
  'TX-07': 'nice-portail-coulissant-aluminium-renforce-5-m-tx-07-mpn',
  'TX-08': 'bft-kit-motorisation-coulissant-1000-kg-premium-tx-08-mpn',
  'TX-09': 'somfy-coulisse-longue-4-m-hors-normes-tx-09-mpn',
  'TX-10': 'somfy-tablier-de-volet-roulant-sur-mesure-tx-10-mpn',
  'TX-13': 'somfy-coffre-tunnel-volet-roulant-expedition-fabricant-tx-13-mpn',
  'TX-14': 'nice-portail-coulissant-8-m-sur-mesure-tx-14-mpn',
  'TX-16': 'bft-motorisation-enterree-kit-complet-tx-16-mpn',
  'TX-20': 'faac-photocellule-infrarouge-sans-fil-tx-20-mpn',
};

/**
 * Ajoute au panier un produit du catalogue de test, par SKU.
 *
 * Ne vide PAS le panier : les scénarios multi-lignes (mixte, franco + exclusion)
 * enchaînent plusieurs appels. Appeler `clearCart()` en amont.
 */
export async function addSkuToCart(page: Page, sku: string, quantity = 1): Promise<void> {
  const slug = TX_SLUGS[sku];
  if (!slug) {
    throw new Error(`SKU ${sku} absent de TX_SLUGS — compléter la table dans helpers.ts`);
  }

  await page.goto(`/produits/${slug}`, { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 20_000 });
  await expect(page.getByText(`Réf. ${sku}`)).toBeVisible({ timeout: 10_000 });

  if (quantity > 1) {
    // Stepper Alpine `x-model.number` relié à @entangle('quantity').live :
    // remplir puis blur pour que Livewire reçoive la valeur avant le clic.
    const qtyInput = page.locator('#quantity');
    await qtyInput.fill(String(quantity));
    await qtyInput.blur();
  }

  const lw = waitForLivewire(page);
  await page.getByRole('button', { name: 'Ajouter au panier' }).click();
  await lw;
}

/**
 * Le formulaire des options de livraison (composant ShippingOptions, L5).
 * Sert de racine pour cibler les cartes/radios sans capter d'autres <form>
 * (adresse, paiement) de la page.
 */
export function shippingForm(page: Page) {
  return page.locator('form').filter({ hasText: 'Mode de livraison' });
}

/**
 * Les radios de mode de livraison. Ciblés par leur `value` (= identifier de
 * l'option) car le composant ne pose pas d'attribut `name` : sans ce filtre,
 * les radios de sélection de point relais seraient capturés aussi.
 */
export function optionRadios(page: Page) {
  return shippingForm(page).locator(
    'input[type="radio"][value^="chronopost."], ' +
      'input[type="radio"][value="free_shipping"], ' +
      'input[type="radio"][value^="quote."], ' +
      'input[type="radio"][value^="surcharge."]',
  );
}

/**
 * Amène le tunnel jusqu'à l'étape de sélection du mode de livraison, avec les
 * radios INTERACTIFS (currentStep == shipping_option).
 *
 * Prérequis : produit déjà au panier, utilisateur pro connecté.
 *
 * ⚠️ État persistant : le panier Lunar (et donc `cart->shippingAddress` +
 * l'option choisie) est rattaché au user et **survit entre les tests** d'un
 * même run (migrate:fresh une seule fois par suite). Selon ce que les tests
 * précédents ont enregistré, `CheckoutPage::determineCheckoutStep()` peut :
 *   - currentStep 1 (aucune adresse)        → l'étape adresse est ouverte
 *   - currentStep 2 (adresse, pas d'option) → les radios sont déjà interactifs
 *   - currentStep 3 (adresse + option)      → les options sont collapsées (Edit)
 * Ce helper normalise tous ces cas vers « radios interactifs », et échoue
 * VITE (assertions bornées) plutôt que de bloquer sur un clic impossible.
 */
/**
 * Amène le tunnel jusqu'à l'étape « Paiement » pour un panier 100 % devis.
 *
 * Ces paniers ne produisent AUCUNE option de livraison : `determineCheckoutStep()`
 * saute l'étape « mode de livraison » et ouvre directement le paiement. Il n'y a
 * donc ni carte à sélectionner ni bouton « Continuer » à cliquer.
 */
export async function reachPaymentStep(page: Page): Promise<void> {
  await page.goto('/checkout');
  await expect(page.locator('body')).toContainText(
    /Adresse de livraison|Mode de livraison|Paiement/,
    { timeout: 60_000 },
  );

  const firstName = page.getByRole('textbox', { name: /^Prénom/ }).first();
  if (await firstName.isEditable({ timeout: 5_000 }).catch(() => false)) {
    await fillShippingAddress(page);
    const lwAddress = waitForLivewire(page);
    await page.locator('button:has-text("Enregistrer l\'adresse")').click();
    await lwAddress;
  }

  await expect(page.getByRole('heading', { name: 'Paiement' })).toBeVisible({
    timeout: 20_000,
  });
}

export async function reachShippingStep(page: Page): Promise<void> {
  await page.goto('/checkout');
  await expect(page.locator('body')).toContainText(
    /Adresse de livraison|Mode de livraison|Paiement/,
    { timeout: 60_000 },
  );

  // 1. Étape adresse ouverte → remplir + enregistrer (fait avancer à l'étape 2/3).
  //    ⚠️ `isEditable()` (contrairement à `isVisible()`) AUTO-ATTEND l'élément :
  //    sans timeout borné, quand l'étape adresse est collapsée (adresse déjà
  //    enregistrée par un test précédent → pas de champ Prénom), l'attente monte
  //    jusqu'au timeout global du test (240 s) avant que le `.catch` s'active.
  const firstName = page.getByRole('textbox', { name: /^Prénom/ }).first();
  if (await firstName.isEditable({ timeout: 5_000 }).catch(() => false)) {
    await fillShippingAddress(page);
    const lwAddress = waitForLivewire(page);
    await page.locator('button:has-text("Enregistrer l\'adresse")').click();
    await lwAddress;
  }

  // 2. Si l'étape livraison est collapsée (une option avait été enregistrée par
  //    un test précédent → currentStep 4), rouvrir via son bouton « Modifier »
  //    (wire:click $set currentStep). On garde `isVisible()` : sans bouton
  //    visible, on NE clique PAS (évite le hang jusqu'au timeout global).
  const collapsed = page
    .locator('div')
    .filter({ has: page.getByRole('heading', { name: 'Mode de livraison' }) })
    .getByRole('button', { name: 'Modifier' })
    .first();
  if (await collapsed.isVisible().catch(() => false)) {
    const lwEdit = waitForLivewire(page);
    await collapsed.click();
    await lwEdit;
  }

  // 3. Le composant doit maintenant être rendu. Assertion bornée → en cas
  //    d'anomalie, échec net et rapide plutôt qu'un blocage silencieux.
  await expect(shippingForm(page)).toBeVisible({ timeout: 15_000 });
}
