<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Extensions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lunar\Admin\Support\Extending\EditPageExtension;
use Pko\CustomerAuth\Sirene\SireneClient;

/**
 * Rend le SIRET éditable depuis la fiche client (back-office) : une entreprise
 * peut changer de SIRET. À l'enregistrement, si le SIRET a changé, on relance la
 * vérification INSEE et on rafraîchit les données dérivées (NAF, adresse, statut).
 *
 * Le SIRET vit dans `meta['siret']` (JSON) — le champ de formulaire `siret` est
 * virtuel : chargé depuis meta en beforeFill, réécrit dans meta en beforeUpdate
 * (fusion, pour ne pas écraser les autres clés meta : activity, phone, adresse…).
 */
class CustomerSiretExtension extends EditPageExtension
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeFill(array $data): array
    {
        $data['siret'] = $data['meta']['siret'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeUpdate(array $data, Model $record): array
    {
        // Champ virtuel : jamais persisté tel quel (pas de colonne `siret`).
        $siret = preg_replace('/\D/', '', (string) ($data['siret'] ?? '')) ?: null;
        unset($data['siret']);

        $meta = (array) ($record->meta ?? []);
        $current = $meta['siret'] ?? null;

        // SIRET inchangé (ou vidé) → rien à revérifier.
        if ($siret === null || $siret === $current) {
            return $data;
        }

        if (! SireneClient::validateSiret($siret)) {
            throw ValidationException::withMessages([
                'data.siret' => 'SIRET invalide : 14 chiffres attendus (clé de contrôle incorrecte).',
            ]);
        }

        $result = app(SireneClient::class)->verify($siret);
        $meta['siret'] = $siret;

        if ($result->isActive()) {
            $meta['naf_code'] = $result->nafCode;
            $meta['sirene_address'] = [
                'line_1' => $result->addressLine1,
                'postcode' => $result->postcode,
                'city' => $result->city,
            ];
            $data['naf_code'] = $result->nafCode;
            $data['sirene_status'] = 'active';
            $data['sirene_verified_at'] = now();
        } elseif ($result->isInactive()) {
            $data['sirene_status'] = 'inactive';
            $data['sirene_verified_at'] = null;
        }
        // Status::Pending (API indisponible) : on garde le nouveau SIRET, statut inchangé.

        $data['meta'] = $meta;

        return $data;
    }
}
