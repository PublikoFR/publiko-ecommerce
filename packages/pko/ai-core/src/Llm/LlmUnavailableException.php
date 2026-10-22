<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm;

/** Fournisseur ou modèle IA inutilisable (inconnu, sans clé active). Message destiné à l'utilisateur. */
final class LlmUnavailableException extends \RuntimeException {}
