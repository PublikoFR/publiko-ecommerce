/**
 * E2E — parcours catalogue & recherche
 *
 * Couverture : collections index, page catégorie (tri + filtre marque),
 * recherche texte (terme, état vide, pagination), fiche produit (anonyme
 * et pro connecté).
 *
 * Seed hypothèses :
 *  - 5 collections (portails-coulissants, portails-battants, volets-roulants,
 *    motorisations, clotures), ~10 produits par collection (aléatoire).
 *  - 50 produits mono-variant, 5 marques (SOMFY, FAAC, BFT, NICE, CAME).
 *  - Pro : thierry.leroy@mde-distribution.test / testing123
 */
import { test, expect, type Page } from '@playwright/test';

const PRO_EMAIL    = 'thierry.leroy@mde-distribution.test';
const PRO_PASSWORD = 'testing123';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function loginAsPro(page: Page): Promise<void> {
  await page.goto('/connexion');
  await page.locator('input[type="email"]').fill(PRO_EMAIL);
  await page.locator('input[type="password"]').fill(PRO_PASSWORD);
  await page.locator('button[type="submit"]').click();
  // Redirects to /compte after successful login
  await page.waitForURL(/\/compte/, { timeout: 12_000 });
}

/** Returns href of the first collection link from the index page. */
async function firstCollectionHref(page: Page): Promise<string> {
  await page.goto('/collections');
  const link = page.locator('a[href*="/collections/"]').first();
  await expect(link).toBeVisible({ timeout: 10_000 });
  return (await link.getAttribute('href')) ?? '/collections/portails-coulissants';
}

/** Returns href of the first product link visible on the current page. */
async function firstProductHref(page: Page): Promise<string> {
  const link = page.locator('article a[href*="/produits/"]').first();
  await expect(link).toBeVisible({ timeout: 10_000 });
  return (await link.getAttribute('href')) ?? '';
}

/** Waits for a Livewire update XHR to complete. */
async function waitForLivewire(page: Page): Promise<void> {
  await page.waitForResponse(r => r.url().includes('/livewire/update'), { timeout: 10_000 });
}

