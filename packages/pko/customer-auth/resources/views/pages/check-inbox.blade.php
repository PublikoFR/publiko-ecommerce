{{-- Après l'inscription : le compte n'est pas connecté (validation manuelle, FEAT-006). --}}
@component('customer-auth::layouts.auth', ['title' => 'Vérifiez votre boîte mail — '.brand_name()])
    <x-ui.card padding="lg" class="text-center">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600">
            <x-ui.icon name="mail" class="w-7 h-7" />
        </div>

        <h1 class="font-display font-bold text-2xl text-neutral-900">Vérifiez votre boîte mail</h1>

        <p class="mt-3 text-sm text-neutral-600">
            Votre demande de compte pro a bien été enregistrée.
            @if ($email)
                Un lien de confirmation vient d'être envoyé à <strong class="text-neutral-900">{{ $email }}</strong>.
            @else
                Un lien de confirmation vient de vous être envoyé par e-mail.
            @endif
        </p>

        <ol class="mt-6 space-y-3 text-left text-sm text-neutral-700">
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white">1</span>
                <span>Cliquez sur le lien reçu pour confirmer votre adresse e-mail.</span>
            </li>
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-700">2</span>
                <span>Notre équipe étudie votre demande et valide votre compte.</span>
            </li>
            <li class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-700">3</span>
                <span>Vous recevez un e-mail de confirmation et pouvez vous connecter.</span>
            </li>
        </ol>

        <p class="mt-6 text-xs text-neutral-500">
            Rien reçu ? Pensez à vérifier vos courriers indésirables. Une tentative de connexion vous renverra le lien.
        </p>

        <div class="mt-6">
            <x-ui.button href="{{ url('/') }}" variant="secondary" fullWidth>Retour à l'accueil</x-ui.button>
        </div>
    </x-ui.card>
@endcomponent
