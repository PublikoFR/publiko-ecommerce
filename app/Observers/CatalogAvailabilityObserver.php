<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\CatalogAvailability;
use Illuminate\Database\Eloquent\Model;

/**
 * Empêche Lunar de semer des lignes de visibilité par groupe client à la création
 * d'une collection ou d'un produit (cf. CatalogAvailability pour le pourquoi).
 *
 * ⚠️ CONTRAINTE D'ORDRE — ne pas déplacer l'enregistrement à la légère.
 *
 * `HasCustomerGroups::bootHasCustomerGroups()` branche son propre listener
 * `created` au boot du modèle. Le nôtre doit passer APRÈS, sinon le sync du trait
 * réinsère les lignes qu'on vient d'effacer et le correctif est un no-op
 * silencieux. `Model::observe()` instancie le modèle (`new static`), ce qui force
 * `bootIfNotBooted()` et donc l'enregistrement du listener du trait avant le
 * nôtre — l'ordre est garanti tant qu'on passe par `observe()` et non par
 * `Model::created()`, qui lui ne boote pas le modèle.
 *
 * Couvert par CatalogAvailabilityTest::test_creating_a_collection_seeds_no_rows().
 *
 * Sous-classé plutôt que paramétré : Laravel résout les observers via le
 * conteneur, un constructeur à paramètre primitif lève une BindingResolutionException.
 */
abstract class CatalogAvailabilityObserver
{
    abstract protected function table(): string;

    public function created(Model $model): void
    {
        CatalogAvailability::forget($this->table(), (int) $model->getKey());
    }
}
