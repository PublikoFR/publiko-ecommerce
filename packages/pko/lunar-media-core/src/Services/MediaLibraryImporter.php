<?php

declare(strict_types=1);

namespace Pko\LunarMediaCore\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
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
 *  1. URL — `source_url` (URL d'origine) + `source_keys` (clés normalisées :
 *     URL principale ET alias). Évite un re-téléchargement quand la même URL
 *     réapparaît, y compris sous une variante (http/https, casse du domaine,
 *     ancre, port par défaut).
 *  2. `sha1` — évite un doublon binaire quand le même fichier est servi sous
 *     des URLs différentes (calculé après download). L'URL est alors mémorisée
 *     comme alias du média existant : les imports suivants ne la retéléchargent
 *     plus. Un média déposé à la main (sans `sha1`) est rapproché par sa taille
 *     puis son empreinte, calculée et mémorisée à la volée.
 *
 * Seuls les médias de la BIBLIOTHÈQUE (possédés par un `Folder`) sont
 * réutilisés : un média Spatie possédé par une autre entité (image native d'une
 * catégorie, d'un produit) disparaît avec elle et ne doit pas être partagé.
 *
 * Mémo par instance : une URL déjà résolue (ou déjà en échec) pendant la vie de
 * l'objet n'est ni recherchée ni retéléchargée. Garder UNE instance par import.
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

    /** Nombre max de médias sans empreinte comparés par taille (dédup des dépôts manuels). */
    private const SHA1_BACKFILL_LIMIT = 20;

    /** @var array<string, int> clé d'URL normalisée → id du média résolu. */
    private array $resolved = [];

    /** @var array<string, string> clé d'URL normalisée → motif de l'échec. */
    private array $failed = [];

    private ?string $lastError = null;

    /**
     * Motif du dernier échec d'import (`null` après un succès) : HTTP 404, type de
     * fichier refusé, fichier trop volumineux… À journaliser par l'appelant.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Clé de déduplication d'une URL : sans schéma (http = https), domaine en
     * minuscules, sans `www.`, sans port par défaut ni ancre. Le chemin et la
     * query string sont conservés tels quels (ils désignent le fichier).
     */
    public static function urlKey(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower($parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        $port = isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $host.$port.$path.$query;
    }

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
        $this->lastError = null;
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return $this->fail(null, 'URL invalide');
        }

        $key = self::urlKey($url);

        // Mémo d'instance : même URL déjà traitée pendant cet import.
        if (isset($this->failed[$key])) {
            return $this->fail(null, $this->failed[$key]);
        }
        if (isset($this->resolved[$key]) && ($hit = Media::query()->find($this->resolved[$key])) !== null) {
            return $hit;
        }

        // Dédup 1 : URL déjà importée (principale ou alias) → pas de téléchargement.
        if ($hit = $this->findBySourceUrl($url, $key)) {
            return $this->remember($key, $hit);
        }

        $binary = $this->download($url);
        if ($binary === null) {
            return $this->fail($key, $this->lastError ?? 'téléchargement impossible');
        }

        // Dédup 2 : même binaire déjà présent (autre URL, ou dépôt manuel) → on le
        // réutilise et on mémorise l'URL comme alias (plus de re-téléchargement).
        $sha1 = sha1($binary);
        if ($hit = $this->findBySha1($sha1, strlen($binary))) {
            $this->addAlias($hit, $key);

            return $this->remember($key, $hit);
        }

        $ext = $this->resolveExtension($url, $binary, $allowedExtensions);
        if ($ext === null) {
            return $this->fail($key, 'type de fichier non autorisé ('.implode(', ', $allowedExtensions).' attendu)');
        }

        $base = $this->baseName($url, $name);

        $tmp = tempnam(sys_get_temp_dir(), 'pko-media-');
        if ($tmp === false) {
            return $this->fail(null, 'fichier temporaire impossible à créer');
        }
        file_put_contents($tmp, $binary);

        try {
            $media = $folder->addMedia($tmp)
                ->usingName($name !== null && trim($name) !== '' ? trim($name) : $base)
                ->usingFileName($base.'.'.$ext)
                ->withCustomProperties(['source_url' => $url, 'source_keys' => [$key], 'sha1' => $sha1])
                ->toMediaCollection((string) ($folder->collection ?: 'default'));

            return $this->remember($key, $media);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail($key, mb_strimwidth($e->getMessage(), 0, 200, '…'));
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

    private function remember(string $key, Media $media): Media
    {
        $this->resolved[$key] = (int) $media->id;
        $this->lastError = null;

        return $media;
    }

    /**
     * @param  string|null  $key  clé à mettre en échec pour le reste de l'import (null = ne pas mémoriser)
     */
    private function fail(?string $key, string $reason): null
    {
        if ($key !== null) {
            $this->failed[$key] = $reason;
        }
        $this->lastError = $reason;

        return null;
    }

    /** Médias de la bibliothèque uniquement (possédés par un dossier). */
    private function libraryMedia(): Builder
    {
        return Media::query()->where('model_type', (new Folder)->getMorphClass());
    }

    private function findBySourceUrl(string $url, string $key): ?Media
    {
        return $this->libraryMedia()
            ->where(function (Builder $q) use ($url, $key): void {
                $q->whereJsonContains('custom_properties->source_keys', $key)
                    // Médias importés avant `source_keys` : URL exacte ou variante de schéma.
                    ->orWhereIn('custom_properties->source_url', array_values(array_unique([
                        $url, 'https://'.$key, 'http://'.$key,
                    ])));
            })
            ->orderBy('id')
            ->first();
    }

    private function findBySha1(string $sha1, int $size): ?Media
    {
        $hit = $this->libraryMedia()->where('custom_properties->sha1', $sha1)->orderBy('id')->first();
        if ($hit !== null) {
            return $hit;
        }

        // Médias sans empreinte (dépôt manuel, import antérieur) de même taille :
        // on calcule leur sha1 une fois, on le mémorise, on compare.
        $candidates = $this->libraryMedia()
            ->where('size', $size)
            ->whereNull('custom_properties->sha1')
            ->orderBy('id')
            ->limit(self::SHA1_BACKFILL_LIMIT)
            ->get();

        foreach ($candidates as $candidate) {
            try {
                $path = $candidate->getPath();
                $candidateSha1 = is_file($path) ? sha1_file($path) : false;
            } catch (\Throwable) {
                $candidateSha1 = false;
            }
            if ($candidateSha1 === false) {
                continue;
            }
            $candidate->setCustomProperty('sha1', $candidateSha1);
            $candidate->save();

            if ($candidateSha1 === $sha1) {
                return $candidate;
            }
        }

        return null;
    }

    /** Mémorise une URL supplémentaire servant le même fichier. */
    private function addAlias(Media $media, string $key): void
    {
        $keys = array_values(array_filter((array) $media->getCustomProperty('source_keys', []), 'is_string'));
        $source = $media->getCustomProperty('source_url');
        if (is_string($source) && $source !== '') {
            $keys[] = self::urlKey($source);
        }
        $keys[] = $key;

        $media->setCustomProperty('source_keys', array_values(array_unique($keys)));
        $media->save();
    }

    private function download(string $url): ?string
    {
        // Client HTTP Laravel avec son User-Agent par défaut : asset.somfy.com répond
        // 403 aux streams PHP (`file_get_contents`) comme à tout User-Agent
        // personnalisé ou de navigateur, et 200 au client Guzzle par défaut.
        try {
            $response = Http::timeout(20)
                ->withOptions(['verify' => false])
                ->get($url);
        } catch (\Throwable $e) {
            $this->lastError = 'téléchargement impossible ('.mb_strimwidth($e->getMessage(), 0, 120, '…').')';

            return null;
        }

        if (! $response->successful()) {
            $this->lastError = 'HTTP '.$response->status();

            return null;
        }

        $binary = $response->body();
        if (strlen($binary) < 8) {
            $this->lastError = 'fichier vide';

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
