<?php

declare(strict_types=1);

/*
 * Versions par défaut REMPLACÉES de `account.welcome`, `account.activated` et
 * `account.registered_admin`, avant la validation manuelle des comptes pro
 * (FEAT-006 : la vérification de l'e-mail n'active plus le compte).
 *
 * DATA, comme `../fr.php` : la migration du même nom ne remplace le contenu en
 * base que s'il est encore identique à cette version (cf. DefaultContentUpgrade).
 * Sur une autre enseigne, ce fichier ne correspond à rien en base : sans effet.
 */

/** @param array<int, array<string, mixed>> $blocks */
$page = static fn (array $blocks): array => [
    'heading' => '',
    'sections' => [
        ['layout' => '1col', 'columns' => [['blocks' => $blocks]]],
    ],
];

return [
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

    // Inscription — notification équipe (onboarding téléphone)
    'account.registered_admin' => [
        'subject' => 'Nouvelle inscription — :company_name',
        'enabled' => true,
        'content' => $page([
            ['type' => 'title', 'level' => 'h2', 'text' => 'Nouvelle inscription client'],
            ['type' => 'text', 'html' => '<p>Un nouveau compte professionnel vient d\'être créé. Contacter le client par téléphone pour l\'onboarding.</p>'],
            ['type' => 'text', 'html' => '<p><strong>Société</strong> : :company_name<br>
<strong>Contact</strong> : :contact_name<br>
<strong>E-mail</strong> : :email<br>
<strong>Téléphone</strong> : :phone<br>
<strong>SIRET</strong> : :siret<br>
<strong>N° TVA</strong> : :vat_number<br>
<strong>Code NAF</strong> : :naf_code<br>
<strong>Activité</strong> : :activity<br>
<strong>Adresse</strong> : :address<br>
<strong>Groupe(s)</strong> : :groups<br>
<strong>Statut compte</strong> : :status<br>
<strong>SIRET INSEE</strong> : :sirene_status</p>'],
            ['type' => 'button', 'label' => 'Appeler :phone', 'url' => ':phone_url', 'variant' => 'accent'],
            ['type' => 'button', 'label' => 'Voir la fiche client', 'url' => ':admin_url', 'variant' => 'secondary'],
        ]),
    ],
];
