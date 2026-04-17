<?php

declare(strict_types=1);

namespace Mde\CustomerAuth\Livewire;

use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ForgotPasswordPage extends Component
{
    public string $email = '';

    public ?string $sentMessage = null;

    public function sendLink(): void
    {
        $this->validate(['email' => 'required|email']);

        $status = Password::broker()->sendResetLink(['email' => $this->email]);

        $this->sentMessage = $status === Password::RESET_LINK_SENT
            ? 'Un lien de réinitialisation vous a été envoyé.'
            : 'Impossible d\'envoyer le lien. Vérifiez votre adresse.';
    }

    #[Layout('customer-auth::layouts.auth')]
    public function render(): View
    {
        return view('customer-auth::livewire.forgot-password-page');
    }
}
