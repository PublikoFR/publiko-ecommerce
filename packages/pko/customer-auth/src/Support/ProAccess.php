<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Point unique de vérité pour « ce compte est-il un pro actif ? ».
 *
 * Partagé par RequireProCustomer (gate /compte) et RedirectIfProCustomer
 * (redirection depuis /connexion). Sans cette symétrie, un utilisateur
 * authentifié mais non-actif (SIRET en attente, hors groupe) boucle entre
 * /compte (qui le renvoie vers /connexion) et /connexion (qui le renvoie
 * vers /compte) → ERR_TOO_MANY_REDIRECTS.
 */
class ProAccess
{
    /**
     * Retourne null si l'utilisateur est un pro actif, sinon le motif (message FR)
     * du refus d'accès à l'espace pro.
     */
    public static function denialReason(?Authenticatable $user): ?string
    {
        if ($user === null) {
            return 'Connectez-vous pour accéder à cette page.';
        }

        $customer = method_exists($user, 'customers') ? $user->customers()->first() : null;

        if (! $customer) {
            return "Votre compte n'est pas encore rattaché à une société pro.";
        }

        $pkoStatus = $customer->getAttribute('pko_status');
        if ($pkoStatus === 'banned') {
            return 'Votre compte a été suspendu. Contactez-nous pour plus d\'informations.';
        }
        if ($pkoStatus === 'pending') {
            // Distingue le motif : e-mail non vérifié (action possible par le client)
            // vs validation manuelle du SIRET (rien à faire côté client).
            if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
                return 'Confirmez votre adresse e-mail (lien reçu à l\'inscription) pour activer votre compte.';
            }

            return 'Votre compte est en cours de validation. Vous serez notifié par e-mail.';
        }

        $status = $customer->getAttribute('sirene_status');
        if ($status !== null && $status !== 'active') {
            return 'Votre compte est en cours de validation. Vous serez notifié par e-mail.';
        }

        $required = (string) config('customer-auth.default_customer_group_handle', 'installateurs');
        if (! $customer->customerGroups()->where('handle', $required)->exists()) {
            return 'Accès réservé aux comptes professionnels.';
        }

        return null;
    }

    public static function isActivePro(?Authenticatable $user): bool
    {
        return $user !== null && self::denialReason($user) === null;
    }
}
