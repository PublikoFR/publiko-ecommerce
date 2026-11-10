<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Forms\Form;

/**
 * Variante marques de HideLunarMediaExtension : masque l'UI média native Lunar
 * (page, onglet, relation, colonne) SANS ajouter la section « Médias »
 * (image principale + galerie) au formulaire. Le logo d'une marque se gère
 * dans la fiche unifiée ManageBrandContent (mediagroup `logo`, lu par
 * pko_brand_logo()) ; la galerie n'était affichée nulle part.
 */
class HideBrandLunarMediaExtension extends HideLunarMediaExtension
{
    public function extendForm(Form $form): Form
    {
        return $form;
    }
}
