<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Livewire;

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
    /** Clé du flash de session portant l'e-mail inscrit (page « vérifiez vos e-mails »). */
    public const REGISTERED_EMAIL_SESSION_KEY = 'pko.registered_email';

    public string $siret = '';

    /** Établissement confirmé actif par l'INSEE (vérif async au blur du SIRET). */
    public bool $sireneVerified = false;

    /** Message d'erreur de la vérification SIRET async (null si OK / pas encore vérifié). */
    public ?string $sireneError = null;

    public ?string $companyName = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public string $email = '';

    /** Ressaisie de l'e-mail pour éviter les fautes de frappe. Jamais persistée. */
    public string $emailConfirmation = '';

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
            // Comparaison insensible à la casse et aux espaces de bord : une
            // majuscule ou un espace parasite n'est pas une faute de frappe.
            'emailConfirmation' => ['required', 'email', function (string $attribute, mixed $value, \Closure $fail): void {
                if (self::normalizeEmail((string) $value) !== self::normalizeEmail($this->email)) {
                    $fail('Les deux adresses e-mail ne correspondent pas.');
                }
            }],
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

    public function validationAttributes(): array
    {
        return ['emailConfirmation' => 'confirmation de l\'e-mail'];
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
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

        // La confirmation n'est jamais persistée : on peut la nettoyer librement
        // (un espace collé en bord ferait échouer la règle `email`).
        $this->emailConfirmation = trim($this->emailConfirmation);

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

        // PAS de connexion automatique (FEAT-006) : chaque compte pro est validé
        // manuellement par l'équipe. Le client doit d'abord confirmer son e-mail
        // (lien reçu), puis attendre la validation — il ne se connecte qu'ensuite.
        // L'adresse passe par un flash de session pour être affichée sur la page
        // suivante sans apparaître dans l'URL (historique, logs, Referer).
        session()->flash(self::REGISTERED_EMAIL_SESSION_KEY, $result['user']->email);

        return redirect()->route('register.check-inbox');
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
