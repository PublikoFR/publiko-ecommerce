<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Models\QuoteRequestFile;

/**
 * Répartit les pièces jointes d'une demande entre celles qu'on attache au mail
 * équipe et celles qu'on laisse à télécharger dans l'admin.
 *
 * Une demande peut peser jusqu'à 80 Mo (8 × 10 Mo) : aucune messagerie ne
 * l'accepte. On joint donc les fichiers dans l'ordre d'envoi tant que le
 * cumul reste sous `attachments.mail_max_total_kb` (15 Mo par défaut, soit
 * ~20 Mo une fois encodés en base64, sous la limite courante de 25 Mo) ; un
 * fichier qui ferait dépasser le plafond est sauté, un plus petit derrière
 * peut encore passer. Un fichier absent du disque n'est jamais joint.
 */
final class MailAttachmentSelection
{
    /**
     * @param  Collection<int, QuoteRequestFile>  $attached
     * @param  Collection<int, QuoteRequestFile>  $notAttached
     */
    private function __construct(
        public readonly Collection $attached,
        public readonly Collection $notAttached,
    ) {}

    public static function for(QuoteRequest $request): self
    {
        $request->loadMissing('files');

        $budget = max(0, (int) config('quote-requests.attachments.mail_max_total_kb', 15360)) * 1024;
        $attached = collect();
        $notAttached = collect();
        $used = 0;

        foreach ($request->files->sortBy('id') as $file) {
            $fits = $used + $file->size <= $budget;

            if ($fits && Storage::disk($file->disk)->exists($file->path)) {
                $attached->push($file);
                $used += $file->size;
            } else {
                $notAttached->push($file);
            }
        }

        return new self($attached->values(), $notAttached->values());
    }

    /**
     * Libellé du placeholder `:attachments` : chaque nom suivi de son sort, pour
     * que l'équipe sache d'un coup d'œil ce qui reste à récupérer dans l'admin.
     */
    public function summary(): string
    {
        if ($this->attached->isEmpty() && $this->notAttached->isEmpty()) {
            return 'aucune';
        }

        $parts = $this->attached
            ->map(fn (QuoteRequestFile $f): string => $f->original_name.' (joint à ce mail)')
            ->merge($this->notAttached->map(
                fn (QuoteRequestFile $f): string => $f->original_name.' ('.$f->humanSize().', à télécharger dans l\'admin)'
            ));

        return $parts->implode(', ');
    }
}
