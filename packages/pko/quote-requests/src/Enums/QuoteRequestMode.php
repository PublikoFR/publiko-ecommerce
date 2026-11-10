<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Enums;

/**
 * Les deux parcours du formulaire de devis. Un seul formulaire, des textes et
 * des champs mis en avant différents selon le mode.
 *
 * L'ancien mode « projet » a été fusionné dans la demande générale (même
 * formulaire, aucun champ propre) : `?mode=project` retombe sur General.
 */
enum QuoteRequestMode: string
{
    case General = 'general';
    case PartPhoto = 'part_photo';

    public static function fromQuery(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::General) : self::General;
    }

    /** Libellé court (onglet, admin, e-mail). */
    public function label(): string
    {
        return match ($this) {
            self::General => 'Demande de devis',
            self::PartPhoto => 'Identification d\'une pièce',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General => 'messages-square',
            self::PartPhoto => 'camera',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::General => 'Demander un devis',
            self::PartPhoto => 'Identifier une pièce',
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::General => 'Produits, références, chantier ou installation complète : décrivez votre besoin et joignez vos plans, photos ou listes de matériel, notre équipe vous répond avec une proposition adaptée.',
            self::PartPhoto => 'Vous ne connaissez pas la référence ? Envoyez-nous des photos de la pièce, de son étiquette et de son environnement : nous l\'identifions pour vous.',
        };
    }

    public function messageLabel(): string
    {
        return match ($this) {
            self::General => 'Votre demande',
            self::PartPhoto => 'Description de la pièce',
        };
    }

    public function messagePlaceholder(): string
    {
        return match ($this) {
            self::General => 'Produits ou références recherchés, quantités, type de chantier, délais, contraintes de livraison…',
            self::PartPhoto => 'Marque et modèle de l\'équipement si connus, inscriptions visibles, symptôme ou panne…',
        };
    }

    /** Au moins une photo est attendue dans ce mode. */
    public function requiresAttachment(): bool
    {
        return $this === self::PartPhoto;
    }

    public function attachmentsHint(): string
    {
        return match ($this) {
            self::PartPhoto => 'Au moins une photo : vue d\'ensemble, étiquette / plaque signalétique, détail des fixations.',
            self::General => 'Facultatif : plans, photos, listes de matériel (PDF, images, CSV, XLSX).',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
