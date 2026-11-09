<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Support;

use ZipArchive;

/**
 * Détection du type réel d'un fichier par sa signature binaire (magic bytes).
 *
 * L'extension et le MIME déclarés par le navigateur sont contrôlés par
 * l'expéditeur : un exécutable renommé « plan.pdf » passe une règle `mimes:`.
 * On lit donc les premiers octets et on n'accepte que les formats attendus :
 * photos (JPEG, PNG, WebP), PDF, plans DWG, listes CSV/XLSX.
 *
 * Le type détecté doit en plus correspondre à l'extension du nom : un PDF
 * renommé « .xlsx » est refusé, l'équipe ne doit pas ouvrir un fichier avec un
 * logiciel inattendu.
 */
final class FileSignature
{
    /** @var array<string, array{mime: string, extensions: array<int, string>}> */
    public const TYPES = [
        'jpg' => ['mime' => 'image/jpeg', 'extensions' => ['jpg', 'jpeg']],
        'png' => ['mime' => 'image/png', 'extensions' => ['png']],
        'webp' => ['mime' => 'image/webp', 'extensions' => ['webp']],
        'pdf' => ['mime' => 'application/pdf', 'extensions' => ['pdf']],
        'dwg' => ['mime' => 'image/vnd.dwg', 'extensions' => ['dwg']],
        'xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extensions' => ['xlsx']],
        'csv' => ['mime' => 'text/csv', 'extensions' => ['csv']],
    ];

    /** Extensions acceptées, pour l'attribut `accept` du champ fichier. */
    public static function acceptAttribute(): string
    {
        $extensions = [];
        foreach (self::TYPES as $type) {
            foreach ($type['extensions'] as $extension) {
                $extensions[] = '.'.$extension;
            }
        }

        return implode(',', $extensions);
    }

    /**
     * Type détecté (clé de TYPES) si le contenu ET l'extension concordent,
     * sinon null.
     */
    public static function detect(string $path, string $originalName): ?string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $type = self::sniff($path);

        if ($type === null || ! in_array($extension, self::TYPES[$type]['extensions'], true)) {
            return null;
        }

        return $type;
    }

    public static function mime(string $type): string
    {
        return self::TYPES[$type]['mime'];
    }

    private static function sniff(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = (string) fread($handle, 16);
        fclose($handle);

        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            str_starts_with($head, '%PDF-') => 'pdf',
            (bool) preg_match('/^AC10\d\d/', $head) => 'dwg',
            str_starts_with($head, "PK\x03\x04") => self::isXlsx($path) ? 'xlsx' : null,
            default => self::isCsv($path) ? 'csv' : null,
        };
    }

    /** Un XLSX est une archive ZIP qui contient un classeur Office Open XML. */
    private static function isXlsx(string $path): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $isWorkbook = $zip->locateName('[Content_Types].xml') !== false
            && $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();

        return $isWorkbook;
    }

    /**
     * Pas de signature pour un CSV : on exige du texte (aucun octet nul, aucun
     * caractère de contrôle hors tabulation / fins de ligne) sur le premier
     * bloc lu.
     */
    private static function isCsv(string $path): bool
    {
        $sample = (string) @file_get_contents($path, false, null, 0, 8192);
        if ($sample === '') {
            return false;
        }

        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample) !== 1;
    }
}
