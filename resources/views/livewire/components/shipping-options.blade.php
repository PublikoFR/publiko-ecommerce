<form wire:submit="save"
      class="border border-neutral-200 rounded-xl shadow-sm overflow-hidden">
    <div class="flex justify-between items-center px-5 py-4 bg-white border-b border-neutral-100">
        <span class="text-base font-bold text-neutral-900">Mode de livraison</span>
    </div>

    @if ($this->shippingAddress)
        {{-- Bandeaux dynamiques --}}
        @if ($this->isFrancoReached)
            <div class="px-5 py-3 bg-success-50 border-b border-success-200 flex items-start gap-2 text-sm text-success-800">
                <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-success-600 shrink-0" />
                <span>Votre commande est éligible à la livraison standard offerte. Vous pouvez choisir une livraison express avec supplément.</span>
            </div>
        @endif

        @if ($this->hasExcludedLines)
            <div class="px-5 py-3 bg-blue-50 border-b border-blue-200 flex items-start gap-2 text-sm text-blue-800">
                <x-ui.icon name="info" class="w-4 h-4 mt-0.5 text-blue-500 shrink-0" />
                <span>Certains produits volumineux, spécifiques ou livrés dans des zones particulières peuvent faire l'objet de frais de transport complémentaires.</span>
            </div>
        @endif

        @if ($this->hasMultipleSources)
            <div class="px-5 py-3 bg-blue-50 border-b border-blue-200 flex items-start gap-2 text-sm text-blue-800">
                <x-ui.icon name="info" class="w-4 h-4 mt-0.5 text-blue-500 shrink-0" />
                <span>Votre commande peut être expédiée en plusieurs colis afin de garantir les meilleurs délais.</span>
            </div>
        @endif

        {{-- Cartes modes de livraison --}}
        <div class="p-4 space-y-3">
            @foreach ($this->shippingOptions as $option)
                @php
                    $identifier = $option->getIdentifier();
                    $labels = $this->serviceLabels[$identifier] ?? null;
                    $isFranco = ($option->meta['franco'] ?? false) === true;
                @endphp
                <label wire:key="shipping_option_{{ $identifier }}"
                       class="flex items-start gap-3 p-4 border-2 rounded-lg cursor-pointer transition
                              {{ $chosenOption === $identifier
                                  ? 'border-primary-500 bg-primary-50'
                                  : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
                    <input type="radio"
                           wire:model.live="chosenOption"
                           value="{{ $identifier }}"
                           class="mt-1 text-primary-600 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="font-semibold text-neutral-900 text-sm">
                                {{ $labels['title'] ?? $option->name }}
                            </span>
                            <span class="font-bold text-sm {{ $isFranco ? 'text-success-700' : 'text-neutral-900' }}">
                                @if ($isFranco)
                                    Offert
                                @else
                                    {{ $option->getPrice()->formatted() }} HT
                                @endif
                            </span>
                        </div>
                        @if ($labels)
                            <p class="text-xs text-neutral-500 mt-0.5">{{ $labels['description'] }}</p>
                        @elseif ($option->description)
                            <p class="text-xs text-neutral-500 mt-0.5">{{ $option->description }}</p>
                        @endif
                    </div>
                </label>
            @endforeach
        </div>
    @else
        <div class="px-5 py-4 text-sm text-neutral-500">
            Veuillez renseigner une adresse de livraison pour afficher les modes disponibles.
        </div>
    @endif

    @if ($errors->has('chosenOption'))
        <p class="px-5 pb-3 text-sm text-red-500">{{ $errors->first('chosenOption') }}</p>
    @endif

    <div class="flex justify-end w-full px-5 py-4 bg-neutral-50 border-t border-neutral-100">
        <x-ui.button type="submit" variant="primary">
            Continuer
        </x-ui.button>
    </div>
</form>
