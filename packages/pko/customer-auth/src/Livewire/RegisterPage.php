<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Pko\CustomerAuth\Actions\RegisterProCustomer;
use Pko\CustomerAuth\Sirene\SireneClient;

class RegisterPage extends Component
{
    public string $siret = '';

    public ?string $companyName = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public string $email = '';

    public string $phone = '';

    public string $activity = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $terms = false;

    public ?array $sireneSnapshot = null;

    public function rules(): array
    {
        return [
            'siret' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'firstName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'companyName' => ['nullable', 'string', 'max:200'],
            'activity' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:8', 'confirmed:passwordConfirmation'],
            'terms' => ['accepted'],
        ];
    }

    public function submit(RegisterProCustomer $action): mixed
    {
        // Normalise le SIRET : on accepte les espaces / séparateurs de saisie
        // (ex. « 981 043 979 00021 ») et on ne conserve que les chiffres.
        $this->siret = preg_replace('/\D/', '', (string) $this->siret) ?? '';

        $validated = $this->validate();

        if (! SireneClient::validateSiret($this->siret)) {
            throw ValidationException::withMessages(['siret' => 'SIRET invalide : 14 chiffres attendus (clé de contrôle incorrecte).']);
        }

        try {
            $result = $action->handle([
                'siret' => $this->siret,
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'first_name' => $validated['firstName'] ?? null,
                'last_name' => $validated['lastName'] ?? null,
                'activity' => $validated['activity'] ?? null,
                'company_name' => $validated['companyName'] ?? null,
            ]);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['siret' => $e->getMessage()]);
        }

        // Un compte actif est connecté immédiatement et accède à l'espace pro.
        if ($result['sirene']->isActive()) {
            Auth::login($result['user']);
            session()->regenerate();
            session()->flash('status', 'Bienvenue ! Votre compte pro est actif.');

            return redirect('/compte');
        }

        // Compte en attente de validation SIRET : on ne connecte PAS l'utilisateur.
        // Le connecter puis rediriger vers /compte provoquerait une boucle de
        // redirection (pro.customer renvoie les comptes non-actifs vers /connexion,
        // que redirect.if.pro renvoie à son tour vers /compte pour un user authentifié).
        session()->flash('status', 'Compte créé. Nous finalisons la vérification de votre SIRET, vous serez notifié par e-mail dès activation.');

        return redirect('/connexion');
    }

    #[Layout('customer-auth::layouts.auth')]
    public function render(): View
    {
        return view('customer-auth::livewire.register-page');
    }
}
