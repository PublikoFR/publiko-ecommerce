<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Pose des styles inline sur le HTML riche saisi dans l'éditeur.
 *
 * L'éditeur produit du HTML sémantique (`<p>`, `<a>`, `<ul>`) sans style : sur
 * le site, la feuille de styles s'en charge. Un client mail n'en a pas, et
 * applique ses propres valeurs par défaut — marges de paragraphe différentes
 * d'un client à l'autre, liens en bleu système au lieu de la couleur de marque,
 * indentation de liste imprévisible.
 *
 * Les styles déjà présents sur l'élément sont conservés et placés APRÈS les
 * nôtres : à déclarations égales dans un même attribut `style`, la dernière
 * l'emporte, donc la mise en forme choisie par le rédacteur gagne toujours.
 */
final class EmailHtml
{
    /** @var array<string, string> */
    private const STYLES = [
        'p' => 'margin:0 0 12px;',
        'a' => 'color:#00453e;text-decoration:underline;',
        'ul' => 'margin:0 0 12px;padding-left:20px;',
        'ol' => 'margin:0 0 12px;padding-left:20px;',
        'li' => 'margin:0 0 4px;',
        'h1' => 'margin:0 0 12px;font-size:20px;line-height:1.35;color:#00453e;',
        'h2' => 'margin:0 0 10px;font-size:18px;line-height:1.35;color:#00453e;',
        'h3' => 'margin:0 0 10px;font-size:16px;line-height:1.4;color:#00453e;',
        'h4' => 'margin:0 0 8px;font-size:15px;line-height:1.4;color:#00453e;',
        'blockquote' => 'margin:0 0 12px;padding:0 0 0 14px;border-left:3px solid #aac932;color:#4a5a55;',
        'hr' => 'border:0;border-top:1px solid #eef1f0;margin:18px 0;',
        'table' => 'border-collapse:collapse;width:100%;margin:0 0 12px;',
        'th' => 'border:1px solid #e0e4e2;padding:6px 8px;text-align:left;background:#f6f8f7;',
        'td' => 'border:1px solid #e0e4e2;padding:6px 8px;',
        'img' => 'max-width:100%;height:auto;display:block;border:0;',
    ];

    public static function inlineStyles(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        // Le HTML vient déjà assaini par le page-builder, mais il reste un
        // fragment : on l'enveloppe pour que DOMDocument ne lui ajoute pas de
        // <html>/<body>, et on neutralise ses avertissements de parsing.
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="pko-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($loaded === false) {
            // HTML irrécupérable : mieux vaut le rendre non stylé que rien.
            return $html;
        }

        try {
            $xpath = new DOMXPath($document);

            foreach (self::STYLES as $tag => $style) {
                foreach ($xpath->query('//'.$tag) ?: [] as $node) {
                    if ($node instanceof DOMElement) {
                        $existing = $node->getAttribute('style');
                        $node->setAttribute('style', $style.$existing);
                    }
                }
            }

            $root = $document->getElementById('pko-root');

            if ($root === null) {
                return $html;
            }

            $out = '';
            foreach ($root->childNodes as $child) {
                $out .= $document->saveHTML($child);
            }

            return $out;
        } catch (Throwable) {
            return $html;
        }
    }
}
