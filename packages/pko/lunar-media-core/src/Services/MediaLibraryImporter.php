<?php

declare(strict_types=1);

namespace Pko\LunarMediaCore\Services;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

/**
 * Importe un fichier distant (URL) dans la médiathèque custom (média Spatie
 * possédé par un `Folder`, façon WordPress) puis le rend disponible pour être
 * lié à n'importe quelle entité via `pko_mediables` (média-core) ou une table
 * dédiée (ex. `pko_product_documents`).
 *
 * Déduplication à deux niveaux, stockée dans `custom_properties` :
 *  1. `source_url` — évite un re-téléchargement quand la même URL réapparaît.
 *  2. `sha1` — évite un doublon binaire quand la même image est servie sous des
 *     URLs différentes (calculé après download).
 *
 * Réutilisé par l'import produit (ai-importer) ET l'import URL manuel de la
 * médiathèque (`PkoMediaLibrary::importFromUrl`).
 */
class MediaLibraryImporter
{
    /** Extensions image acceptées par défaut. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

    /** Extensions document acceptées (notices, brochures…). */
    public const DOCUMENT_EXTENSIONS = ['pdf'];

    /**
     * Importe une URL dans un dossier (par `collection`) avec déduplication.
     * Retourne le `Media` (existant ou créé), ou null si échec / URL invalide.
     *
     * @param  array<int, string>  $allowedExtensions
     */
    public function importFromUrl(
        string $url,
        string $folderCollection = 'products',
        ?string $name = null,
        array $allowedExtensions = self::IMAGE_EXTENSIONS,
    ): ?Media {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $this->importIntoFolder($this->resolveFolder($folderCollection), $url, $name, $allowedExtensions);
    }

    /**
     * Variante ciblant un `Folder` déjà résolu (ex. dossier courant de la
     * médiathèque). Même déduplication.
     *
     * @param  array<int, string>  $allowedExtensions
     */
    public function importIntoFolder(
        Folder $folder,
        string $url,
        ?string $name = null,
        array $allowedExtensions = self::IMAGE_EXTENSIONS,
    ): ?Media {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        // Dédup 1 : URL source déjà importée → réutilise sans re-télécharger.
        if ($hit = $this->findBySourceUrl($url)) {
            return $hit;
        }

        $binary = $this->download($url);
        if ($binary === null) {
            return null;
        }

        // Dédup 2 : même binaire déjà présent (autre URL) → réutilise.
        $sha1 = sha1($binary);
        if ($hit = $this->findBySha1($sha1)) {
            return $hit;
        }

        $ext = $this->resolveExtension($url, $binary, $allowedExtensions);
        if ($ext === null) {
            return null;
        }

        $base = $this->baseName($url, $name);

        $tmp = tempnam(sys_get_temp_dir(), 'pko-media-');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $binary);

        try {
            return $folder->addMedia($tmp)
                ->usingName($name !== null && trim($name) !== '' ? trim($name) : $base)
                ->usingFileName($base.'.'.$ext)
                ->withCustomProperties(['source_url' => $url, 'sha1' => $sha1])
                ->toMediaCollection((string) ($folder->collection ?: 'default'));
        } catch (\Throwable $e) {
            report($e);

            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Résout (ou crée) le dossier bibliothèque par slug de `collection`.
     */
    public function resolveFolder(string $collection, ?string $name = null): Folder
    {
        $collection = Str::slug($collection) ?: 'default';

        return Folder::query()->firstOrCreate(
            ['collection' => $collection],
            ['name' => $name ?? Str::title(str_replace('-', ' ', $collection))],
        );
    }

    private function findBySourceUrl(string $url): ?Media
    {
        return Media::query()->where('custom_properties->source_url', $url)->first();
    }

    private function findBySha1(string $sha1): ?Media
    {
        return Media::query()->where('custom_properties->sha1', $sha1)->first();
    }

    private function download(string $url): ?string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 20, 'user_agent' => 'pko-media-importer/1.0', 'follow_location' => 1],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $binary = @file_get_contents($url, false, $context);
        if ($binary === false || strlen($binary) < 8) {
            return null;
        }

        return $binary;
    }

    /**
     * Détermine l'extension : d'abord depuis l'URL si autorisée, sinon depuis la
     * signature binaire (magic bytes).
     *
     * @param  array<int, string>  $allowed
     */
    private function resolveExtension(string $url, string $binary, array $allowed): ?string
    {
        $extFromUrl = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if ($extFromUrl !== '' && in_array($extFromUrl, $allowed, true)) {
            return $extFromUrl;
        }

        $sig = substr($binary, 0, 12);
        $detected = match (true) {
            str_starts_with($sig, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($sig, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($sig, 'GIF8') => 'gif',
            str_contains($sig, 'WEBP') => 'webp',
            str_starts_with($sig, '%PDF') => 'pdf',
            str_contains($sig, '<svg') || str_contains($sig, '<?xml') => 'svg',
            default => null,
        };

        return $detected !== null && in_array($detected, $allowed, true) ? $detected : null;
    }

    private function baseName(string $url, ?string $name): string
    {
        if ($name !== null && trim($name) !== '') {
            $slug = Str::slug(pathinfo(trim($name), PATHINFO_FILENAME));
            if ($slug !== '') {
                return $slug;
            }
        }

        $fromUrl = Str::slug(pathinfo(parse_url($url, PHP_URL_PATH) ?? 'media', PATHINFO_FILENAME));

        return $fromUrl !== '' ? $fromUrl : 'media';
    }
}
