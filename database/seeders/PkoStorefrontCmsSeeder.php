<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Pko\StorefrontCms\Models\HomeOffer;
use Pko\StorefrontCms\Models\HomeSlide;
use Pko\StorefrontCms\Models\HomeTile;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;

class PkoStorefrontCmsSeeder extends Seeder
{
    public function run(): void
    {
        // Garantir l'existence des 2 post types de base (la migration les crée déjà mais idempotent).
        $articleType = PostType::firstOrCreate(
            ['handle' => 'article'],
            ['label' => 'Article', 'url_segment' => 'article', 'icon' => 'heroicon-o-newspaper', 'sort_order' => 10],
        );
        $pageType = PostType::firstOrCreate(
            ['handle' => 'page'],
            ['label' => 'Page', 'url_segment' => 'page', 'icon' => 'heroicon-o-document-text', 'sort_order' => 20],
        );

        HomeSlide::query()->truncate();
        foreach ([
            ['title' => 'Les performances qui font la différence', 'subtitle' => 'Gamme outillage pro Milwaukee — jusqu\'à -30% sur sélection', 'bg_color' => '#0f172a', 'text_color' => '#ffffff', 'cta_label' => 'J\'en profite', 'cta_url' => '/collections/outillage', 'position' => 1],
            ['title' => 'Votre partenaire portails & automatismes', 'subtitle' => '60 000 références pros disponibles en 24h', 'bg_color' => '#1e40af', 'text_color' => '#ffffff', 'cta_label' => 'Découvrir le catalogue', 'cta_url' => '/collections/portails-coulissants', 'position' => 2],
            ['title' => 'Exclusivités MDE 2026', 'subtitle' => 'Les nouveautés pour vos chantiers', 'bg_color' => '#0369a1', 'text_color' => '#ffffff', 'cta_label' => 'Voir les nouveautés', 'cta_url' => '/collections/nouveautes', 'position' => 3],
        ] as $slide) {
            HomeSlide::create($slide);
        }

        HomeTile::query()->truncate();
        foreach ([
            ['title' => 'Portails coulissants', 'subtitle' => 'Motorisés ou manuels', 'cta_label' => 'Découvrir', 'cta_url' => '/collections/portails-coulissants', 'position' => 1],
            ['title' => 'Portails battants', 'subtitle' => 'Automatismes au choix', 'cta_label' => 'Découvrir', 'cta_url' => '/collections/portails-battants', 'position' => 2],
            ['title' => 'Volets roulants', 'subtitle' => 'Solutions connectées', 'cta_label' => 'Découvrir', 'cta_url' => '/collections/volets-roulants', 'position' => 3],
            ['title' => 'Motorisations', 'subtitle' => 'Toutes marques', 'cta_label' => 'Découvrir', 'cta_url' => '/collections/motorisations', 'position' => 4],
        ] as $tile) {
            HomeTile::create($tile);
        }

        HomeOffer::query()->truncate();
        foreach ([
            ['title' => 'Offres pros du trimestre', 'subtitle' => 'Remises dégressives jusqu\'à -25%', 'badge' => 'Jusqu\'au 30/06', 'cta_label' => 'Voir les offres', 'cta_url' => '/collections/offres', 'position' => 1],
            ['title' => 'Équipez vos chantiers', 'subtitle' => 'EPI, outillage, accessoires', 'badge' => 'Pack chantier', 'cta_label' => 'Composer mon pack', 'cta_url' => '/collections/epi', 'position' => 2],
            ['title' => 'Domotique connectée', 'subtitle' => 'Installation simplifiée', 'cta_label' => 'Explorer', 'cta_url' => '/collections/domotique', 'position' => 3],
            ['title' => 'Service de pose', 'subtitle' => 'Nos partenaires experts', 'cta_label' => 'En savoir plus', 'cta_url' => '/page/service-pose', 'position' => 4],
        ] as $offer) {
            HomeOffer::create($offer);
        }

        // Réinitialisation : on purge tous les posts mais on garde les post types (référentiel).
        DB::table('pko_posts')->delete();

        foreach ([
            ['slug' => 'mde-partenaire-siam-sbh-2026', 'title' => 'SIAM SBH fait confiance à MDE pour son automatisme', 'excerpt' => 'Retour d\'expérience d\'un client historique sur l\'intégration de notre catalogue à leurs projets.', 'body' => '<p>Cas client détaillé à venir.</p>', 'status' => 'published', 'published_at' => now()->subDays(3)],
            ['slug' => 'recrutement-techniciens-2026', 'title' => 'Découvrez nos offres d\'emploi', 'excerpt' => 'MDE recrute : techniciens, commerciaux, responsables dépôt.', 'body' => '<p>Postulez dès maintenant.</p>', 'status' => 'published', 'published_at' => now()->subDays(10)],
            ['slug' => 'evenement-milwaukee-rome-2026', 'title' => 'MDE à l\'événement Milwaukee Rome 2026', 'excerpt' => 'Retour sur la conférence Milwaukee World of Solutions 2026.', 'body' => '<p>Retour détaillé à venir.</p>', 'status' => 'published', 'published_at' => now()->subWeeks(3)],
            ['slug' => 'don-sang-mde', 'title' => 'Don du sang chez MDE', 'excerpt' => 'Une belle mobilisation pour la collecte de sang au sein de notre siège.', 'body' => '<p>Merci à tous les participants !</p>', 'status' => 'published', 'published_at' => now()->subWeeks(5)],
        ] as $post) {
            Post::create(array_merge($post, ['post_type_id' => $articleType->id]));
        }

        foreach ([
            ['slug' => 'qui-sommes-nous', 'title' => 'Qui sommes-nous ?', 'body' => '<p>MDE Distribution est un distributeur professionnel français spécialisé dans les matériaux du bâtiment, les portails, volets, automatismes et solutions domotiques.</p><p>Notre mission : accompagner les installateurs et artisans avec un catalogue premium, des prix pros dégressifs, et un support commercial réactif.</p>', 'status' => 'published'],
            ['slug' => 'cgv', 'title' => 'Conditions Générales de Vente', 'body' => '<p>Les présentes CGV régissent l\'ensemble des transactions commerciales conclues entre MDE Distribution et ses clients professionnels.</p><p><em>Document en cours de finalisation — version provisoire.</em></p>', 'status' => 'published'],
            ['slug' => 'mentions-legales', 'title' => 'Mentions légales', 'body' => '<p>MDE Distribution — Distributeur professionnel — France.</p>', 'status' => 'published'],
            ['slug' => 'politique-cookies', 'title' => 'Politique cookies', 'body' => '<p>Ce site utilise des cookies essentiels au fonctionnement et à l\'amélioration de l\'expérience utilisateur.</p>', 'status' => 'published'],
            ['slug' => 'politique-donnees', 'title' => 'Politique de données personnelles', 'body' => '<p>MDE Distribution respecte le RGPD et la CNIL.</p>', 'status' => 'published'],
            ['slug' => 'nous-contacter', 'title' => 'Nous contacter', 'body' => '<p>Notre équipe commerciale est à votre disposition du lundi au vendredi, 8h-18h.</p>', 'status' => 'published'],
        ] as $page) {
            Post::create(array_merge($page, ['post_type_id' => $pageType->id]));
        }
    }
}
