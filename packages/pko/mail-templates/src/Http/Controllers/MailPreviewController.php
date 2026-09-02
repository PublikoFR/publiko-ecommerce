<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Http\Controllers;

use Illuminate\Http\Response;
use Pko\MailTemplates\Support\MailPreview;
use Pko\MailTemplates\Support\MailTemplateRegistry;
use Pko\MailTemplates\Support\TemplateResolver;

/**
 * Prévisualisation des e-mails, hors production uniquement (cf. provider).
 * Permet de relire les contenus dans un navigateur sans déclencher d'envoi.
 */
class MailPreviewController
{
    public function index(): Response
    {
        $rows = '';

        foreach (MailTemplateRegistry::all() as $key => $meta) {
            $resolved = TemplateResolver::resolve($key);
            $state = $resolved === null
                ? '<span style="color:#b45309;">désactivé / sans contenu</span>'
                : '<span style="color:#15803d;">actif</span>';

            $rows .= sprintf(
                '<tr><td style="padding:6px 14px 6px 0;"><a href="/_mail/%s">%s</a></td>'
                .'<td style="padding:6px 14px 6px 0;color:#555;">%s</td><td style="padding:6px 0;">%s</td></tr>',
                e($key),
                e($key),
                e($meta['label']),
                $state,
            );
        }

        return response(
            '<!doctype html><meta charset="utf-8"><title>Aperçu des e-mails</title>'
            .'<body style="font-family:system-ui,sans-serif;padding:32px;">'
            .'<h1 style="font-size:18px;">Aperçu des e-mails transactionnels</h1>'
            .'<table style="font-size:14px;border-collapse:collapse;">'.$rows.'</table></body>'
        );
    }

    public function show(string $key): mixed
    {
        abort_unless(MailTemplateRegistry::has($key), 404, "Clé d'e-mail inconnue : {$key}");

        $mail = MailPreview::mail($key);

        abort_unless(
            $mail->shouldSend(),
            404,
            "L'e-mail « {$key} » est désactivé ou sans contenu — rien à prévisualiser."
        );

        return $mail;
    }
}
