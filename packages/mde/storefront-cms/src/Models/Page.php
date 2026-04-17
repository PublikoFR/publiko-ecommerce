<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $table = 'mde_pages';

    protected $fillable = ['slug', 'title', 'body', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
