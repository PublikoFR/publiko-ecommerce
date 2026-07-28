<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Observers;

use Illuminate\Support\Str;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Support\DefaultCustomerGroup;

/**
 * Garantit l'invariant « handle de groupe client = slug » à l'écriture, quel que
 * soit le chemin (admin Filament, seeder, import, tinker).
 *
 * Le champ `handle` de la resource Lunar est un TextInput libre : un groupe créé
 * depuis l'admin en recopiant son nom obtient un handle « Nouveau client » qui
 * ne matche plus le `nouveau-client` attendu par l'inscription et par le
 * contrôle d'accès pro. L'observer normalise (et dé-duplique) le handle, et
 * verrouille celui du groupe par défaut pour qu'il ne puisse plus dériver.
 */
class CustomerGroupHandleObserver
{
    public function saving(CustomerGroup $group): void
    {
        $source = (string) ($group->handle ?? '');
        if (trim($source) === '') {
            $source = (string) ($group->name ?? '');
        }

        $slug = Str::slug($source);
        if ($slug === '') {
            // Rien d'exploitable (nom exclusivement non-latin, par ex.) : on
            // laisse la valeur d'origine et la validation du formulaire décider.
            return;
        }

        // Le groupe par défaut ne change jamais de handle : l'inscription et
        // ProAccess s'appuient dessus. Un ancien handle non slugifié
        // (« Nouveau client ») est donc réaligné à la première sauvegarde.
        if ($group->exists) {
            $original = Str::slug((string) $group->getOriginal('handle'));
            if ($original === Str::slug(DefaultCustomerGroup::handle())) {
                $group->handle = DefaultCustomerGroup::handle();

                return;
            }
        }

        $group->handle = $this->uniqueHandle($slug, $group);
    }

    /**
     * Deux noms distincts peuvent produire le même slug (apostrophes, accents).
     * La colonne `handle` étant unique, on suffixe plutôt que de laisser
     * remonter une violation de contrainte à l'admin.
     */
    private function uniqueHandle(string $slug, CustomerGroup $group): string
    {
        $candidate = $slug;
        $suffix = 1;

        while (CustomerGroup::where('handle', $candidate)
            ->when($group->exists, fn ($query) => $query->whereKeyNot($group->getKey()))
            ->exists()
        ) {
            $suffix++;
            $candidate = $slug.'-'.$suffix;
        }

        return $candidate;
    }
}
