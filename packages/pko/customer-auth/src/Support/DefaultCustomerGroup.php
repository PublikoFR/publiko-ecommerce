<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use Illuminate\Support\Str;
use Lunar\Models\CustomerGroup;

/**
 * Résolution du groupe client « par défaut » (« Nouveau client »), rattaché à
 * toute inscription professionnelle et exigé par le contrôle d'accès pro.
 *
 * Historiquement le lookup se faisait par un `where('handle', 'nouveau-client')`
 * strict, dupliqué en trois endroits. Un groupe créé à la main depuis l'admin
 * Filament (dont le champ `handle` est un texte libre) reçoit un handle non
 * slugifié — « Nouveau client » — qui ne matche alors plus rien : l'inscription
 * n'attache aucun groupe et l'accès pro est refusé à tout le monde, sans erreur
 * visible. C'est arrivé en environnement de dev (cf. docs/packages/customer-auth.md).
 *
 * Le lookup passe donc désormais par cette classe, qui retombe sur une
 * comparaison slugifiée du handle puis du nom. L'invariant est en outre garanti
 * à l'écriture par CustomerGroupHandleObserver, qui slugifie tout handle
 * enregistré et verrouille celui du groupe par défaut.
 */
final class DefaultCustomerGroup
{
    /**
     * Handle attendu pour le groupe par défaut (configurable par env).
     */
    public static function handle(): string
    {
        return (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');
    }

    /**
     * Le groupe par défaut, ou null s'il est réellement absent de la base.
     *
     * Trois passes, de la plus stricte à la plus permissive : handle exact,
     * handle slugifié, nom slugifié. Aucune écriture — la normalisation reste
     * du ressort de l'observer.
     */
    public static function resolve(): ?CustomerGroup
    {
        $handle = self::handle();

        $exact = CustomerGroup::where('handle', $handle)->first();
        if ($exact !== null) {
            return $exact;
        }

        $slug = Str::slug($handle);

        return CustomerGroup::get()
            ->first(fn (CustomerGroup $group) => Str::slug((string) $group->handle) === $slug
                || Str::slug((string) $group->name) === $slug);
    }

    /**
     * Ce groupe est-il le groupe par défaut ? (comparaison tolérante identique)
     */
    public static function matches(CustomerGroup $group): bool
    {
        $default = self::resolve();

        return $default !== null && (int) $default->id === (int) $group->id;
    }
}
