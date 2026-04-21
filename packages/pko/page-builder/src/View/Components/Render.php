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
    /** @var array{sections: array<int, array<string, mixed>>} */
    public array $tree;

    public ?string $fallback;

    public function __construct(mixed $content = null, ?string $fallback = null)
    {
        $this->tree = PageBuilderManager::normalize(is_array($content) ? $content : null);
        // Le fallback (legacy $post->body) peut contenir du HTML user-contributed ;
        // sanitize ici pour éviter stored XSS si la donnée a été saisie avant la
        // mise en place de la sanitization au save.
        $this->fallback = $fallback !== null ? PageBuilderManager::sanitizeHtml($fallback) : null;
    }

    public function hasSections(): bool
    {
        return $this->tree['sections'] !== [];
    }

    public function render(): View
    {
        return view('page-builder::components.render');
    }
}
