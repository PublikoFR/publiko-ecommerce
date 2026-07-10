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
 * Modifie ou publie un contenu CMS existant. Édition partielle : seuls les
 * champs fournis sont modifiés. Publier = passer `status` à "published".
 */
#[IsDestructive]
class UpdatePageTool extends Tool
{
    protected string $name = 'update_cms_page';

    protected string $description = 'Modifie ou PUBLIE un contenu CMS (page/article) existant, identifié par son id. Édition partielle : ne fournir que les champs à changer. Pour publier une page en brouillon, passer status="published". Le contenu fourni est normalisé/assaini. Retourne l\'état après mise à jour.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id du contenu à modifier (voir create_cms_page ou la liste des posts).')->required(),
            'status' => $schema->string()->description('"draft" ou "published". Passer "published" pour mettre en ligne.'),
            'title' => $schema->string()->description('Nouveau nom (back-office), optionnel.'),
            'heading' => $schema->string()->description('Nouveau H1 on-page (SEO), optionnel.'),
            'slug' => $schema->string()->description('Nouveau slug, rendu unique automatiquement, optionnel.'),
            'excerpt' => $schema->string()->description('Nouvel extrait, optionnel.'),
            'seo_title' => $schema->string()->description('Nouveau titre SEO, optionnel.'),
            'seo_description' => $schema->string()->description('Nouvelle méta description, optionnel.'),
            'content' => $schema->object()->description('Nouvel arbre de contenu { heading?, sections[] } (remplace le contenu), optionnel.'),
            'cover_media_id' => $schema->integer()->description('Id média de couverture (0/absent = inchangé), optionnel.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('id');
        $post = Post::withoutGlobalScope('pko_api_published_only')->find($id);

        if (! $post) {
            return Response::error("Aucun contenu trouvé pour l'id {$id}.");
        }

        $data = $request->all();
        unset($data['id']);

        /** @var PageComposer $composer */
        $composer = app(PageComposer::class);

        try {
            $post = $composer->update($post, $data);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (Throwable $e) {
            return Response::error('Échec de la mise à jour : '.$e->getMessage());
        }

        return Response::json([
            'ok' => true,
            'id' => $post->getKey(),
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'public_url' => $composer->publicUrl($post),
            'admin_edit_url' => $this->adminUrl($post),
        ]);
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
