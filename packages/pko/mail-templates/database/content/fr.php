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
 * Format : arbre page-builder `{heading, sections:[{layout, columns:[{blocks}]}]}`
 * (cf. packages/pko/page-builder/resources/schema/content.schema.json). Les
 * e-mails n'utilisent qu'une seule section 1 colonne : le helper `$page()`
 * évite d'écrire cette enveloppe 18 fois. Identifiants, marges et couleurs sont
 * ajoutés à la lecture par PageBuilderManager::normalize().
 *
 * Blocs utilisés ici : `text` (HTML), `title`, `button`, `separator`.
 *
 * Placeholders : `:nom`, déclarés par clé dans MailTemplateRegistry.
 * `enabled => false` : déclencheur câblé, contenu non fourni ou module absent.
 */

/** @param array<int, array<string, mixed>> $blocks */
$page = static fn (array $blocks): array => [
    'heading' => '',
    'sections' => [
        ['layout' => '1col', 'columns' => [['blocks' => $blocks]]],
    ],
];

return [

    // 01 — Bienvenue / inscription du compte
    'account.welcome' => [
        'subject' => 'Bienvenue dans la communauté WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'title', 'level' => 'h2', 'text' => 'Bienvenue chez WEKLO 👋'],
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre compte professionnel vient d\'être créé avec succès et votre SIRET a bien été validé.</p>'],
            ['type' => 'text', 'html' => '<p>Vous faites désormais partie de la communauté WEKLO, et nous sommes heureux de vous compter parmi nous.</p>'],
            ['type' => 'text', 'html' => '<p>WEKLO a été créé avec une conviction simple : les professionnels doivent pouvoir acheter chez un distributeur qui travaille uniquement avec des professionnels.</p>'],
            ['type' => 'text', 'html' => '<p>Chez nous, aucune vente aux particuliers et aucun prix professionnel affiché publiquement. Vos conditions d\'achat restent réservées aux pros afin de vous permettre de travailler sereinement, de préserver vos marges et de conserver toute la valeur de votre savoir-faire auprès de vos propres clients.</p>'],
            ['type' => 'text', 'html' => '<p>Depuis votre espace professionnel, vous pourrez retrouver progressivement l\'ensemble de nos solutions autour de la fermeture, de la motorisation et du contrôle d\'accès : volets roulants, portes de garage, stores, portails, automatismes, interphonie et visiophonie, contrôle d\'accès, alarme, vidéosurveillance, domotique et pièces détachées.</p>'],
            ['type' => 'text', 'html' => '<p>Mais WEKLO, ce n\'est pas seulement un catalogue de produits.</p>'],
            ['type' => 'text', 'html' => '<p>Nous voulons construire une relation basée sur la proximité, la réactivité, l\'expertise métier et la confiance. Vous accompagner dans vos recherches, vous aider à trouver la bonne solution et vous faire gagner du temps au quotidien fait partie de notre métier.</p>'],
            ['type' => 'text', 'html' => '<p>Et parce que chez WEKLO, votre fidélité mérite d\'être récompensée, chaque achat compte. Grâce à notre programme de fidélité, vos achats réalisés tout au long de l\'année vous permettent de bénéficier d\'avantages et de récompenses qui évoluent avec votre activité chez WEKLO.</p>'],
            ['type' => 'text', 'html' => '<p>Plus vous centralisez vos achats, plus nous récompensons votre confiance.</p>'],
            ['type' => 'separator', 'variant' => 'line'],
            ['type' => 'text', 'html' => '<p>Il ne vous reste maintenant qu\'une dernière étape : confirmez votre adresse e-mail afin de sécuriser votre compte et accéder pleinement à votre espace professionnel.</p>'],
            ['type' => 'button', 'label' => 'Confirmer mon adresse e-mail', 'url' => ':verify_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Encore bienvenue chez WEKLO. Ici, vous êtes entre pros. Et ça change beaucoup de choses.</p>'],
            ['type' => 'text', 'html' => '<p>À très bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 02 — Activation du compte
    'account.activated' => [
        'subject' => 'Votre compte WEKLO est activé ✅',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Bonne nouvelle : votre compte WEKLO est maintenant pleinement activé.</p>'],
            ['type' => 'text', 'html' => '<p>Votre SIRET a été validé et vous pouvez désormais profiter de l\'ensemble de votre espace professionnel : consulter nos produits, accéder à vos tarifs et passer vos commandes directement en ligne.</p>'],
            ['type' => 'text', 'html' => '<p>Depuis votre espace WEKLO, vous retrouverez progressivement nos solutions autour de la fermeture, la motorisation et le contrôle d\'accès : volets roulants, portes de garage, portails, automatismes, interphonie et visiophonie, contrôle d\'accès, alarme, vidéosurveillance, domotique, stores et accessoires.</p>'],
            ['type' => 'button', 'label' => 'Accéder à mon espace', 'url' => ':account_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Contactez-nous. Derrière WEKLO, il y a une équipe disponible pour vous répondre.</p>'],
            ['type' => 'text', 'html' => '<p>Bienvenue chez nous 👋 Ici, vous êtes entre pros. Et ça change beaucoup de choses.</p>'],
            ['type' => 'text', 'html' => '<p>À très bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 03 — Première commande avec petit cadeau
    'order.first_order_gift' => [
        'subject' => 'Merci pour votre première commande chez WEKLO 🎉',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Merci pour votre première commande chez WEKLO !</p>'],
            ['type' => 'text', 'html' => '<p>Votre commande n° :order_reference a bien été enregistrée et nous sommes heureux de vous compter désormais parmi nos clients.</p>'],
            ['type' => 'text', 'html' => '<p>Et parce qu\'une première commande, ça se fête, un petit cadeau vous a été glissé avec votre commande 🎁</p>'],
            ['type' => 'text', 'html' => '<p>C\'est aussi le début de votre fidélité chez WEKLO : chez nous, chaque achat compte et votre fidélité est récompensée.</p>'],
            ['type' => 'text', 'html' => '<p>Merci pour votre confiance et bienvenue dans l\'aventure WEKLO.</p>'],
            ['type' => 'text', 'html' => '<p>À très bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 04 — Confirmation de commande
    'order.confirmed' => [
        'subject' => 'Votre commande WEKLO est bien enregistrée ✅',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Merci pour votre commande !</p>'],
            ['type' => 'text', 'html' => '<p>Votre commande n° :order_reference a bien été enregistrée pour un montant de :order_total € HT.</p>'],
            ['type' => 'button', 'label' => 'Voir ma commande', 'url' => ':order_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Nous allons maintenant nous occuper de la suite et vous tiendrons informé de son avancement.</p>'],
            ['type' => 'text', 'html' => '<p>À très bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 05 — Paiement confirmé
    'order.payment_received' => [
        'subject' => 'Paiement reçu pour votre commande WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre paiement a bien été reçu.</p>'],
            ['type' => 'text', 'html' => '<p>Votre commande n° :order_reference est maintenant confirmée et peut poursuivre son traitement.</p>'],
            ['type' => 'text', 'html' => '<p>Vous serez informé dès qu\'elle sera prête à être expédiée.</p>'],
            ['type' => 'text', 'html' => '<p>Merci pour votre confiance,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 06 — Commande expédiée
    'order.shipped' => [
        'subject' => 'Votre commande WEKLO est en route 🚚',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Bonne nouvelle, votre commande n° :order_reference est en route !</p>'],
            ['type' => 'text', 'html' => '<p>Votre matériel vient d\'être expédié. Vous pouvez suivre son acheminement grâce au lien ci-dessous :</p>'],
            ['type' => 'button', 'label' => 'Suivre mon colis', 'url' => ':tracking_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Plus qu\'un peu de patience avant de recevoir votre matériel !</p>'],
            ['type' => 'text', 'html' => '<p>À très bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 07 — Commande disponible au retrait (contenu client en attente)
    'order.ready_for_pickup' => [
        'subject' => 'Votre commande WEKLO est disponible au retrait',
        'enabled' => false,
        'content' => $page([
        ]),
    ],

    // 08 — Expédiée directement par le partenaire (contenu client en attente)
    'order.shipped_by_supplier' => [
        'subject' => 'Votre commande WEKLO est en route',
        'enabled' => false,
        'content' => $page([
        ]),
    ],

    // 09 — Retard de commande
    'order.delayed' => [
        'subject' => 'Mise à jour concernant votre commande WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Nous souhaitions vous tenir informé concernant votre commande n° :order_reference.</p>'],
            ['type' => 'text', 'html' => '<p>Un retard indépendant de notre volonté affecte actuellement votre matériel. La nouvelle date estimée est le :new_date.</p>'],
            ['type' => 'text', 'html' => '<p>Nous savons que les délais sont importants pour vos chantiers et nous suivons votre commande au plus près.</p>'],
            ['type' => 'text', 'html' => '<p>Une question ou une urgence ? Contactez-nous directement.</p>'],
            ['type' => 'text', 'html' => '<p>L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 10 — Relance devis
    'quote.reminder' => [
        'subject' => 'Votre devis WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Petit message concernant votre devis n° :quote_reference.</p>'],
            ['type' => 'text', 'html' => '<p>Avez-vous pu regarder notre proposition ?</p>'],
            ['type' => 'button', 'label' => 'Consulter mon devis', 'url' => ':quote_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Si votre projet a évolué, si vous souhaitez modifier une référence ou si vous avez besoin d\'un conseil avant de vous décider, nous sommes disponibles pour en parler avec vous.</p>'],
            ['type' => 'text', 'html' => '<p>À bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 11 — Panier non finalisé
    'cart.abandoned' => [
        'subject' => 'Votre sélection vous attend sur WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Vous aviez commencé à préparer une commande sur WEKLO mais elle n\'a pas été finalisée.</p>'],
            ['type' => 'text', 'html' => '<p>Votre sélection vous attend toujours dans votre espace professionnel.</p>'],
            ['type' => 'button', 'label' => 'Reprendre ma commande', 'url' => ':cart_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Un doute sur un produit ou une compatibilité ? N\'hésitez pas à nous demander avant de commander, nous sommes là pour ça.</p>'],
            ['type' => 'text', 'html' => '<p>À bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 12 — Commande livrée
    'order.delivered' => [
        'subject' => 'Votre commande WEKLO a été livrée',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre commande n° :order_reference devrait maintenant être entre vos mains.</p>'],
            ['type' => 'text', 'html' => '<p>Nous espérons que tout est conforme et que votre matériel est prêt à rejoindre le chantier.</p>'],
            ['type' => 'text', 'html' => '<p>Si vous constatez le moindre problème à la réception, contactez-nous rapidement afin que nous puissions vous accompagner.</p>'],
            ['type' => 'text', 'html' => '<p>Merci encore pour votre confiance,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 13 — Demande d'avis
    'order.review_request' => [
        'subject' => 'Votre avis compte pour WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre avis nous intéresse.</p>'],
            ['type' => 'text', 'html' => '<p>Vous avez récemment commandé chez WEKLO et votre retour nous aide directement à améliorer notre service.</p>'],
            ['type' => 'text', 'html' => '<p>Si vous avez une minute, dites-nous simplement comment s\'est passée votre expérience.</p>'],
            ['type' => 'button', 'label' => 'Donner mon avis', 'url' => ':review_url', 'variant' => 'accent'],
            ['type' => 'text', 'html' => '<p>Merci de contribuer à faire grandir WEKLO avec nous.</p>'],
            ['type' => 'text', 'html' => '<p>L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 14 — Demande SAV reçue (aucun module SAV dans le projet)
    'support.request_received' => [
        'subject' => 'Nous avons bien reçu votre demande SAV',
        'enabled' => false,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre demande SAV a bien été reçue.</p>'],
            ['type' => 'text', 'html' => '<p>Elle concerne : :subject_label</p>'],
            ['type' => 'text', 'html' => '<p>Nous allons étudier votre demande et revenir vers vous avec la marche à suivre.</p>'],
            ['type' => 'text', 'html' => '<p>Notre objectif : vous apporter une réponse claire et remettre votre chantier en mouvement le plus rapidement possible.</p>'],
            ['type' => 'text', 'html' => '<p>À très bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 15 — Retour SAV accepté (aucun module SAV dans le projet)
    'support.return_accepted' => [
        'subject' => 'Votre retour SAV est accepté',
        'enabled' => false,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre demande de retour concernant :product_label a bien été validée.</p>'],
            ['type' => 'text', 'html' => '<p>Vous trouverez votre étiquette de transport ainsi que les instructions nécessaires pour nous retourner le matériel.</p>'],
            ['type' => 'button', 'label' => 'Télécharger mon étiquette', 'url' => ':return_label_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>Merci de bien protéger le produit avant son expédition. Dès réception, nous nous occupons de la suite avec le fabricant.</p>'],
            ['type' => 'text', 'html' => '<p>L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 16 — Avoir disponible
    'credit_note.available' => [
        'subject' => 'Votre avoir WEKLO est disponible',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre avoir n° :credit_note_reference, d\'un montant de :credit_note_total € HT, est maintenant disponible.</p>'],
            ['type' => 'text', 'html' => '<p>Il est rattaché à votre compte professionnel WEKLO et pourra être utilisé conformément aux conditions prévues.</p>'],
            ['type' => 'button', 'label' => 'Voir mon espace client', 'url' => ':account_url', 'variant' => 'primary'],
            ['type' => 'text', 'html' => '<p>À bientôt,<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 17 — Fidélité / nouveau palier
    'loyalty.tier_unlocked' => [
        'subject' => 'Vous avez atteint un nouveau palier WEKLO 🎉',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Votre fidélité porte ses fruits 🎉</p>'],
            ['type' => 'text', 'html' => '<p>Grâce à vos achats chez WEKLO, vous venez d\'atteindre :tier_name.</p>'],
            ['type' => 'text', 'html' => '<p>Parce que nous considérons qu\'un client fidèle mérite plus qu\'un simple merci, ce nouveau niveau vous permet de bénéficier de :tier_benefit.</p>'],
            ['type' => 'text', 'html' => '<p>Vous nous faites confiance, nous vous le rendons.</p>'],
            ['type' => 'text', 'html' => '<p>Merci de faire grandir WEKLO avec nous.<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

    // 18 — Anniversaire de première commande / fidélité annuelle
    'loyalty.anniversary' => [
        'subject' => 'Merci pour votre fidélité à WEKLO',
        'enabled' => true,
        'content' => $page([
            ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
            ['type' => 'text', 'html' => '<p>Cela fait maintenant :duration_label que vous travaillez avec WEKLO.</p>'],
            ['type' => 'text', 'html' => '<p>Depuis votre première commande, vous avez fait le choix de nous accorder votre confiance et nous tenions simplement à vous dire merci.</p>'],
            ['type' => 'text', 'html' => '<p>Chez nous, la fidélité n\'est pas seulement un mot : elle doit avoir de la valeur.</p>'],
            ['type' => 'text', 'html' => '<p>Et l\'aventure continue.</p>'],
            ['type' => 'text', 'html' => '<p>L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
        ]),
    ],

];
