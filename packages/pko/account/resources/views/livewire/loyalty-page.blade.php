<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-display font-bold text-neutral-900">Programme fidélité</h1>
        <p class="text-neutral-600 mt-1 text-sm">Cumulez des points et débloquez des cadeaux exclusifs.</p>
    </div>

    @if ($snapshot === null)
        <x-ui.card padding="lg" class="text-center">
            <x-ui.icon name="check" class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
            <p class="text-neutral-500">Aucune donnée de fidélité disponible pour le moment.</p>
        </x-ui.card>
    @else
        @php
            $totalPoints = (int) ($snapshot['total_points'] ?? 0);
            $prevPoints = (int) ($snapshot['prev_points'] ?? 0);
            $upcoming = $snapshot['upcoming_tiers'] ?? collect();
            $unlocked = $snapshot['unlocked_tiers'] ?? collect();
            $pointsHistory = $snapshot['points_history'] ?? collect();

            $g1 = $upcoming[0] ?? null;
            $g2 = $upcoming[1] ?? null;

            // Remplissage du connecteur "vous → prochain cadeau" (0-100 %).
            $fill1 = 0;
            $toNext = 0;
            if ($g1) {
                $span = max(1, (int) $g1->points_required - $prevPoints);
                $fill1 = (int) round(min(100, max(0, (($totalPoints - $prevPoints) / $span) * 100)));
                $toNext = max(0, (int) $g1->points_required - $totalPoints);
            }
        @endphp

        {{-- Solde de points + wizard vers les 2 prochains cadeaux --}}
        <x-ui.card padding="lg">
            <div class="flex items-baseline gap-2 mb-1">
                <span class="text-5xl font-display font-bold text-primary-700">{{ $totalPoints }}</span>
                <span class="text-sm text-neutral-500">{{ \Illuminate\Support\Str::plural('point', $totalPoints) }}</span>
            </div>

            @if ($g1)
                <p class="text-sm text-neutral-600">
                    Plus que <strong class="text-primary-700">{{ $toNext }}</strong>
                    {{ \Illuminate\Support\Str::plural('point', $toNext) }} pour débloquer
                    <strong class="text-neutral-900">{{ $g1->gift_title }}</strong>
                </p>

                {{-- Stepper horizontal : Vous → cadeau qui arrive → cadeau suivant --}}
                <div class="mt-8 flex items-start">
                    {{-- Départ : position actuelle --}}
                    <div class="flex flex-col items-center w-16 shrink-0">
                        <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center ring-4 ring-primary-100">
                            <x-ui.icon name="check" class="w-5 h-5 text-white" />
                        </div>
                        <span class="mt-2 text-xs font-semibold text-neutral-700">Vous</span>
                        <span class="text-[11px] text-neutral-400">{{ $totalPoints }} pts</span>
                    </div>

                    {{-- Connecteur 1 (progression vers le cadeau qui arrive) --}}
                    <div class="flex-1 mt-5 h-1.5 rounded-full bg-neutral-100 overflow-hidden">
                        <div class="h-full bg-primary-600 rounded-full transition-all" style="width: {{ $fill1 }}%"></div>
                    </div>

                    {{-- Cadeau qui arrive (au milieu) — actif --}}
                    <div class="flex flex-col items-center w-24 shrink-0">
                        <div class="w-12 h-12 rounded-full bg-white ring-4 ring-primary-200 overflow-hidden flex items-center justify-center shadow-sm">
                            @if ($g1->gift_image_url)
                                <img src="{{ $g1->gift_image_url }}" alt="{{ $g1->gift_title }}" class="w-full h-full object-cover" />
                            @else
                                <x-ui.icon name="gift" class="w-6 h-6 text-primary-600" />
                            @endif
                        </div>
                        <span class="mt-2 text-xs font-semibold text-neutral-900 text-center leading-tight">{{ $g1->gift_title }}</span>
                        <span class="text-[11px] text-neutral-500">{{ (int) $g1->points_required }} pts</span>
                    </div>

                    @if ($g2)
                        {{-- Connecteur 2 (verrouillé tant que le 1er n'est pas atteint) --}}
                        <div class="flex-1 mt-5 h-1.5 rounded-full bg-neutral-100"></div>

                        {{-- Cadeau suivant (à droite) — verrouillé --}}
                        <div class="flex flex-col items-center w-24 shrink-0">
                            <div class="w-12 h-12 rounded-full bg-neutral-50 ring-4 ring-neutral-100 overflow-hidden flex items-center justify-center opacity-70">
                                @if ($g2->gift_image_url)
                                    <img src="{{ $g2->gift_image_url }}" alt="{{ $g2->gift_title }}" class="w-full h-full object-cover grayscale" />
                                @else
                                    <x-ui.icon name="gift" class="w-6 h-6 text-neutral-400" />
                                @endif
                            </div>
                            <span class="mt-2 text-xs font-semibold text-neutral-500 text-center leading-tight">{{ $g2->gift_title }}</span>
                            <span class="text-[11px] text-neutral-400">{{ (int) $g2->points_required }} pts</span>
                        </div>
                    @endif
                </div>
            @elseif (! empty($snapshot['no_tiers_configured']))
                <p class="text-sm text-neutral-500 mt-2">Aucun palier de fidélité n'est configuré pour le moment.</p>
            @elseif (! empty($snapshot['all_tiers_unlocked']))
                <p class="text-sm text-primary-700 mt-2 font-semibold">Bravo, vous avez débloqué tous les paliers de fidélité !</p>
            @endif
        </x-ui.card>

        {{-- Tous les cadeaux à venir --}}
        @if ($upcoming->isNotEmpty())
            <div>
                <h2 class="text-lg font-display font-bold text-neutral-900 mb-3">Tous les cadeaux à venir</h2>
                <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory -mx-1 px-1">
                    @foreach ($upcoming as $tier)
                        <div class="snap-start shrink-0 w-40">
                            <x-ui.card padding="lg" class="h-full flex flex-col items-center text-center">
                                <div class="w-16 h-16 rounded-full bg-primary-50 overflow-hidden flex items-center justify-center shrink-0">
                                    @if ($tier->gift_image_url)
                                        <img src="{{ $tier->gift_image_url }}" alt="{{ $tier->gift_title }}" class="w-full h-full object-cover" />
                                    @else
                                        <x-ui.icon name="gift" class="w-7 h-7 text-primary-600" />
                                    @endif
                                </div>
                                <p class="mt-3 text-sm font-semibold text-neutral-900 leading-tight">{{ $tier->gift_title }}</p>
                                <span class="mt-1 text-xs text-neutral-500">{{ (int) $tier->points_required }} pts</span>
                            </x-ui.card>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Cadeaux débloqués --}}
        <div>
            <h2 class="text-lg font-display font-bold text-neutral-900 mb-3">Cadeaux débloqués</h2>
            @if ($unlocked->isEmpty())
                <x-ui.card padding="lg" class="text-center">
                    <x-ui.icon name="gift" class="w-10 h-10 text-neutral-300 mx-auto mb-2" />
                    <p class="text-neutral-500 text-sm">Vous n'avez pas encore débloqué de cadeau. Continuez à cumuler des points !</p>
                </x-ui.card>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($unlocked as $gift)
                        <x-ui.card padding="lg" class="flex gap-4">
                            @if ($gift->tier?->gift_image_url)
                                <img src="{{ $gift->tier->gift_image_url }}" alt="{{ $gift->tier->gift_title }}"
                                     class="w-16 h-16 rounded-md object-cover shrink-0" />
                            @else
                                <div class="w-16 h-16 rounded-md bg-primary-50 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="gift" class="w-7 h-7 text-primary-600" />
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-neutral-900">{{ $gift->tier?->gift_title ?? 'Cadeau' }}</p>
                                @if ($gift->tier?->name)
                                    <p class="text-xs text-neutral-500">Palier {{ $gift->tier->name }}</p>
                                @endif
                                <div class="mt-2 flex items-center gap-2">
                                    <x-ui.badge variant="{{ $gift->status === \Pko\Loyalty\Enums\GiftStatus::Sent ? 'success' : 'primary' }}">
                                        {{ $gift->status?->label() ?? '—' }}
                                    </x-ui.badge>
                                    <span class="text-xs text-neutral-400">{{ optional($gift->unlocked_at)->format('d/m/Y') }}</span>
                                </div>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Historique des points --}}
        <div>
            <h2 class="text-lg font-display font-bold text-neutral-900 mb-3">Historique des points</h2>
            <x-ui.card padding="none">
                @if ($pointsHistory->isEmpty())
                    <p class="text-center text-neutral-500 py-16 text-sm">Aucun point cumulé pour l'instant.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200">
                            <thead class="bg-neutral-50">
                                <tr class="text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Commande</th>
                                    <th class="px-4 py-3 text-right">Montant HT</th>
                                    <th class="px-4 py-3 text-right">Points</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 text-sm">
                                @foreach ($pointsHistory as $entry)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-4 py-3 text-neutral-600">{{ optional($entry->created_at)->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 font-mono text-neutral-900">
                                            {{ $entry->order_reference ? '#'.$entry->order_reference : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-neutral-600">
                                            {{ number_format(((int) $entry->order_total_ht) / 100, 2, ',', ' ') }} €
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-primary-700">
                                            +{{ (int) $entry->points_earned }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
