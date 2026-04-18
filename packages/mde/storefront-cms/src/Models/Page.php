<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;
use Mde\StorefrontCms\Concerns\HasMediaAttachments;

class Page extends Model
{
    use HasMediaAttachments;

    protected $table = 'mde_pages';

    protected $fillable = ['slug', 'title', 'body', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
