<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Models\Cart;
use Lunar\Models\Customer;

/**
 * Traitement RGPD d'un client, avec deux stratégies :
 *
 * - {@see anonymize()} : la fiche `lunar_customers` et ses commandes sont
 *   CONSERVÉES (obligation légale de conservation comptable ~10 ans, cf.
 *   Pennylane) ; on efface les données personnelles et on supprime les
 *   paniers/adresses/comptes de connexion liés. La fiche est horodatée
 *   (`anonymized_at`) pour être masquée de la liste admin par défaut.
 * - {@see deleteCompletely()} : suppression physique complète de la fiche —
 *   uniquement pertinent pour un client SANS commande (prospect, compte de
 *   test) où aucune pièce comptable n'est à conserver.
 *
 * {@see purge()} choisit automatiquement la bonne stratégie selon la présence
 * de commandes.
 */
class AnonymizeCustomer
{
    /**
     * Applique la stratégie RGPD adaptée : suppression physique si le client
     * n'a aucune commande, anonymisation sinon.
     *
     * @return string 'deleted' | 'anonymized'
     */
    public function purge(Customer $customer): string
    {
        if ($customer->orders()->exists()) {
            $this->anonymize($customer);

            return 'anonymized';
        }

        $this->deleteCompletely($customer);

        return 'deleted';
    }

    /**
     * Alias rétro-compatible → {@see anonymize()}. Conservé pour les appelants
     * historiques qui veulent forcer l'anonymisation sans passer par purge().
     */
    public function handle(Customer $customer): void
    {
        $this->anonymize($customer);
    }

    /**
     * Anonymisation : conserve la fiche client et ses commandes, efface les
     * données personnelles et horodate `anonymized_at`.
     */
    public function anonymize(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $this->stripPersonalData($customer);

            $customer->forceFill([
                'title' => null,
                'first_name' => 'Client',
                'last_name' => 'anonymisé',
                'company_name' => 'Client anonymisé',
                'tax_identifier' => null,
                'meta' => [],
                'sirene_status' => null,
                'sirene_verified_at' => null,
                'naf_code' => null,
                'pko_status' => 'banned',
                'pko_street' => null,
                'pko_postcode' => null,
                'pko_city' => null,
                'pko_country' => null,
                'sepa_enabled' => false,
                'anonymized_at' => now(),
            ])->save();
        });
    }

    /**
     * Suppression physique complète de la fiche client. À réserver aux clients
     * sans commande (sinon la FK NO ACTION `lunar_orders.customer_id` bloque et
     * la conservation comptable l'interdit — passer par {@see anonymize()}).
     */
    public function deleteCompletely(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            // Dénoue les FK NO ACTION restantes (adresses, paniers, pivots, users).
            $this->stripPersonalData($customer);

            // Les données rattachées en CASCADE (loyalty, pennylane, listes d'achat,
            // prix négociés) sont supprimées automatiquement par la FK.
            $customer->delete();
        });
    }

    /**
     * Efface les données personnelles rattachées et dénoue les FK NO ACTION
     * (hors commandes) : comptes de connexion, adresses, paniers, groupes, remises.
     */
    private function stripPersonalData(Customer $customer): void
    {
        // Comptes de connexion liés → suppression (FK dénouées d'abord).
        foreach ($customer->users()->get() as $user) {
            $this->deleteUser($user);
        }

        $customer->addresses()->delete();
        $this->deleteCarts(Cart::withTrashed()->where('customer_id', $customer->id)->pluck('id')->all());
        $customer->customerGroups()->detach();
        $customer->discounts()->detach();
    }

    private function deleteUser(User $user): void
    {
        // FK NO ACTION vers `users` : on dénoue avant de supprimer le compte.
        // Les commandes sont conservées → user_id nullifié (colonne nullable).
        DB::table('lunar_orders')->where('user_id', $user->id)->update(['user_id' => null]);
        DB::table('lunar_discount_user')->where('user_id', $user->id)->delete();
        DB::table('lunar_customer_user')->where('user_id', $user->id)->delete();
        $this->deleteCarts(Cart::withTrashed()->where('user_id', $user->id)->pluck('id')->all());

        $user->delete();
    }

    /**
     * Supprime physiquement des paniers en dénouant d'abord toutes les FK
     * `NO ACTION` qui pointent dessus (adresses, lignes, intents Stripe,
     * paniers fusionnés). Un `forceDelete()` direct échoue sinon en 1451.
     *
     * `withTrashed()` est indispensable côté appelant : Cart utilise
     * SoftDeletes, donc un panier déjà soft-deleted reste en base avec ses FK
     * `customer_id` / `user_id` actives et bloque la suppression du client.
     *
     * @param  array<int, int>  $cartIds
     */
    private function deleteCarts(array $cartIds): void
    {
        if ($cartIds === []) {
            return;
        }

        // Panier fusionné dans un autre panier : la self-FK `merged_id` est en
        // NO ACTION, on la dénoue (colonne nullable).
        DB::table('lunar_carts')->whereIn('merged_id', $cartIds)->update(['merged_id' => null]);

        DB::table('lunar_cart_addresses')->whereIn('cart_id', $cartIds)->delete();
        DB::table('lunar_cart_lines')->whereIn('cart_id', $cartIds)->delete();

        // Table fournie par lunarphp/stripe : absente si le driver n'est pas installé.
        if (Schema::hasTable('lunar_stripe_payment_intents')) {
            DB::table('lunar_stripe_payment_intents')->whereIn('cart_id', $cartIds)->delete();
        }

        // Les commandes gardent leur `cart_id` en nullOnDelete → rien à faire.
        Cart::withTrashed()->whereIn('id', $cartIds)->forceDelete();
    }
}
