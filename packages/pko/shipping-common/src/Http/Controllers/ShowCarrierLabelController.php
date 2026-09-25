<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Pko\ShippingCommon\Models\CarrierShipment;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert l'étiquette PDF d'un envoi, affichée dans le navigateur (impression directe).
 *
 * Même garde que les PDF Pennylane : session staff (middleware de la route) ET
 * signature temporaire, vérifiée ici. Le fichier vit sur le disque privé `local`,
 * jamais exposé publiquement.
 */
final class ShowCarrierLabelController
{
    public function __invoke(Request $request, int $shipment): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $record = CarrierShipment::query()->findOrFail($shipment);
        $path = (string) $record->label_path;

        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        $body = (string) Storage::disk('local')->get($path);
        $filename = basename($path);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Length' => (string) strlen($body),
        ]);
    }
}
