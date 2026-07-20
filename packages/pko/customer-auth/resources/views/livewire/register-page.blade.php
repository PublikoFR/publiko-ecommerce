<div>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-display font-bold text-neutral-900">Créer un compte pro</h1>
        <p class="mt-2 text-sm text-neutral-600">Réservé aux installateurs et entreprises du bâtiment.</p>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-1 rounded-xl bg-neutral-100 p-1">
        <a href="/connexion" wire:navigate class="rounded-lg py-2.5 text-center text-sm font-semibold text-neutral-500 hover:text-neutral-700 transition">Connexion</a>
        <a href="/inscription" wire:navigate class="rounded-lg py-2.5 text-center text-sm font-semibold bg-white text-primary-700 shadow-sm">Inscription</a>
    </div>

    <x-ui.card padding="lg">
        <form wire:submit="submit" class="space-y-5">
            <x-ui.alert variant="info">
                <strong>Vérification SIRET automatique</strong> via la base INSEE. Vos informations société seront récupérées et préremplies dès validation.
            </x-ui.alert>

            <div class="grid grid-cols-1 gap-5">
                <x-ui.input wire:model.blur="siret" label="SIRET (14 chiffres)" placeholder="12345678901234" required inputmode="numeric" :error="$sireneError ?: $errors->first('siret')">
                    <x-slot:trailing>
                        {{-- Loader pendant l'appel INSEE --}}
                        <svg wire:loading wire:target="siret" class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{-- Établissement confirmé actif --}}
                        @if ($sireneVerified)
                            <svg wire:loading.remove wire:target="siret" class="h-5 w-5 text-success-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @endif
                    </x-slot:trailing>
                </x-ui.input>

                @if ($sireneVerified)
                    <p class="-mt-3 text-sm text-success-700 flex items-center gap-1.5">Établissement vérifié auprès de l'INSEE — informations préremplies ci-dessous.</p>
                @endif

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

            <div class="grid grid-cols-1 gap-5">
                <x-ui.input wire:model="street" label="Adresse" placeholder="Rue, avenue, lieu-dit…" :error="$errors->first('street')" />
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                <x-ui.input wire:model="postcode" label="Code postal" placeholder="75001" inputmode="numeric" class="col-span-1" :error="$errors->first('postcode')" />
                <x-ui.input wire:model="city" label="Ville" placeholder="Paris" class="col-span-1 sm:col-span-2" :error="$errors->first('city')" />
                {{-- Livraison France uniquement : pays verrouillé, affiché en clair (pas de code ISO). --}}
                <x-ui.input label="Pays" value="France" readonly class="col-span-1 bg-neutral-50 text-neutral-500 cursor-not-allowed" hint="France uniquement" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-ui.input wire:model="password" label="Mot de passe (min. 8 car.)" type="password" required :error="$errors->first('password')" />
                <x-ui.input wire:model="passwordConfirmation" label="Confirmer" type="password" required />
            </div>

            <x-ui.checkbox wire:model="terms" :error="$errors->first('terms')">
                J'accepte les <a href="/pages/cgv" class="text-primary-600 hover:underline">conditions générales</a> et la <a href="/pages/politique-donnees" class="text-primary-600 hover:underline">politique de données</a>.
            </x-ui.checkbox>
            @error('terms')<p class="text-sm text-danger-600 -mt-3">{{ $message }}</p>@enderror

            <x-ui.button type="submit" variant="accent" size="lg" fullWidth>Créer mon compte pro</x-ui.button>
        </form>
    </x-ui.card>

    <p class="mt-6 text-center text-sm text-neutral-600">
        Déjà inscrit ?
        <a href="/connexion" class="font-semibold text-primary-600 hover:text-primary-700" wire:navigate>Se connecter</a>
    </p>
</div>
