/**
 * E2E — Gardes serveur du paiement par lien devis (provider Stripe).
 *
 * Le paiement d'un devis passe par un lien signé (`/paiement-devis/{order}`)
 * qui crée un PaymentIntent Stripe, puis une confirmation. Le vrai encaissement
 * Stripe n'est pas pilotable en e2e (clés factices), MAIS les gardes de sécurité
 * qui protègent ce parcours s'exécutent AVANT tout appel Stripe et sont donc
 * déterministes :
 *  - lien de paiement non signé / falsifié → 403 (URL signée obligatoire) ;
 *  - confirmation sans paiement en cours → 410 (aucun intent rattaché) ;
 *  - commande inexistante → 404 (route model binding).
 *
 * On cible la commande seedée #1 (toujours présente après le seed).
 */
import { test, expect } from '@playwright/test';
import { getWithRetry } from './helpers';

const SEEDED_ORDER_ID = 1;
const UNKNOWN_ORDER_ID = 999_999;

test.describe('Paiement devis — gardes serveur', () => {
  test('le lien de paiement non signé est rejeté (403)', async ({ request }) => {
    const res = await getWithRetry(request, `/paiement-devis/${SEEDED_ORDER_ID}`);
    expect(res.status()).toBe(403);
  });

  test('un lien de paiement avec signature invalide est rejeté (403)', async ({ request }) => {
    const res = await getWithRetry(request, `/paiement-devis/${SEEDED_ORDER_ID}?signature=deadbeef&transport_cents=1000`);
    expect(res.status()).toBe(403);
  });

  test('la confirmation sans paiement en cours répond 410', async ({ request }) => {
    const res = await getWithRetry(request, `/paiement-devis/${SEEDED_ORDER_ID}/confirmation`);
    expect(res.status()).toBe(410);
  });

  test('une commande inexistante répond 404', async ({ request }) => {
    const show = await getWithRetry(request, `/paiement-devis/${UNKNOWN_ORDER_ID}`);
    expect(show.status()).toBe(404);

    const confirm = await getWithRetry(request, `/paiement-devis/${UNKNOWN_ORDER_ID}/confirmation`);
    expect(confirm.status()).toBe(404);
  });
});
