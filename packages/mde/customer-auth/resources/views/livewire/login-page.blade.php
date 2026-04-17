<div>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-neutral-900">Connexion pro</h1>
        <p class="mt-2 text-sm text-neutral-600">Accédez à votre espace revendeur MDE.</p>
    </div>

    <x-ui.card padding="lg">
        <form wire:submit="authenticate" class="space-y-5">
            <x-ui.input wire:model="email" label="Adresse e-mail" type="email" required autofocus :error="$errors->first('email')" />

            <div>
                <x-ui.input wire:model="password" label="Mot de passe" type="password" required :error="$errors->first('password')" />
                <div class="mt-2 text-right text-xs">
                    <a href="/mot-de-passe-oublie" class="text-primary-600 hover:text-primary-700 font-semibold" wire:navigate>Mot de passe oublié ?</a>
                </div>
            </div>

            <x-ui.checkbox wire:model="remember" label="Se souvenir de moi" />

            <x-ui.button type="submit" variant="primary" size="lg" class="w-full">Se connecter</x-ui.button>
        </form>
    </x-ui.card>

    <p class="mt-6 text-center text-sm text-neutral-600">
        Pas encore de compte pro ?
        <a href="/inscription" class="font-semibold text-primary-600 hover:text-primary-700" wire:navigate>Créer un compte installateur</a>
    </p>
</div>
