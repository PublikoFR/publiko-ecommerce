{{-- Styles des écrans d'import. Feuille embarquée (et non classes Tailwind) : le
     thème admin n'est recompilé qu'au build, ces vues doivent s'afficher sans lui.
     Couleurs = variables CSS de Filament, donc alignées sur le thème et le mode sombre. --}}
@once
<style>
    .pko-imp { --pko-line: rgba(var(--gray-950), .08); --pko-muted: rgb(var(--gray-500)); --pko-text: rgb(var(--gray-950)); --pko-soft: rgb(var(--gray-50)); font-size: .875rem; line-height: 1.5; color: var(--pko-text); }
    .dark .pko-imp { --pko-line: rgba(255, 255, 255, .1); --pko-muted: rgb(var(--gray-400)); --pko-text: #fff; --pko-soft: rgba(255, 255, 255, .04); }
    .pko-imp-muted { color: var(--pko-muted); }
    .pko-imp-card { border: 1px solid var(--pko-line); border-radius: .75rem; padding: 1rem 1.25rem; background: var(--pko-soft); }
    .pko-imp-card-title { font-weight: 600; margin-bottom: .5rem; }
    .pko-imp-chips { display: flex; flex-wrap: wrap; gap: .375rem; margin: .25rem 0 .5rem; }
    .pko-imp-chip { display: inline-flex; align-items: center; gap: .375rem; border: 1px solid var(--pko-line); border-radius: 999px; padding: .125rem .625rem; font-size: .75rem; background: rgba(var(--gray-500), .06); white-space: nowrap; }
    .pko-imp-chip strong { font-weight: 600; }

    /* Constats (erreur / avertissement / information) */
    .pko-imp-findings { list-style: none; margin: 0; padding: 0; display: grid; gap: .375rem; }
    .pko-imp-finding { display: flex; gap: .625rem; align-items: flex-start; padding: .5rem .75rem; border-radius: .5rem; border: 1px solid var(--pko-line); background: rgba(var(--gray-500), .04); }
    .pko-imp-finding svg { width: 1.125rem; height: 1.125rem; flex: none; margin-top: .125rem; }
    .pko-imp-finding--error { border-color: rgba(var(--danger-500), .35); background: rgba(var(--danger-500), .07); }
    .pko-imp-finding--error svg { color: rgb(var(--danger-600)); }
    .pko-imp-finding--warning { border-color: rgba(var(--warning-500), .4); background: rgba(var(--warning-500), .08); }
    .pko-imp-finding--warning svg { color: rgb(var(--warning-600)); }
    .pko-imp-finding--info svg { color: rgb(var(--gray-400)); }
    .pko-imp-finding--success { border-color: rgba(var(--success-500), .35); background: rgba(var(--success-500), .07); }
    .pko-imp-finding--success svg { color: rgb(var(--success-600)); }

    /* Fil des étapes */
    .pko-imp-steps { display: flex; align-items: center; gap: .5rem; margin: 0 0 1.25rem; padding: 0; list-style: none; }
    .pko-imp-step { display: flex; align-items: center; gap: .5rem; white-space: nowrap; color: var(--pko-muted); font-weight: 500; }
    .pko-imp-step-dot { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 999px; border: 1.5px solid rgba(var(--gray-400), .6); font-size: .8125rem; font-weight: 600; flex: none; }
    .pko-imp-step-dot svg { width: 1rem; height: 1rem; }
    .pko-imp-step--done { color: var(--pko-text); }
    .pko-imp-step--done .pko-imp-step-dot { background: rgb(var(--primary-600)); border-color: rgb(var(--primary-600)); color: #fff; }
    .pko-imp-step--current { color: var(--pko-text); font-weight: 600; }
    .pko-imp-step--current .pko-imp-step-dot { border-color: rgb(var(--primary-600)); color: rgb(var(--primary-600)); box-shadow: 0 0 0 3px rgba(var(--primary-500), .18); }
    .dark .pko-imp-step--current .pko-imp-step-dot { color: rgb(var(--primary-400)); border-color: rgb(var(--primary-400)); }
    .pko-imp-step-line { flex: 1; height: 1.5px; min-width: 1rem; background: rgba(var(--gray-400), .45); }
    .pko-imp-step-line--done { background: rgb(var(--primary-600)); }
    @media (max-width: 640px) { .pko-imp-step-label { display: none; } .pko-imp-step--current .pko-imp-step-label { display: inline; } }

    /* État courant */
    .pko-imp-state { display: flex; gap: .875rem; align-items: flex-start; }
    .pko-imp-state-icon { flex: none; width: 2.5rem; height: 2.5rem; border-radius: .625rem; display: inline-flex; align-items: center; justify-content: center; background: rgba(var(--gray-500), .1); color: rgb(var(--gray-500)); }
    .pko-imp-state-icon svg { width: 1.375rem; height: 1.375rem; }
    .pko-imp-state-icon--info { background: rgba(var(--info-500), .12); color: rgb(var(--info-600)); }
    .pko-imp-state-icon--warning { background: rgba(var(--warning-500), .14); color: rgb(var(--warning-600)); }
    .pko-imp-state-icon--danger { background: rgba(var(--danger-500), .12); color: rgb(var(--danger-600)); }
    .pko-imp-state-icon--success { background: rgba(var(--success-500), .12); color: rgb(var(--success-600)); }
    .pko-imp-state-icon--spin svg { animation: pko-imp-spin 1.6s linear infinite; }
    @keyframes pko-imp-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .pko-imp-state-icon--spin svg { animation: none; } }
    .pko-imp-state-title { font-size: 1.0625rem; font-weight: 600; line-height: 1.35; }
    .pko-imp-state-text { color: var(--pko-muted); margin-top: .125rem; max-width: 70ch; }
    .pko-imp-error { margin-top: .625rem; padding: .5rem .75rem; border-radius: .5rem; border: 1px solid rgba(var(--danger-500), .35); background: rgba(var(--danger-500), .07); word-break: break-word; }

    .pko-imp-progress { margin-top: .875rem; }
    .pko-imp-progress-bar { height: .5rem; border-radius: 999px; background: rgba(var(--gray-500), .18); overflow: hidden; }
    .pko-imp-progress-fill { height: 100%; border-radius: 999px; background: rgb(var(--primary-600)); transition: width .4s ease; }
    .pko-imp-progress-legend { display: flex; justify-content: space-between; margin-top: .25rem; font-size: .75rem; color: var(--pko-muted); font-variant-numeric: tabular-nums; }

    .pko-imp-counters { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; margin-top: 1rem; padding-top: .875rem; border-top: 1px solid var(--pko-line); }
    .pko-imp-counter { display: flex; align-items: baseline; gap: .375rem; }
    .pko-imp-counter b { font-size: 1.125rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .pko-imp-counter span { color: var(--pko-muted); }
    .pko-imp-counter--danger b { color: rgb(var(--danger-600)); }
    .pko-imp-counter--success b { color: rgb(var(--success-600)); }
    .pko-imp-counter--warning b { color: rgb(var(--warning-600)); }

    /* Fiche détails */
    .pko-imp-dl { display: grid; grid-template-columns: minmax(9rem, 14rem) 1fr; gap: .5rem 1rem; margin: 0; }
    .pko-imp-dl dt { color: var(--pko-muted); }
    .pko-imp-dl dd { margin: 0; min-width: 0; word-break: break-word; }
    @media (max-width: 640px) { .pko-imp-dl { grid-template-columns: 1fr; gap: .125rem; } .pko-imp-dl dd { margin-bottom: .5rem; } }
    .pko-imp-link { color: rgb(var(--primary-600)); font-weight: 500; text-decoration: none; }
    .pko-imp-link:hover { text-decoration: underline; }
    .dark .pko-imp-link { color: rgb(var(--primary-400)); }

    /* Console */
    .pko-imp-console { background: #0b1020; border: 1px solid #1f2937; border-radius: .5rem; padding: .75rem 1rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; line-height: 1.45; max-height: 26rem; overflow-y: auto; color: #e5e7eb; }
    .pko-imp-console-line { display: flex; align-items: baseline; gap: .5rem; }
    .pko-imp-console-line > span { flex: none; }
    .pko-imp-console-line > .pko-imp-console-msg { flex: 1; min-width: 0; white-space: pre-wrap; word-break: break-word; }
</style>
@endonce
