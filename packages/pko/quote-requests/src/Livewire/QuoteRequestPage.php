<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Pko\QuoteRequests\Actions\StoreQuoteRequest;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Support\FileSignature;
use Pko\QuoteRequests\Support\QuoteContext;

/**
 * Formulaire de devis unique : demande générale, projet, identification d'une
 * pièce par photo. Le mode change les textes et les champs mis en avant, pas
 * l'état : passer d'un mode à l'autre conserve toute la saisie.
 *
 * Aucun prix n'est affiché dans ce parcours.
 */
class QuoteRequestPage extends Component
{
    use WithFileUploads;

    #[Url(except: 'general')]
    public string $mode = 'general';

    /**
     * Contexte d'origine résolu côté serveur au montage. Verrouillé : le client
     * ne peut pas le réécrire pour rattacher la demande à un autre produit.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $context = [];

    public string $name = '';

    public string $company = '';

    public string $email = '';

    public string $phone = '';

    public string $postcode = '';

    public string $message = '';

    /** @var array<int, array{label: string, quantity: int|string}> */
    public array $lines = [];

    /** Fichiers retenus (validés un par un à l'ajout). @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /** Sélection en cours dans le champ fichier, fusionnée dans $attachments. @var array<int, TemporaryUploadedFile> */
    public array $newAttachments = [];

    /** Lignes produit dépliées à la demande dans le mode photo. */
    public bool $showLines = false;

    public bool $consent = false;

    /** Honeypot anti-spam — doit rester vide (champ caché aux humains). */
    public string $website = '';

    public bool $sent = false;

    public ?string $sentNumber = null;

    public function mount(): void
    {
        $this->mode = QuoteRequestMode::fromQuery($this->mode)->value;
        $this->context = QuoteContext::fromQuery(request()->query());

        $this->prefillFromCustomer();

        $first = $this->context['reference'] ?? $this->context['product_name'] ?? null;
        $label = is_string($first) ? $first : '';
        if ($label !== '' && ($this->context['reference'] ?? null) && ($this->context['product_name'] ?? null)) {
            $label = $this->context['reference'].' — '.$this->context['product_name'];
        }
        $this->lines = [['label' => $label, 'quantity' => 1]];
    }

    public function setMode(string $mode): void
    {
        $this->mode = QuoteRequestMode::fromQuery($mode)->value;
        $this->resetValidation();
    }

    public function updatedMode(): void
    {
        $this->mode = QuoteRequestMode::fromQuery($this->mode)->value;
    }

    public function addLine(): void
    {
        if (count($this->lines) < (int) config('quote-requests.max_lines', 30)) {
            $this->lines[] = ['label' => '', 'quantity' => 1];
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines[] = ['label' => '', 'quantity' => 1];
        }
    }

    /**
     * Chaque sélection est validée fichier par fichier (taille + signature
     * binaire) : un fichier refusé est écarté avec un message, les autres sont
     * conservés.
     */
    public function updatedNewAttachments(): void
    {
        $max = $this->maxFiles();
        $errors = [];

        foreach ($this->newAttachments as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            $error = $this->attachmentError($file);
            if ($error === null && count($this->attachments) >= $max) {
                $error = "Vous pouvez joindre au maximum {$max} fichiers.";
            }

            if ($error !== null) {
                $errors[] = $error;
                $file->delete();

                continue;
            }

            $this->attachments[] = $file;
        }

        $this->newAttachments = [];
        $this->resetValidation('attachments');

        if ($errors !== []) {
            $this->addError('attachments', implode(' ', array_unique($errors)));
        }
    }

    public function removeAttachment(int $index): void
    {
        $file = $this->attachments[$index] ?? null;
        if ($file instanceof TemporaryUploadedFile) {
            $file->delete();
        }
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(StoreQuoteRequest $store): void
    {
        // Honeypot : succès simulé, rien n'est enregistré ni envoyé.
        if ($this->website !== '') {
            $this->resetForm();
            $this->sent = true;

            return;
        }

        $rateKey = 'quote-request:'.request()->ip();
        $maxAttempts = (int) config('quote-requests.rate_limit.max_attempts', 5);
        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($rateKey) / 60);

            throw ValidationException::withMessages([
                'form' => "Trop de demandes envoyées depuis votre connexion. Réessayez dans {$minutes} min ou contactez-nous par téléphone.",
            ]);
        }

        $mode = QuoteRequestMode::fromQuery($this->mode);
        $lines = $this->filledLines();

        $this->validate($this->rules($mode, $lines !== []), $this->messages());
        $this->validateAttachments($mode);

        RateLimiter::hit($rateKey, (int) config('quote-requests.rate_limit.decay_seconds', 3600));

        $request = $store->handle(
            mode: $mode,
            data: [
                'name' => trim($this->name),
                'company' => $this->company,
                'email' => trim($this->email),
                'phone' => $this->phone,
                'postcode' => $this->postcode,
                'message' => trim($this->message),
            ],
            lines: $lines,
            context: $this->context,
            files: $this->attachments,
            user: Auth::user(),
            ip: request()->ip(),
        );

