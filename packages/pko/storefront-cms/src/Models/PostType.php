<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property string $handle
 * @property string $url_segment
 * @property string|null $layout
 * @property string|null $icon
 * @property int $sort_order
 */
class PostType extends Model
{
    protected $table = 'pko_post_types';

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    /**
     * Segments réservés interdits en url_segment (collision avec routes explicites).
     */
    public static function reservedUrlSegments(): array
    {
        return [
            'admin', 'api',
            'produit', 'produits',
            'collection', 'collections',
            'marque', 'marques',
            'panier', 'cart',
            'recherche', 'search',
            'newsletter',
            'livraison', 'shipping',
            'checkout',
            'login', 'logout', 'register', 'account', 'mon-compte',
            'actualites',
            'pages',
            'listes-d-achat', 'purchase-lists',
            'commande-rapide', 'quick-order',
            'magasins', 'stores',
        ];
    }
}
