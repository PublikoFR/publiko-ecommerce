<div>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-neutral-900">Créer un compte pro</h1>
        <p class="mt-2 text-sm text-neutral-600">Réservé aux installateurs et entreprises du bâtiment.</p>
    </div>

    <x-ui.card padding="lg">
        <form wire:submit="submit" class="space-y-5">
            <x-ui.alert variant="info">
                <strong>Vérification SIRET automatique</strong> via la base INSEE. Vos informations société seront récupérées et préremplies dès validation.
            </x-ui.alert>

            <div class="grid grid-cols-1 gap-5">
                <x-ui.input wire:model="siret" label="SIRET (14 chiffres)" placeholder="12345678901234" required inputmode="numeric" :error="$errors->first('siret')" />

                <x-ui.input wire:model="companyName" label="Raison sociale" placeholder="Optionnel — détecté automatiquement" :error="$errors->first('companyName')" />

                <x-ui.input wire:model="activity" label="Activité / secteur" placeholder="Ex : installateur portails, automatismes…" :error="$errors->first('activity')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-ui.input wire:model="firstName" label="Prénom" :error="$errors->first('firstName')" />
                <x-ui.input wire:model="lastName" label="Nom" :error="$errors->first('lastName')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-ui.input wire:model="email" label="E-mail pro" type="email" required :error="$errors->first('email')" />
                <x-ui.input wire:model="phone" label="Téléphone" :error="$errors->first('phone')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-ui.input wire:model="password" label="Mot de passe (min. 8 car.)" type="password" required :error="$errors->first('password')" />
                <x-ui.input wire:model="passwordConfirmation" label="Confirmer" type="password" required />
            </div>

            <x-ui.checkbox wire:model="terms" :error="$errors->first('terms')">
                J'accepte les <a href="/pages/cgv" class="text-primary-600 hover:underline">conditions générales</a> et la <a href="/pages/politique-donnees" class="text-primary-600 hover:underline">politique de données</a>.
            </x-ui.checkbox>
            @error('terms')<p class="text-sm text-danger-600 -mt-3">{{ $message }}</p>@enderror

            <x-ui.button type="submit" variant="primary" size="lg" class="w-full">Créer mon compte pro</x-ui.button>
        </form>
    </x-ui.card>

    <p class="mt-6 text-center text-sm text-neutral-600">
        Déjà inscrit ?
        <a href="/connexion" class="font-semibold text-primary-600 hover:text-primary-700" wire:navigate>Se connecter</a>
    </p>
</div>
