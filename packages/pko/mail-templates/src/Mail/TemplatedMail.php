<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Pko\MailTemplates\Support\Placeholders;
use Pko\MailTemplates\Support\TemplateResolver;

/**
 * Mailable unique de tous les e-mails transactionnels : le contenu vient de la
 * base (ou du fichier de contenus par défaut), seul le jeu de placeholders
 * change d'un envoi à l'autre.
 *
 * Un mail désactivé, ou dont la clé n'existe pas, ne lève pas d'exception :
 * `shouldSend()` renvoie false et l'appelant s'abstient. Un contenu manquant ne
 * doit jamais faire échouer la transaction métier qui le déclenche (un paiement
 * encaissé ne doit pas être perdu parce qu'un texte n'a pas été rédigé).
 */
class TemplatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var array{subject: string, content: array<string, mixed>, enabled: bool}|null */
    private ?array $template;

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public function __construct(
        public readonly string $key,
        public readonly array $values = [],
        // `$locale` est déjà défini par Illuminate\Mail\Mailable (et non readonly) :
        // le redéclarer ici casse le chargement de la classe.
        public readonly string $templateLocale = 'fr',
    ) {
        $this->template = TemplateResolver::resolve($key, $templateLocale);
    }

    /**
     * Préfixe ajouté à l'objet, pour distinguer un envoi de test d'un vrai
     * message dans la boîte de réception du destinataire.
     */
    public string $subjectPrefix = '';

    /** Le contenu existe et le mail est actif. */
    public function shouldSend(): bool
    {
        return $this->template !== null;
    }

    public function build(): static
    {
        if ($this->template === null) {
            throw new \LogicException(
                "E-mail « {$this->key} » indisponible (clé inconnue ou désactivée). ".
                'Vérifier shouldSend() avant l\'envoi.'
            );
        }

        $subject = $this->subjectPrefix.Placeholders::apply($this->template['subject'], $this->values);

        return $this
            ->subject($subject)
            ->view('pko-mail-templates::message', [
                'content' => Placeholders::applyToContent($this->template['content'], $this->values),
                'subjectLine' => $subject,
            ]);
    }
}
