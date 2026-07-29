{{--
    Bandeau d'alerte maintenance — visible UNIQUEMENT par le staff Lunar
    lorsqu'il navigue sur le front pendant que le mode maintenance est actif.
    Les visiteurs non-staff ne voient jamais ce bandeau : ils sont interceptés
    en amont par CheckStorefrontMaintenance (page 503 de maintenance).
--}}
@if (auth('staff')->check() && \Pko\StorefrontCms\Models\Setting::get('storefront.maintenance', false))
    <div class="bg-danger-600 text-white" role="alert">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm font-medium">
            <span class="flex items-center gap-2">
                <x-ui.icon name="warning" class="w-4 h-4 shrink-0" />
                <span><strong>Site en maintenance</strong> — visible uniquement par vous (staff). Les visiteurs voient la page de maintenance.</span>
            </span>
            <a href="{{ url('/admin') }}" class="underline underline-offset-2 hover:no-underline whitespace-nowrap">
                Gérer dans l'admin
            </a>
        </div>
    </div>
@endif
