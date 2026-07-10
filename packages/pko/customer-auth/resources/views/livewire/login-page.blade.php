<div>
    <div class="text-center mb-8">
        <h1 class="font-display font-bold text-3xl text-neutral-900">Connexion pro</h1>
        <p class="mt-2 text-sm text-neutral-600">Accédez à votre espace revendeur.</p>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-1 rounded-xl bg-neutral-100 p-1">
        <a href="/connexion" wire:navigate class="rounded-lg py-2.5 text-center text-sm font-semibold bg-white text-primary-700 shadow-sm">Connexion</a>
        <a href="/inscription" wire:navigate class="rounded-lg py-2.5 text-center text-sm font-semibold text-neutral-500 hover:text-neutral-700 transition">Inscription</a>
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

            <x-ui.button type="submit" variant="accent" size="lg" fullWidth>Se connecter</x-ui.button>
        </form>
    </x-ui.card>

    <p class="mt-6 text-center text-sm text-neutral-600">
        Pas encore de compte pro ?
        <a href="/inscription" class="font-semibold text-primary-600 hover:text-primary-700" wire:navigate>Créer un compte pro</a>
    </p>
</div>
