<div>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-neutral-900">Nouveau mot de passe</h1>
    </div>

    <x-ui.card padding="lg">
        <form wire:submit="submit" class="space-y-5">
            <x-ui.input wire:model="email" label="Adresse e-mail" type="email" required :error="$errors->first('email')" />
            <x-ui.input wire:model="password" label="Nouveau mot de passe" type="password" required :error="$errors->first('password')" />
            <x-ui.input wire:model="passwordConfirmation" label="Confirmer le mot de passe" type="password" required />
            <x-ui.button type="submit" variant="primary" size="lg" class="w-full">Réinitialiser</x-ui.button>
        </form>
    </x-ui.card>
</div>
