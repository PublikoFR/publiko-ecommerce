{{-- Nom + rôle du staff connecté, affiché à gauche de l'avatar (topbar).
     Injecté via renderHook(PanelsRenderHook::USER_MENU_BEFORE). --}}
@php
    $u = filament()->auth()->user();
    $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : null;
    $role = $u?->roles?->first()?->name ?? (($u->admin ?? false) ? 'Administrateur' : 'Membre');
@endphp

@if ($u && $name !== '')
    <div class="wk-user-identity">
        <span class="wk-user-name">{{ $name }}</span>
        <span class="wk-user-role">{{ \Illuminate\Support\Str::title($role) }}</span>
    </div>
@endif
