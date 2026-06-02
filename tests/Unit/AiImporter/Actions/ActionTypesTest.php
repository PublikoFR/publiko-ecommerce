<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Actions;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Models\ImportJob;

/**
 * Pure unit tests for the 17 action types — no DB, no container.
 *
 * `ExecutionContext` receives a non-persisted `ImportJob` because we don't
 * need a real row for actions that only look at `$value`, `$ctx->row`, or
 * `$ctx->sheets`.
 */
class ActionTypesTest extends TestCase
{
    private function ctx(array $row = [], array $sheets = []): ExecutionContext
    {
        return new ExecutionContext(job: new ImportJob, row: $row, sheets: $sheets);
    }

    public function test_math_multiply_then_round_chains_through_factory(): void
    {
        $multiply = Action::make(['type' => 'math', 'operation' => 'multiply', 'value' => 1.2]);
        $round = Action::make(['type' => 'round', 'decimals' => 2]);

        $result = $round->execute($multiply->execute(100, $this->ctx()), $this->ctx());

        $this->assertSame(120.0, $result);
    }

    public function test_math_divide_by_zero_is_noop(): void
    {
        $action = Action::make(['type' => 'math', 'operation' => 'divide', 'value' => 0]);

        $this->assertSame(42.0, $action->execute(42, $this->ctx()));
    }

    public function test_math_tolerates_unknown_comment_key(): void
    {
        // Real PrestaShop configs annotate actions with a "comment" key that is
        // not a constructor parameter. It must be silently ignored, not crash.
        $action = Action::make([
            'type' => 'math',
            'operation' => 'multiply',
            'value' => 1.2,
            'comment' => 'Marge B2B +20%',
            '_source' => 'somfy.json',
        ]);

        $this->assertSame('multiply', $action->operation);
        $this->assertSame(1.2, $action->value);
        $this->assertSame(120.0, $action->execute(100, $this->ctx()));
    }

    public function test_legacy_multiply_type_tolerates_comment_key(): void
    {
        $action = Action::make(['type' => 'multiply', 'value' => 1.5, 'comment' => 'doc']);

        $this->assertSame('multiply', $action->operation);
        $this->assertSame(150.0, $action->execute(100, $this->ctx()));
    }

    public function test_base_factory_tolerates_unknown_keys(): void
    {
        // Default Action::fromArray (used by simple actions like trim/prefix)
        // must also drop keys absent from the constructor signature.
        $action = Action::make([
            'type' => 'prefix',
            'text' => 'REF',
            'separator' => '-',
            'comment' => 'préfixe référence',
            '_note' => 'ignored',
        ]);

        $this->assertSame('REF-4275', $action->execute('4275', $this->ctx()));
    }

    public function test_change_case_handles_three_modes(): void
    {
        $upper = Action::make(['type' => 'change_case', 'mode' => 'upper']);
        $lower = Action::make(['type' => 'change_case', 'mode' => 'lower']);
        $capitalize = Action::make(['type' => 'change_case', 'mode' => 'capitalize']);

        $this->assertSame('SOMFY', $upper->execute('Somfy', $this->ctx()));
        $this->assertSame('somfy', $lower->execute('SOMFY', $this->ctx()));
        $this->assertSame('Somfy Rts', $capitalize->execute('somfy rts', $this->ctx()));
    }

    public function test_truncate_appends_suffix_and_respects_length(): void
    {
        $action = Action::make(['type' => 'truncate', 'length' => 10, 'suffix' => '…']);

        $this->assertSame('Lorem ips…', $action->execute('Lorem ipsum dolor', $this->ctx()));
    }

    public function test_truncate_noop_when_short_enough(): void
    {
        $action = Action::make(['type' => 'truncate', 'length' => 50]);

        $this->assertSame('short', $action->execute('short', $this->ctx()));
    }

    public function test_concat_joins_other_columns_from_row(): void
    {
        $action = Action::make(['type' => 'concat', 'sources' => ['brand', 'model'], 'separator' => ' - ']);

        $this->assertSame('Somfy - RTS', $action->execute(null, $this->ctx(['brand' => 'Somfy', 'model' => 'RTS'])));
    }

    public function test_template_interpolates_named_placeholders(): void
    {
        $action = Action::make([
            'type' => 'template',
            'template' => '{brand} {name} ({sku})',
            'sources' => ['brand' => 'brand_col', 'name' => 'name_col', 'sku' => 'ref'],
        ]);

        $row = ['brand_col' => 'Somfy', 'name_col' => 'Moteur RS100', 'ref' => 'R-123'];

        $this->assertSame('Somfy Moteur RS100 (R-123)', $action->execute(null, $this->ctx($row)));
    }

