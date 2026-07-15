@php
    $phone = brand_setting('contact.phone', config('storefront.contact.phone'));
    $email = brand_setting('contact.email', config('storefront.contact.email'));
    $tagline = brand_setting('contact.tagline', config('storefront.contact.tagline'));
@endphp

<section class="py-6 md:py-10">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb class="mb-6" :items="[
            ['label' => 'Contact'],
        ]" />

        <div class="max-w-2xl mb-8 md:mb-10">
            <h1 class="font-display font-bold text-3xl md:text-4xl text-neutral-900">Contactez-nous</h1>
            <p class="mt-3 text-neutral-600">
                Une question sur un produit, un devis ou une commande&nbsp;? Notre équipe vous répond au plus vite.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[340px_minmax(0,1fr)] gap-6 lg:gap-10">
            {{-- Coordonnées --}}
            <aside class="space-y-4">
                <div class="relative overflow-hidden rounded-2xl bg-primary-600 text-white p-6">
                    <span class="wk-decor wk-decor--br wk-decor--lime" style="--wk-decor-size: 200px; --wk-decor-opacity: 0.10;"></span>
                    <div class="relative">
                    @if ($tagline)
                        <p class="text-sm text-accent-300 font-semibold">{{ $tagline }}</p>
                    @endif
                    @if ($phone)
                        <a href="tel:{{ preg_replace('/\s/', '', $phone) }}" class="mt-2 block font-display font-bold text-2xl hover:text-accent-300 transition">
                            {{ $phone }}
                        </a>
                    @endif
                    @if ($email)
                        <a href="mailto:{{ $email }}" class="mt-4 inline-flex items-center gap-2 text-sm text-primary-50 hover:text-white transition">
                            <x-ui.icon name="headset" class="w-4 h-4 shrink-0" />
                            {{ $email }}
                        </a>
                    @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200 p-6">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
                            <x-ui.icon name="truck" class="w-5 h-5" />
                        </span>
                        <div class="text-sm">
                            <p class="font-semibold text-neutral-900">Commandes &amp; livraisons</p>
                            <p class="text-neutral-500 mt-0.5">Un suivi de commande&nbsp;? Précisez votre numéro de commande dans le message.</p>
                        </div>
                    </div>
                </div>
            </aside>

            {{-- Formulaire --}}
            <div class="rounded-2xl border border-neutral-200 p-6 md:p-8">
                @if ($sent)
                    <x-ui.alert variant="success" title="Message envoyé">
                        Merci&nbsp;! Votre message a bien été transmis à notre équipe. Nous vous répondrons dans les meilleurs délais.
                    </x-ui.alert>
                    <div class="mt-5">
                        <x-ui.button variant="secondary" size="sm" wire:click="$set('sent', false)">
                            Envoyer un autre message
                        </x-ui.button>
                    </div>
                @else
                    <form wire:submit.prevent="submit" class="space-y-5">
                        {{-- Honeypot anti-spam : masqué aux humains, piège à bots. --}}
                        <div class="hidden" aria-hidden="true">
                            <label>Ne pas remplir<input type="text" wire:model="website" tabindex="-1" autocomplete="off" /></label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-ui.input
                                label="Nom complet *"
                                wire:model="name"
                                placeholder="Jean Dupont"
                                autocomplete="name"
                                :error="$errors->first('name')"
                            />
                            <x-ui.input
                                label="E-mail *"
                                type="email"
                                wire:model="email"
                                placeholder="jean.dupont@exemple.fr"
                                autocomplete="email"
                                :error="$errors->first('email')"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-ui.input
                                label="Téléphone"
                                type="tel"
                                wire:model="phone"
                                placeholder="06 12 34 56 78"
                                autocomplete="tel"
                                :error="$errors->first('phone')"
                            />
                            <x-ui.select label="Sujet *" wire:model="subject" :error="$errors->first('subject')">
                                <option value="">Choisir un sujet…</option>
                                <option value="Demande d'information">Demande d'information</option>
                                <option value="Demande de devis">Demande de devis</option>
                                <option value="Suivi de commande">Suivi de commande</option>
                                <option value="Service après-vente">Service après-vente</option>
                                <option value="Autre">Autre</option>
                            </x-ui.select>
                        </div>

                        <x-ui.textarea
                            label="Votre message *"
                            wire:model="message"
                            rows="6"
                            placeholder="Décrivez votre demande…"
                            :error="$errors->first('message')"
                        />

                        <div>
                            <x-ui.checkbox wire:model="consent">
                                J'accepte que mes informations soient utilisées pour traiter ma demande, conformément à la politique de confidentialité.
                            </x-ui.checkbox>
                            @error('consent')
                                <p class="mt-1.5 text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-1">
                            <x-ui.button
                                type="submit"
                                variant="accent"
                                size="lg"
                                iconRight="arrow-right"
                                wire:target="submit"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="submit">Envoyer le message</span>
                                <span wire:loading wire:target="submit">Envoi en cours…</span>
                            </x-ui.button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
