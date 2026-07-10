<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Lunar\Admin\Models\Staff as LunarStaff;

/**
 * Sous-modèle de `Lunar\Admin\Models\Staff` ajoutant `HasApiTokens` (requis par
 * Passport) SANS modifier le vendor. Utilisé uniquement par le provider Passport
 * dédié `oauth_staff` (guard `api`) — le panel Filament garde son guard `staff`
 * et son modèle Lunar inchangés. Même table `staff` → mêmes enregistrements.
 *
 * Compatibilité Liskov : partout où Lunar attend un Staff, cette sous-classe
 * fonctionne (elle EST un Staff).
 */
class Staff extends LunarStaff implements OAuthenticatable
{
    use HasApiTokens;
}
