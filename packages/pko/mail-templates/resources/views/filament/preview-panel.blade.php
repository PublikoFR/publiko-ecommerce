{{--
    Aperçu du mail rendu, isolé dans une iframe : le HTML d'un e-mail porte ses
    propres styles (fond, tables, largeurs fixes) qui déborderaient sur le
    back-office s'ils étaient injectés directement dans la page.
--}}
<iframe
    srcdoc="{{ $html }}"
    style="width:100%;height:75vh;border:0;background:#f6f8f7;"
    sandbox=""
    title="{{ __('pko-mail-templates::admin.action.preview') }}"
></iframe>
