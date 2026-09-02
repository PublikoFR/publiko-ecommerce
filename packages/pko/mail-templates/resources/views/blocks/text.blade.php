{{--
    Bloc `text` : HTML riche (Tiptap, assaini par PageBuilderManager::sanitizeHtml
    à l'enregistrement). Rendu non échappé — c'est pourquoi les VALEURS de
    placeholders substituées dans ce champ sont échappées en amont
    (cf. Placeholders::ESCAPED_VALUE_FIELDS).

    EmailHtml pose les styles inline que les clients mail n'ont pas : sans lui,
    les marges de paragraphe varient d'un client à l'autre et les liens
    s'affichent en bleu système au lieu de la couleur de marque.
--}}
<div style="margin:0 0 14px;font-size:15px;line-height:1.65;color:#16201d;">
    {!! \Pko\MailTemplates\Support\EmailHtml::inlineStyles($block['html'] ?? '') !!}
</div>
