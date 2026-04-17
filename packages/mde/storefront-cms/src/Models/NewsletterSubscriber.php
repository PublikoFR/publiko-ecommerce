<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $table = 'mde_newsletter_subscribers';

    protected $fillable = ['email', 'ip', 'consent_at', 'unsubscribed_at'];

    protected $casts = ['consent_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
}
