<?php

namespace Pionia\Validations;

use Pionia\Collections\Arrayable;
use Pionia\Exceptions\ValidationException;

/**
 * Declarative field validation — pipe strings or rule arrays.
 *
 * @example rules($data, ['email' => 'required|email', 'age' => 'integer|min:18']);
 */
final class ValidationRules
{
    /**
     * @param array<string, string|array<int, string>> $rules
     */
    public static function check(Arrayable $data, array $rules): void
    {
        foreach ($rules as $field => $definition) {
            if (!is_string($field)) {
                throw new ValidationException('Validation rule keys must be field names.');
            }

            self::checkField($data, $field, $definition);
        }
    }

    /**
     * @param string|array<int, string> $definition
     */
    private static function checkField(Arrayable $data, string $field, string|array $definition): void
    {
        $ruleList = is_array($definition)
            ? $definition
            : array_values(array_filter(array_map('trim', explode('|', $definition))));

        if ($ruleList === []) {
            return;
        }

        $skipWhenEmpty = self::hasNullableRule($ruleList);

        if ($skipWhenEmpty && self::isEmptyValue($data, $field)) {
            return;
        }

        $validator = Validator::validate($field, $data);

        foreach ($ruleList as $rule) {
            if (in_array($rule, ['nullable', 'sometimes'], true)) {
                continue;
            }

            self::applyRule($validator, $field, $rule, $data);
        }
    }

    /**
     * @param array<int, string> $ruleList
     */
    private static function hasNullableRule(array $ruleList): bool
    {
        return in_array('nullable', $ruleList, true) || in_array('sometimes', $ruleList, true);
    }

    private static function isEmptyValue(Arrayable $data, string $field): bool
    {
        if (!$data->has($field)) {
            return true;
        }

        return blank($data->get($field));
    }

    private static function applyRule(Validator $validator, string $field, string $rule, Arrayable $data): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
        $name = strtolower(trim($name));

        if (self::applyCustomRule($validator, $field, $name, $parameter, $data)) {
            return;
        }

        match ($name) {
            'required' => $validator->required(),
            'string' => $validator->string(),
            'integer', 'int' => $validator->integer(),
            'numeric' => $validator->asNumeric(),
            'number' => $validator->asNumber(),
            'boolean', 'bool' => $validator->boolean(),
            'array' => $validator->array(),
            'email' => $validator->email(),
            'url' => $validator->asUrl(),
            'ip' => $validator->asIp(),
            'slug' => $validator->asSlug(),
            'uuid' => $validator->uuid(),
            'ulid' => $validator->ulid(),
            'otp' => $validator->asOtp(self::parseOtpLength($parameter)),
            'token' => $validator->asToken(self::parseTokenMinBytes($parameter)),
            'password' => $validator->asPassword(),
            'phone' => $validator->asInternationalPhone($parameter),
            'min' => $validator->min(self::parseNumericParameter($parameter, 'min')),
            'max' => $validator->max(self::parseNumericParameter($parameter, 'max')),
            'between' => $validator->between(...self::parseBetween($parameter)),
            'in' => $validator->in(self::parseList($parameter)),
            'not_in', 'notin' => $validator->notIn(self::parseList($parameter)),
            'regex' => $validator->regex(self::parseRegexParameter($parameter)),
            'confirmed', 'matches' => $validator->matches($parameter ?? $field . '_confirmation'),
            'required_with' => $validator->requiredWith(self::requireParameter($parameter, 'required_with')),
            'required_without' => $validator->requiredWithout(self::requireParameter($parameter, 'required_without')),
            'date' => $validator->date(),
            'float' => $validator->float(),
            default => throw new ValidationException("Unknown validation rule [{$name}] on field [{$field}]."),
        };
    }

    private static function applyCustomRule(
        Validator $validator,
        string $field,
        string $name,
        ?string $parameter,
        Arrayable $data,
    ): bool {
        if (!function_exists('app') || !app()->has(ValidationManager::class)) {
            return false;
        }

        $manager = app()->get(ValidationManager::class);

        if (!$manager->has($name)) {
            return false;
        }

        $manager->apply($name, $validator, $field, $parameter, $data);

        return true;
    }

    private static function parseNumericParameter(?string $parameter, string $rule): int|float
    {
        if ($parameter === null || $parameter === '') {
            throw new ValidationException("Validation rule [{$rule}] requires a parameter.");
        }

        return str_contains($parameter, '.') ? (float) $parameter : (int) $parameter;
    }

    /**
     * @return array{0: int|float, 1: int|float}
     */
    private static function parseBetween(?string $parameter): array
    {
        if ($parameter === null || !str_contains($parameter, ',')) {
            throw new ValidationException('Validation rule [between] requires min,max parameters.');
        }

        [$min, $max] = array_map('trim', explode(',', $parameter, 2));

        return [
            str_contains($min, '.') ? (float) $min : (int) $min,
            str_contains($max, '.') ? (float) $max : (int) $max,
        ];
    }

    /**
     * @return list<string>
     */
    private static function parseList(?string $parameter): array
    {
        if ($parameter === null || $parameter === '') {
            throw new ValidationException('Validation rule [in/not_in] requires a comma-separated list.');
        }

        return array_values(array_filter(array_map('trim', explode(',', $parameter)), static fn ($v) => $v !== ''));
    }

    private static function parseRegexParameter(?string $parameter): string
    {
        if ($parameter === null || $parameter === '') {
            throw new ValidationException('Validation rule [regex] requires a pattern.');
        }

        return $parameter;
    }

    private static function requireParameter(?string $parameter, string $rule): string
    {
        if ($parameter === null || $parameter === '') {
            throw new ValidationException("Validation rule [{$rule}] requires a field name.");
        }

        return $parameter;
    }

    private static function parseOtpLength(?string $parameter): int
    {
        if ($parameter === null || $parameter === '') {
            return 6;
        }

        return (int) $parameter;
    }

    private static function parseTokenMinBytes(?string $parameter): int
    {
        if ($parameter === null || $parameter === '') {
            return 16;
        }

        return (int) $parameter;
    }
}
