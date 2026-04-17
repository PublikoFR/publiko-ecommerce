<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Mde\StorefrontCms\Models\HomeOffer;
use Mde\StorefrontCms\Models\HomeSlide;
use Mde\StorefrontCms\Models\HomeTile;
use Mde\StorefrontCms\Models\Page;
use Mde\StorefrontCms\Models\Post;

class MdeStorefrontCmsSeeder extends Seeder
{
    public function run(): void
    {
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
            ['title' => 'Service de pose', 'subtitle' => 'Nos partenaires experts', 'cta_label' => 'En savoir plus', 'cta_url' => '/pages/service-pose', 'position' => 4],
        ] as $offer) {
            HomeOffer::create($offer);
        }

        Post::query()->truncate();
        foreach ([
            ['slug' => 'mde-partenaire-siam-sbh-2026', 'title' => 'SIAM SBH fait confiance à MDE pour son automatisme', 'excerpt' => 'Retour d\'expérience d\'un client historique sur l\'intégration de notre catalogue à leurs projets.', 'body' => '<p>Cas client détaillé à venir.</p>', 'status' => 'published', 'published_at' => now()->subDays(3)],
            ['slug' => 'recrutement-techniciens-2026', 'title' => 'Découvrez nos offres d\'emploi', 'excerpt' => 'MDE recrute : techniciens, commerciaux, responsables dépôt.', 'body' => '<p>Postulez dès maintenant.</p>', 'status' => 'published', 'published_at' => now()->subDays(10)],
            ['slug' => 'evenement-milwaukee-rome-2026', 'title' => 'MDE à l\'événement Milwaukee Rome 2026', 'excerpt' => 'Retour sur la conférence Milwaukee World of Solutions 2026.', 'body' => '<p>Retour détaillé à venir.</p>', 'status' => 'published', 'published_at' => now()->subWeeks(3)],
            ['slug' => 'don-sang-mde', 'title' => 'Don du sang chez MDE', 'excerpt' => 'Une belle mobilisation pour la collecte de sang au sein de notre siège.', 'body' => '<p>Merci à tous les participants !</p>', 'status' => 'published', 'published_at' => now()->subWeeks(5)],
        ] as $post) {
            Post::create($post);
        }

        Page::query()->truncate();
        foreach ([
            ['slug' => 'qui-sommes-nous', 'title' => 'Qui sommes-nous ?', 'body' => '<p>MDE Distribution est un distributeur professionnel français spécialisé dans les matériaux du bâtiment, les portails, volets, automatismes et solutions domotiques.</p><p>Notre mission : accompagner les installateurs et artisans avec un catalogue premium, des prix pros dégressifs, et un support commercial réactif.</p>'],
            ['slug' => 'cgv', 'title' => 'Conditions Générales de Vente', 'body' => '<p>Les présentes CGV régissent l\'ensemble des transactions commerciales conclues entre MDE Distribution et ses clients professionnels.</p><p><em>Document en cours de finalisation — version provisoire.</em></p>'],
            ['slug' => 'mentions-legales', 'title' => 'Mentions légales', 'body' => '<p>MDE Distribution — Distributeur professionnel — France.</p>'],
            ['slug' => 'politique-cookies', 'title' => 'Politique cookies', 'body' => '<p>Ce site utilise des cookies essentiels au fonctionnement et à l\'amélioration de l\'expérience utilisateur.</p>'],
            ['slug' => 'politique-donnees', 'title' => 'Politique de données personnelles', 'body' => '<p>MDE Distribution respecte le RGPD et la CNIL.</p>'],
            ['slug' => 'nous-contacter', 'title' => 'Nous contacter', 'body' => '<p>Notre équipe commerciale est à votre disposition du lundi au vendredi, 8h-18h.</p>'],
        ] as $page) {
            Page::create($page);
        }
    }
}
