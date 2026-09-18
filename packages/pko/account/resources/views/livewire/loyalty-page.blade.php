<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-display font-bold text-neutral-900">Programme fidélité</h1>
        <p class="text-neutral-600 mt-1 text-sm">Cumulez des points et débloquez des cadeaux exclusifs. Vos points sont remis à zéro chaque 1<sup>er</sup> janvier.</p>
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
            $giftHistory = $snapshot['gift_history'] ?? $unlocked;
            $pointsHistory = $snapshot['points_history'] ?? collect();

            $g1 = $upcoming[0] ?? null;

            // Remplissage de la mini barre de progression du cadeau en cours (0-100 %).
            $fill = 0;
            $toNext = 0;
            if ($g1) {
                $span = max(1, (int) $g1->points_required - $prevPoints);
                $fill = (int) round(min(100, max(0, (($totalPoints - $prevPoints) / $span) * 100)));
                $toNext = max(0, (int) $g1->points_required - $totalPoints);
            }

            // Piste "Flow" : cadeaux débloqués (chronologique) → cadeau en cours → cadeaux à venir.
            $unlockedAsc = $unlocked->sortBy('unlocked_at')->values();
            $flow = $unlockedAsc->map(fn ($gift) => ['type' => 'unlocked', 'tier' => $gift->tier, 'gift' => $gift])
                ->values();
            if ($g1) {
                $flow->push(['type' => 'current', 'tier' => $g1, 'gift' => null]);
            }
            foreach ($upcoming->skip(1) as $tier) {
                $flow->push(['type' => 'upcoming', 'tier' => $tier, 'gift' => null]);
            }
            $currentIndex = $flow->search(fn ($item) => $item['type'] === 'current');
            if ($currentIndex === false && $unlockedAsc->isNotEmpty() && empty($snapshot['no_tiers_configured'])) {
                $flow->push(['type' => 'trophy', 'tier' => null, 'gift' => null]);
            }
            $initialActive = $currentIndex !== false ? $currentIndex : max(0, $flow->count() - 1);
        @endphp

        {{-- Flow : débloqués (perspective, gauche) → prochain cadeau (centre) → à venir (perspective, droite) --}}
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
            @elseif (! empty($snapshot['no_tiers_configured']))
                <p class="text-sm text-neutral-500 mt-2">Aucun palier de fidélité n'est configuré pour le moment.</p>
            @elseif (! empty($snapshot['all_tiers_unlocked']))
                <p class="text-sm text-primary-700 mt-2 font-semibold">Bravo, vous avez débloqué tous les paliers de fidélité !</p>
            @endif

            @if ($flow->isNotEmpty())
                <div
                    class="relative mt-8 -mx-7 h-72 sm:h-80 overflow-hidden cursor-grab active:cursor-grabbing select-none [touch-action:none] [perspective:1600px]"
                    x-data="loyaltyFlow({ initialActive: {{ $initialActive }}, count: {{ $flow->count() }} })"
                    @wheel="onWheel"
                    @mousedown="onDown" @mousemove.window="onMove" @mouseup.window="onUp"
                    @touchstart="onDown" @touchmove.window="onMove" @touchend.window="onUp"
                >
                    {{-- Halo lime derrière le cadeau en cours --}}
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="w-64 h-64 rounded-full bg-accent-400/20 blur-3xl"></div>
                    </div>

                    @foreach ($flow as $i => $item)
                        @php
                            $isCurrent = $item['type'] === 'current';
                            $tier = $item['tier'];
                        @endphp

                        <div
                            @if ($i === 0) x-ref="sizer" @endif
                            class="absolute left-1/2 top-1/2 flex flex-col items-center text-center w-28 sm:w-36 will-change-transform"
                            :class="snapping ? 'transition-[transform,opacity] duration-300 ease-out' : ''"
                            :style="cardStyle({{ $i }})"
                        >
                            @if ($isCurrent)
                                <span class="absolute -top-8 left-1/2 -translate-x-1/2 w-max inline-flex items-center rounded-full bg-accent-500 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-primary-900 shadow-accent">
                                    Prochain cadeau
                                </span>
                            @endif

                            <div @class([
                                'relative w-full aspect-square rounded-lg overflow-hidden flex items-center justify-center border',
                                'bg-accent-50 border-accent-400 ring-[6px] ring-accent-200 shadow-accent' => $isCurrent,
                                'bg-white border-neutral-200 shadow-md' => ! $isCurrent && $item['type'] === 'unlocked',
                                'bg-neutral-100 border-neutral-200 shadow-sm' => $item['type'] === 'upcoming',
                                'bg-accent-50 border-accent-300 shadow-accent' => $item['type'] === 'trophy',
                            ])>
                                @if ($tier?->gift_image_url)
                                    <img
                                        src="{{ $tier->gift_image_url }}"
                                        alt="{{ $tier->gift_title }}"
                                        draggable="false"
                                        class="w-full h-full object-cover pointer-events-none"
                                    />
                                @else
                                    <x-ui.icon
                                        :name="$item['type'] === 'trophy' ? 'trophy' : 'gift'"
                                        class="w-9 h-9 {{ $isCurrent ? 'text-primary-700' : 'text-neutral-400' }}"
                                    />
                                @endif

                                @if ($item['type'] === 'unlocked')
                                    <span class="absolute bottom-1.5 right-1.5 flex items-center justify-center w-5 h-5 rounded-full bg-accent-500 shadow-sm ring-2 ring-white">
                                        <x-ui.icon name="check" class="w-3 h-3 text-primary-900" />
                                    </span>
                                @elseif ($item['type'] === 'upcoming')
                                    <span class="absolute top-1.5 right-1.5 flex items-center justify-center w-6 h-6 rounded-full bg-neutral-900/60 backdrop-blur-sm">
                                        <x-ui.icon name="lock" class="w-3.5 h-3.5 text-white" />
                                    </span>
                                @endif
                            </div>

                            @if ($isCurrent)
                                <div class="mt-3 w-full h-2 rounded-full bg-neutral-100 overflow-hidden">
                                    <div class="h-full bg-accent-500 rounded-full" style="width: {{ $fill }}%"></div>
                                </div>
                            @endif

                            <p class="mt-2 text-xs font-semibold {{ $isCurrent ? 'text-neutral-900' : 'text-neutral-600' }} leading-tight line-clamp-2">
                                {{ $tier?->gift_title ?? 'Tout débloqué' }}
                            </p>
                            @if ($tier)
                                <span class="text-[11px] text-neutral-400">{{ (int) $tier->points_required }} pts</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        {{-- Suivi des cadeaux débloqués --}}
        <div>
            <h2 class="text-lg font-display font-bold text-neutral-900 mb-3">Suivi de vos cadeaux débloqués</h2>
            @if ($giftHistory->isEmpty())
                <x-ui.card padding="lg" class="text-center">
                    <x-ui.icon name="gift" class="w-10 h-10 text-neutral-300 mx-auto mb-2" />
                    <p class="text-neutral-500 text-sm">Vous n'avez pas encore débloqué de cadeau. Continuez à cumuler des points !</p>
                </x-ui.card>
            @else
                <x-ui.card padding="none">
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($giftHistory as $gift)
                            <li class="flex items-center gap-4 px-5 py-4">
                                <div class="w-10 h-10 rounded-full bg-accent-50 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="gift" class="w-5 h-5 text-primary-700" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-neutral-900 truncate">{{ $gift->tier?->gift_title ?? 'Cadeau' }}</p>
                                    @if ($gift->tier?->name)
                                        <p class="text-xs text-neutral-500">Palier {{ $gift->tier->name }}</p>
                                    @endif
                                </div>
                                <x-ui.badge variant="{{ $gift->status === \Pko\Loyalty\Enums\GiftStatus::Sent ? 'success' : 'primary' }}">
                                    {{ $gift->status?->label() ?? '—' }}
                                </x-ui.badge>
                                <span class="text-xs text-neutral-400 shrink-0 w-16 text-right">{{ optional($gift->unlocked_at)->format('d/m/Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
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
                                            @if ((int) $entry->points_revoked > 0)
                                                <span class="block text-xs font-normal text-neutral-500">−{{ (int) $entry->points_revoked }} (remboursement)</span>
                                            @endif
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
