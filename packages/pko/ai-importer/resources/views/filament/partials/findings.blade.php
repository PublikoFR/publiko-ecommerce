{{-- Liste de constats `[level, message]` (ConfigDiagnostics / FilePreflight). --}}
@php
    $icons = [
        'error' => 'heroicon-m-x-circle',
        'warning' => 'heroicon-m-exclamation-triangle',
        'info' => 'heroicon-m-information-circle',
        'success' => 'heroicon-m-check-circle',
    ];
@endphp
<ul class="pko-imp-findings">
    @foreach ($findings as $finding)
        <li class="pko-imp-finding pko-imp-finding--{{ $finding['level'] }}">
            <x-filament::icon :icon="$icons[$finding['level']] ?? $icons['info']" />
            <span>{{ $finding['message'] }}</span>
        </li>
    @endforeach
</ul>
