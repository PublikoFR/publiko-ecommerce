<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Illuminate\Support\Str;
use Pko\CatalogFeatures\Models\FeatureFamily;

/**
 * Familles de caractéristiques et valeurs réelles du site, lues en base à l'instant T.
 *
 * Pendant de {@see CollectionCatalog} : `expand()` remplace le marqueur
 * {@see self::PLACEHOLDER} du contexte IA par la liste actuelle, `parse()`
 * rattache la réponse de l'IA (`Famille:Valeur 1,Valeur 2|Famille 2:Valeur`)
 * aux handles du package catalog-features.
 */
class FeatureCatalog
{
    public const PLACEHOLDER = '{{features}}';

    /** @var array<int, array{handle: string, name: string, values: array<int, array{handle: string, name: string}>}>|null */
    private static ?array $families = null;

    /** À appeler au début d'un parse / d'un import : la liste est relue une fois par job. */
    public static function flush(): void
    {
        self::$families = null;
    }

    /**
     * @return array<int, string> Une ligne par famille : « Famille : Valeur 1, Valeur 2 ».
     */
    public function lines(): array
    {
        return array_map(
            fn (array $f): string => $f['name'].' : '.implode(', ', array_column($f['values'], 'name')),
            $this->families(),
        );
    }

    public function expand(string $context): string
    {
        if (! str_contains($context, self::PLACEHOLDER)) {
            return $context;
        }

        $lines = $this->lines();

        return str_replace(
            self::PLACEHOLDER,
            $lines === [] ? '(aucune caractéristique définie sur le site)' : implode("\n", $lines),
            $context,
        );
    }

    /**
     * « Type de produit:Moteur seul|Marque:SOMFY,CAME » → hash de handles.
     * Familles et valeurs sont retrouvées par nom OU handle (insensible à la
     * casse et aux accents) ; ce qui n'existe pas est renvoyé dans `unresolved`.
     *
     * @return array{features: array<string, array<int, string>>, unresolved: array<int, string>}
     */
    public function parse(string $raw): array
    {
        $features = [];
        $unresolved = [];

        foreach (explode('|', $raw) as $chunk) {
            [$familyLabel, $valuesRaw] = array_pad(explode(':', $chunk, 2), 2, '');
            $familyLabel = trim($familyLabel);
            if ($familyLabel === '') {
                continue;
            }

            $family = $this->findFamily($familyLabel);
            if ($family === null) {
                $unresolved[] = "family:{$familyLabel}";

                continue;
            }

            foreach (array_filter(array_map('trim', explode(',', $valuesRaw))) as $valueLabel) {
                $value = $this->findValue($family, $valueLabel);
                if ($value === null) {
                    $unresolved[] = "value:{$family['name']}.{$valueLabel}";

                    continue;
                }
                $features[$family['handle']][] = $value;
            }
        }

        return [
            'features' => array_map(fn (array $v): array => array_values(array_unique($v)), $features),
            'unresolved' => array_values(array_unique($unresolved)),
        ];
    }

    /**
     * @return array{handle: string, name: string, values: array<int, array{handle: string, name: string}>}|null
     */
    private function findFamily(string $label): ?array
    {
        $needle = self::normalize($label);
        foreach ($this->families() as $family) {
            if (self::normalize($family['name']) === $needle || self::normalize($family['handle']) === $needle) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param  array{handle: string, name: string, values: array<int, array{handle: string, name: string}>}  $family
     */
    private function findValue(array $family, string $label): ?string
    {
        $needle = self::normalize($label);
        foreach ($family['values'] as $value) {
            if (self::normalize($value['name']) === $needle || self::normalize($value['handle']) === $needle) {
                return $value['handle'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{handle: string, name: string, values: array<int, array{handle: string, name: string}>}>
     */
    private function families(): array
    {
        if (self::$families !== null) {
            return self::$families;
        }
        if (! class_exists(FeatureFamily::class)) {
            return self::$families = [];
        }

        return self::$families = FeatureFamily::query()
            ->with('values')
            ->orderBy('name')
            ->get()
            ->map(fn (FeatureFamily $f): array => [
                'handle' => (string) $f->handle,
                'name' => (string) $f->name,
                'values' => $f->values
                    ->sortBy('name')
                    ->map(fn ($v): array => ['handle' => (string) $v->handle, 'name' => (string) $v->name])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii(trim($value)));
    }
}
