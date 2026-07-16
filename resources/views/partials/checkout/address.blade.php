<form wire:submit="saveAddress('{{ $type }}')"
      class="bg-white border border-neutral-100 rounded-xl">
    <div class="flex items-center justify-between h-16 px-6 border-b border-neutral-100">
        <h3 class="text-lg font-medium">
            {{ $type === 'shipping' ? 'Adresse de livraison' : 'Adresse de facturation' }}
        </h3>

        @if ($type == 'shipping' && $step == $currentStep)
            <label class="flex items-center p-2 rounded-lg cursor-pointer hover:bg-neutral-50">
                <input class="w-5 h-5 text-green-600 border-neutral-100 rounded"
                       type="checkbox"
                       value="1"
                       wire:model.live="shippingIsBilling" />

                <span class="ml-2 text-xs font-medium">
                    Identique à la facturation
                </span>
            </label>
        @endif

        @if ($currentStep > $step)
            <button class="px-5 py-2 text-sm font-medium text-neutral-600 rounded-lg hover:bg-neutral-100 hover:text-neutral-700"
                    type="button"
                    wire:click.prevent="$set('currentStep', {{ $step }})">
                Modifier
            </button>
        @endif
    </div>

    @if ($currentStep >= $step)
        <div class="p-6">
            @if ($step == $currentStep && $this->customerAddresses->isNotEmpty())
                <div class="mb-6">
                    <p class="mb-3 text-sm font-medium text-neutral-700">Adresses enregistrées</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($this->customerAddresses as $savedAddress)
                            <button type="button"
                                    wire:key="saved_address_{{ $type }}_{{ $savedAddress->id }}"
                                    wire:click="useCustomerAddress({{ $savedAddress->id }}, '{{ $type }}')"
                                    class="text-left p-3 border border-neutral-200 rounded-lg hover:border-primary-400 hover:bg-primary-50 text-sm transition-colors">
                                <span class="block font-medium text-neutral-900">{{ $savedAddress->company_name ?: ($savedAddress->first_name . ' ' . $savedAddress->last_name) }}</span>
                                <span class="block text-neutral-500">{{ $savedAddress->line_one }}, {{ $savedAddress->postcode }} {{ $savedAddress->city }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-4 h-px bg-neutral-100"></div>
                    <p class="mt-4 text-xs text-neutral-500">Ou saisir une nouvelle adresse ci-dessous</p>
                </div>
            @endif

            @if ($step == $currentStep)
                <div class="grid grid-cols-6 gap-4">
                    <x-input.group class="col-span-3"
                                   label="Prénom"
                                   :errors="$errors->get($type . '.first_name')"
                                   required>
                        <x-input.text wire:model.live="{{ $type }}.first_name"
                                      required />
                    </x-input.group>

                    <x-input.group class="col-span-3"
                                   label="Nom"
                                   :errors="$errors->get($type . '.last_name')"
                                   required>
                        <x-input.text wire:model.live="{{ $type }}.last_name"
                                      required />
                    </x-input.group>

                    <x-input.group class="col-span-6"
                                   label="Raison sociale"
                                   :errors="$errors->get($type . '.company_name')">
                        <x-input.text wire:model.live="{{ $type }}.company_name" />
                    </x-input.group>

                    <x-input.group class="col-span-6 sm:col-span-3"
                                   label="Téléphone de contact"
                                   :errors="$errors->get($type . '.contact_phone')">
                        <x-input.text wire:model.live="{{ $type }}.contact_phone" />
                    </x-input.group>

                    <x-input.group class="col-span-6 sm:col-span-3"
                                   label="E-mail de contact"
                                   :errors="$errors->get($type . '.contact_email')"
                                   required>
                        <x-input.text wire:model.live="{{ $type }}.contact_email"
                                      type="email"
                                      required />
                    </x-input.group>

                    <div class="col-span-6">
                        <hr class="h-px my-4 bg-neutral-100 border-none">
                    </div>

                    <x-input.group class="col-span-3 sm:col-span-2"
                                   label="Adresse ligne 1"
                                   :errors="$errors->get($type . '.line_one')"
                                   required>
                        <x-input.text wire:model.live="{{ $type }}.line_one"
                                      required />
                    </x-input.group>

                    <x-input.group class="col-span-3 sm:col-span-2"
                                   label="Adresse ligne 2"
                                   :errors="$errors->get($type . '.line_two')">
                        <x-input.text wire:model.live="{{ $type }}.line_two" />
                    </x-input.group>

                    <x-input.group class="col-span-3 sm:col-span-2"
                                   label="Adresse ligne 3"
                                   :errors="$errors->get($type . '.line_three')">
                        <x-input.text wire:model.live="{{ $type }}.line_three" />
                    </x-input.group>

                    <x-input.group class="col-span-3 sm:col-span-2"
                                   label="Ville"
                                   :errors="$errors->get($type . '.city')"
                                   required>
                        <x-input.text wire:model.live="{{ $type }}.city"
                                      required />
                    </x-input.group>

                    <x-input.group class="col-span-3 sm:col-span-2"
                                   label="Région / Département"
                                   :errors="$errors->get($type . '.state')">
                        <x-input.text wire:model.live="{{ $type }}.state" />
                    </x-input.group>

                    <x-input.group class="col-span-3 sm:col-span-2"
                                   label="Code postal"
                                   :errors="$errors->get($type . '.postcode')"
                                   required>
                        <x-input.text wire:model.live="{{ $type }}.postcode"
                                      required />
                    </x-input.group>

                    <x-input.group class="col-span-6"
                                   label="Pays"
                                   required>
                        <select class="w-full p-3 border border-neutral-200 rounded-lg sm:text-sm"
                                wire:model.live="{{ $type }}.country_id">
                            <option value>Sélectionnez un pays</option>
                            @foreach ($this->countries as $country)
                                <option value="{{ $country->id }}"
                                        wire:key="country_{{ $country->id }}">
                                    {{ $country->native }}
                                </option>
                            @endforeach
                        </select>
                    </x-input.group>
                </div>
            @elseif($currentStep > $step)
                @php($saved = $this->cart->{$type . 'Address'})
                <dl class="grid grid-cols-1 gap-8 text-sm sm:grid-cols-2">
                    <div>
                        <div class="space-y-4">
                            <div>
                                <dt class="font-medium">
                                    Nom
                                </dt>

                                <dd class="mt-0.5">
                                    {{ $saved?->first_name }} {{ $saved?->last_name }}
                                </dd>
                            </div>

                            @if ($saved?->company_name)
                                <div>
                                    <dt class="font-medium">
                                        Raison sociale
                                    </dt>

                                    <dd class="mt-0.5">
                                        {{ $saved->company_name }}
                                    </dd>
                                </div>
                            @endif

                            @if ($saved?->contact_phone)
                                <div>
                                    <dt class="font-medium">
                                        Téléphone
                                    </dt>

                                    <dd class="mt-0.5">
                                        {{ $saved->contact_phone }}
                                    </dd>
                                </div>
                            @endif

                            <div>
                                <dt class="font-medium">
                                    E-mail
                                </dt>

                                <dd class="mt-0.5">
                                    {{ $saved?->contact_email }}
                                </dd>
                            </div>
                        </div>
                    </div>

                    <div>
                        <dt class="font-medium">
                            Adresse
                        </dt>

                        <dd class="mt-0.5">
                            {{ $saved?->line_one }}<br>
                            @if ($saved?->line_two)
                                {{ $saved->line_two }}<br>
                            @endif
                            @if ($saved?->line_three)
                                {{ $saved->line_three }}<br>
                            @endif
                            @if ($saved?->city)
                                {{ $saved->city }}<br>
                            @endif
                            @if ($saved?->state)
                                {{ $saved->state }}<br>
                            @endif
                            {{ $saved?->postcode }}<br>
                            {{ $saved?->country?->native }}
                        </dd>
                    </div>
                </dl>
            @endif

            @if ($step == $currentStep)
                <div class="mt-6 text-right">
                    <button class="px-5 py-3 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
                            type="submit"
                            wire:key="submit_btn"
                            wire:loading.attr="disabled"
                            wire:target="saveAddress">
                        <span wire:loading.remove
                              wire:target="saveAddress">
                            Enregistrer l'adresse
                        </span>

                        <span wire:loading
                              wire:target="saveAddress">
                            <span class="inline-flex items-center">
                                Enregistrement...

                                <x-icon.loading />
                            </span>
                        </span>
                    </button>
                </div>
            @endif
        </div>

    @endif
</form>
