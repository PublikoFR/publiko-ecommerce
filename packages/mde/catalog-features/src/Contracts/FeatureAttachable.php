<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Optional contract for models (other than Product) that want to accept
 * feature value attachments through the FeatureManager.
 *
 * A conforming model must expose a `featureValues()` BelongsToMany relation
 * that points at mde_feature_values via an appropriate pivot table.
 */
interface FeatureAttachable
{
    public function featureValues(): BelongsToMany;
}
