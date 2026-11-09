<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Point unique de vérité pour « ce compte est-il un pro actif ? ».
 *
 * Règle (FEAT-006) : est pro actif un utilisateur rattaché à un Customer dont
 * `pko_status` vaut `active`. Ce statut n'est posé que par l'équipe (validation
 * manuelle en back-office) — le client ne l'obtient jamais seul, même en
 * confirmant son e-mail. Seule exception : l'impersonation admin.
 *
 * Partagé par RequireProCustomer (gate /compte), RedirectIfProCustomer
 * (redirection depuis /connexion) et LoginPage (refus de connexion). Sans cette
 * symétrie, un utilisateur authentifié mais non-actif boucle entre /compte (qui
 * le renvoie vers /connexion) et /connexion (qui le renvoie vers /compte)
 * → ERR_TOO_MANY_REDIRECTS.
 */
class ProAccess
{
    /**
     * Clé de session posée par ImpersonateCustomerUser : id du membre du staff
     * à l'origine de l'impersonation.
     */
    public const IMPERSONATOR_SESSION_KEY = 'pko.impersonator_staff_id';

    public const UNVERIFIED_EMAIL_REASON = 'Confirmez d\'abord votre adresse e-mail en cliquant sur le lien reçu à l\'inscription.';

    public const AWAITING_VALIDATION_REASON = 'Votre compte est en cours de validation par notre équipe, nous vous recontacterons.';

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
        if ($pkoStatus !== 'active') {
            // Chaque compte pro est validé MANUELLEMENT par l'équipe (back-office,
            // action « Valider le compte ») : ni l'inscription ni la vérification
            // de l'e-mail n'ouvrent l'accès. Deux messages, car le client n'a pas
            // la même chose à faire : confirmer son e-mail (LoginPage lui renvoie
            // alors le lien), ou simplement attendre la validation.
            if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
                return self::UNVERIFIED_EMAIL_REASON;
            }

            return self::AWAITING_VALIDATION_REASON;
        }

        // Pas de gate sur `sirene_status` : le SIRET n'est pas une valeur fiable
        // (non vérifié quand INSEE est off, voire null). L'équipe le contrôle
        // avant de valider le compte.

        // AUCUN gate sur les groupes clients. Les groupes Lunar servent à la
        // tarification et à la visibilité catalogue, pas à l'authentification :
        // ce sont des étiquettes que l'admin déplace librement depuis la fiche
        // client (retirer « Nouveau client » une fois le client qualifié, le
        // basculer dans son groupe métier…).
        //
        // Tant que ce gate exigeait l'appartenance au groupe par défaut, ce
        // geste d'administration parfaitement légitime déconnectait le client
        // définitivement — il ne pouvait plus se reconnecter tant qu'on ne lui
        // remettait pas « Nouveau client ». Le rattachement au groupe par défaut
        // reste posé à l'inscription (cf. RegisterProCustomer) pour que le client
        // ait des prix, mais son retrait n'est plus un motif de refus.
        //
        // Le critère d'accès pro est donc : un compte rattaché à un Customer dont
        // `pko_status` vaut `active`, c'est-à-dire validé par l'équipe.

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
