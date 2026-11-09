{{-- Après le clic sur le lien de vérification : e-mail confirmé, compte en attente de validation (FEAT-006). --}}
@component('customer-auth::layouts.auth', ['title' => 'Adresse e-mail confirmée — '.brand_name()])
    <x-ui.card padding="lg" class="text-center">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-success-50 text-success-600">
            <x-ui.icon name="badge-check" class="w-7 h-7" />
        </div>

        <h1 class="font-display font-bold text-2xl text-neutral-900">Adresse e-mail confirmée</h1>

        @if ($accountActive)
            <p class="mt-3 text-sm text-neutral-600">
                Merci ! Votre compte pro est actif : vous pouvez vous connecter à votre espace.
            </p>

            <div class="mt-6">
                <x-ui.button href="{{ route('login') }}" variant="accent" size="lg" fullWidth>Me connecter</x-ui.button>
            </div>
        @else
            <p class="mt-3 text-sm text-neutral-600">
                Merci ! Notre équipe va maintenant étudier votre demande et valider votre compte pro.
                Nous vous recontacterons très prochainement.
            </p>
            <p class="mt-3 text-sm text-neutral-600">
                Vous recevrez un e-mail dès que votre compte sera validé : vous pourrez alors vous connecter à {{ brand_name() }}.
            </p>

            <div class="mt-6">
                <x-ui.button href="{{ url('/') }}" variant="secondary" fullWidth>Retour à l'accueil</x-ui.button>
            </div>
        @endif
    </x-ui.card>
@endcomponent
