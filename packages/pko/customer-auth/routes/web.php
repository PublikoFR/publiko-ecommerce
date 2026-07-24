<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Pko\CustomerAuth\Livewire\ForgotPasswordPage;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Livewire\ResetPasswordPage;
use Pko\CustomerAuth\Mail\EmailVerificationMail;
use Pko\CustomerAuth\Support\JustRegistered;
use Pko\CustomerAuth\Support\ProAccess;

Route::middleware(['web', 'redirect.if.pro'])->group(function () {
    Route::get('/connexion', LoginPage::class)->name('login');
    Route::get('/inscription', RegisterPage::class)->name('register');
    Route::get('/mot-de-passe-oublie', ForgotPasswordPage::class)->name('password.request');
    Route::get('/reinitialisation/{token}', ResetPasswordPage::class)->name('password.reset');
});

// Vérification d'e-mail via lien signé (fonctionne sans session préalable, donc
// depuis n'importe quel appareil). Le middleware `signed` valide signature + expiration.
Route::middleware(['web', 'signed'])
    ->get('/verification-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));

            // L'e-mail vérifié lève le statut « pending » : le compte devient
            // pleinement actif, à condition que le SIRET soit lui aussi actif
            // (sinon il reste en attente de validation manuelle).
            foreach ($user->customers()->get() as $customer) {
                if ($customer->getAttribute('pko_status') === 'pending'
                    && $customer->getAttribute('sirene_status') === 'active') {
                    $customer->setAttribute('pko_status', 'active');
                    $customer->save();
                }
            }
        }

        Auth::login($user);

        return redirect('/compte')->with('status', 'Votre adresse e-mail a bien été vérifiée. Merci !');
    })
    ->name('verification.verify');

// Renvoi du lien de vérification depuis le bandeau de rappel (utilisateur connecté).
Route::middleware(['web', 'auth', 'throttle:6,1'])
    ->post('/verification-email/renvoyer', function (Request $request) {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back();
        }

        Mail::to($user->email)->send(new EmailVerificationMail($user));

        return back()->with('status', 'Un nouvel e-mail de vérification vous a été envoyé.');
    })
    ->name('verification.send');

// Déconnexion — logout Laravel standard. POST uniquement (un GET serait
// déclenchable par un prefetch). Le lien est un `<form>` POST plein-page : la
// navigation dure abandonne côté navigateur toute requête Livewire en vol, donc
// pas de « session zombie » à neutraliser. Route exemptée de CSRF (bootstrap/app.php)
// pour qu'un token périmé (page cachée par wire:navigate) ne renvoie pas 419.
// Idempotente : rejouée sur une session déjà déconnectée, elle redirige sans erreur.
Route::middleware('web')->post('/deconnexion', function (Request $request) {
    Auth::guard('web')->logout();

    // Impersonation admin : le staff partage le cookie de session. On ne fait
    // alors PAS d'invalidate() (qui éjecterait l'admin de son panel) — on retire
    // seulement la part front (guard web déjà vidé + marqueur d'impersonation).
    if (Auth::guard('staff')->check()) {
        $request->session()->forget(ProAccess::IMPERSONATOR_SESSION_KEY);
    } else {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    // La branche impersonation n'invalide pas la session : on retire donc
    // explicitement le flag « fraîchement inscrit », sans quoi un compte pending
    // pourrait se reconnecter dans la même session (cf. ProAccess).
    JustRegistered::clear();

    return redirect('/');
})->name('logout');
