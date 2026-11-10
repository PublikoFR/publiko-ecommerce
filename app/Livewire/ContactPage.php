<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Pko\QuoteRequests\Actions\StoreContactMessage;
use Throwable;

class ContactPage extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    public bool $consent = false;

    /** Honeypot anti-spam — doit rester vide (champ caché aux humains). */
    public string $website = '';

    public bool $sent = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
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
            'subject.required' => 'Merci d\'indiquer un sujet.',
            'message.required' => 'Merci d\'écrire votre message.',
            'message.min' => 'Votre message doit contenir au moins 10 caractères.',
            'consent.accepted' => 'Vous devez accepter la politique de confidentialité.',
        ];
    }

    public function submit(): void
    {
        // Honeypot : si le champ caché est rempli, c'est un bot → on simule le
        // succès sans rien envoyer ni valider (pas de signal exploitable).
        if ($this->website !== '') {
            $this->resetForm();
            $this->sent = true;

            return;
        }

        $data = $this->validate();

        // Enregistré d'abord : le message reste suivi dans l'admin (page
        // « Contacts ») même si l'envoi de l'e-mail échoue.
        app(StoreContactMessage::class)->handle($data, Auth::user(), request()->ip());

        $to = trim((string) brand_setting('contact.email', config('storefront.contact.email')));
        if ($to === '') {
            $to = (string) config('mail.from.address', '');
        }

        if ($to !== '') {
            try {
                Mail::to($to)->send(new ContactMessage(
                    senderName: $data['name'],
                    senderEmail: $data['email'],
                    senderPhone: $data['phone'] ?? '',
                    subjectLine: $data['subject'],
                    body: $data['message'],
                ));
            } catch (Throwable $e) {
                // Message déjà enregistré : une panne SMTP ne doit ni afficher
                // d'erreur au visiteur ni l'inciter à renvoyer (doublon).
                report($e);
            }
        }

        $this->resetForm();
        $this->sent = true;
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'email', 'phone', 'subject', 'message', 'consent', 'website']);
    }

    #[Layout('layouts.storefront')]
    public function render(): View
    {
        return view('livewire.contact-page');
    }
}
