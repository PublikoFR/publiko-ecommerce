@props([
    'tone' => null,
    'variant' => 'soft',   // soft | solid  (Design System)
    'size' => 'sm',
    'dot' => false,
])

@php
// Rétro-compat : d'anciens appels passent une couleur via `variant`
// (neutral/primary/success/warning/danger/new). On la remappe sur `tone`.
$legacyColors = ['neutral', 'primary', 'forest', 'lime', 'success', 'warning', 'danger', 'info', 'new'];
if ($tone === null && in_array($variant, $legacyColors, true)) {
    $tone = $variant;
    $variant = ($variant === 'new') ? 'solid' : 'soft';
}
$tone = $tone ?? 'neutral';
if ($tone === 'new') { $tone = 'lime'; $variant = 'solid'; }
if ($tone === 'primary') { $tone = 'forest'; }

$soft = [
    'forest'  => 'bg-primary-50 text-primary-700',
    'lime'    => 'bg-accent-50 text-accent-700',
    'neutral' => 'bg-neutral-100 text-neutral-700',
    'success' => 'bg-success-100 text-success-700',
    'warning' => 'bg-warning-100 text-warning-700',
    'danger'  => 'bg-danger-100 text-danger-700',
    'info'    => 'bg-info-100 text-info-700',
];
$solid = [
    'forest'  => 'bg-primary-600 text-white',
    'lime'    => 'bg-accent-500 text-primary-700',
    'neutral' => 'bg-neutral-700 text-white',
    'success' => 'bg-success-500 text-white',
    'warning' => 'bg-warning-500 text-white',
    'danger'  => 'bg-danger-500 text-white',
    'info'    => 'bg-info-500 text-white',
];
$dotColors = [
    'forest'  => 'bg-primary-600',
    'lime'    => 'bg-accent-500',
    'neutral' => 'bg-neutral-400',
    'success' => 'bg-success-500',
    'warning' => 'bg-warning-500',
    'danger'  => 'bg-danger-500',
    'info'    => 'bg-info-500',
];
$palette = $variant === 'solid' ? $solid : $soft;
$classes = $palette[$tone] ?? $palette['neutral'];

$sizes = ['xs' => 'px-1.5 py-0.5 text-[10px] gap-1', 'sm' => 'px-2 py-0.5 text-xs gap-1.5', 'md' => 'px-2.5 py-1 text-sm gap-1.5'];
@endphp

<span {{ $attributes->class(['inline-flex items-center font-semibold rounded-full', $classes, $sizes[$size] ?? $sizes['sm']]) }}>
    @if ($dot)<span class="w-1.5 h-1.5 rounded-full {{ $dotColors[$tone] ?? $dotColors['neutral'] }}"></span>@endif
    {{ $slot }}
</span>
