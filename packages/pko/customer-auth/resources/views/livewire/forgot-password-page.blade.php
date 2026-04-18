<div>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-neutral-900">Mot de passe oublié</h1>
        <p class="mt-2 text-sm text-neutral-600">Nous vous enverrons un lien pour en définir un nouveau.</p>
    </div>

    <x-ui.card padding="lg">
        @if ($sentMessage)
            <x-ui.alert variant="success">{{ $sentMessage }}</x-ui.alert>
        @else
            <form wire:submit="sendLink" class="space-y-5">
                <x-ui.input wire:model="email" label="Adresse e-mail" type="email" required :error="$errors->first('email')" />
                <x-ui.button type="submit" variant="primary" size="lg" class="w-full">Envoyer le lien</x-ui.button>
            </form>
        @endif
    </x-ui.card>

    <p class="mt-6 text-center text-sm">
        <a href="/connexion" class="font-semibold text-primary-600 hover:text-primary-700" wire:navigate>← Retour à la connexion</a>
    </p>
</div>
