<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Pièce jointe d'une demande de devis, stockée sur un disque PRIVÉ.
 *
 * Volontairement hors de `pko/lunar-media-core` : la médiathèque sert ses
 * fichiers publiquement (disque public, URL directe), alors qu'une photo ou un
 * plan envoyé par un client ne doit être lisible que par l'équipe.
 */
class QuoteRequestFile extends Model
{
    protected $table = 'pko_quote_request_files';

    protected $guarded = ['id'];

    protected $casts = [
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        // Le fichier disque suit la ligne : pas d'orphelin privé qui traîne.
        static::deleted(function (self $file): void {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    /** @return BelongsTo<QuoteRequest, $this> */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        $size = $this->size;

        return $size >= 1048576
            ? number_format($size / 1048576, 1, ',', ' ').' Mo'
            : max(1, (int) round($size / 1024)).' Ko';
    }

    public function downloadUrl(): string
    {
        return route('quote-requests.files.download', $this);
    }
}
