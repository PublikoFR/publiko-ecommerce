<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Actions\RegisterProCustomer;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\Status;

class RegisterPage extends Component
{
    public string $siret = '';

    /** Établissement confirmé actif par l'INSEE (vérif async au blur du SIRET). */
    public bool $sireneVerified = false;

    /** Message d'erreur de la vérification SIRET async (null si OK / pas encore vérifié). */
    public ?string $sireneError = null;

    public ?string $companyName = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public string $email = '';

    public string $phone = '';

    public string $activity = '';

    /** Groupe « métier » choisi dans la liste déroulante (facultatif). */
    public ?int $metierGroupId = null;

    public string $street = '';

    public string $postcode = '';

    public string $city = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $terms = false;

    public ?array $sireneSnapshot = null;

    public function rules(): array
    {
        return [
            'siret' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'companyName' => ['nullable', 'string', 'max:200'],
            'activity' => ['nullable', 'string', 'max:200'],
            'metierGroupId' => ['nullable', 'integer', 'exists:lunar_customer_groups,id'],
            'street' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed:passwordConfirmation'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Vérification SIRET asynchrone déclenchée au blur du champ (wire:model.blur).
     * Si l'établissement est actif, préremplit les champs société encore vides.
     */
    public function updatedSiret(): void
    {
        $this->sireneError = null;
        $this->sireneVerified = false;

        $digits = preg_replace('/\D/', '', (string) $this->siret) ?? '';
        $this->siret = $digits;

        if ($digits === '') {
            return;
        }

        if (! SireneClient::validateSiret($digits)) {
            $this->sireneError = 'SIRET invalide : 14 chiffres attendus (clé de contrôle incorrecte).';

            return;
        }

        $result = app(SireneClient::class)->verify($digits);

        if ($result->status === Status::Inactive) {
            $this->sireneError = 'Cet établissement ne semble pas actif dans la base INSEE. Vérifiez le numéro.';

            return;
        }

        if ($result->status === Status::Active) {
            $this->sireneVerified = true;

            // Préremplissage : on ne remplace que les champs laissés vides par
            // l'utilisateur (il garde la main sur ce qu'il a déjà saisi).
            $this->companyName = $this->companyName ?: $result->raisonSociale;
            $this->activity = $this->activity !== '' ? $this->activity : ($result->nafLabel ?? $result->nafCode ?? '');
            $this->street = $this->street !== '' ? $this->street : ($result->addressLine1 ?? '');
            $this->postcode = $this->postcode !== '' ? $this->postcode : ($result->postcode ?? '');
            $this->city = $this->city !== '' ? $this->city : ($result->city ?? '');
        }
        // Status::Pending (API désactivée / indisponible) : ni erreur ni préremplissage,
        // la revalidation serveur tranchera au submit.
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
                'customer_group_id' => $validated['metierGroupId'] ?? null,
                'company_name' => $validated['companyName'] ?? null,
                'street' => $validated['street'] ?? null,
                'postcode' => $validated['postcode'] ?? null,
                'city' => $validated['city'] ?? null,
                // Le SIRET (INSEE) garantit une entreprise française : pas de champ pays.
                'country' => 'FR',
            ]);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['siret' => $e->getMessage()]);
        }

        // SIRET revalidé actif côté serveur → on connecte immédiatement pour
        // limiter la friction. Le compte reste néanmoins « pending » : il ne
        // deviendra pleinement actif qu'une fois l'adresse e-mail vérifiée (lien
        // du mail de bienvenue). On redirige donc vers l'accueil (et non /compte,
        // qui est gated tant que le compte n'est pas actif) avec un rappel.
        if ($result['sirene']->isActive()) {
            Auth::login($result['user']);
            session()->regenerate();
            session()->flash('status', 'Bienvenue ! Votre compte a bien été créé. Pour l\'activer, validez votre adresse e-mail en cliquant sur le lien reçu par e-mail.');

            return redirect('/');
        }

        // Compte en attente de validation SIRET : on ne connecte PAS l'utilisateur.
        // Le connecter puis rediriger vers /compte provoquerait une boucle de
        // redirection (pro.customer renvoie les comptes non-actifs vers /connexion,
        // que redirect.if.pro renvoie à son tour vers /compte pour un user authentifié).
        session()->flash('status', 'Compte créé. Nous finalisons la vérification de votre SIRET, vous serez notifié par e-mail dès activation.');

        return redirect('/connexion');
    }

    #[Layout('customer-auth::layouts.auth', ['containerClass' => 'max-w-3xl'])]
    public function render(): View
    {
        return view('customer-auth::livewire.register-page', [
            'metierGroups' => CustomerGroup::query()
                ->where('pko_is_metier', true)
                ->orderBy('name')
                ->pluck('name', 'id'),
        ]);
    }
}
