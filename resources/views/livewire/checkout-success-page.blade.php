<section class="py-20 md:py-28">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success-100 text-success-700 mb-6">
            <x-ui.icon name="check" class="w-8 h-8" />
        </span>

        <h1 class="font-display font-bold text-3xl md:text-4xl text-neutral-900">
            Commande confirmée
        </h1>

        <p class="mt-4 text-neutral-600">
            Merci pour votre commande. Votre numéro de référence est
            <strong class="font-mono text-primary-700">{{ $order->reference }}</strong>.
        </p>

        @if ($order->status === 'awaiting-quote')
            <div class="mt-6 text-left">
                <x-ui.card padding="md" class="!bg-warning-50 !border-warning-100">
                    <div class="flex gap-3 text-sm text-warning-700">
                        <x-ui.icon name="truck" class="w-5 h-5 shrink-0 text-warning-500" />
                        <p>Votre commande nécessite un devis transport. Vous recevrez un lien de paiement avec le montant final une fois les frais de port calculés.</p>
                    </div>
                </x-ui.card>
            </div>
        @endif

        <div class="mt-8 flex items-center justify-center gap-3">
            <x-ui.button variant="primary" size="lg" href="{{ url('/') }}">Retour à l'accueil</x-ui.button>
            @auth
                <x-ui.button variant="secondary" size="lg" href="/compte/commandes">Mes commandes</x-ui.button>
            @endauth
        </div>
    </div>
</section>
