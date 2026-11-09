{{--
    Mention « dont éco-participation X € » (FEAT 003, modèle « dont » — docs/ecotax.md).
    Le prix affiché INCLUT déjà l'éco-taxe : cette mention est informative, jamais additionnée.
    Rien n'est rendu quand le montant vaut 0.

    Props :
      - cents     : montant en cents HT (int) ;
      - currency  : devise Lunar (défaut : devise par défaut) ;
      - suffix    : convention du prix voisin ('HT' par défaut, null pour ne rien suffixer) ;
      - formatted : montant déjà formaté (court-circuite cents/currency ; vide = rien).
--}}
@props(['cents' => 0, 'currency' => null, 'suffix' => 'HT', 'formatted' => null])

@php
$label = filled($formatted)
    ? (string) $formatted
    : ((int) $cents > 0 ? \App\Support\Ecotax::format((int) $cents, $currency) : null);
@endphp

@if ($label !== null)
    <span {{ $attributes->class(['flex items-center gap-1 text-xs text-neutral-500 font-sans font-normal leading-snug']) }}
       title="Contribution au recyclage des équipements électriques (DEEE)">
        <span>dont éco-participation {{ $label }}@if ($suffix) {{ $suffix }}@endif</span>
        <x-ui.icon name="info" class="w-3.5 h-3.5 text-neutral-400 shrink-0" />
    </span>
@endif
