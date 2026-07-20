<?php

namespace Pionia\Auth;

use Pionia\Auth\Attributes\Authenticated;
use Pionia\Auth\Attributes\Can;
use Pionia\Auth\Attributes\CanAny;
use Pionia\Http\Services\AbstractService;
use Pionia\Utils\Support;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resolves declarative auth from {@see Authenticated}, {@see Can}, and {@see CanAny}.
 *
 * Method attributes override / refine service-level attributes. Service-level
 * `$except` lists skip class rules for matching actions.
 *
 * @see AbstractService::processAction()
 */
final class ActionAuthResolver
{
    /**
     * @return array{
     *     requires_auth: bool,
     *     auth_message: ?string,
     *     can_all: list<string>,
     *     can_any: list<string>,
     *     can_message: ?string,
     *     can_any_message: ?string,
     *     from_attributes: bool
     * }
     */
    public static function resolve(
        AbstractService $service,
        ReflectionMethod $method,
        string $requestAction,
        string $serviceName = 'service',
    ): array {
        $class = new ReflectionClass($service);
        $requirement = [
            'requires_auth' => false,
            'auth_message' => null,
            'can_all' => [],
            'can_any' => [],
            'can_message' => null,
            'can_any_message' => null,
            'from_attributes' => false,
        ];

        $methodName = $method->getName();

        // --- Class-level attributes (skipped when action is exempt) ---
        foreach ($class->getAttributes(Authenticated::class) as $attribute) {
            /** @var Authenticated $instance */
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            if (!self::isExempt($requestAction, $methodName, $instance->except)) {
                $requirement['requires_auth'] = true;
                $requirement['auth_message'] = $instance->message
                    ?? "Service $serviceName requires authentication";
            }
        }

        foreach ($class->getAttributes(Can::class) as $attribute) {
            /** @var Can $instance */
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            if (!self::isExempt($requestAction, $methodName, $instance->except)) {
                $requirement['requires_auth'] = true;
                $requirement['can_all'] = self::mergePermissions(
                    $requirement['can_all'],
                    $instance->permission,
                );
                if ($instance->message !== null) {
                    $requirement['can_message'] = $instance->message;
                }
            }
        }

        foreach ($class->getAttributes(CanAny::class) as $attribute) {
            /** @var CanAny $instance */
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            if (!self::isExempt($requestAction, $methodName, $instance->except)) {
                $requirement['requires_auth'] = true;
                $requirement['can_any'] = self::mergePermissions(
                    $requirement['can_any'],
                    $instance->permission,
                );
                if ($instance->message !== null) {
                    $requirement['can_any_message'] = $instance->message;
                }
            }
        }

        // --- Method-level attributes (always win / add) ---
        foreach ($method->getAttributes(Authenticated::class) as $attribute) {
            /** @var Authenticated $instance */
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            $requirement['requires_auth'] = true;
            if ($instance->message !== null) {
                $requirement['auth_message'] = $instance->message;
            }
        }

        $methodCanAll = [];
        $methodCanAny = [];
        $methodHasCan = false;

        foreach ($method->getAttributes(Can::class) as $attribute) {
            /** @var Can $instance */
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            $methodHasCan = true;
            $methodCanAll = self::mergePermissions($methodCanAll, $instance->permission);
            if ($instance->message !== null) {
                $requirement['can_message'] = $instance->message;
            }
        }

        foreach ($method->getAttributes(CanAny::class) as $attribute) {
            /** @var CanAny $instance */
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            $methodHasCan = true;
            $methodCanAny = self::mergePermissions($methodCanAny, $instance->permission);
            if ($instance->message !== null) {
                $requirement['can_any_message'] = $instance->message;
            }
        }

        if ($methodHasCan) {
            $requirement['requires_auth'] = true;
            // Method permissions replace class-level Can/CanAny for this action
            $requirement['can_all'] = $methodCanAll;
            $requirement['can_any'] = $methodCanAny;
        }

        // --- Legacy properties only when no auth attributes were used ---
        if (!$requirement['from_attributes']) {
            if ($service->serviceRequiresAuth) {
                $requirement['requires_auth'] = true;
                $requirement['auth_message'] ??= $service->authMessage
                    ?? "Service $serviceName requires authentication";
            }

            $rawAction = self::normalizeName($requestAction);
            foreach ($service->actionsRequiringAuth as $listed) {
                if (self::normalizeName((string) $listed) === $rawAction
                    || self::normalizeName((string) $listed) === self::normalizeName($methodName)) {
                    $requirement['requires_auth'] = true;
                    $requirement['auth_message'] ??= "Action $requestAction requires authentication";
                    break;
                }
            }
        }

        if ($requirement['can_all'] === [] && $requirement['can_any'] === []) {
            $legacy = $service->actionPermissions[$methodName]
                ?? $service->actionPermissions[$requestAction]
                ?? $service->actionPermissions[self::normalizeName($requestAction)]
                ?? null;

            if (is_string($legacy)) {
                $requirement['can_all'] = [$legacy];
                $requirement['requires_auth'] = true;
            } elseif (is_array($legacy) && $legacy !== []) {
                $requirement['can_all'] = array_values($legacy);
                $requirement['requires_auth'] = true;
            }
        }

        return $requirement;
    }

