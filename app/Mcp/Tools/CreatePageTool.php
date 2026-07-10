<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Services\PageComposer;
use Throwable;

/**
 * Tool MCP (greffé sur laravel-boost) qui crée un contenu CMS (page ou article)
 * à partir de blocs page-builder fournis par l'IA. Appeler page_builder_catalog
 * d'abord pour connaître les blocs et leur forme. La page est créée en brouillon
 * par défaut (à publier depuis le back-office).
 */
#[IsDestructive]
class CreatePageTool extends Tool
{
    protected string $name = 'create_cms_page';

    protected string $description = "Crée un contenu CMS (page ou article) à partir de blocs page-builder. Appelle d'abord page_builder_catalog pour connaître les types de contenu, les blocs disponibles et la forme du champ \"content\". La page est créée en BROUILLON par défaut. Le contenu est normalisé/assaini automatiquement (blocs inconnus ignorés, valeurs bornées). Retourne l'id, le slug et les URLs (publique + back-office).";

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_type' => $schema->string()
                ->description('Type de contenu : handle (ex. "page", "article") ou id. Voir page_builder_catalog.post_types.')
                ->required(),
            'title' => $schema->string()
                ->description('Nom du contenu (navigation / back-office). Sert de H1 par défaut si "heading" absent.')
                ->required(),
            'content' => $schema->object()
                ->description('Arbre de contenu { "heading"?: string, "sections": [ { "layout", "columns": [ { "blocks": [...] } ] } ] } conforme au catalogue (content_shape).')
                ->required(),
            'heading' => $schema->string()
                ->description('Titre H1 affiché en haut de page (SEO). Optionnel — peut différer du "title". Défaut = title.'),
            'slug' => $schema->string()
                ->description('Slug d\'URL. Optionnel : généré depuis le titre et rendu unique automatiquement.'),
            'status' => $schema->string()
                ->description('"draft" (défaut) ou "published".'),
            'excerpt' => $schema->string()->description('Court résumé, optionnel.'),
            'seo_title' => $schema->string()->description('Titre SEO (balise title), optionnel.'),
            'seo_description' => $schema->string()->description('Méta description, optionnel.'),
            'cover_media_id' => $schema->integer()->description('Id d\'un média EXISTANT pour l\'image de couverture, optionnel.'),
        ];
    }

    public function handle(Request $request): Response
    {
        /** @var PageComposer $composer */
        $composer = app(PageComposer::class);

        try {
            $post = $composer->create($request->all());
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (Throwable $e) {
            return Response::error('Échec de création du contenu : '.$e->getMessage());
        }

        return Response::json([
            'ok' => true,
            'id' => $post->getKey(),
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'blocks_count' => $this->countBlocks($post),
            'public_url' => $composer->publicUrl($post),
            'admin_edit_url' => $this->adminUrl($post),
        ]);
    }

    private function countBlocks(Post $post): int
    {
        $count = 0;
        foreach (($post->content['sections'] ?? []) as $section) {
            foreach (($section['columns'] ?? []) as $column) {
                $count += count($column['blocks'] ?? []);
            }
        }

        return $count;
    }

    private function adminUrl(Post $post): ?string
    {
        try {
            return PostResource::getUrl('edit', ['record' => $post->getKey()]);
        } catch (Throwable) {
            return null;
        }
    }
}