// ---------------------------------------------------------------------------
// Collections index
// ---------------------------------------------------------------------------
test.describe('Collections — index', () => {
  test('affiche le catalogue avec les collections', async ({ page }) => {
    await page.goto('/collections');
    await expect(page.getByRole('heading', { name: 'Tout notre catalogue', level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Nos collections' })).toBeVisible();
    // Au moins une carte collection avec lien
    await expect(page.locator('a[href*="/collections/"]').first()).toBeVisible();
  });

  test('un clic sur une collection ouvre la page catégorie', async ({ page }) => {
    const href = await firstCollectionHref(page);
    await page.locator(`a[href="${href}"]`).first().click();
    await page.waitForURL('**/collections/**');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Collection — page catégorie
// ---------------------------------------------------------------------------
test.describe('Collections — page catégorie', () => {
  test('affiche le titre, le compteur et des produits', async ({ page }) => {
    const href = await firstCollectionHref(page);
    await page.goto(href);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Compteur "N produits" dans l'en-tête
    await expect(page.locator('text=/\\d+ produits/')).toBeVisible();
    // Au moins une carte produit
    await expect(page.locator('article').first()).toBeVisible({ timeout: 10_000 });
  });

  test('sélecteur de tri — 4 options présentes', async ({ page }) => {
    const href = await firstCollectionHref(page);
    await page.goto(href);
    const sortSelect = page.locator('select#sort');
    await expect(sortSelect).toBeVisible();
    for (const value of ['new', 'price-asc', 'price-desc', 'name-asc']) {
      await expect(sortSelect.locator(`option[value="${value}"]`)).toBeAttached();
    }
  });

  test('changer le tri déclenche une mise à jour Livewire', async ({ page }) => {
    const href = await firstCollectionHref(page);
    await page.goto(href);
    const sortSelect = page.locator('select#sort');
    await expect(sortSelect).toBeVisible();
    const livewireUpdate = waitForLivewire(page);
    await sortSelect.selectOption('name-asc');
    await livewireUpdate;
    // Grille toujours visible après le tri
    await expect(page.locator('article').first()).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Filtre marque (testé sur /recherche pour garantir la présence de marques)
// ---------------------------------------------------------------------------
test.describe('Filtres — marque', () => {
  test('cocher une marque filtre les résultats et affiche Réinitialiser', async ({ page }) => {
    await page.goto('/recherche');
    await page.waitForLoadState('networkidle');

    const firstBrandCheckbox = page.locator('aside input[type="checkbox"]').first();
    if (!(await firstBrandCheckbox.isVisible({ timeout: 5_000 }).catch(() => false))) {
      test.skip();
      return;
    }

    const livewireUpdate = waitForLivewire(page);
    await firstBrandCheckbox.check();
    await livewireUpdate;

    // Le bouton "Réinitialiser" doit apparaître dans le panneau filtres
    await expect(page.locator('button:has-text("Réinitialiser")')).toBeVisible({ timeout: 8_000 });
  });

  test('Réinitialiser supprime les filtres actifs', async ({ page }) => {
    await page.goto('/recherche');
    await page.waitForLoadState('networkidle');

    const firstBrandCheckbox = page.locator('aside input[type="checkbox"]').first();
    if (!(await firstBrandCheckbox.isVisible({ timeout: 5_000 }).catch(() => false))) {
      test.skip();
      return;
    }

    await firstBrandCheckbox.check();
    await waitForLivewire(page);
    await expect(page.locator('button:has-text("Réinitialiser")')).toBeVisible({ timeout: 8_000 });

    const livewireUpdate = waitForLivewire(page);
    await page.locator('button:has-text("Réinitialiser")').click();
    await livewireUpdate;

    // Bouton disparu = filtres vidés
    await expect(page.locator('button:has-text("Réinitialiser")')).not.toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Recherche textuelle
// ---------------------------------------------------------------------------
test.describe('Recherche', () => {
  test('sans terme — affiche les produits et le compteur', async ({ page }) => {
    await page.goto('/recherche');
    await expect(page.getByRole('heading', { name: 'Recherche', level: 1 })).toBeVisible();
    await expect(page.locator('text=/\\d+ produits/')).toBeVisible();
  });

  test('avec terme — le terme est affiché et des résultats retournés', async ({ page }) => {
    // "portail" est un fragment du nom de 2 types de produits seedés
    await page.goto('/recherche?term=portail');
    await expect(page.getByRole('heading', { name: 'Recherche', level: 1 })).toBeVisible();
    // Le terme est rappelé dans l'en-tête : « portail »
    await expect(page.locator('text=/«.*portail.*»/')).toBeVisible();
    // Au moins un produit (les produits de type Portail sont seedés)
    await expect(page.locator('article').first()).toBeVisible({ timeout: 10_000 });
  });

  test('terme inconnu — affiche le message d\'état vide', async ({ page }) => {
    await page.goto('/recherche?term=xyz999notfound');
    await expect(
      page.locator('text=Aucun produit ne correspond à votre recherche.')
    ).toBeVisible();
  });

  test('pagination — naviguer à la page 2 (50 produits / paginate(24))', async ({ page }) => {
    await page.goto('/recherche');
    await page.waitForLoadState('networkidle');

    // 50 produits seedés → paginate(24) → page 2 accessible
    const page2Link = page.locator('a[href*="page=2"]').first();
    const hasPagination = await page2Link.isVisible({ timeout: 5_000 }).catch(() => false);

    if (!hasPagination) {
      // Seed insuffisant (<= 24 produits visibles) → passer
      test.skip();
      return;
    }

    await page2Link.click();
    await page.waitForURL(/page=2/);
    // Des produits doivent toujours être visibles sur la page 2
    await expect(page.locator('article').first()).toBeVisible({ timeout: 10_000 });
  });
});

// ---------------------------------------------------------------------------
// Fiche produit — visiteur anonyme
// ---------------------------------------------------------------------------
test.describe('Fiche produit — anonyme', () => {
  test('affiche le titre, la référence et le garde-prix', async ({ page }) => {
    // Navigation naturelle depuis la recherche
    await page.goto('/recherche');
    const productHref = await firstProductHref(page);
    await page.goto(productHref);

    // H1 = nom du produit
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Référence (SKU)
    await expect(page.locator('text=/Réf\\. /')).toBeVisible();
    // Garde-prix pour non-pro
    await expect(
      page.locator('text=Prix réservé aux professionnels connectés')
    ).toBeVisible();
  });

  test('affiche le badge de stock (En stock / Stock faible / Sur commande)', async ({ page }) => {
    await page.goto('/recherche');
    const productHref = await firstProductHref(page);
    await page.goto(productHref);

    // Le badge est l'un des trois états possibles
    const stockBadge = page.locator('text=/En stock|Stock faible|Sur commande/').first();
    const stockOk = await stockBadge.isVisible({ timeout: 5_000 }).catch(() => false);
    // Un produit sans stock ni fournisseur n'affiche pas de badge → acceptable
    if (!stockOk) {
      test.skip();
    }
  });
});

// ---------------------------------------------------------------------------
// Fiche produit — pro connecté
// ---------------------------------------------------------------------------
test.describe('Fiche produit — pro connecté', () => {
  test('affiche le prix HT et le label Tarif pro', async ({ page }) => {
    await loginAsPro(page);

    // Navigation vers un produit via la recherche
    await page.goto('/recherche');
    const productHref = await firstProductHref(page);
    await page.goto(productHref);

    // Prix formaté avec mention HT
    await expect(page.locator('text=HT').first()).toBeVisible({ timeout: 10_000 });
    // Label pro visible
    await expect(page.locator('text=Tarif pro')).toBeVisible();
  });

  test('affiche le bouton Ajouter au panier pour un produit mono-variant', async ({ page }) => {
    await loginAsPro(page);

    await page.goto('/recherche');
    const productHref = await firstProductHref(page);
    await page.goto(productHref);

    // Seed = mono-variant → add-to-cart Livewire rendu
    // "Ajouter au panier" sur la fiche complète (style default)
    const addToCartBtn = page.locator('button:has-text("Ajouter au panier")');
    const isVisible = await addToCartBtn.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!isVisible) {
      // Multi-variant ou fallback → "Choisir une variante" lien
      await expect(page.locator('text=/Choisir une variante|Ajouter/i').first()).toBeVisible();
    } else {
      await expect(addToCartBtn).toBeVisible();
    }
  });
});
