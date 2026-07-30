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
        @elseif ($this->francoRemainingCents > 0)
            <div class="px-5 py-3 bg-amber-50 border-b border-amber-200 flex items-start gap-2 text-sm text-amber-800">
                <x-ui.icon name="info" class="w-4 h-4 mt-0.5 text-amber-500 shrink-0" />
                <span>Plus que <strong>{{ $this->formatHtCents($this->francoRemainingCents) }} HT</strong> d'articles éligibles pour bénéficier de la livraison standard offerte.</span>
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
                    $prices = $this->optionPrices($option);
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
                            <span class="text-right font-bold text-sm {{ $isFranco ? 'text-success-700' : 'text-neutral-900' }}">
                                @if ($isFranco)
                                    Offert
                                @else
                                    @if ($this->priceDisplay === 'ttc')
                                        <span class="block">{{ $prices['ttc']->formatted() }} TTC</span>
                                    @elseif ($this->priceDisplay === 'ht')
                                        <span class="block">{{ $prices['ht']->formatted() }} HT</span>
                                    @else
                                        <span class="block">{{ $prices['ttc']->formatted() }} TTC</span>
                                        <span class="block text-xs font-normal text-neutral-500">{{ $prices['ht']->formatted() }} HT</span>
                                    @endif
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

            {{-- Récap ventilé : affiché si plusieurs composants de frais se cumulent --}}
            @if ($this->hasVentilatedRecap && $this->selectedOptionMeta !== null)
                @php
                    $meta      = $this->selectedOptionMeta;
                    $labels    = $this->serviceLabels;
                    $recapLabel = $labels[$chosenOption]['title'] ?? $chosenOption;
                    $gridCents  = (int) ($meta['grid_price_cents'] ?? 0);
                    $isFranco   = (bool) ($meta['franco'] ?? false);
                    $flatCents  = (int) ($meta['flat_price_cents'] ?? 0);
                    $surgCents  = (int) ($meta['surcharge_cents'] ?? 0);
                @endphp
                <div class="rounded-lg border border-neutral-100 overflow-hidden text-sm">
                    <table class="w-full">
                        <tbody class="divide-y divide-neutral-50">
                            <tr>
                                <td class="px-4 py-2 text-neutral-700">{{ $recapLabel }}</td>
                                <td class="px-4 py-2 text-right font-medium {{ $isFranco ? 'text-success-700' : 'text-neutral-900' }}">
                                    {{ $isFranco ? 'Offert' : $this->formatHtCents($gridCents) }}
                                </td>
                            </tr>
                            @if ($flatCents > 0)
                                @forelse ($this->flatLineRows as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-neutral-700">{{ $row['label'] }}</td>
                                        <td class="px-4 py-2 text-right font-medium">+ {{ $this->formatHtCents($row['cents']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-2 text-neutral-700">Frais de transport forfaitaires</td>
                                        <td class="px-4 py-2 text-right font-medium">+ {{ $this->formatHtCents($flatCents) }}</td>
                                    </tr>
                                @endforelse
                            @endif
                            @if ($surgCents > 0)
                                <tr>
                                    <td class="px-4 py-2 text-neutral-700">Supplément transport</td>
                                    <td class="px-4 py-2 text-right font-medium">+ {{ $this->formatHtCents($surgCents) }}</td>
                                </tr>
                            @endif
                            @foreach ($this->sentinelOptions as $sentinel)
                                <tr>
                                    <td class="px-4 py-2 text-neutral-700">{{ $sentinel->getName() }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-neutral-500 italic">Sur devis</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-neutral-50 border-t border-neutral-200">
                                <td class="px-4 py-2 font-semibold text-neutral-800">Total livraison HT</td>
                                <td class="px-4 py-2 text-right font-bold text-neutral-900">{{ $this->formatHtCents($this->selectedOptionTotalCents) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            {{-- Sélection du point relais (Chrono Relais) --}}
            @if ($this->requiresPickupPoint)
                <div class="mt-1 p-4 border-2 border-primary-200 bg-primary-50/40 rounded-lg space-y-3">
                    <p class="text-sm font-semibold text-neutral-900">Choisissez votre point relais</p>

                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Code postal</label>
                            <input type="text"
                                   wire:model="pickupSearchPostcode"
                                   inputmode="numeric"
                                   class="w-full rounded-lg border-neutral-300 text-sm"
                                   placeholder="Ex : 75001" />
                        </div>
                        <x-ui.button type="button" variant="secondary" wire:click="searchPickupPoints">
                            Rechercher
                        </x-ui.button>
                    </div>
                    @error('pickupSearchPostcode')
                        <p class="text-sm text-red-500">{{ $message }}</p>
                    @enderror

                    @if (! empty($pickupPoints))
                        @once
                        @push('styles')
                            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                                  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
                        @endpush
                        @endonce
                        @once
                            @push('scripts')
                                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                                        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPeE=" crossorigin="anonymous"></script>
                            @endpush
                        @endonce

                        {{-- Conteneur principal : liste + carte côte à côte --}}
                        <div
                            class="flex flex-col md:flex-row gap-3"
                            x-data="{
                                map: null,
                                markers: {},
                                selectedId: @js($pickupPointId),
                                points: @js($pickupPoints),
                                init() {
                                    this.$nextTick(() => {
                                        const hasCoords = this.points.some(p => p.latitude && p.longitude);
                                        if (!hasCoords) return;

                                        const firstWithCoords = this.points.find(p => p.latitude && p.longitude);
                                        this.map = L.map(this.$refs.mapContainer).setView(
                                            [firstWithCoords.latitude, firstWithCoords.longitude], 13
                                        );
                                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            maxZoom: 18,
                                            attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>'
                                        }).addTo(this.map);

                                        this.points.forEach(p => {
                                            if (!p.latitude || !p.longitude) return;
                                            const isSelected = p.id === this.selectedId;
                                            const icon = L.divIcon({
                                                className: '',
                                                html: `<div class=\"${isSelected ? 'bg-primary-600 ring-2 ring-primary-300' : 'bg-primary-400 hover:bg-primary-600'} text-white rounded-full w-5 h-5 flex items-center justify-center shadow-md cursor-pointer text-xs font-bold transition\">P</div>`,
                                                iconSize: [20, 20],
                                                iconAnchor: [10, 10],
                                            });
                                            const marker = L.marker([p.latitude, p.longitude], {icon})
                                                .addTo(this.map)
                                                .bindPopup(`<strong class=\"text-sm\">${p.name}</strong><br><span class=\"text-xs text-neutral-500\">${p.address1}, ${p.postcode} ${p.city}</span>`);
                                            marker.on('click', () => {
                                                this.selectPoint(p.id);
                                            });
                                            this.markers[p.id] = marker;
                                        });
                                    });
                                },
                                selectPoint(id) {
                                    this.selectedId = id;
                                    $wire.set('pickupPointId', id);
                                    Object.keys(this.markers).forEach(k => {
                                        const isSelected = k === id;
                                        const p = this.points.find(pt => pt.id === k);
                                        if (!p) return;
                                        const icon = L.divIcon({
                                            className: '',
                                            html: `<div class=\"${isSelected ? 'bg-primary-600 ring-2 ring-primary-300' : 'bg-primary-400 hover:bg-primary-600'} text-white rounded-full w-5 h-5 flex items-center justify-center shadow-md cursor-pointer text-xs font-bold transition\">P</div>`,
                                            iconSize: [20, 20],
                                            iconAnchor: [10, 10],
                                        });
                                        this.markers[k].setIcon(icon);
                                    });
                                },
                            }"
                        >
                            {{-- Liste des points (scrollable) --}}
                            <div class="md:w-1/2 space-y-2 max-h-72 overflow-y-auto pr-1">
                                @foreach ($pickupPoints as $point)
                                    <label wire:key="pickup_{{ $point['id'] }}"
                                           @click="selectPoint('{{ $point['id'] }}')"
                                           class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer bg-white transition
                                                  {{ $pickupPointId === $point['id'] ? 'border-primary-500 ring-1 ring-primary-500' : 'border-neutral-200 hover:border-neutral-300' }}">
                                        <input type="radio"
                                               wire:model="pickupPointId"
                                               value="{{ $point['id'] }}"
                                               class="mt-1 text-primary-600 shrink-0" />
                                        <div class="flex-1 min-w-0 text-sm">
                                            <span class="font-semibold text-neutral-900">{{ $point['name'] }}</span>
                                            <p class="text-xs text-neutral-500">{{ $point['address1'] }}, {{ $point['postcode'] }} {{ $point['city'] }}</p>
                                            @if (! empty($point['distance_km']))
                                                <p class="text-xs text-neutral-400">À {{ number_format((float) $point['distance_km'], 1, ',', ' ') }} km</p>
                                            @endif
                                            @if (! empty($point['opening_hours']))
                                                <p class="text-xs text-neutral-400 mt-0.5">{{ $point['opening_hours'] }}</p>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Carte Leaflet (wire:ignore : Livewire ne doit pas re-render le conteneur) --}}
                            @if (collect($pickupPoints)->some(fn ($p) => ! empty($p['latitude']) && ! empty($p['longitude'])))
                                <div class="md:w-1/2" wire:ignore>
                                    <div x-ref="mapContainer"
                                         class="w-full h-64 md:h-72 rounded-lg border border-neutral-200 overflow-hidden z-0"></div>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Saisie manuelle simplifiée (V1) — aucun point retourné automatiquement --}}
                        <div class="space-y-2">
                            <p class="text-xs text-neutral-500">Saisissez les coordonnées de votre point relais Chronopost / Pickup :</p>
                            <input type="text" wire:model="manualPickupPoint.name"
                                   class="w-full rounded-lg border-neutral-300 text-sm" placeholder="Nom du point relais" />
                            <input type="text" wire:model="manualPickupPoint.address1"
                                   class="w-full rounded-lg border-neutral-300 text-sm" placeholder="Adresse" />
                            <div class="flex gap-2">
                                <input type="text" wire:model="manualPickupPoint.postcode"
                                       class="w-1/3 rounded-lg border-neutral-300 text-sm" placeholder="Code postal" />
                                <input type="text" wire:model="manualPickupPoint.city"
                                       class="flex-1 rounded-lg border-neutral-300 text-sm" placeholder="Ville" />
                            </div>
                        </div>
                    @endif

                    @if ($selectedPickupPoint)
                        <div class="flex items-start gap-2 text-sm text-success-800 bg-success-50 border border-success-200 rounded-lg px-3 py-2">
                            <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-success-600 shrink-0" />
                            <span>Point relais retenu : <strong>{{ $selectedPickupPoint['name'] }}</strong> — {{ $selectedPickupPoint['postcode'] }} {{ $selectedPickupPoint['city'] }}</span>
                        </div>
                    @endif

                    @error('pickupPointId')
                        <p class="text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            @endif
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