    /**
     * @param array{
     *     requires_auth: bool,
     *     auth_message: ?string,
     *     can_all: list<string>,
     *     can_any: list<string>,
     *     can_message: ?string,
     *     can_any_message: ?string,
     *     from_attributes: bool
     * } $requirement
     */
    public static function enforce(AbstractService $service, array $requirement): void
    {
        if ($requirement['can_all'] !== []) {
            if (count($requirement['can_all']) === 1) {
                $service->can(
                    $requirement['can_all'][0],
                    $requirement['can_message'] ?? 'You do not have access to this resource',
                );
            } else {
                $service->canAll(
                    $requirement['can_all'],
                    $requirement['can_message'] ?? 'You do not have access to this resource',
                );
            }
        }

        if ($requirement['can_any'] !== []) {
            $service->canAny(
                $requirement['can_any'],
                $requirement['can_any_message'] ?? 'You do not have access to this resource',
            );
        }

        if ($requirement['requires_auth']
            && $requirement['can_all'] === []
            && $requirement['can_any'] === []) {
            $service->mustAuthenticate(
                $requirement['auth_message'] ?? 'You must be authenticated to access this resource',
            );
        }
    }

    /**
     * Whether auth attributes mark this action as requiring authentication (for docs).
     */
    public static function infersRequiredAuth(ReflectionClass $class, ReflectionMethod $method, string $actionName): bool
    {
        $requirement = self::resolveFromReflection($class, $method, $actionName);

        return $requirement['requires_auth']
            || $requirement['can_all'] !== []
            || $requirement['can_any'] !== [];
    }

    /**
     * @return list<string>
     */
    public static function inferredPermissions(ReflectionClass $class, ReflectionMethod $method, string $actionName): array
    {
        $requirement = self::resolveFromReflection($class, $method, $actionName);

        return array_values(array_unique([...$requirement['can_all'], ...$requirement['can_any']]));
    }

    /**
     * Attribute-only resolve (no service instance / legacy properties).
     *
     * @return array{
     *     requires_auth: bool,
     *     auth_message: ?string,
     *     can_all: list<string>,
     *     can_any: list<string>,
     *     can_message: ?string,
     *     can_any_message: ?string,
     *     from_attributes: bool
     * }
     */
    private static function resolveFromReflection(
        ReflectionClass $class,
        ReflectionMethod $method,
        string $requestAction,
    ): array {
        $requirement = [
            'requires_auth' => false,
            'auth_message' => null,
            'can_all' => [],
            'can_any' => [],
            'can_message' => null,
            'can_any_message' => null,
            'from_attributes' => false,
        ];

        $methodName = $method->getName();

        foreach ($class->getAttributes(Authenticated::class) as $attribute) {
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            if (!self::isExempt($requestAction, $methodName, $instance->except)) {
                $requirement['requires_auth'] = true;
            }
        }

        foreach ($class->getAttributes(Can::class) as $attribute) {
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            if (!self::isExempt($requestAction, $methodName, $instance->except)) {
                $requirement['requires_auth'] = true;
                $requirement['can_all'] = self::mergePermissions($requirement['can_all'], $instance->permission);
            }
        }

        foreach ($class->getAttributes(CanAny::class) as $attribute) {
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            if (!self::isExempt($requestAction, $methodName, $instance->except)) {
                $requirement['requires_auth'] = true;
                $requirement['can_any'] = self::mergePermissions($requirement['can_any'], $instance->permission);
            }
        }

        foreach ($method->getAttributes(Authenticated::class) as $attribute) {
            $requirement['from_attributes'] = true;
            $requirement['requires_auth'] = true;
        }

        $methodCanAll = [];
        $methodCanAny = [];
        $methodHasCan = false;

        foreach ($method->getAttributes(Can::class) as $attribute) {
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            $methodHasCan = true;
            $methodCanAll = self::mergePermissions($methodCanAll, $instance->permission);
        }

        foreach ($method->getAttributes(CanAny::class) as $attribute) {
            $instance = $attribute->newInstance();
            $requirement['from_attributes'] = true;
            $methodHasCan = true;
            $methodCanAny = self::mergePermissions($methodCanAny, $instance->permission);
        }

        if ($methodHasCan) {
            $requirement['requires_auth'] = true;
            $requirement['can_all'] = $methodCanAll;
            $requirement['can_any'] = $methodCanAny;
        }

        return $requirement;
    }

    /**
     * @param list<string> $except
     */
    public static function isExempt(string $requestAction, string $methodName, array $except): bool
    {
        if ($except === []) {
            return false;
        }

        $aliases = self::actionAliases($requestAction, $methodName);
        foreach ($except as $item) {
            if (in_array(self::normalizeName((string) $item), $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function actionAliases(string $requestAction, string $methodName): array
    {
        $base = preg_replace('/Action$/i', '', $methodName) ?? $methodName;

        return array_values(array_unique(array_filter([
            self::normalizeName($requestAction),
            self::normalizeName($methodName),
            self::normalizeName($base),
            self::normalizeName(Support::toSnakeCase($base)),
        ])));
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/Action$/i', '', $name) ?? $name;

        return strtolower(Support::toSnakeCase($name));
    }

    /**
     * @param list<string> $existing
     * @param string|list<string> $incoming
     *
     * @return list<string>
     */
    private static function mergePermissions(array $existing, string|array $incoming): array
    {
        $list = is_array($incoming) ? $incoming : [$incoming];
        $merged = [...$existing, ...array_map('strval', $list)];

        return array_values(array_unique(array_filter($merged, static fn ($p) => $p !== '')));
    }
}
