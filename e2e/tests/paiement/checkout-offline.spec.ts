/**
 * E2E — Paiement offline (cash-in-hand) : parcours de bout en bout.
 *
 * Couvre :
 *  - le parcours complet panier → checkout → adresse → transport → paiement ;
 *  - le succès du paiement offline (commande créée, rattachée au pro) ;
 *  - la mise à jour du statut de commande (`payment-offline`) ;
 *  - le « reçu » : la fiche détail de la commande sert de justificatif.
 *
 * Note comportement (bug UI connu, code `app/` hors périmètre) : après
 * `CheckoutPage::checkout()`, l'app redirige vers `/` au lieu d'afficher la
 * page de confirmation (`cart.completedOrder` null → `CheckoutSuccessPage`
 * rebondit). La commande est néanmoins bien créée côté serveur : les
 * assertions portent donc sur l'espace compte (source de vérité), pas sur
 * une page de confirmation.
 */
import { test, expect } from '@playwright/test';
import { loginAsPro, placeOfflineOrder, OFFLINE_STATUS } from './helpers';

test.describe('Paiement offline — parcours complet', () => {
  test('règle une commande hors-ligne et la retrouve payée dans l\'espace compte', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAsPro(page);
    await placeOfflineOrder(page);

    // La commande figure dans l'historique du pro avec le statut offline.
    // Aucune commande seedée n'utilise ce statut → présence = commande créée
    // par ce parcours de paiement.
    await page.goto('/compte/commandes');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: 'Mes commandes', level: 1 })).toBeVisible();
    const offlineBadge = page.locator(`text=${OFFLINE_STATUS}`).first();
    await expect(offlineBadge).toBeVisible({ timeout: 10_000 });
  });

  test('la fiche détail de la commande offline fait office de reçu', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAsPro(page);
    await placeOfflineOrder(page);

    await page.goto('/compte/commandes');
    await page.waitForLoadState('networkidle');

    // La commande fraîchement passée (placed_at = maintenant) est en tête de
    // liste (tri placed_at desc) → premier lien « Détails ».
    await page.locator('a:has-text("Détails")').first().click();
    await page.waitForURL(/\/compte\/commandes\/\d+/, { timeout: 10_000 });

    // Contenu du reçu : titre commande, articles, ventilation des totaux.
    await expect(page.getByRole('heading', { name: /Commande #/ })).toBeVisible();
    await expect(page.locator(`text=${OFFLINE_STATUS}`).first()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Articles commandés' })).toBeVisible();
    // Ventilation des totaux (scopée au contenu principal : « Livraison » et
    // « Total TTC » apparaissent aussi dans l'en-tête/pied de page du site).
    const main = page.locator('main');
    await expect(main.getByText('Total TTC', { exact: true })).toBeVisible();
    await expect(main.getByText('Sous-total', { exact: true })).toBeVisible();
    await expect(main.getByText('Livraison', { exact: true })).toBeVisible();
  });
});
