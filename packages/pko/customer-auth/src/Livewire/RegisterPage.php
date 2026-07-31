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
use Pko\CustomerAuth\Support\JustRegistered;

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
            // Obligatoire : c'est la raison sociale reprise telle quelle comme
            // company_name du Customer, puis pré-remplie sur l'adresse de
            // commande. Elle n'est renseignée automatiquement que si la
            // vérification INSEE est active (off par défaut) — la laisser
            // facultative produisait des comptes sans raison sociale, et donc un
            // champ « Raison sociale » vide au premier checkout.
            'companyName' => ['required', 'string', 'max:200'],
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

        // Auto-login systématique : une inscription qui aboutit connecte toujours.
        //
        // RÉGRESSION RÉCURRENTE — ne pas reconditionner ce bloc au statut SIRENE.
        // `RegisterProCustomer` a déjà rejeté (DomainException) le seul cas
        // disqualifiant, `Status::Inactive`. Il ne reste donc que `Active` et
        // `Pending`, et `Pending` ne veut PAS dire « en attente de validation » :
        // c'est le statut retourné dès que l'INSEE n'est pas consulté
        // (INSEE_ENABLED=false — le défaut — clé API absente, timeout, 5xx). Gater
        // l'auto-login sur `isActive()` revenait donc à ne JAMAIS auto-connecter
        // sur toute installation sans compte INSEE.
        //
        // C'est cohérent avec ProAccess : le SIRET n'y est pas un critère d'accès,
        // seule la vérification e-mail l'est. Le compte reste `pending` tant que
        // l'e-mail n'est pas confirmé, d'où la redirection vers l'accueil (et non
        // /compte, gated) et le flag JustRegistered qui ouvre l'accès le temps de
        // la session.
        Auth::login($result['user']);
        session()->regenerate();
        // Exception assumée à la règle « un compte pending ne reste jamais
        // connecté » : on ne casse pas le parcours d'inscription.
        JustRegistered::flag();
        session()->flash('status', 'Bienvenue ! Votre compte a bien été créé. Pour l\'activer, validez votre adresse e-mail en cliquant sur le lien reçu par e-mail.');

        return redirect('/');
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
