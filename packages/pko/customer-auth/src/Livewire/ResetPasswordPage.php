<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ResetPasswordPage extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request('email', '');
    }

    public function submit(): mixed
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed:passwordConfirmation',
            'token' => 'required',
        ]);

        $status = Password::broker()->reset([
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'token' => $this->token,
        ], function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Impossible de réinitialiser le mot de passe.']);
        }

        session()->flash('status', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');

        return redirect('/connexion');
    }

    #[Layout('customer-auth::layouts.auth')]
    public function render(): View
    {
        return view('customer-auth::livewire.reset-password-page');
    }
}
