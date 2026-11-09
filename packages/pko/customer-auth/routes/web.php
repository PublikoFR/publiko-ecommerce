<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Pko\CustomerAuth\Livewire\ForgotPasswordPage;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Livewire\ResetPasswordPage;
use Pko\CustomerAuth\Mail\CustomerRegisteredAdminMail;
use Pko\CustomerAuth\Support\ProAccess;
use Pko\MailTemplates\Support\AdminRecipient;

Route::middleware(['web', 'redirect.if.pro'])->group(function () {
    Route::get('/connexion', LoginPage::class)->name('login');
    Route::get('/inscription', RegisterPage::class)->name('register');
    Route::get('/mot-de-passe-oublie', ForgotPasswordPage::class)->name('password.request');
    Route::get('/reinitialisation/{token}', ResetPasswordPage::class)->name('password.reset');
});

// Après l'inscription : « vérifiez votre boîte mail ». Page publique — le compte
// n'est jamais connecté à l'inscription (validation manuelle, FEAT-006). L'adresse
// affichée vient d'un flash de session posé par RegisterPage, jamais de l'URL.
Route::middleware('web')
    ->get('/inscription/verifiez-vos-emails', fn () => view('customer-auth::pages.check-inbox', [
        'email' => session(RegisterPage::REGISTERED_EMAIL_SESSION_KEY),
    ]))
    ->name('register.check-inbox');

// Après le clic sur le lien de vérification : « adresse confirmée, notre équipe
// étudie votre compte ». Page publique, idempotente.
Route::middleware('web')
    ->get('/inscription/confirmee', fn () => view('customer-auth::pages.email-verified', [
        'accountActive' => session('pko.account_active') === true,
    ]))
    ->name('register.email-verified');

// Vérification d'e-mail via lien signé (fonctionne sans session préalable, donc
// depuis n'importe quel appareil). Le middleware `signed` valide signature + expiration.
//
// Confirmer son e-mail N'OUVRE PAS l'accès : le compte reste `pending` jusqu'à la
// validation manuelle par l'équipe (action « Valider le compte » en back-office,
// qui envoie alors AccountActivatedMail). Ici on se contente de poser
// `email_verified_at`, de prévenir l'équipe que le compte est prêt à être étudié,
// puis d'afficher la page de confirmation. Pas de connexion automatique.
Route::middleware(['web', 'signed'])
    ->get('/verification-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));

            // Notification équipe : le compte est maintenant prêt à être validé.
            // Envoyée à la vérification (et non à l'inscription) pour ne signaler
            // que des adresses réelles. Un échec SMTP ne bloque pas la vérification.
            foreach ($user->customers()->get() as $customer) {
                if ($customer->getAttribute('pko_status') !== 'pending') {
                    continue;
                }

                try {
                    AdminRecipient::send(new CustomerRegisteredAdminMail($customer, $user), $customer);
                } catch (Throwable $e) {
                    logger()->error('CustomerRegisteredAdminMail failed', ['error' => $e->getMessage()]);
                }
            }
        }

        // Lien rejoué sur un compte déjà validé (ou créé actif en back-office) :
        // la page invite alors à se connecter au lieu d'annoncer une étude.
        $active = $user->customers()->where('pko_status', 'active')->exists();

        return redirect()->route('register.email-verified')->with('pko.account_active', $active);
    })
    ->name('verification.verify');

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

    return redirect('/');
})->name('logout');
