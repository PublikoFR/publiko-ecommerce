<x-layout.storefront>
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush

    <section class="py-8 md:py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => 'Nos magasins']]" class="mb-4" />
            <h1 class="text-3xl md:text-4xl font-black text-neutral-900 mb-2">Nos magasins MDE</h1>
            <p class="text-neutral-600 mb-8">Plus de 80 points de vente partout en France.</p>

            @if ($stores->isEmpty())
                <x-ui.card padding="lg" class="text-center">
                    <x-ui.icon name="map-pin" class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
                    <p class="text-neutral-500">Aucun magasin enregistré pour le moment.</p>
                </x-ui.card>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-6">
                    <div id="stores-map" class="h-[500px] lg:h-auto rounded-lg border border-neutral-200 z-0"></div>

                    <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2">
                        @foreach ($stores as $store)
                            <a href="{{ route('stores.show', $store->slug) }}" class="block bg-white border border-neutral-200 rounded-lg p-4 hover:border-primary-300 hover:shadow-sm transition">
                                <h3 class="font-bold text-neutral-900">{{ $store->name }}</h3>
                                <p class="text-sm text-neutral-600 mt-1">{{ $store->address_line_1 }}, {{ $store->postcode }} {{ $store->city }}</p>
                                @if ($store->phone)<p class="text-sm text-primary-600 mt-1">{{ $store->phone }}</p>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('stores-map');
                if (!el) return;
                @php
                    $storesJson = $stores
                        ->map(fn ($s) => ['name' => $s->name, 'slug' => $s->slug, 'lat' => $s->lat, 'lng' => $s->lng, 'addr' => $s->address_line_1.', '.$s->postcode.' '.$s->city])
                        ->filter(fn ($s) => $s['lat'] && $s['lng'])
                        ->values();
                @endphp
                var stores = {!! $storesJson->toJson() !!};
                var map = L.map('stores-map').setView([46.5, 2.5], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
                var markers = [];
                stores.forEach(function (s) {
                    var m = L.marker([s.lat, s.lng]).addTo(map);
                    m.bindPopup('<strong>' + s.name + '</strong><br>' + s.addr + '<br><a href="/magasins/' + s.slug + '">Voir le magasin →</a>');
                    markers.push(m);
                });
                if (markers.length > 0) {
                    var grp = L.featureGroup(markers);
                    map.fitBounds(grp.getBounds().pad(0.2));
                }
            });
        </script>
    @endpush
</x-layout.storefront>
