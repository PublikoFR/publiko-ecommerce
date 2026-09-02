{{--
    Rendu générique d'un e-mail transactionnel à partir de l'arbre page-builder
    (`{heading, sections:[{layout, columns:[{blocks:[]}]}]}`).

    Aucun texte en dur ici : tout vient de la base ou du fichier de contenus.

    Pourquoi un rendu dédié plutôt que celui du page-builder : le rendu web
    s'appuie sur Tailwind et des classes CSS, que Gmail et Outlook ignorent.
    Chaque bloc est donc redécliné en `<table>` + styles inline dans `blocks/`.
--}}
@php
    // Blocs du page-builder sans équivalent e-mail : une vidéo ne se lit pas dans
    // un client mail, un accordéon n'a pas de JS, une galerie casse la mise en
    // page. On les ignore silencieusement plutôt que de rendre du vide cassé.
    $supported = ['text', 'title', 'button', 'separator', 'callout', 'list', 'image', 'quote'];

    $sections = $content['sections'] ?? [];
@endphp

<x-storefront-cms::mail.layout :title="$subjectLine" :preheader="$preheader ?? null">
    @if (! empty($content['heading']))
        <h1 style="margin:0 0 16px;font-size:20px;line-height:1.35;color:#00453e;">{{ $content['heading'] }}</h1>
    @endif

    @foreach ($sections as $section)
        @php
            $columns = array_values(array_filter(
                $section['columns'] ?? [],
                static fn ($column) => ! empty($column['blocks']),
            ));
        @endphp

        @continue($columns === [])

        @if (count($columns) === 1)
            @foreach ($columns[0]['blocks'] as $block)
                @if (in_array($block['type'] ?? '', $supported, true))
                    @include('pko-mail-templates::blocks.'.$block['type'], ['block' => $block])
                @endif
            @endforeach
        @else
            {{-- Multi-colonnes : table à cellules côte à côte. Volontairement limité
                 à 2 colonnes rendues — au-delà, illisible sur mobile et mal géré
                 par Outlook ; les colonnes suivantes sont empilées à la suite. --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                <tr>
                    @foreach (array_slice($columns, 0, 2) as $column)
                        <td valign="top" width="50%" style="padding-right:12px;">
                            @foreach ($column['blocks'] as $block)
                                @if (in_array($block['type'] ?? '', $supported, true))
                                    @include('pko-mail-templates::blocks.'.$block['type'], ['block' => $block])
                                @endif
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            </table>

            @foreach (array_slice($columns, 2) as $column)
                @foreach ($column['blocks'] as $block)
                    @if (in_array($block['type'] ?? '', $supported, true))
                        @include('pko-mail-templates::blocks.'.$block['type'], ['block' => $block])
                    @endif
                @endforeach
            @endforeach
        @endif
    @endforeach
</x-storefront-cms::mail.layout>
