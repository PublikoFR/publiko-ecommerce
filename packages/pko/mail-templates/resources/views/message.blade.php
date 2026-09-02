{{--
    Rendu générique d'un e-mail transactionnel à partir de l'arbre page-builder
    (`{heading, sections:[{layout, columns:[{blocks:[]}]}]}`).

    Aucun texte en dur ici : tout vient de la base ou du fichier de contenus.

    Pourquoi un rendu dédié plutôt que celui du page-builder : le rendu web
    s'appuie sur Tailwind et des classes CSS, que Gmail et Outlook ignorent.
    Chaque bloc est donc redécliné en `<table>` + styles inline dans `blocks/`.
--}}
@php
    use Pko\MailTemplates\Support\EmailLayout;

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
            $count = count($columns);
            $columnWidth = EmailLayout::columnWidth($count);
        @endphp

        @continue($count === 0)

        @if ($count === 1)
            @foreach ($columns[0]['blocks'] as $block)
                @if (in_array($block['type'] ?? '', $supported, true))
                    @include('pko-mail-templates::blocks.'.$block['type'], ['block' => $block])
                @endif
            @endforeach
        @else
            {{--
                Colonnes en « fluid hybrid » : des blocs `inline-block` bornés par
                `max-width`, qui se replient d'eux-mêmes quand la fenêtre devient
                trop étroite. Pas de media query, volontairement — l'application
                Gmail Android les ignore, et c'est le client où le repli compte le plus.

                Outlook (Word) ne connaît pas `inline-block` : les commentaires
                conditionnels lui fournissent une vraie table, invisible partout ailleurs.

                `font-size:0` sur le conteneur supprime l'espace blanc que les
                navigateurs insèrent entre deux éléments inline ; chaque colonne
                rétablit sa taille de police.
            --}}
            <div style="font-size:0;text-align:left;margin:0 0 8px;">
                <!--[if mso]><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><![endif]-->

                @foreach ($columns as $column)
                    <!--[if mso]><td width="{{ EmailLayout::msoColumnPercent($count) }}%" valign="top"><![endif]-->
                    <div style="display:inline-block;width:100%;max-width:{{ $columnWidth }}px;vertical-align:top;font-size:15px;line-height:1.65;color:#16201d;">
                        <div style="padding:0 {{ $loop->last ? 0 : EmailLayout::COLUMN_GAP }}px 0 0;">
                            @foreach ($column['blocks'] as $block)
                                @if (in_array($block['type'] ?? '', $supported, true))
                                    @include('pko-mail-templates::blocks.'.$block['type'], ['block' => $block])
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <!--[if mso]></td><![endif]-->
                @endforeach

                <!--[if mso]></tr></table><![endif]-->
            </div>
        @endif
    @endforeach
</x-storefront-cms::mail.layout>
