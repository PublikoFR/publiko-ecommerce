<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Pko\CustomerAuth\Support\ProAccess;

class LoginPage extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    #[Url]
    public ?string $intended = null;

    public function authenticate(): mixed
    {
        $validated = $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        session()->regenerate();

        // Compte authentifié mais pas (encore) pro actif (SIRET en attente, hors
        // groupe) : on ne l'envoie pas vers /compte (qui rebondirait) — accueil +
        // message explicatif.
        $reason = ProAccess::denialReason(Auth::user());
        if ($reason !== null) {
            return redirect('/')->with('status', $reason);
        }

        return redirect($this->intended ?: '/compte');
    }

    #[Layout('customer-auth::layouts.auth')]
    public function render(): View
    {
        return view('customer-auth::livewire.login-page');
    }
}