    public function test_concat_resolves_object_sources_from_secondary_sheets(): void
    {
        // FAB-DIS: sources référencent des feuilles secondaires via {col, sheet}.
        $action = Action::make([
            'type' => 'concat',
            'sources' => [
                ['col' => 'URL_IMAGE_1', 'sheet' => 'B03_MEDIA'],
                ['col' => 'URL_IMAGE_2', 'sheet' => 'B03_MEDIA'],
            ],
            'separator' => ',',
        ]);

        $sheets = ['B03_MEDIA' => [['URL_IMAGE_1' => 'a.jpg', 'URL_IMAGE_2' => 'b.jpg']]];

        $this->assertSame('a.jpg,b.jpg', $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_concat_mixes_string_and_object_sources(): void
    {
        // Rétrocompat : string (row primaire) + objet (feuille secondaire) côte à côte.
        $action = Action::make([
            'type' => 'concat',
            'sources' => [
                'brand',
                ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE'],
            ],
            'separator' => ' - ',
        ]);

        $row = ['brand' => 'Somfy'];
        $sheets = ['B01_COMMERCE' => [['LIBELLE' => 'Moteur RS100']]];

        $this->assertSame('Somfy - Moteur RS100', $action->execute(null, $this->ctx($row, $sheets)));
    }

    public function test_concat_object_source_with_empty_sheet_reads_primary_row(): void
    {
        // sheet vide → la colonne est lue dans la row primaire.
        $action = Action::make([
            'type' => 'concat',
            'sources' => [['col' => 'brand', 'sheet' => '']],
        ]);

        $this->assertSame('Somfy', $action->execute(null, $this->ctx(['brand' => 'Somfy'])));
    }

    public function test_template_resolves_object_sources_from_multiple_sheets(): void
    {
        // template avec sources objet pointant deux feuilles secondaires distinctes.
        $action = Action::make([
            'type' => 'template',
            'template' => '{nom} — {marque}',
            'sources' => [
                'nom' => ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE'],
                'marque' => ['col' => 'MARQUE', 'sheet' => 'B02_MARQUE'],
            ],
        ]);

        $sheets = [
            'B01_COMMERCE' => [['LIBELLE' => 'Moteur RS100']],
            'B02_MARQUE' => [['MARQUE' => 'Somfy']],
        ];

        $this->assertSame('Moteur RS100 — Somfy', $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_template_mixes_string_and_object_sources(): void
    {
        $action = Action::make([
            'type' => 'template',
            'template' => '{ref} {nom}',
            'sources' => [
                'ref' => 'ref_col',
                'nom' => ['col' => 'LIBELLE', 'sheet' => 'B01_COMMERCE'],
            ],
        ]);

        $row = ['ref_col' => 'R-123'];
        $sheets = ['B01_COMMERCE' => [['LIBELLE' => 'Moteur RS100']]];

        $this->assertSame('R-123 Moteur RS100', $action->execute(null, $this->ctx($row, $sheets)));
    }

    public function test_object_source_missing_secondary_row_yields_empty(): void
    {
        // Feuille secondaire sans row jointe pour cette ligne → chaîne vide (pas de crash).
        $action = Action::make([
            'type' => 'concat',
            'sources' => [
                'brand',
                ['col' => 'URL_IMAGE_1', 'sheet' => 'B03_MEDIA'],
            ],
        ]);

        $this->assertSame('Somfy', $action->execute(null, $this->ctx(['brand' => 'Somfy'])));
    }

    public function test_map_single_value(): void
    {
        $action = Action::make([
            'type' => 'map',
            'values' => ['A' => 'Actif', 'I' => 'Inactif'],
            'default' => 'Inconnu',
        ]);

        $this->assertSame('Actif', $action->execute('A', $this->ctx()));
        $this->assertSame('Inconnu', $action->execute('Z', $this->ctx()));
    }

    public function test_map_multi_value_splits_on_separator(): void
    {
        $action = Action::make([
            'type' => 'map',
            'values' => ['red' => 'Rouge', 'blue' => 'Bleu'],
            'default' => null,
            'multi_value' => true,
        ]);

        $this->assertSame('Rouge,Bleu', $action->execute('red,blue,yellow', $this->ctx()));
    }

    public function test_validate_ean13_accepts_valid_and_rejects_bad_checksum(): void
    {
        $action = Action::make(['type' => 'validate_ean13']);

        $this->assertSame('3017620422003', $action->execute('3017620422003', $this->ctx()));
        $this->assertSame('', $action->execute('3017620422002', $this->ctx()));
        $this->assertSame('', $action->execute('nope', $this->ctx()));
    }

    public function test_slugify_produces_url_safe_string(): void
    {
        $action = Action::make(['type' => 'slugify']);

        $this->assertSame('portail-aluminium-2m', $action->execute('Portail Aluminium 2M !', $this->ctx()));
    }

    public function test_replace_and_regex_replace(): void
    {
        $replace = Action::make(['type' => 'replace', 'search' => 'FR', 'replace' => 'France']);
        $regex = Action::make(['type' => 'regex_replace', 'pattern' => '/\\s+/', 'replace' => '-']);

        $this->assertSame('Made in France', $replace->execute('Made in FR', $this->ctx()));
        $this->assertSame('ab-cd-ef', $regex->execute("ab  cd\tef", $this->ctx()));
    }

    public function test_copy_returns_other_column(): void
    {
        $action = Action::make(['type' => 'copy', 'col' => 'src']);

        $this->assertSame('hello', $action->execute('original', $this->ctx(['src' => 'hello'])));
    }

    public function test_trim_respects_side(): void
    {
        $left = Action::make(['type' => 'trim', 'side' => 'left']);
        $right = Action::make(['type' => 'trim', 'side' => 'right']);

        $this->assertSame('foo  ', $left->execute('  foo  ', $this->ctx()));
        $this->assertSame('  foo', $right->execute('  foo  ', $this->ctx()));
    }

    public function test_date_format_converts_between_patterns(): void
    {
        $action = Action::make(['type' => 'date_format', 'from' => 'Y-m-d', 'to' => 'd/m/Y']);

        $this->assertSame('17/04/2026', $action->execute('2026-04-17', $this->ctx()));
    }

    public function test_multiline_aggregate_concat_filters_and_joins(): void
    {
        $sheets = [
            'B02' => [
                ['type' => 'CODE_IMAGE', 'url' => 'a.jpg'],
                ['type' => 'CODE_DOC', 'url' => 'manual.pdf'],
                ['type' => 'CODE_IMAGE', 'url' => 'b.jpg'],
            ],
        ];

        $action = Action::make([
            'type' => 'multiline_aggregate',
            'sheet' => 'B02',
            'method' => 'concat',
            'separator' => '|',
            'filter_type' => 'CODE_IMAGE',
            'columns' => ['url'],
        ]);

        $this->assertSame('a.jpg|b.jpg', $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_multiline_aggregate_count(): void
    {
        $sheets = ['X' => [['foo' => 1], ['foo' => 2], ['foo' => 3]]];
        $action = Action::make(['type' => 'multiline_aggregate', 'sheet' => 'X', 'method' => 'count', 'columns' => ['foo']]);

        $this->assertSame(3, $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_multiline_aggregate_filter_type_accepts_csv(): void
    {
        $sheets = [
            'M' => [
                ['type' => 'NOTICE', 'url' => 'notice.pdf'],
                ['type' => 'PHOTO', 'url' => 'a.jpg'],
                ['type' => 'BROCH', 'url' => 'brochure.pdf'],
                ['type' => 'VIDEO', 'url' => 'v.mp4'],
            ],
        ];

        $action = Action::make([
            'type' => 'multiline_aggregate',
            'sheet' => 'M',
            'method' => 'count',
            'filter_type' => 'NOTICE,BROCH',
            'columns' => ['url'],
        ]);

        $this->assertSame(2, $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_multiline_aggregate_filters_on_custom_type_col(): void
    {
        $sheets = [
            'B03' => [
                ['MTYP' => 'PHOTO', 'URL' => 'a.jpg'],
                ['MTYP' => 'NOTICE', 'URL' => 'manual.pdf'],
                ['MTYP' => 'PHOTO', 'URL' => 'b.jpg'],
            ],
        ];

        $action = Action::make([
            'type' => 'multiline_aggregate',
            'sheet' => 'B03',
            'method' => 'concat',
            'separator' => '|',
            'filter_type' => 'PHOTO',
            'type_col' => 'MTYP',
            'columns' => ['URL'],
        ]);

        $this->assertSame('a.jpg|b.jpg', $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_prefix_prepends_text(): void
    {
        $action = Action::make(['type' => 'prefix', 'text' => 'SOM']);

        $this->assertSame('SOM4275', $action->execute('4275', $this->ctx()));
    }

    public function test_prefix_with_separator(): void
    {
        $action = Action::make(['type' => 'prefix', 'text' => 'REF', 'separator' => '-']);

        $this->assertSame('REF-4275', $action->execute('4275', $this->ctx()));
    }

    public function test_prefix_on_empty_value_returns_text_only(): void
    {
        $action = Action::make(['type' => 'prefix', 'text' => 'SOM', 'separator' => '-']);

        $this->assertSame('SOM', $action->execute('', $this->ctx()));
    }

    public function test_suffix_appends_text(): void
    {
        $action = Action::make(['type' => 'suffix', 'text' => 'EUR', 'separator' => ' ']);

        $this->assertSame('19.90 EUR', $action->execute('19.90', $this->ctx()));
    }

    public function test_legacy_multiply_type_routes_to_math(): void
    {
        $action = Action::make(['type' => 'multiply', 'value' => 1.2]);

        $this->assertSame(120.0, $action->execute(100, $this->ctx()));
    }

    public function test_legacy_divide_type_routes_to_math(): void
    {
        $action = Action::make(['type' => 'divide', 'value' => 10]);

        $this->assertSame(2.5, $action->execute(25, $this->ctx()));
    }

    public function test_legacy_add_and_subtract_types_route_to_math(): void
    {
        $add = Action::make(['type' => 'add', 'value' => 5]);
        $sub = Action::make(['type' => 'subtract', 'value' => 3]);

        $this->assertSame(15.0, $add->execute(10, $this->ctx()));
        $this->assertSame(7.0, $sub->execute(10, $this->ctx()));
    }

    public function test_parse_features_string_default_format(): void
    {
        $action = Action::make(['type' => 'parse_features_string']);

        $result = $action->execute('Couleur:Rouge,Bleu|Matière:Aluminium|Application:BSO,Volet roulant', $this->ctx());

        $this->assertSame([
            'couleur' => ['rouge', 'bleu'],
            'matiere' => ['aluminium'],
            'application' => ['bso', 'volet-roulant'],
        ], $result);
    }

    public function test_parse_features_string_empty_input_returns_empty_array(): void
    {
        $action = Action::make(['type' => 'parse_features_string']);

        $this->assertSame([], $action->execute('', $this->ctx()));
        $this->assertSame([], $action->execute('   ', $this->ctx()));
    }

    public function test_parse_features_string_skips_malformed_pairs(): void
    {
        $action = Action::make(['type' => 'parse_features_string']);

        $result = $action->execute('Garbage|OnlyKey:|:OnlyValue|Real:Yes', $this->ctx());

        $this->assertSame(['real' => ['yes']], $result);
    }

    public function test_parse_features_string_no_slugify_keeps_raw(): void
    {
        $action = Action::make(['type' => 'parse_features_string', 'slugify' => false]);

        $result = $action->execute('couleur:Rouge,Bleu', $this->ctx());

        $this->assertSame(['couleur' => ['Rouge', 'Bleu']], $result);
    }

    public function test_parse_features_string_custom_separators(): void
    {
        $action = Action::make([
            'type' => 'parse_features_string',
            'family_separator' => ';',
            'kv_separator' => '=',
            'value_separator' => '/',
        ]);

        $result = $action->execute('couleur=rouge/bleu;matiere=aluminium', $this->ctx());

        $this->assertSame([
            'couleur' => ['rouge', 'bleu'],
            'matiere' => ['aluminium'],
        ], $result);
    }

    public function test_parse_category_breadcrumb_leaf_mode_default(): void
    {
        $action = Action::make(['type' => 'parse_category_breadcrumb']);

        $result = $action->execute('Accueil>Motorisation>Moteur filaire,Accueil>Domotique', $this->ctx());

        $this->assertSame('moteur-filaire,domotique', $result);
    }

    public function test_parse_category_breadcrumb_all_mode_keeps_every_segment(): void
    {
        $action = Action::make(['type' => 'parse_category_breadcrumb', 'mode' => 'all']);

        $result = $action->execute('Accueil>Motorisation>Moteur filaire', $this->ctx());

        $this->assertSame('accueil,motorisation,moteur-filaire', $result);
    }

    public function test_parse_category_breadcrumb_dedupes_handles(): void
    {
        $action = Action::make(['type' => 'parse_category_breadcrumb']);

        $result = $action->execute('A>B>Moteur filaire,X>Y>Moteur filaire', $this->ctx());

        $this->assertSame('moteur-filaire', $result);
    }

    public function test_parse_category_breadcrumb_empty_input(): void
    {
        $action = Action::make(['type' => 'parse_category_breadcrumb']);

        $this->assertSame('', $action->execute('', $this->ctx()));
        $this->assertSame('', $action->execute('   ', $this->ctx()));
    }

    public function test_condition_first_matching_branch_wins(): void
    {
        $action = Action::make([
            'type' => 'condition',
            'branches' => [
                [
                    'logic' => 'AND',
                    'rules' => [['field' => 'flag', 'operator' => '=', 'value' => 'A']],
                    'actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 2]],
                ],
                [
                    'logic' => 'AND',
                    'rules' => [['field' => 'flag', 'operator' => '=', 'value' => 'B']],
                    'actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 3]],
                ],
            ],
            'else_actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 10]],
        ]);

        $this->assertSame(20.0, $action->execute(10, $this->ctx(['flag' => 'A'])));
        $this->assertSame(30.0, $action->execute(10, $this->ctx(['flag' => 'B'])));
        $this->assertSame(100.0, $action->execute(10, $this->ctx(['flag' => 'Z'])));
    }

    public function test_condition_supports_sheet_col_field_syntax(): void
    {
        $action = Action::make([
            'type' => 'condition',
            'branches' => [[
                'rules' => [['field' => 'B01:AB', 'operator' => '=', 'value' => 'GTK']],
                'actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 1.2]],
            ]],
            'else_actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 1.0]],
        ]);

        $sheets = ['B01' => [['AB' => 'GTK', 'M' => 100]]];

        $this->assertSame(120.0, $action->execute(100, $this->ctx([], $sheets)));
    }

    public function test_condition_or_logic(): void
    {
        $action = Action::make([
            'type' => 'condition',
            'branches' => [[
                'logic' => 'OR',
                'rules' => [
                    ['field' => 'cat', 'operator' => '=', 'value' => 'A'],
                    ['field' => 'cat', 'operator' => '=', 'value' => 'B'],
                ],
                'actions' => [['type' => 'math', 'operation' => 'add', 'value' => 1]],
            ]],
            'else_actions' => [],
        ]);

        $this->assertSame(11.0, $action->execute(10, $this->ctx(['cat' => 'A'])));
        $this->assertSame(11.0, $action->execute(10, $this->ctx(['cat' => 'B'])));
        $this->assertSame(10, $action->execute(10, $this->ctx(['cat' => 'C']))); // else_actions vide → valeur inchangée
    }

    public function test_condition_in_operator_with_csv_value(): void
    {
        $action = Action::make([
            'type' => 'condition',
            'branches' => [[
                'rules' => [['field' => 'cat', 'operator' => 'in', 'value' => 'A,B,C']],
                'actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 2]],
            ]],
            'else_actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 1]],
        ]);

        $this->assertSame(20.0, $action->execute(10, $this->ctx(['cat' => 'B'])));
        $this->assertSame(10.0, $action->execute(10, $this->ctx(['cat' => 'Z'])));
    }

    public function test_condition_no_branch_matches_runs_else_actions(): void
    {
        $action = Action::make([
            'type' => 'condition',
            'branches' => [[
                'rules' => [['field' => 'never', 'operator' => '=', 'value' => 'never']],
                'actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 100]],
            ]],
            'else_actions' => [['type' => 'math', 'operation' => 'multiply', 'value' => 3]],
        ]);

        $this->assertSame(30.0, $action->execute(10, $this->ctx(['flag' => 'X'])));
    }

    public function test_condition_nested_self_is_silently_ignored(): void
    {
        // Defensive: chaining condition inside a branch's actions is no-op to
        // avoid pathological recursion. Use a top-level chain of conditions
        // at the column level instead.
        $action = Action::make([
            'type' => 'condition',
            'branches' => [[
                'rules' => [['field' => 'flag', 'operator' => '=', 'value' => 'A']],
                'actions' => [
                    ['type' => 'condition', 'branches' => []], // ignored
                    ['type' => 'math', 'operation' => 'multiply', 'value' => 2],
                ],
            ]],
            'else_actions' => [],
        ]);

        $this->assertSame(20.0, $action->execute(10, $this->ctx(['flag' => 'A'])));
    }

    public function test_category_map_maps_value_to_breadcrumb_then_csv_handles(): void
    {
        $action = Action::make([
            'type' => 'category_map',
            'values' => [
                'Moteurs' => 'Accueil>Motorisation>Moteur filaire',
                'Domotique' => 'Accueil>Domotique',
            ],
            'default_category' => 'Accueil>Divers',
        ]);

        // Leaf + slugify (aligné sur ParseCategoryBreadcrumbAction) → compatible writer.
        $this->assertSame('moteur-filaire', $action->execute('Moteurs', $this->ctx()));
        $this->assertSame('domotique', $action->execute('Domotique', $this->ctx()));
    }

    public function test_category_map_falls_back_to_default_category_on_miss(): void
    {
        $action = Action::make([
            'type' => 'category_map',
            'values' => ['Moteurs' => 'Accueil>Motorisation>Moteur filaire'],
            'default_category' => 'Accueil>Divers',
        ]);

        $this->assertSame('divers', $action->execute('Inconnu', $this->ctx()));
    }

    public function test_category_map_no_match_no_default_returns_empty(): void
    {
        $action = Action::make([
            'type' => 'category_map',
            'values' => ['Moteurs' => 'Accueil>Motorisation>Moteur filaire'],
        ]);

        $this->assertSame('', $action->execute('Inconnu', $this->ctx()));
    }

    public function test_category_map_all_mode_keeps_every_segment(): void
    {
        $action = Action::make([
            'type' => 'category_map',
            'values' => ['Moteurs' => 'Accueil>Motorisation>Moteur filaire'],
            'mode' => 'all',
        ]);

        $this->assertSame('accueil,motorisation,moteur-filaire', $action->execute('Moteurs', $this->ctx()));
    }

    public function test_conditional_greater_than_returns_branch_value(): void
    {
        $action = Action::make([
            'type' => 'conditional',
            'condition' => '> 0',
            'if_true' => '1',
            'if_false' => '0',
        ]);

        $this->assertSame('1', $action->execute('5', $this->ctx()));
        $this->assertSame('0', $action->execute('0', $this->ctx()));
        $this->assertSame('0', $action->execute('-3', $this->ctx()));
    }

    public function test_conditional_gte_operator(): void
    {
        $action = Action::make([
            'type' => 'conditional',
            'condition' => '>= 5',
            'if_true' => 'ok',
            'if_false' => 'ko',
        ]);

        $this->assertSame('ok', $action->execute(5, $this->ctx()));
        $this->assertSame('ko', $action->execute(4, $this->ctx()));
    }

    public function test_conditional_equality_operator(): void
    {
        $action = Action::make([
            'type' => 'conditional',
            'condition' => '= GTK',
            'if_true' => 'oui',
            'if_false' => 'non',
        ]);

        $this->assertSame('oui', $action->execute('GTK', $this->ctx()));
        $this->assertSame('non', $action->execute('ABC', $this->ctx()));
    }

    public function test_conditional_bare_condition_is_treated_as_equality(): void
    {
        $action = Action::make([
            'type' => 'conditional',
            'condition' => 'GTK',
            'if_true' => 'oui',
            'if_false' => 'non',
        ]);

        $this->assertSame('oui', $action->execute('GTK', $this->ctx()));
        $this->assertSame('non', $action->execute('XXX', $this->ctx()));
    }

    public function test_conditional_contains_operator(): void
    {
        $action = Action::make([
            'type' => 'conditional',
            'condition' => 'contains pro',
            'if_true' => 'pro',
            'if_false' => 'std',
        ]);

        $this->assertSame('pro', $action->execute('gamme professionnelle', $this->ctx()));
        $this->assertSame('std', $action->execute('gamme standard', $this->ctx()));
    }

    public function test_conditional_numeric_operator_with_non_numeric_value_is_false(): void
    {
        $action = Action::make([
            'type' => 'conditional',
            'condition' => '> 0',
            'if_true' => '1',
            'if_false' => '0',
        ]);

        $this->assertSame('0', $action->execute('abc', $this->ctx()));
    }

    public function test_legacy_uppercase_type_routes_to_change_case(): void
    {
        $action = Action::make(['type' => 'uppercase']);

        $this->assertSame('SOMFY', $action->execute('Somfy', $this->ctx()));
    }

    public function test_legacy_lowercase_type_routes_to_change_case(): void
    {
        $action = Action::make(['type' => 'lowercase']);

        $this->assertSame('somfy', $action->execute('SOMFY', $this->ctx()));
    }

    public function test_legacy_capitalize_type_routes_to_change_case(): void
    {
        $action = Action::make(['type' => 'capitalize']);

        $this->assertSame('Somfy Rts', $action->execute('somfy rts', $this->ctx()));
    }
}
