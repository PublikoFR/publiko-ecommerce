<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Pko\MailTemplates\Support\ContentGuard;
use Pko\MailTemplates\Support\ContentGuardException;

/**
 * @property string $key
 * @property string $locale
 * @property string $subject
 * @property array{heading: string, sections: array<int, array<string, mixed>>} $content
 * @property bool $enabled
 */
class MailTemplate extends Model
{
    protected $table = 'pko_mail_templates';

    protected $fillable = [
        'key',
        'locale',
        'subject',
        'content',
        'enabled',
    ];

    protected $casts = [
        'content' => 'array',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Filet de sécurité valable pour TOUS les chemins d'écriture (éditeur
        // Filament, éditeur PageBuilder, seeder, tinker) : ContentGuard ne doit
        // jamais pouvoir être contourné, contrairement à une validation qui ne
        // vivrait que dans une page Filament.
        static::saving(function (self $template): void {
            $error = ContentGuard::check(
                $template->key,
                $template->subject,
                $template->content ?? ['heading' => '', 'sections' => []],
                $template->enabled,
            );

            if ($error !== null) {
                throw new ContentGuardException($error);
            }
        });
    }
}
