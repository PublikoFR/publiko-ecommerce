<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Enums;

/**
 * Les trois parcours du formulaire de devis. Un seul formulaire, des textes et
 * des champs mis en avant différents selon le mode.
 */
enum QuoteRequestMode: string
{
    case General = 'general';
    case Project = 'project';
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
            self::Project => 'Devis pour un projet',
            self::PartPhoto => 'Identification d\'une pièce',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General => 'messages-square',
            self::Project => 'clipboard-pen',
            self::PartPhoto => 'camera',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::General => 'Demander un devis',
            self::Project => 'Présentez-nous votre projet',
            self::PartPhoto => 'Identifier une pièce',
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::General => 'Indiquez les références ou les produits recherchés et les quantités : notre équipe vous répond avec une proposition adaptée.',
            self::Project => 'Chantier, rénovation, installation complète : décrivez votre besoin et joignez vos plans ou listes de matériel, nous étudions la meilleure solution.',
            self::PartPhoto => 'Vous ne connaissez pas la référence ? Envoyez-nous des photos de la pièce, de son étiquette et de son environnement : nous l\'identifions pour vous.',
        };
    }

    public function messageLabel(): string
    {
        return match ($this) {
            self::General => 'Votre demande',
            self::Project => 'Description du projet',
            self::PartPhoto => 'Description de la pièce',
        };
    }

    public function messagePlaceholder(): string
    {
        return match ($this) {
            self::General => 'Précisez votre besoin, vos délais, vos contraintes de livraison…',
            self::Project => 'Type de chantier, dimensions, nombre d\'ouvrants, délais, contraintes…',
            self::PartPhoto => 'Marque et modèle de l\'équipement si connus, inscriptions visibles, symptôme ou panne…',
        };
    }

    /** Les lignes produit sont proposées d'emblée (dépliées) dans ce mode. */
    public function showsLinesByDefault(): bool
    {
        return $this !== self::PartPhoto;
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
            self::Project => 'Plans, photos du chantier, listes de matériel (PDF, images, CSV, XLSX).',
            self::General => 'Facultatif : photos, PDF, listes de références (CSV, XLSX).',
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
