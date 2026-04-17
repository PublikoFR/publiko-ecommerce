<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-neutral-900">Mon profil</h1>
        <p class="text-neutral-600 mt-1 text-sm">Gérez vos informations personnelles et votre mot de passe.</p>
    </div>

    @if ($saved)
        <x-ui.alert variant="success">{{ $saved }}</x-ui.alert>
    @endif

    <x-ui.card padding="lg">
        <form wire:submit="save" class="space-y-5">
            <h2 class="font-bold text-neutral-900 mb-1">Informations personnelles</h2>
            <x-ui.input wire:model="name" label="Nom complet" required :error="$errors->first('name')" />
            <x-ui.input wire:model="email" label="Adresse e-mail" type="email" required :error="$errors->first('email')" />
            <div class="flex justify-end"><x-ui.button type="submit" variant="primary">Enregistrer</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card padding="lg">
        <form wire:submit="changePassword" class="space-y-5">
            <h2 class="font-bold text-neutral-900 mb-1">Mot de passe</h2>
            <x-ui.input wire:model="currentPassword" label="Mot de passe actuel" type="password" :error="$errors->first('currentPassword')" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-ui.input wire:model="newPassword" label="Nouveau mot de passe" type="password" :error="$errors->first('newPassword')" />
                <x-ui.input wire:model="newPasswordConfirmation" label="Confirmer" type="password" />
            </div>
            <div class="flex justify-end"><x-ui.button type="submit" variant="primary">Modifier le mot de passe</x-ui.button></div>
        </form>
    </x-ui.card>
</div>
