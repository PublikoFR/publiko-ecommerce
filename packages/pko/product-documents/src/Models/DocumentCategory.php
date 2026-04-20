<?php

declare(strict_types=1);

namespace Pko\ProductDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property string $handle
 * @property int $sort_order
 */
class DocumentCategory extends Model
{
    protected $table = 'pko_document_categories';

    protected $guarded = [];

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class, 'category_id');
    }
}
