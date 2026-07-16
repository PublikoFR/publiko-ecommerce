<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Extensions;

use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Customer;

/**
 * Ajoute un champ email sur la page CreateCustomer et crée le User Lunar
 * après la création du client, puis envoie un email de définition du mot de passe.
 *
 * Stocke temporairement l'email dans la propriété $pendingEmail entre
 * beforeCreation et afterCreation (même cycle de requête, pas de concurrence
 * multi-worker problématique en PHP-FPM).
 */
class CustomerCreateExtension extends ResourceExtension
{
    private ?string $pendingEmail = null;

    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Section::make('Accès au compte')
                ->schema([
                    TextInput::make('user_email')
                        ->label('Email (compte utilisateur)')
                        ->email()
                        ->unique(table: 'users', column: 'email')
                        ->maxLength(255)
                        ->helperText('Un email sera envoyé au client pour définir son mot de passe.'),
                ]),
        ]);
    }

    /**
     * Extrait l'email du tableau de données avant la création du Customer
     * pour éviter qu'Eloquent essaie de persister un champ inconnu.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeCreation(array $data): array
    {
        $this->pendingEmail = filled($data['user_email'] ?? null) ? $data['user_email'] : null;
        unset($data['user_email']);

        return $data;
    }

    /**
     * Crée le User et l'attache au Customer, puis envoie un lien de
     * définition du mot de passe si un email a été renseigné.
     *
     * @param  array<string, mixed>  $data
     */
    public function afterCreation(Customer $record, array $data): Customer
    {
        if ($this->pendingEmail === null) {
            return $record;
        }

        $name = trim(($record->first_name ?? '').' '.($record->last_name ?? ''))
            ?: $record->company_name
            ?: $this->pendingEmail;

        $user = User::create([
            'name' => $name,
            'email' => $this->pendingEmail,
            'password' => Hash::make(Str::random(32)),
        ]);

        $record->users()->attach($user);

        // Envoie un lien "Définir votre mot de passe" via le broker Laravel standard.
        // L'email utilise le template password.reset de l'application.
        Password::broker()->sendResetLink(['email' => $user->email]);

        $this->pendingEmail = null;

        return $record;
    }
}
