<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions;

use Pko\AiImporter\Contracts\ActionInterface;

/**
 * Base class for every action type. Subclasses must:
 *  - implement `execute(mixed $value, ExecutionContext $ctx): mixed`
 *  - declare their public `type()` identifier
 *  - use constructor property promotion for their typed params
 *
 * Factory via `Action::make(['type' => 'math', 'operation' => 'multiply', 'value' => 1.2])`.
 */
abstract class Action implements ActionInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): ActionInterface
    {
        $type = $config['type'] ?? null;
        if (! is_string($type) || $type === '') {
            throw new \InvalidArgumentException('Action config requires a "type" key.');
        }

        $class = ActionRegistry::resolve($type);

        return $class::fromArray($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): static
    {
        // Default factory: simply instantiate with the config as named args.
        // Subclasses override when more complex parsing is required.
        unset($config['type']);

        return new static(...static::filterConstructorParams($config)); // @phpstan-ignore-line
    }

    /**
     * Keep only the keys that match a parameter of the target constructor, and
     * coerce scalar values to the parameter's declared type.
     *
     * Real-world PrestaShop configs annotate actions with documentation keys
     * (e.g. `comment`) or internal markers (`_*`) that are not constructor
     * parameters. Passing them through `new static(...$config)` would raise an
     * `ArgumentCountError: Unknown named parameter`. We silently drop them.
     *
     * The same configs (edited in a browser form, serialised as JSON) carry
     * numbers as strings — `"llm_config_id": "1"`, `"value": "1.2"`,
     * `"decimals": "2"`. Every action file declares `strict_types=1`, so a
     * string handed to an `int`/`float` promoted property is a `TypeError`
     * that kills the row. Numeric strings are cast to the declared scalar
     * type ; an empty string on a nullable parameter becomes `null`, on a
     * non-nullable one it is dropped so the constructor default applies.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function filterConstructorParams(array $config): array
    {
        $constructor = (new \ReflectionClass(static::class))->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $out = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (! array_key_exists($name, $config)) {
                continue;
            }

            $type = $parameter->getType();
            $value = $config[$name];

            if (! $type instanceof \ReflectionNamedType || ! $type->isBuiltin()) {
                $out[$name] = $value;

                continue;
            }

            $coerced = self::coerceScalar($value, $type->getName(), $type->allowsNull());
            if ($coerced === self::DROP) {
                continue;
            }

            $out[$name] = $coerced;
        }

        return $out;
    }

    /** Sentinel : la clé est omise pour laisser jouer la valeur par défaut du constructeur. */
    private const DROP = "\0__drop__\0";

    private static function coerceScalar(mixed $value, string $type, bool $nullable): mixed
    {
        if ($value === null) {
            return $nullable ? null : self::DROP;
        }

        if ($type === 'mixed' || ! is_scalar($value)) {
            return $value;
        }

        return match ($type) {
            'int' => is_int($value) ? $value : self::toInt($value, $nullable),
            'float' => is_float($value) ? $value : self::toFloat($value, $nullable),
            'bool' => is_bool($value) ? $value : self::toBool($value, $nullable),
            'string' => is_string($value) ? $value : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
            default => $value,
        };
    }

    private static function toInt(mixed $value, bool $nullable): mixed
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return $nullable ? null : self::DROP;
        }

        return is_numeric($s) ? (int) round((float) $s) : self::DROP;
    }

    private static function toFloat(mixed $value, bool $nullable): mixed
    {
        if (is_bool($value) || is_int($value)) {
            return (float) $value;
        }
        $s = str_replace(',', '.', trim((string) $value));
        if ($s === '') {
            return $nullable ? null : self::DROP;
        }

        return is_numeric($s) ? (float) $s : self::DROP;
    }

    private static function toBool(mixed $value, bool $nullable): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        $s = strtolower(trim((string) $value));
        if ($s === '') {
            return $nullable ? null : self::DROP;
        }

        return match ($s) {
            '1', 'true', 'yes', 'on', 'oui' => true,
            '0', 'false', 'no', 'off', 'non' => false,
            default => self::DROP,
        };
    }
}
