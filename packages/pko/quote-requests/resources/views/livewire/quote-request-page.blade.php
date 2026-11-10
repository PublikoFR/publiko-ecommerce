{{-- Formulaire de devis unique (packages/pko/quote-requests). Aucun prix affiché. --}}
<section class="py-6 md:py-10">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb class="mb-6" :items="[
            ['label' => 'Demande de devis'],
        ]" />

        <div class="max-w-3xl mb-6 md:mb-8">
            <h1 class="font-display font-bold text-3xl md:text-4xl text-neutral-900">{{ $currentMode->title() }}</h1>
            <p class="mt-3 text-neutral-600">{{ $currentMode->intro() }}</p>
        </div>

        @if ($sent)
            <div class="max-w-3xl rounded-2xl border border-neutral-200 bg-white p-6 md:p-8">
                <x-ui.alert variant="success" title="Demande envoyée">
                    Merci&nbsp;! Votre demande @if ($sentNumber)<span class="font-mono">{{ $sentNumber }}</span>@endif a bien été transmise à notre équipe.
                    Un e-mail de confirmation vous a été envoyé&nbsp;; nous revenons vers vous dans les meilleurs délais.
                </x-ui.alert>
                <div class="mt-5 flex flex-wrap gap-3">
                    <x-ui.button variant="secondary" size="sm" wire:click="$set('sent', false)">
                        Faire une autre demande
                    </x-ui.button>
                    @if (! empty($context['from']))
                        <x-ui.button variant="ghost" size="sm" :href="$context['from']" icon="arrow-left">
                            Revenir à la page précédente
                        </x-ui.button>
                    @endif
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6 lg:gap-10 items-start">
                <form wire:submit.prevent="submit" class="space-y-6 rounded-2xl border border-neutral-200 bg-white p-6 md:p-8" novalidate>
                    {{-- Honeypot anti-spam : masqué aux humains, piège à bots. --}}
                    <div class="hidden" aria-hidden="true">
                        <label>Ne pas remplir<input type="text" wire:model="website" tabindex="-1" autocomplete="off" /></label>
                    </div>

                    {{-- Choix du mode : la saisie est conservée d'un mode à l'autre. --}}
                    <fieldset>
                        <legend class="text-sm font-semibold text-neutral-900 mb-3">Type de demande</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup">
                            @foreach ($modes as $option)
                                @php $active = $option === $currentMode; @endphp
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $active ? 'true' : 'false' }}"
                                    wire:click="setMode('{{ $option->value }}')"
                                    @class([
                                        'flex items-center gap-3 rounded-xl border p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                        'border-primary-600 bg-primary-50 text-primary-800 shadow-sm' => $active,
                                        'border-neutral-200 text-neutral-700 hover:border-primary-300 hover:bg-neutral-50' => ! $active,
                                    ])
                                >
                                    <span @class([
                                        'shrink-0 w-9 h-9 rounded-full flex items-center justify-center',
                                        'bg-primary-600 text-white' => $active,
                                        'bg-primary-50 text-primary-600' => ! $active,
                                    ])>
                                        <x-ui.icon :name="$option->icon()" class="w-5 h-5" />
                                    </span>
                                    <span class="text-sm font-semibold leading-snug">{{ $option->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </fieldset>

                    @error('form')
                        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
                    @enderror

                    {{-- Coordonnées --}}
                    <fieldset class="space-y-5">
                        <legend class="text-sm font-semibold text-neutral-900 mb-1">Vos coordonnées</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-ui.input label="Nom complet *" wire:model="name" autocomplete="name" placeholder="Jean Dupont" :error="$errors->first('name')" />
                            <x-ui.input label="Société" wire:model="company" autocomplete="organization" :error="$errors->first('company')" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-ui.input label="E-mail *" type="email" wire:model="email" autocomplete="email" placeholder="jean.dupont@exemple.fr" :error="$errors->first('email')" />
                            <x-ui.input label="Téléphone" type="tel" wire:model="phone" autocomplete="tel" placeholder="06 12 34 56 78" :error="$errors->first('phone')" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-ui.input label="Code postal du chantier / de livraison" wire:model="postcode" autocomplete="postal-code" :error="$errors->first('postcode')" />
                        </div>
                    </fieldset>

                    {{-- Description --}}
                    <x-ui.textarea
                        id="quote-message"
                        :label="$currentMode->messageLabel()"
                        wire:model="message"
                        rows="6"
                        :placeholder="$currentMode->messagePlaceholder()"
                        :error="$errors->first('message')"
                    />

                    {{-- Pièces jointes --}}
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">
                            Pièces jointes @if ($currentMode->requiresAttachment())<span>*</span>@endif
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $currentMode->attachmentsHint() }}</p>

                        <label class="mt-3 flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-neutral-300 px-4 py-6 text-center cursor-pointer hover:border-primary-400 hover:bg-neutral-50 transition">
                            <x-ui.icon :name="$currentMode === \Pko\QuoteRequests\Enums\QuoteRequestMode::PartPhoto ? 'camera' : 'paperclip'" class="w-6 h-6 text-primary-600" />
                            <span class="text-sm font-semibold text-primary-700">Choisir des fichiers</span>
                            <span class="text-xs text-neutral-500">JPG, PNG, WebP, PDF, DWG, CSV, XLSX — {{ $maxSizeMb }} Mo max par fichier, {{ $maxFiles }} fichiers max</span>
                            <input type="file" class="sr-only" multiple wire:model="newAttachments" accept="{{ $accept }}" />
                        </label>
                        <p wire:loading wire:target="newAttachments" class="mt-2 text-sm text-neutral-500">Envoi du fichier en cours…</p>

                        @error('attachments')
                            <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                        @enderror

                        @if ($attachments !== [])
                            <ul class="mt-3 divide-y divide-neutral-100 rounded-xl border border-neutral-200">
                                @foreach ($attachments as $index => $file)
                                    <li class="flex items-center gap-3 px-3 py-2" wire:key="quote-file-{{ $index }}">
                                        <x-ui.icon name="document" class="w-4 h-4 shrink-0 text-neutral-500" />
                                        <span class="flex-1 truncate text-sm text-neutral-800">{{ $file->getClientOriginalName() }}</span>
                                        <span class="text-xs text-neutral-500 font-mono">{{ number_format($file->getSize() / 1048576, 1, ',', ' ') }} Mo</span>
                                        <button type="button" wire:click="removeAttachment({{ $index }})" class="p-1.5 rounded-md text-neutral-500 hover:text-danger-600 hover:bg-neutral-100 transition" aria-label="Retirer le fichier">
                                            <x-ui.icon name="close" class="w-4 h-4" />
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div>
                        <x-ui.checkbox id="quote-consent" wire:model="consent">
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
                            wire:target="submit,newAttachments"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="submit">Envoyer ma demande</span>
                            <span wire:loading wire:target="submit">Envoi en cours…</span>
                        </x-ui.button>
                    </div>
                </form>

                <aside class="space-y-4">
                    @if ($hasContext)
                        <div class="rounded-2xl border border-primary-200 bg-primary-50 p-5" data-testid="quote-context">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-700">Votre demande concerne</p>
                            @if (! empty($context['product_name']))
                                <p class="mt-2 font-semibold text-neutral-900">
                                    @if (! empty($context['product_url']))
                                        <a href="{{ $context['product_url'] }}" class="hover:text-primary-700 hover:underline">{{ $context['product_name'] }}</a>
                                    @else
                                        {{ $context['product_name'] }}
                                    @endif
                                </p>
                            @endif
                            @if (! empty($context['reference']))
                                <p class="mt-1 text-sm text-neutral-600">Réf. <span class="font-mono text-neutral-900">{{ $context['reference'] }}</span></p>
                            @endif
                            @if (! empty($context['collection_name'] ?? $context['collection']))
                                <p class="mt-1 text-sm text-neutral-600">Catégorie&nbsp;: {{ $context['collection_name'] ?? $context['collection'] }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-2xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-start gap-3">
                            <span class="shrink-0 w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
                                <x-ui.icon name="clock" class="w-5 h-5" />
                            </span>
                            <div class="text-sm">
                                <p class="font-semibold text-neutral-900">Réponse rapide</p>
                                <p class="text-neutral-500 mt-0.5">Notre équipe étudie chaque demande et revient vers vous avec une proposition adaptée.</p>
                            </div>
                        </div>
                    </div>

                    @php $phone = brand_setting('contact.phone', config('storefront.contact.phone')); @endphp
                    @if ($phone)
                        <div class="rounded-2xl border border-neutral-200 bg-white p-5">
                            <div class="flex items-start gap-3">
                                <span class="shrink-0 w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
                                    <x-ui.icon name="headset" class="w-5 h-5" />
                                </span>
                                <div class="text-sm">
                                    <p class="font-semibold text-neutral-900">Besoin d'en parler&nbsp;?</p>
                                    <a href="tel:{{ preg_replace('/\s/', '', (string) $phone) }}" class="mt-0.5 block font-semibold text-primary-700 hover:text-primary-800">{{ $phone }}</a>
                                </div>
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        @endif
    </div>
</section>
