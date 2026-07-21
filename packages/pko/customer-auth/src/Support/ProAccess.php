<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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
     * Clé de session posée par ImpersonateCustomerUser : id du membre du staff
     * à l'origine de l'impersonation.
     */
    public const IMPERSONATOR_SESSION_KEY = 'pko.impersonator_staff_id';

    /**
     * Retourne null si l'utilisateur est un pro actif, sinon le motif (message FR)
     * du refus d'accès à l'espace pro.
     */
    public static function denialReason(?Authenticatable $user): ?string
    {
        if ($user === null) {
            return 'Connectez-vous pour accéder à cette page.';
        }

        // Impersonation depuis le back-office : l'admin traverse le gate pour
        // pouvoir faire du support sur un compte en attente de validation ou
        // dont l'e-mail n'est pas encore confirmé.
        if (self::isImpersonating()) {
            return null;
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

    /**
     * La session courante a-t-elle été ouverte par un admin via l'impersonation ?
     *
     * Double condition volontaire : le flag de session ne suffit pas, on exige
     * aussi que la session staff soit toujours vivante. Ainsi le bypass meurt
     * avec la session admin et ne peut pas survivre à une déconnexion du panel.
     */
    public static function isImpersonating(): bool
    {
        if (! Session::has(self::IMPERSONATOR_SESSION_KEY)) {
            return false;
        }

        return Auth::guard('staff')->check();
    }

    /**
     * Le membre du staff qui a lancé l'impersonation, s'il y en a un.
     */
    public static function impersonatorId(): int|string|null
    {
        return self::isImpersonating()
            ? Session::get(self::IMPERSONATOR_SESSION_KEY)
            : null;
    }
}
