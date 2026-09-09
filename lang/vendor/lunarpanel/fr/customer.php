<?php

declare(strict_types=1);

/*
 * Override FR partiel des traductions customer de Lunar. Laravel fusionne
 * (array_replace_recursive) ce fichier par-dessus
 * vendor/lunarphp/lunar/resources/lang/fr/customer.php.
 *
 * Les libellés d'origine du badge « Type de client » (liste des commandes et
 * fiche client) étaient « Nouveau » / « Retour ». « Retour » se lit comme un
 * retour marchandise / une demande de SAV, alors que la colonne dit seulement
 * si c'est la première commande passée avec cette adresse e-mail. « Récurrent »
 * lève l'ambiguïté et s'aligne sur le filtre FR de Lunar, déjà intitulé
 * « Nouveau / Récurrent ».
 */
return [
    'table' => [
        'new' => [
            'label' => 'Nouveau',
        ],
        'returning' => [
            'label' => 'Récurrent',
        ],
    ],
];
