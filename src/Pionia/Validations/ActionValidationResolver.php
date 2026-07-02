<?php

namespace Pionia\Validations;

use Pionia\Validations\Attributes\Validated;
use Pionia\Validations\Attributes\ValidateField;
use ReflectionMethod;

/**
 * Resolves validation rules from action method attributes.
 */
final class ActionValidationResolver
{
    /**
     * @return array<string, string|list<string>>
     */
    public static function resolve(ReflectionMethod $method): array
    {
        $rules = [];

        foreach ($method->getAttributes(Validated::class) as $attribute) {
            /** @var Validated $instance */
            $instance = $attribute->newInstance();
            foreach ($instance->rules as $field => $definition) {
                $rules[$field] = self::mergeFieldRules($rules[$field] ?? null, $definition);
            }
        }

        foreach ($method->getAttributes(ValidateField::class) as $attribute) {
            /** @var ValidateField $instance */
            $instance = $attribute->newInstance();
            $rules[$instance->field] = self::mergeFieldRules(
                $rules[$instance->field] ?? null,
                $instance->rules,
            );
        }

        return $rules;
    }

    /**
     * @param string|list<string>|null $existing
     * @param string|list<string> $incoming
     *
     * @return string|list<string>
     */
    private static function mergeFieldRules(string|array|null $existing, string|array $incoming): string|array
    {
        if ($existing === null) {
            return $incoming;
        }

        $existingList = is_array($existing)
            ? $existing
            : array_values(array_filter(array_map('trim', explode('|', $existing))));

        $incomingList = is_array($incoming)
            ? $incoming
            : array_values(array_filter(array_map('trim', explode('|', $incoming))));

        $merged = array_values(array_unique([...$existingList, ...$incomingList]));

        return count($merged) === 1 ? $merged[0] : implode('|', $merged);
    }
}
