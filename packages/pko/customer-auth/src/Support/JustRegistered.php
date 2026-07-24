<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use Illuminate\Http\Request;

/**
 * Marque la session d'un compte qui vient de s'inscrire.
 *
 * Règle produit : un compte `pending` ne doit jamais rester authentifié — sinon
 * le header affiche son nom alors qu'aucune page ne lui est accessible
 * (« semi-connexion »). Deux exceptions assumées :
 *
 * 1. l'impersonation admin (gérée par [[ProAccess::isImpersonating()]]) ;
 * 2. l'auto-login qui suit immédiatement l'inscription, pour ne pas casser le
 *    parcours d'entrée — c'est ce que ce flag matérialise.
 *
 * Le flag ne survit pas à la session : dès que l'utilisateur se déconnecte ou
 * que la session expire, le compte pending redevient non-connectable tant que
 * son e-mail n'est pas vérifié.
 */
class JustRegistered
{
    private const SESSION_KEY = 'pko.just_registered';

    public static function flag(): void
    {
        session()->put(self::SESSION_KEY, true);
    }

    public static function isFlagged(Request $request): bool
    {
        return $request->hasSession() && $request->session()->get(self::SESSION_KEY) === true;
    }

    /**
     * Variante sans Request : lit directement la session courante (comme
     * [[ProAccess::isImpersonating()]]). Utilisée par ProAccess::denialReason()
     * qui n'a pas de Request sous la main. C'est ce flag qui accorde à un compte
     * fraîchement inscrit un accès complet le temps de sa session — sans quoi
     * l'auto-login ne produit qu'une « semi-connexion » (nom affiché, aucune page
     * accessible).
     */
    public static function isActive(): bool
    {
        return session()->get(self::SESSION_KEY) === true;
    }

    /**
     * Retire le flag. À appeler à la déconnexion : la session n'étant pas
     * invalidée (choix anti-419, cf. LogoutController), le flag y survivrait
     * sinon et un compte pending pourrait se reconnecter dans la même session
     * en étant accepté à tort. « Le flag meurt avec la session » doit donc être
     * rendu explicite.
     */
    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
