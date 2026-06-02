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
     * Keep only the keys that match a parameter of the target constructor.
     *
     * Real-world PrestaShop configs annotate actions with documentation keys
     * (e.g. `comment`) or internal markers (`_*`) that are not constructor
     * parameters. Passing them through `new static(...$config)` would raise an
     * `ArgumentCountError: Unknown named parameter`. We silently drop them.
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

        $allowed = [];
        foreach ($constructor->getParameters() as $parameter) {
            $allowed[$parameter->getName()] = true;
        }

        return array_intersect_key($config, $allowed);
    }
}
