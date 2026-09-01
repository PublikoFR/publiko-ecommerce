<?php

declare(strict_types=1);

/*
 * Contenus par défaut des e-mails transactionnels — DATA, pas du code.
 *
 * Ce fichier porte le discours commercial et la marque de la boutique courante.
 * C'est le pendant d'un seeder (§3.0.4 du CLAUDE.md) : réutiliser le back-office
 * sur une autre enseigne se fait en remplaçant ce fichier, sans toucher une seule
 * classe PHP. Aucun autre endroit du package ne contient de nom de marque.
 *
 * Source : document client « Bibliothèque d'emails », version du 31/08/2026.
 *
 * Placeholders : `:nom`, déclarés par clé dans MailTemplateRegistry.
 * `enabled => false` : déclencheur câblé, contenu non fourni ou module absent.
 */

$signature = "L'équipe WEKLO\nLa fermeture pour les pros.";

return [

    // 01 — Bienvenue / inscription du compte
    'account.welcome' => [
        'subject' => 'Bienvenue dans la communauté WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'heading', 'text' => 'Bienvenue chez WEKLO 👋'],
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => "Votre compte professionnel vient d'être créé avec succès et votre SIRET a bien été enregistré."],
            ['type' => 'paragraph', 'text' => 'Vous faites désormais partie de la communauté WEKLO, et nous sommes heureux de vous compter parmi nous.'],
            ['type' => 'paragraph', 'text' => 'WEKLO a été créé avec une conviction simple : les professionnels doivent pouvoir acheter chez un distributeur qui travaille uniquement avec des professionnels.'],
            ['type' => 'paragraph', 'text' => 'Chez nous, aucune vente aux particuliers et aucun prix professionnel affiché publiquement. Vos conditions d\'achat restent réservées aux pros afin de vous permettre de travailler sereinement, de préserver vos marges et de conserver toute la valeur de votre savoir-faire auprès de vos propres clients.'],
            ['type' => 'paragraph', 'text' => "Depuis votre espace professionnel, vous pourrez retrouver progressivement l'ensemble de nos solutions autour de la fermeture, de la motorisation et du contrôle d'accès : volets roulants, portes de garage, stores, portails, automatismes, interphonie et visiophonie, contrôle d'accès, alarme, vidéosurveillance, domotique et pièces détachées."],
            ['type' => 'paragraph', 'text' => "Mais WEKLO, ce n'est pas seulement un catalogue de produits."],
            ['type' => 'paragraph', 'text' => 'Nous voulons construire une relation basée sur la proximité, la réactivité, l\'expertise métier et la confiance. Vous accompagner dans vos recherches, vous aider à trouver la bonne solution et vous faire gagner du temps au quotidien fait partie de notre métier.'],
            ['type' => 'paragraph', 'text' => "Et parce que chez WEKLO, votre fidélité mérite d'être récompensée, chaque achat compte. Grâce à notre programme de fidélité, vos achats réalisés tout au long de l'année vous permettent de bénéficier d'avantages et de récompenses qui évoluent avec votre activité chez WEKLO."],
            ['type' => 'paragraph', 'text' => 'Plus vous centralisez vos achats, plus nous récompensons votre confiance.'],
            ['type' => 'divider'],
            ['type' => 'paragraph', 'text' => "Il ne vous reste maintenant qu'une dernière étape : confirmez votre adresse e-mail afin de sécuriser votre compte et accéder pleinement à votre espace professionnel."],
            ['type' => 'button', 'label' => 'Confirmer mon adresse e-mail', 'url' => ':verify_url'],
            ['type' => 'paragraph', 'text' => 'Encore bienvenue chez WEKLO. Ici, vous êtes entre pros. Et ça change beaucoup de choses.'],
            ['type' => 'signature', 'text' => "À très bientôt,\n".$signature],
        ],
    ],

    // 02 — Activation du compte
    'account.activated' => [
        'subject' => 'Votre compte WEKLO est activé ✅',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Bonne nouvelle : votre compte WEKLO est maintenant pleinement activé.'],
            ['type' => 'paragraph', 'text' => "Vous pouvez désormais profiter de l'ensemble de votre espace professionnel : consulter nos produits, accéder à vos tarifs et passer vos commandes directement en ligne."],
            ['type' => 'paragraph', 'text' => "Depuis votre espace WEKLO, vous retrouverez progressivement nos solutions autour de la fermeture, la motorisation et le contrôle d'accès : volets roulants, portes de garage, portails, automatismes, interphonie et visiophonie, contrôle d'accès, alarme, vidéosurveillance, domotique, stores et accessoires."],
            ['type' => 'button', 'label' => 'Accéder à mon espace', 'url' => ':account_url'],
            ['type' => 'paragraph', 'text' => 'Contactez-nous. Derrière WEKLO, il y a une équipe disponible pour vous répondre.'],
            ['type' => 'paragraph', 'text' => 'Bienvenue chez nous 👋 Ici, vous êtes entre pros. Et ça change beaucoup de choses.'],
            ['type' => 'signature', 'text' => "À très bientôt,\n".$signature],
        ],
    ],

    // 03 — Première commande avec petit cadeau
    'order.first_order_gift' => [
        'subject' => 'Merci pour votre première commande chez WEKLO 🎉',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Merci pour votre première commande chez WEKLO !'],
            ['type' => 'paragraph', 'text' => 'Votre commande n° :order_reference a bien été enregistrée et nous sommes heureux de vous compter désormais parmi nos clients.'],
            ['type' => 'paragraph', 'text' => "Et parce qu'une première commande, ça se fête, un petit cadeau vous a été glissé avec votre commande 🎁"],
            ['type' => 'paragraph', 'text' => "C'est aussi le début de votre fidélité chez WEKLO : chez nous, chaque achat compte et votre fidélité est récompensée."],
            ['type' => 'paragraph', 'text' => "Merci pour votre confiance et bienvenue dans l'aventure WEKLO."],
            ['type' => 'signature', 'text' => "À très bientôt,\n".$signature],
        ],
    ],

    // 04 — Confirmation de commande
    'order.confirmed' => [
        'subject' => 'Votre commande WEKLO est bien enregistrée ✅',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Merci pour votre commande !'],
            ['type' => 'paragraph', 'text' => 'Votre commande n° :order_reference a bien été enregistrée pour un montant de :order_total € HT.'],
            ['type' => 'button', 'label' => 'Voir ma commande', 'url' => ':order_url'],
            ['type' => 'paragraph', 'text' => 'Nous allons maintenant nous occuper de la suite et vous tiendrons informé de son avancement.'],
            ['type' => 'signature', 'text' => "À très bientôt,\n".$signature],
        ],
    ],

    // 05 — Paiement confirmé
    'order.payment_received' => [
        'subject' => 'Paiement reçu pour votre commande WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Votre paiement a bien été reçu.'],
            ['type' => 'paragraph', 'text' => 'Votre commande n° :order_reference est maintenant confirmée et peut poursuivre son traitement.'],
            ['type' => 'paragraph', 'text' => "Vous serez informé dès qu'elle sera prête à être expédiée."],
            ['type' => 'signature', 'text' => "Merci pour votre confiance,\n".$signature],
        ],
    ],

    // 06 — Commande expédiée
    'order.shipped' => [
        'subject' => 'Votre commande WEKLO est en route 🚚',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Bonne nouvelle, votre commande n° :order_reference est en route !'],
            ['type' => 'paragraph', 'text' => "Votre matériel vient d'être expédié. Vous pouvez suivre son acheminement grâce au lien ci-dessous :"],
            ['type' => 'button', 'label' => 'Suivre mon colis', 'url' => ':tracking_url'],
            ['type' => 'paragraph', 'text' => "Plus qu'un peu de patience avant de recevoir votre matériel !"],
            ['type' => 'signature', 'text' => "À très bientôt,\n".$signature],
        ],
    ],

    // 07 — Commande disponible au retrait (contenu client en attente)
    'order.ready_for_pickup' => [
        'subject' => 'Votre commande WEKLO est disponible au retrait',
        'enabled' => false,
        'blocks' => [],
    ],

    // 08 — Expédiée directement par le partenaire (contenu client en attente)
    'order.shipped_by_supplier' => [
        'subject' => 'Votre commande WEKLO est en route',
        'enabled' => false,
        'blocks' => [],
    ],

    // 09 — Retard de commande
    'order.delayed' => [
        'subject' => 'Mise à jour concernant votre commande WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Nous souhaitions vous tenir informé concernant votre commande n° :order_reference.'],
            ['type' => 'paragraph', 'text' => 'Un retard indépendant de notre volonté affecte actuellement votre matériel. La nouvelle date estimée est le :new_date.'],
            ['type' => 'paragraph', 'text' => 'Nous savons que les délais sont importants pour vos chantiers et nous suivons votre commande au plus près.'],
            ['type' => 'paragraph', 'text' => 'Une question ou une urgence ? Contactez-nous directement.'],
            ['type' => 'signature', 'text' => $signature],
        ],
    ],

    // 10 — Relance devis
    'quote.reminder' => [
        'subject' => 'Votre devis WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Petit message concernant votre devis n° :quote_reference.'],
            ['type' => 'paragraph', 'text' => 'Avez-vous pu regarder notre proposition ?'],
            ['type' => 'button', 'label' => 'Consulter mon devis', 'url' => ':quote_url'],
            ['type' => 'paragraph', 'text' => "Si votre projet a évolué, si vous souhaitez modifier une référence ou si vous avez besoin d'un conseil avant de vous décider, nous sommes disponibles pour en parler avec vous."],
            ['type' => 'signature', 'text' => "À bientôt,\n".$signature],
        ],
    ],

    // 11 — Panier non finalisé
    'cart.abandoned' => [
        'subject' => 'Votre sélection vous attend sur WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => "Vous aviez commencé à préparer une commande sur WEKLO mais elle n'a pas été finalisée."],
            ['type' => 'paragraph', 'text' => 'Votre sélection vous attend toujours dans votre espace professionnel.'],
            ['type' => 'button', 'label' => 'Reprendre ma commande', 'url' => ':cart_url'],
            ['type' => 'paragraph', 'text' => "Un doute sur un produit ou une compatibilité ? N'hésitez pas à nous demander avant de commander, nous sommes là pour ça."],
            ['type' => 'signature', 'text' => "À bientôt,\n".$signature],
        ],
    ],

    // 12 — Commande livrée
    'order.delivered' => [
        'subject' => 'Votre commande WEKLO a été livrée',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Votre commande n° :order_reference devrait maintenant être entre vos mains.'],
            ['type' => 'paragraph', 'text' => 'Nous espérons que tout est conforme et que votre matériel est prêt à rejoindre le chantier.'],
            ['type' => 'paragraph', 'text' => 'Si vous constatez le moindre problème à la réception, contactez-nous rapidement afin que nous puissions vous accompagner.'],
            ['type' => 'signature', 'text' => "Merci encore pour votre confiance,\n".$signature],
        ],
    ],

    // 13 — Demande d'avis
    'order.review_request' => [
        'subject' => 'Votre avis compte pour WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Votre avis nous intéresse.'],
            ['type' => 'paragraph', 'text' => 'Vous avez récemment commandé chez WEKLO et votre retour nous aide directement à améliorer notre service.'],
            ['type' => 'paragraph', 'text' => "Si vous avez une minute, dites-nous simplement comment s'est passée votre expérience."],
            ['type' => 'button', 'label' => 'Donner mon avis', 'url' => ':review_url', 'variant' => 'accent'],
            ['type' => 'paragraph', 'text' => 'Merci de contribuer à faire grandir WEKLO avec nous.'],
            ['type' => 'signature', 'text' => $signature],
        ],
    ],

    // 14 — Demande SAV reçue (aucun module SAV dans le projet)
    'support.request_received' => [
        'subject' => 'Nous avons bien reçu votre demande SAV',
        'enabled' => false,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Votre demande SAV a bien été reçue.'],
            ['type' => 'paragraph', 'text' => 'Elle concerne : :subject_label'],
            ['type' => 'paragraph', 'text' => 'Nous allons étudier votre demande et revenir vers vous avec la marche à suivre.'],
            ['type' => 'paragraph', 'text' => 'Notre objectif : vous apporter une réponse claire et remettre votre chantier en mouvement le plus rapidement possible.'],
            ['type' => 'signature', 'text' => "À très bientôt,\n".$signature],
        ],
    ],

    // 15 — Retour SAV accepté (aucun module SAV dans le projet)
    'support.return_accepted' => [
        'subject' => 'Votre retour SAV est accepté',
        'enabled' => false,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Votre demande de retour concernant :product_label a bien été validée.'],
            ['type' => 'paragraph', 'text' => 'Vous trouverez votre étiquette de transport ainsi que les instructions nécessaires pour nous retourner le matériel.'],
            ['type' => 'button', 'label' => 'Télécharger mon étiquette', 'url' => ':return_label_url'],
            ['type' => 'paragraph', 'text' => 'Merci de bien protéger le produit avant son expédition. Dès réception, nous nous occupons de la suite avec le fabricant.'],
            ['type' => 'signature', 'text' => $signature],
        ],
    ],

    // 16 — Avoir disponible
    'credit_note.available' => [
        'subject' => 'Votre avoir WEKLO est disponible',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => "Votre avoir n° :credit_note_reference, d'un montant de :credit_note_total € HT, est maintenant disponible."],
            ['type' => 'paragraph', 'text' => 'Il est rattaché à votre compte professionnel WEKLO et pourra être utilisé conformément aux conditions prévues.'],
            ['type' => 'button', 'label' => 'Voir mon espace client', 'url' => ':account_url'],
            ['type' => 'signature', 'text' => "À bientôt,\n".$signature],
        ],
    ],

    // 17 — Fidélité / nouveau palier
    'loyalty.tier_unlocked' => [
        'subject' => 'Vous avez atteint un nouveau palier WEKLO 🎉',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Votre fidélité porte ses fruits 🎉'],
            ['type' => 'paragraph', 'text' => "Grâce à vos achats chez WEKLO, vous venez d'atteindre :tier_name."],
            ['type' => 'paragraph', 'text' => "Parce que nous considérons qu'un client fidèle mérite plus qu'un simple merci, ce nouveau niveau vous permet de bénéficier de :tier_benefit."],
            ['type' => 'paragraph', 'text' => 'Vous nous faites confiance, nous vous le rendons.'],
            ['type' => 'signature', 'text' => "Merci de faire grandir WEKLO avec nous.\n".$signature],
        ],
    ],

    // 18 — Anniversaire de première commande / fidélité annuelle
    'loyalty.anniversary' => [
        'subject' => 'Merci pour votre fidélité à WEKLO',
        'enabled' => true,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Bonjour :first_name,'],
            ['type' => 'paragraph', 'text' => 'Cela fait maintenant :duration_label que vous travaillez avec WEKLO.'],
            ['type' => 'paragraph', 'text' => 'Depuis votre première commande, vous avez fait le choix de nous accorder votre confiance et nous tenions simplement à vous dire merci.'],
            ['type' => 'paragraph', 'text' => "Chez nous, la fidélité n'est pas seulement un mot : elle doit avoir de la valeur."],
            ['type' => 'paragraph', 'text' => "Et l'aventure continue."],
            ['type' => 'signature', 'text' => $signature],
        ],
    ],
];
