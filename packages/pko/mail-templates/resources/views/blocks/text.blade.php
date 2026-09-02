{{--
    Bloc `text` : HTML riche (Tiptap, assaini par PageBuilderManager::sanitizeHtml
    à l'enregistrement). Rendu non échappé — c'est pourquoi les VALEURS de
    placeholders substituées dans ce champ sont échappées en amont
    (cf. Placeholders::ESCAPED_VALUE_FIELDS).
--}}
<div style="margin:0 0 14px;font-size:15px;line-height:1.65;color:#16201d;">
    {!! $block['html'] ?? '' !!}
</div>
