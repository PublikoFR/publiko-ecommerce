@php
use Pko\Storefront\Support\StorefrontLinks;
use Pko\Storefront\Support\StorefrontText;

// FEAT-009 : footer clair, réduit aux liens de la maquette (3 colonnes éditables
// en admin) + téléphone. Une colonne sans lien valide est masquée.
$phone = trim((string) config('storefront.contact.phone', ''));
$columns = collect(config('storefront.footer.columns', []))
    ->filter(fn ($col) => is_array($col))
    ->map(fn ($col) => [
        'title' => trim((string) ($col['title'] ?? '')),
        'links' => StorefrontLinks::visible($col['links'] ?? []),
    ])
    ->filter(fn ($col) => $col['links'] !== [])
    ->values();
$baseline = StorefrontText::render(config('storefront.footer.baseline'));
$tagline = brand_tagline();
@endphp

<footer class="bg-neutral-100 border-t border-neutral-200 text-neutral-600 mt-16">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-8 gap-y-10">
            {{-- Marque + contact --}}
            <div class="col-span-2 md:col-span-1">
                <a href="/" class="inline-flex" wire:navigate>
                    <span class="sr-only">{{ brand_name() }}</span>
                    <x-layout.logo class="h-11 w-auto max-w-[200px] object-contain" />
                </a>
                @if ($tagline)
                    <p class="mt-3 text-sm text-neutral-700">{{ $tagline }}</p>
                @endif
                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="mt-5 inline-flex items-center gap-2.5 text-primary-600 hover:text-primary-700 transition">
                        <span class="w-9 h-9 rounded-full bg-white border border-neutral-200 flex items-center justify-center shrink-0">
                            <x-ui.icon name="phone" class="w-4 h-4" />
                        </span>
                        <span class="font-display font-bold text-xl">{{ $phone }}</span>
                    </a>
                @endif
            </div>

            @foreach ($columns as $column)
                <div>
                    @if ($column['title'] !== '')
                        <h3 class="font-display font-bold text-[15px] text-neutral-900 mb-4">{{ $column['title'] }}</h3>
                    @endif
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($column['links'] as $link)
                            <li>
                                <a href="{{ $link['href'] }}" class="hover:text-primary-600 transition" @if (StorefrontLinks::isInternal($link['href'])) wire:navigate @endif>{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-10 pt-6 border-t border-neutral-200 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-neutral-500">
            @if ($baseline !== '')
                <p class="flex items-center gap-2 text-center md:text-left">
                    <x-ui.icon name="lock" class="w-3.5 h-3.5 text-primary-600 shrink-0" />
                    {{ $baseline }}
                </p>
            @endif
            <p>© {{ now()->year }} {{ brand_name() }}. Tous droits réservés.</p>
        </div>
    </div>
</footer>
