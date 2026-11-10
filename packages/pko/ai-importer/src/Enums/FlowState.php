<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

/**
 * Où en est un import, vu de l'utilisateur : UN état lisible dérivé des deux
 * statuts techniques (`status` de la préparation, `import_status` de
 * l'écriture catalogue). Pilote l'écran d'un import : étape du fil d'Ariane,
 * message, action principale proposée.
 *
 * Les 4 étapes : 1 Préparation → 2 Vérification → 3 Import → 4 Terminé.
 */
enum FlowState: string
{
    case Waiting = 'waiting';
    case Preparing = 'preparing';
    case PrepareFailed = 'prepare_failed';
    case Cancelled = 'cancelled';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Importing = 'importing';
    case ImportFailed = 'import_failed';
    case Done = 'done';
    case RolledBack = 'rolled_back';

    public static function of(JobStatus $status, ImportStatus $import): self
    {
        return match ($status) {
            JobStatus::Pending => self::Waiting,
            JobStatus::Parsing => self::Preparing,
            JobStatus::Paused, JobStatus::Error => self::PrepareFailed,
            JobStatus::Cancelled => self::Cancelled,
            JobStatus::Parsed => match ($import) {
                ImportStatus::Pending => self::Review,
                ImportStatus::Scheduled => self::Scheduled,
                ImportStatus::Queued, ImportStatus::Importing => self::Importing,
                ImportStatus::Error => self::ImportFailed,
                ImportStatus::Imported => self::Done,
                ImportStatus::RolledBack => self::RolledBack,
            },
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Préparation en file d\'attente',
            self::Preparing => 'Préparation en cours',
            self::PrepareFailed => 'Préparation interrompue',
            self::Cancelled => 'Annulé',
            self::Review => 'À vérifier',
            self::Scheduled => 'Import programmé',
            self::Importing => 'Import en cours',
            self::ImportFailed => 'Import interrompu',
            self::Done => 'Terminé',
            self::RolledBack => 'Import annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting, self::Cancelled => 'gray',
            self::Preparing, self::Scheduled, self::Importing => 'info',
            self::Review, self::RolledBack => 'warning',
            self::PrepareFailed, self::ImportFailed => 'danger',
            self::Done => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Waiting, self::Scheduled => 'heroicon-o-clock',
            self::Preparing, self::Importing => 'heroicon-o-arrow-path',
            self::PrepareFailed, self::ImportFailed => 'heroicon-o-exclamation-triangle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Review => 'heroicon-o-eye',
            self::Done => 'heroicon-o-check-circle',
            self::RolledBack => 'heroicon-o-arrow-uturn-left',
        };
    }

    /** Étape courante du parcours (1 à 4). */
    public function step(): int
    {
        return match ($this) {
            self::Waiting, self::Preparing, self::PrepareFailed, self::Cancelled => 1,
            self::Review, self::RolledBack => 2,
            self::Scheduled, self::Importing, self::ImportFailed => 3,
            self::Done => 4,
        };
    }

    /** Un traitement tourne ou va démarrer seul : l'écran se rafraîchit. */
    public function isRunning(): bool
    {
        return in_array($this, [self::Waiting, self::Preparing, self::Scheduled, self::Importing], true);
    }

    public function isFailure(): bool
    {
        return in_array($this, [self::PrepareFailed, self::ImportFailed], true);
    }

    /** Les lignes préparées peuvent-elles encore être corrigées ? */
    public function allowsStagingEdits(): bool
    {
        return in_array($this, [self::Review, self::Scheduled, self::ImportFailed, self::Done, self::RolledBack, self::PrepareFailed, self::Cancelled], true);
    }

    /**
     * Libellés des 4 étapes du parcours.
     *
     * @return array<int, string>
     */
    public static function steps(): array
    {
        return [1 => 'Préparation', 2 => 'Vérification', 3 => 'Import', 4 => 'Terminé'];
    }
}
