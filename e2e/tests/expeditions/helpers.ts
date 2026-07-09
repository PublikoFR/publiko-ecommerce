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

// Noms des méthodes de livraison seedées (PkoShippingSeeder) pour une adresse
// France métropolitaine. Le driver `free-shipping` (« Livraison offerte »)
// n'apparaît que si le total panier ≥ 500 € HT (franco).
export const METHOD_STANDARD = 'Livraison standard';
export const METHOD_PICKUP = 'Retrait entrepôt';
export const METHOD_FREE = 'Livraison offerte';

/**
 * Le formulaire des options de livraison. Sert de racine pour cibler les
 * cartes/radios sans capter d'autres <form> (adresse, paiement) de la page.
 */
export function shippingForm(page: Page) {
  return page.locator('form').filter({ hasText: 'Shipping Options' });
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
export async function reachShippingStep(page: Page): Promise<void> {
  await page.goto('/checkout');
  await expect(page.locator('body')).toContainText(
    /Shipping Details|Shipping Options|Paiement/,
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

  const form = shippingForm(page);
  const radios = form.locator('input[name="shippingOption"]');

  // 2. Si l'étape livraison est collapsée (une option avait été enregistrée par
  //    un test précédent → currentStep 3), rouvrir via son bouton « Edit »
  //    (wire:click $set currentStep=2). On garde `isVisible()` : sans bouton
  //    Edit visible, on NE clique PAS (évite le hang jusqu'au timeout global).
  const editBtn = form.getByRole('button', { name: /Edit|Modifier/ });
  if (await editBtn.isVisible().catch(() => false)) {
    const lwEdit = waitForLivewire(page);
    await editBtn.click();
    await lwEdit;
  }

  // 3. Les radios doivent maintenant être rendus et interactifs. Ces assertions
  //    sont bornées → en cas d'anomalie (0 option résolue), échec net et rapide
  //    plutôt qu'un blocage silencieux du beforeEach.
  await expect(page.getByText('Shipping Options')).toBeVisible({ timeout: 15_000 });
  await expect(radios.first()).toBeAttached({ timeout: 10_000 });
}
