<?php

declare(strict_types=1);

/*
 * Version par défaut REMPLACÉE de `loyalty.tier_unlocked` (avant l'encart cadeau).
 *
 * DATA, comme `../fr.php` : la migration du même nom ne remplace le contenu en
 * base que s'il est encore identique à cette version (cf. DefaultContentUpgrade).
 * Sur une autre enseigne, ce fichier ne correspond à rien en base : sans effet.
 */

return [
    'loyalty.tier_unlocked' => [
        'subject' => 'Vous avez atteint un nouveau palier WEKLO 🎉',
        'content' => [
            'heading' => '',
            'sections' => [
                ['layout' => '1col', 'columns' => [['blocks' => [
                    ['type' => 'text', 'html' => '<p>Bonjour :first_name,</p>'],
                    ['type' => 'text', 'html' => '<p>Votre fidélité porte ses fruits 🎉</p>'],
                    ['type' => 'text', 'html' => '<p>Grâce à vos achats chez WEKLO, vous venez d\'atteindre :tier_name.</p>'],
                    ['type' => 'text', 'html' => '<p>Parce que nous considérons qu\'un client fidèle mérite plus qu\'un simple merci, ce nouveau niveau vous permet de bénéficier de :tier_benefit.</p>'],
                    ['type' => 'text', 'html' => '<p>Vous nous faites confiance, nous vous le rendons.</p>'],
                    ['type' => 'text', 'html' => '<p>Merci de faire grandir WEKLO avec nous.<br>
L\'équipe WEKLO<br>
La fermeture pour les pros.</p>'],
                ]]]],
            ],
        ],
    ],
];
