<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;
use Pko\StorefrontCms\Concerns\HasMediaAttachments;

class Page extends Model
{
    use HasMediaAttachments;

    protected $table = 'pko_pages';

    protected $fillable = ['slug', 'title', 'body', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
