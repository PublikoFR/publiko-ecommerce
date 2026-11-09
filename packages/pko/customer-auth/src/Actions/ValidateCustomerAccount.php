<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Actions;

use Lunar\Models\Customer;

/**
 * Validation manuelle d'un compte pro par l'équipe (back-office) : `pending` → `active`.
 *
 * C'est le SEUL chemin d'ouverture d'un compte inscrit en ligne : ni l'inscription
 * ni la vérification de l'e-mail n'activent le compte. L'e-mail « compte validé »
 * (AccountActivatedMail) n'est pas envoyé ici mais par CustomerActivationObserver,
 * pour qu'il parte aussi quand l'admin passe par le Select « Statut compte ».
 *
 * Garde : on refuse de valider un compte dont l'e-mail n'est pas confirmé. Valider
 * sans cette preuve ouvrirait l'espace pro à quiconque s'inscrit avec l'adresse
 * d'une autre société (le mot de passe est celui de l'inscrit, pas du titulaire de
 * la boîte). Marquer l'e-mail « vérifié » à la place du client a été écarté pour la
 * même raison : ce serait affirmer une preuve qu'on n'a pas. Si le client n'a pas
 * reçu son lien, il le redemande en tentant de se connecter (LoginPage le renvoie).
 */
class ValidateCustomerAccount
{
    /**
     * Motif (FR) pour lequel ce compte ne peut pas être validé, ou null s'il peut l'être.
     */
    public static function blockReason(Customer $customer): ?string
    {
        if ($customer->getAttribute('pko_status') !== 'pending') {
            return 'Ce compte n\'est pas en attente de validation.';
        }

        if (! self::emailVerified($customer)) {
            return 'L\'adresse e-mail du client n\'est pas encore confirmée : il doit d\'abord cliquer sur le lien reçu à l\'inscription.';
        }

        return null;
    }

    /**
     * Vrai si aucun utilisateur rattaché n'a d'e-mail non confirmé.
     * Un client sans utilisateur (créé en back-office sans e-mail) ne peut pas se
     * connecter : le valider est sans risque.
     */
    public static function emailVerified(Customer $customer): bool
    {
        return ! $customer->users()->whereNull('email_verified_at')->exists();
    }

    /**
     * @throws \DomainException si le compte ne peut pas être validé.
     */
    public function __invoke(Customer $customer): void
    {
        $reason = self::blockReason($customer);

        if ($reason !== null) {
            throw new \DomainException($reason);
        }

        $customer->setAttribute('pko_status', 'active');
        $customer->save();
    }
}