        $this->resetForm();
        $this->sent = true;
        $this->sentNumber = $request->number();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(?QuoteRequestMode $mode = null, bool $hasLines = false): array
    {
        $mode ??= QuoteRequestMode::fromQuery($this->mode);
        // Une liste de références suffit à une demande générale ou projet ;
        // sinon la description est indispensable pour traiter la demande.
        $messageRule = $hasLines ? ['nullable', 'string', 'max:5000'] : ['required', 'string', 'min:10', 'max:5000'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => [$mode === QuoteRequestMode::Project ? 'required' : 'nullable', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'message' => $messageRule,
            'lines' => ['array', 'max:'.(int) config('quote-requests.max_lines', 30)],
            'lines.*.label' => ['nullable', 'string', 'max:190'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Merci d\'indiquer votre nom.',
            'email.required' => 'Merci d\'indiquer votre e-mail.',
            'email.email' => 'L\'adresse e-mail n\'est pas valide.',
            'phone.required' => 'Merci d\'indiquer un téléphone pour échanger sur votre projet.',
            'message.required' => 'Merci de décrire votre demande (ou d\'indiquer au moins une référence).',
            'message.min' => 'Votre description doit contenir au moins 10 caractères.',
            'lines.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'lines.*.quantity.min' => 'La quantité doit être au moins de 1.',
            'consent.accepted' => 'Vous devez accepter la politique de confidentialité.',
        ];
    }

    #[Layout('layouts.storefront')]
    public function render(): View
    {
        return view('pko-quote-requests::livewire.quote-request-page', [
            'currentMode' => QuoteRequestMode::fromQuery($this->mode),
            'modes' => QuoteRequestMode::cases(),
            'hasContext' => QuoteContext::isFilled($this->context),
            'linesVisible' => $this->showLines
                || QuoteRequestMode::fromQuery($this->mode)->showsLinesByDefault()
                || $this->filledLines() !== [],
            'accept' => FileSignature::acceptAttribute(),
            'maxFiles' => $this->maxFiles(),
            'maxSizeMb' => (int) round($this->maxSizeKb() / 1024),
        ]);
    }

    private function prefillFromCustomer(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $customer = method_exists($user, 'latestCustomer') ? $user->latestCustomer() : null;
        $customerName = $customer ? trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) : '';

        $this->name = (string) ($customerName !== '' ? $customerName : ($user->name ?? ''));
        $this->email = (string) ($user->email ?? '');
        $this->company = (string) ($customer->company_name ?? '');
        $this->phone = (string) ($customer?->meta['phone'] ?? '');
        $this->postcode = (string) ($customer->pko_postcode ?? '');
    }

    /** @return array<int, array{label: string, quantity: int}> */
    private function filledLines(): array
    {
        $lines = [];
        foreach ($this->lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $lines[] = ['label' => mb_substr($label, 0, 190), 'quantity' => max(1, (int) ($line['quantity'] ?? 1))];
        }

        return $lines;
    }

    /**
     * Contrôle final des pièces jointes au moment de l'envoi : on ne fait pas
     * confiance à l'état du composant (le tableau peut avoir été manipulé).
     */
    private function validateAttachments(QuoteRequestMode $mode): void
    {
        $files = array_values(array_filter($this->attachments, fn (mixed $f): bool => $f instanceof TemporaryUploadedFile));
        $this->attachments = $files;

        if (count($files) > $this->maxFiles()) {
            throw ValidationException::withMessages(['attachments' => "Vous pouvez joindre au maximum {$this->maxFiles()} fichiers."]);
        }

        $hasImage = false;
        foreach ($files as $file) {
            $error = $this->attachmentError($file);
            if ($error !== null) {
                throw ValidationException::withMessages(['attachments' => $error]);
            }
            $type = FileSignature::detect((string) $file->getRealPath(), $file->getClientOriginalName());
            $hasImage = $hasImage || in_array($type, ['jpg', 'png', 'webp'], true);
        }

        if ($mode->requiresAttachment() && ! $hasImage) {
            throw ValidationException::withMessages(['attachments' => 'Ajoutez au moins une photo de la pièce (JPG, PNG ou WebP).']);
        }
    }

    private function attachmentError(TemporaryUploadedFile $file): ?string
    {
        $name = $file->getClientOriginalName();

        if ((int) $file->getSize() > $this->maxSizeKb() * 1024) {
            return "« {$name} » dépasse la taille maximale de ".(int) round($this->maxSizeKb() / 1024).' Mo.';
        }

        if (FileSignature::detect((string) $file->getRealPath(), $name) === null) {
            return "« {$name} » n'est pas un format accepté (photo JPG/PNG/WebP, PDF, DWG, CSV ou XLSX).";
        }

        return null;
    }

    private function maxFiles(): int
    {
        return max(1, (int) config('quote-requests.attachments.max_files', 8));
    }

    private function maxSizeKb(): int
    {
        return max(1, (int) config('quote-requests.attachments.max_size_kb', 10240));
    }

    private function resetForm(): void
    {
        $this->reset(['message', 'consent', 'website', 'attachments', 'newAttachments']);
        $this->lines = [['label' => '', 'quantity' => 1]];
        if (Auth::guest()) {
            $this->reset(['name', 'company', 'email', 'phone', 'postcode']);
        }
    }
}
