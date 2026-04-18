<x-layout.storefront>
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush

    <section class="py-8 md:py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => 'Nos magasins', 'url' => route('stores.index')], ['label' => $store->name]]" class="mb-4" />

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_500px] gap-8">
                <div>
                    <h1 class="text-3xl md:text-4xl font-black text-neutral-900 mb-2">{{ $store->name }}</h1>
                    <address class="not-italic text-neutral-700 text-lg">
                        {{ $store->address_line_1 }}@if ($store->address_line_2), {{ $store->address_line_2 }}@endif<br>
                        {{ $store->postcode }} {{ $store->city }}
                    </address>

                    @if ($store->phone)
                        <p class="mt-4">
                            <a href="tel:{{ preg_replace('/\s/', '', $store->phone) }}" class="inline-flex items-center gap-2 text-primary-600 font-bold hover:text-primary-700 text-lg">
                                <x-ui.icon name="phone" class="w-5 h-5" /> {{ $store->phone }}
                            </a>
                        </p>
                    @endif

                    @if ($store->email)
                        <p class="mt-2">
                            <a href="mailto:{{ $store->email }}" class="text-primary-600 hover:underline">{{ $store->email }}</a>
                        </p>
                    @endif

                    @if ($store->hours)
                        <div class="mt-8">
                            <h2 class="font-bold text-neutral-900 mb-3">Horaires d'ouverture</h2>
                            <ul class="space-y-1 text-sm text-neutral-700">
                                @foreach ($store->hours as $day => $range)
                                    <li class="flex justify-between max-w-xs"><span>{{ $day }}</span><span class="font-medium">{{ $range }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div>
                    <div id="store-map" class="h-96 rounded-lg border border-neutral-200 z-0"></div>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('store-map');
                if (!el) return;
                @if ($store->lat && $store->lng)
                    var map = L.map('store-map').setView([{{ $store->lat }}, {{ $store->lng }}], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
                    L.marker([{{ $store->lat }}, {{ $store->lng }}]).addTo(map).bindPopup('{{ addslashes($store->name) }}').openPopup();
                @endif
            });
        </script>
    @endpush
</x-layout.storefront>
