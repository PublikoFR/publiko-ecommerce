<?php

declare(strict_types=1);

namespace Pko\PageBuilder\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Pko\PageBuilder\Services\PageBuilderManager;

/**
 * Renders a normalised page-builder content tree as HTML.
 *
 * Usage :
 *   <x-page-builder::render :content="$page->content" />
 *   <x-page-builder::render :content="$post->content" fallback="$post->body" />
 *
 * If `content` is null or empty, falls back to rendering `fallback` as raw
 * HTML (compat with the legacy `body` column).
 */
final class Render extends Component
{
    /** @var array{heading: string, sections: array<int, array<string, mixed>>} */
    public array $tree;

    public ?string $fallback;

    /** Rendre le H1 on-page (heading) en tête. Désactivé par défaut : les
     *  templates front gèrent déjà leur propre <header>. Activé pour l'aperçu admin. */
    public bool $withHeading;

    public function __construct(mixed $content = null, ?string $fallback = null, bool $withHeading = false)
    {
        $this->tree = PageBuilderManager::normalize(is_array($content) ? $content : null);
        $this->withHeading = $withHeading;
        // Le fallback (legacy $post->body) peut contenir du HTML user-contributed ;
        // sanitize ici pour éviter stored XSS si la donnée a été saisie avant la
        // mise en place de la sanitization au save.
        $this->fallback = $fallback !== null ? PageBuilderManager::sanitizeHtml($fallback) : null;
    }

    public function hasSections(): bool
    {
        return $this->tree['sections'] !== [];
    }

    public function heading(): string
    {
        return $this->tree['heading'] ?? '';
    }

    /**
     * Agrège tous les blocs accordéon de la page en un unique JSON-LD FAQPage
     * (schema.org) pour le référencement. Retourne null si aucune paire Q/R
     * complète. Invisible pour l'utilisateur (rendu dans un <script ld+json>).
     * JSON_HEX_TAG neutralise tout `<`/`>` → pas de breakout de balise script.
     */
    public function faqJsonLd(): ?string
    {
        $entities = [];
        foreach ($this->tree['sections'] as $section) {
            foreach ($section['columns'] as $column) {
                foreach ($column['blocks'] as $block) {
                    if (($block['type'] ?? null) !== 'accordion') {
                        continue;
                    }
                    foreach (($block['items'] ?? []) as $item) {
                        $q = trim((string) ($item['q'] ?? ''));
                        $a = trim((string) ($item['a'] ?? ''));
                        if ($q === '' || $a === '') {
                            continue;
                        }
                        $entities[] = [
                            '@type' => 'Question',
                            'name' => $q,
                            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                        ];
                    }
                }
            }
        }

        if ($entities === []) {
            return null;
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    public function render(): View
    {
        return view('page-builder::components.render');
    }
}
