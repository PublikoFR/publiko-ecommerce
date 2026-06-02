<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Mappe une valeur source (libellé fournisseur) vers un fil d'Ariane de
 * catégories via une table `values`, puis le convertit en CSV de handles
 * consommable par `LunarProductWriter::collections`.
 *
 * Config shape (matches la vraie config PrestaShop `somfy.json`) :
 *
 * ```json
 * {
 *   "type": "category_map",
 *   "values": {
 *     "Moteurs": "Accueil>Motorisation>Moteur filaire",
 *     "Domotique": "Accueil>Domotique"
 *   },
 *   "default_category": "Accueil>Divers"
 * }
 * ```
 *
 * Semantics :
 *   - La valeur entrante (trim) est cherchée dans `values`. Match → on récupère
 *     son breadcrumb `"A>B>C"`. Pas de match → fallback `default_category`.
 *   - Le breadcrumb obtenu est ensuite parsé par {@see ParseCategoryBreadcrumbAction}
 *     (réutilisation de sa logique slug/leaf) pour rester compatible avec le
 *     writer : sortie = CSV de handles slugifiés (ex. `"moteur-filaire"`).
 *   - `default_category` vide/absent + aucun match → chaîne vide (aucune
 *     collection rattachée).
 *
 * Les paramètres de séparation/slug sont délégués tels quels au parse du
 * breadcrumb (mêmes défauts : `leaf` + slugify).
 */
final class CategoryMapAction extends Action
{
    /**
     * @param  array<string, string>  $values  table {libellé source => "A>B>C"}
     */
    public function __construct(
        public readonly array $values = [],
        public readonly ?string $default_category = null,
        public readonly string $path_separator = ',',
        public readonly string $segment_separator = '>',
        public readonly string $mode = 'leaf', // leaf|all
        public readonly bool $slugify = true,
        public readonly string $output_separator = ',',
    ) {}

    public static function type(): string
    {
        return 'category_map';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $key = trim((string) $value);
        $breadcrumb = $this->values[$key] ?? $this->default_category ?? '';

        if (trim((string) $breadcrumb) === '') {
            return '';
        }

        return (new ParseCategoryBreadcrumbAction(
            path_separator: $this->path_separator,
            segment_separator: $this->segment_separator,
            mode: $this->mode,
            slugify: $this->slugify,
            output_separator: $this->output_separator,
        ))->execute($breadcrumb, $ctx);
    }
}
