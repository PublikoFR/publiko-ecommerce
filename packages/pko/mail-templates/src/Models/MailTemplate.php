<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $locale
 * @property string $subject
 * @property array<int, array<string, mixed>> $blocks
 * @property bool $enabled
 */
class MailTemplate extends Model
{
    protected $table = 'pko_mail_templates';

    protected $fillable = [
        'key',
        'locale',
        'subject',
        'blocks',
        'enabled',
    ];

    protected $casts = [
        'blocks' => 'array',
        'enabled' => 'boolean',
    ];
}
