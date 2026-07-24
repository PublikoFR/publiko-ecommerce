<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Livewire;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Pko\CustomerAuth\Mail\EmailVerificationMail;
use Pko\CustomerAuth\Support\ProAccess;
use Throwable;

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

        // Compte non-actif (e-mail non vérifié, SIRET en attente, hors groupe) :
        // la connexion est REFUSÉE, pas seulement redirigée. Le laisser
        // authentifié produisait une « semi-connexion » — nom affiché sous le
        // profil, mais aucune page accessible. Un compte pending ne doit jamais
        // dépasser le formulaire de connexion (seules exceptions : impersonation
        // admin et auto-login juste après l'inscription).
        $user = Auth::user();
        $reason = ProAccess::denialReason($user);

        if ($reason !== null) {
            $resent = $this->resendVerificationLink($user);

            // `logout()` seul : il retire la clé d'authentification et le cookie
            // « se souvenir de moi ». Surtout PAS d'`invalidate()` /
            // `regenerateToken()` ici — cela périmerait le token CSRF de la page
            // de connexion encore affichée, et la tentative suivante partirait en
            // 419 « This page has expired ». `Auth::attempt()` a déjà régénéré
            // l'id de session juste avant, il n'y a pas de risque de fixation.
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => $resent
                    ? $reason.' Un nouveau lien de vérification vient de vous être envoyé.'
                    : $reason,
            ]);
        }

        session()->regenerate();

        return redirect($this->intended ?: '/compte');
    }

    /**
     * Renvoie le lien de vérification si c'est bien ce qui bloque le compte.
     * Un échec d'envoi ne doit pas empêcher le refus de connexion : on avale
     * l'exception et on se contente de ne pas l'annoncer à l'utilisateur.
     */
    private function resendVerificationLink(?Authenticatable $user): bool
    {
        if ($user === null || ! method_exists($user, 'hasVerifiedEmail') || $user->hasVerifiedEmail()) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new EmailVerificationMail($user));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    #[Layout('customer-auth::layouts.auth')]
    public function render(): View
    {
        return view('customer-auth::livewire.login-page');
    }
}
