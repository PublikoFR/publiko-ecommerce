<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Cart;
use Lunar\Models\Customer;

/**
 * Anonymisation RGPD d'un client : on NE supprime PAS la ligne `lunar_customers`
 * (ni ses commandes) — indispensable pour la comptabilité / Pennylane. On efface
 * les données personnelles, on supprime les paniers/adresses et les comptes de
 * connexion (User) liés, en dénouant au préalable les FK NO ACTION qui pointent
 * vers `users` (les commandes sont conservées, leur `user_id` est mis à null).
 */
class AnonymizeCustomer
{
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            // 1. Comptes de connexion liés → suppression (FK dénouées d'abord).
            foreach ($customer->users()->get() as $user) {
                $this->deleteUser($user);
            }

            // 2. Données personnelles rattachées au client.
            $customer->addresses()->delete();
            Cart::where('customer_id', $customer->id)->delete();
            $customer->customerGroups()->detach();
            $customer->discounts()->detach();

            // 3. Effacement des données personnelles — la fiche et ses commandes
            //    restent (commandes anonymisées via le client conservé).
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
            ])->save();
        });
    }

    private function deleteUser(User $user): void
    {
        // FK NO ACTION vers `users` : on dénoue avant de supprimer le compte.
        // Les commandes sont conservées → user_id nullifié (colonne nullable).
        DB::table('lunar_orders')->where('user_id', $user->id)->update(['user_id' => null]);
        DB::table('lunar_discount_user')->where('user_id', $user->id)->delete();
        DB::table('lunar_customer_user')->where('user_id', $user->id)->delete();
        Cart::where('user_id', $user->id)->delete();

        $user->delete();
    }
}
