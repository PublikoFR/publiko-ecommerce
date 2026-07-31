<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Pko\ShippingCommon\Models\CarrierShipment;
use RuntimeException;
use ZipArchive;

/**
 * Regroupe les étiquettes PDF de plusieurs envois dans une archive ZIP.
 *
 * Le pendant PrestaShop imprime un PDF unique (concaténation FPDI). Une archive
 * évite d'ajouter une dépendance de fusion PDF pour un besoin d'impression que
 * tous les navigateurs et lecteurs traitent aussi bien : chaque étiquette reste
 * un PDF autonome, au format exact renvoyé par le transporteur.
 */
final class LabelArchive
{
    /**
     * @param  Collection<int, CarrierShipment>  $shipments
     * @return string Chemin absolu de l'archive créée (à supprimer après envoi).
     *
     * @throws RuntimeException si aucune étiquette n'est disponible.
     */
    public static function build(Collection $shipments, string $filename): string
    {
        $disk = Storage::disk('local');

        $available = $shipments->filter(
            fn (CarrierShipment $s): bool => is_string($s->label_path)
                && $s->label_path !== ''
                && $disk->exists($s->label_path)
        );

        if ($available->isEmpty()) {
            throw new RuntimeException('Aucune étiquette disponible pour la sélection.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'labels_').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive {$filename}.");
        }

        foreach ($available as $shipment) {
            $zip->addFromString(
                sprintf(
                    'commande-%d-%s-%s.pdf',
                    (int) $shipment->order_id,
                    (string) $shipment->carrier,
                    (string) ($shipment->tracking_number ?: $shipment->id),
                ),
                (string) $disk->get($shipment->label_path),
            );
        }

        $zip->close();

        return $tmpPath;
    }
}
