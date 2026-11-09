{{--
    Bandeau « Un doute sur la pièce à commander ? » : renvoie vers le
    formulaire de devis en mode identification par photo (`part_photo`).
    Affiché sur l'arborescence Pièces détachées (catégories + fiches produit),
    cf. Pko\StorefrontCms\Support\SparePartsCatalog.

    Usage : <x-pko-quote-requests::part-help-banner :url="quote_request_url('part_photo', [...])" />
--}}
@props(['url'])

<section {{ $attributes->class('rounded-2xl border border-primary-100 bg-primary-50 p-6 md:p-8') }} data-testid="part-help-banner">
    <div class="flex flex-col md:flex-row md:items-center gap-5 md:gap-8">
        <div class="shrink-0 w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary-600">
            <x-ui.icon name="camera" class="w-6 h-6" />
        </div>
        <div class="flex-1">
            <h2 class="font-display font-bold text-xl text-neutral-900">Un doute sur la pièce à commander ?</h2>
            <p class="mt-1.5 text-sm text-neutral-600 max-w-2xl">
                Envoyez-nous une photo de la pièce, de son étiquette ou de la plaque signalétique de l'équipement :
                notre équipe l'identifie et vous indique la bonne référence.
            </p>
        </div>
        <div class="shrink-0">
            <x-ui.button variant="primary" :href="$url" icon="camera" data-testid="part-help-banner-cta">
                Envoyer une photo
            </x-ui.button>
        </div>
    </div>
</section>
