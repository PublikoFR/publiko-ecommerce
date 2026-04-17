<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-neutral-900">Programme fidélité</h1>
        <p class="text-neutral-600 mt-1 text-sm">Cumulez des points et débloquez des cadeaux exclusifs.</p>
    </div>

    @if ($snapshot === null)
        <x-ui.card padding="lg" class="text-center">
            <x-ui.icon name="check" class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
            <p class="text-neutral-500">Aucune donnée de fidélité disponible pour le moment.</p>
        </x-ui.card>
    @else
        <x-ui.card padding="lg">
            <div class="flex items-baseline gap-2 mb-1">
                <span class="text-5xl font-black text-primary-700">{{ $snapshot['current_points'] ?? 0 }}</span>
                <span class="text-sm text-neutral-500">points</span>
            </div>
            @if (! empty($snapshot['current_tier']))
                <p class="text-sm text-neutral-600">Palier actuel : <strong>{{ $snapshot['current_tier']['name'] ?? '—' }}</strong></p>
            @endif
            @if (! empty($snapshot['next_tier']))
                @php
                    $progress = (int) ($snapshot['progress_percent'] ?? 0);
                @endphp
                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs text-neutral-600 mb-1.5">
                        <span>Progression vers {{ $snapshot['next_tier']['name'] }}</span>
                        <span class="font-semibold">{{ $progress }}%</span>
                    </div>
                    <div class="h-3 bg-neutral-100 rounded-full overflow-hidden">
                        <div class="h-full bg-primary-600 rounded-full transition-all" style="width: {{ min(100, max(0, $progress)) }}%"></div>
                    </div>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
