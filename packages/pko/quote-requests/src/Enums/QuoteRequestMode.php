<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Enums;

/**
 * Type d'une demande reçue par la page admin « Contacts ».
 *
 * General et PartPhoto sont les deux parcours du formulaire de devis (un seul
 * formulaire, textes et champs mis en avant différents). Contact est le
 * formulaire de contact classique (/contact) : enregistré dans la même table
 * pour être suivi au même endroit, mais jamais proposé par le formulaire de
 * devis (cf. formModes() / fromQuery()).
 *
 * L'ancien mode « projet » a été fusionné dans la demande générale (même
 * formulaire, aucun champ propre) : `?mode=project` retombe sur General.
 */
enum QuoteRequestMode: string
{
    case General = 'general';
    case PartPhoto = 'part_photo';
    case Contact = 'contact';

    /** Mode du formulaire de devis lu dans l'URL ; Contact n'en fait pas partie. */
    public static function fromQuery(mixed $value): self
    {
        $mode = is_string($value) ? self::tryFrom($value) : null;

        return $mode !== null && $mode->isQuoteForm() ? $mode : self::General;
    }

    /**
     * Onglets du formulaire de devis.
     *
     * @return array<int, self>
     */
    public static function formModes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $mode): bool => $mode->isQuoteForm()));
    }

    public function isQuoteForm(): bool
    {
        return $this !== self::Contact;
    }

    /** Numéro lisible d'une demande de ce type : « DEV-000042 », « MSG-000043 ». */
    public function numberPrefix(): string
    {
        return $this === self::Contact ? 'MSG' : 'DEV';
    }

    /** Libellé court (onglet, admin, e-mail). */
    public function label(): string
    {
        return match ($this) {
            self::General => 'Demande de devis',
            self::PartPhoto => 'Identification d\'une pièce',
            self::Contact => 'Message de contact',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General => 'messages-square',
            self::PartPhoto => 'camera',
            self::Contact => 'mail',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::General => 'Demander un devis',
            self::PartPhoto => 'Identifier une pièce',
            self::Contact => 'Nous contacter',
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::General => 'Produits, références, chantier ou installation complète : décrivez votre besoin et joignez vos plans, photos ou listes de matériel, notre équipe vous répond avec une proposition adaptée.',
            self::PartPhoto => 'Vous ne connaissez pas la référence ? Envoyez-nous des photos de la pièce, de son étiquette et de son environnement : nous l\'identifions pour vous.',
            self::Contact => 'Une question, une remarque ? Écrivez-nous, notre équipe vous répond rapidement.',
        };
    }

    public function messageLabel(): string
    {
        return match ($this) {
            self::General => 'Votre demande',
            self::PartPhoto => 'Description de la pièce',
            self::Contact => 'Votre message',
        };
    }

    public function messagePlaceholder(): string
    {
        return match ($this) {
            self::General => 'Produits ou références recherchés, quantités, type de chantier, délais, contraintes de livraison…',
            self::PartPhoto => 'Marque et modèle de l\'équipement si connus, inscriptions visibles, symptôme ou panne…',
            self::Contact => 'Votre message…',
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
            self::Contact => '',
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
