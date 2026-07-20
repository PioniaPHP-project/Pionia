<?php

namespace Pionia\Documentation;

use Pionia\Auth\ActionAuthResolver;
use Pionia\Documentation\Attributes\MoonlightAction;
use Pionia\Documentation\Contracts\ActionDoc;
use Pionia\Http\Services\AbstractService;
use Pionia\Utils\Support;
use ReflectionClass;
use ReflectionMethod;

class MoonlightDocParser
{
    /**
     * @return list<ActionDoc>
     */
    public function parseServiceActions(
        ReflectionClass $reflection,
        array $deactivatedActions = [],
        array $actionsRequiringAuth = [],
        bool $serviceRequiresAuth = false,
        array $actionPermissions = [],
    ): array {
        $seen = [];
        $actions = [];

        foreach ($this->discoverActionMethods($reflection) as $method) {
            $methodName = $method->getName();
            if (isset($seen[$methodName])) {
                continue;
            }
            $seen[$methodName] = true;

            $actionName = $this->actionNameFromMethod($methodName);
            $tags = $this->parseMethodTags($method);

            if (isset($tags['action'])) {
                $actionName = $tags['action'];
            }

            if (in_array($actionName, $deactivatedActions, true)) {
                continue;
            }

            $summary = $tags['summary'] ?? $this->firstLine($method->getDocComment() ?: '');
            $auth = $tags['auth'] ?? null;
            if ($auth === null) {
                $auth = ActionAuthResolver::infersRequiredAuth($reflection, $method, $actionName)
                    ? 'required'
                    : $this->inferAuth($actionName, $actionsRequiringAuth, $serviceRequiresAuth);
            }

            $permissions = $tags['perm'] ?? [];
            if ($permissions === []) {
                $fromAttrs = ActionAuthResolver::inferredPermissions($reflection, $method, $actionName);
                $permissions = $fromAttrs !== []
                    ? $fromAttrs
                    : ($actionPermissions[$actionName] ?? $actionPermissions[$methodName] ?? []);
            }
            if (is_string($permissions)) {
                $permissions = [$permissions];
            }

            $actions[] = new ActionDoc(
                name: $actionName,
                methodName: $methodName,
                summary: $summary ?: null,
                auth: $auth,
                permissions: array_values($permissions),
                params: $tags['params'] ?? [],
                returnShape: $tags['return'] ?? null,
                example: $tags['example'] ?? null,
                deprecated: isset($tags['deprecated']),
            );
        }

        usort($actions, static fn (ActionDoc $a, ActionDoc $b) => $a->name <=> $b->name);

        return $actions;
    }

    /**
     * @return array{summary: ?string, auth: ?string, table: ?string, service: ?string, version: ?string}
     */
    public function parseServiceTags(ReflectionClass $reflection): array
    {
        $tags = $this->parseDocTags($reflection->getDocComment() ?: '');

        return [
            'summary' => $tags['summary'] ?? $this->firstLine($reflection->getDocComment() ?: ''),
            'auth' => $tags['auth'] ?? null,
            'table' => $tags['table'] ?? null,
            'service' => $tags['service'] ?? null,
            'version' => $tags['version'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDocTags(string $doc): array
    {
        $result = [
            'params' => [],
            'perm' => [],
        ];

        if ($doc === '') {
            return $result;
        }

        if (preg_match('/@moonlight-summary\s+(.+)/i', $doc, $m)) {
            $result['summary'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-action\s+(\S+)/i', $doc, $m)) {
            $result['action'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-auth\s+(\S+)/i', $doc, $m)) {
            $result['auth'] = strtolower(trim($m[1]));
        }

        if (preg_match('/@moonlight-return\s+(.+)/i', $doc, $m)) {
            $result['return'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-example\s+(.+)/i', $doc, $m)) {
            $result['example'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-service\s+(\S+)/i', $doc, $m)) {
            $result['service'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-version\s+(\S+)/i', $doc, $m)) {
            $result['version'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-table\s+(\S+)/i', $doc, $m)) {
            $result['table'] = trim($m[1]);
        }

        if (preg_match('/@moonlight-deprecated\b/i', $doc)) {
            $result['deprecated'] = true;
        }

        if (preg_match_all('/@moonlight-perm\s+(\S+)/i', $doc, $perms)) {
            $result['perm'] = array_map('trim', $perms[1]);
        }

        if (preg_match_all('/@moonlight-param\s+(\S+)\s+(\S+)(?:\s+(.+))?/i', $doc, $params, PREG_SET_ORDER)) {
            foreach ($params as $param) {
                $result['params'][trim($param[2])] = [
                    'type' => trim($param[1]),
                    'description' => isset($param[3]) ? trim($param[3]) : '',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMethodTags(ReflectionMethod $method): array
    {
        $tags = $this->parseDocTags($method->getDocComment() ?: '');

        foreach ($method->getAttributes(MoonlightAction::class) as $attribute) {
            $meta = $attribute->newInstance();
            $tags['action'] = $meta->name;
            if ($meta->summary) {
                $tags['summary'] = $meta->summary;
            }
            if ($meta->auth) {
                $tags['auth'] = strtolower($meta->auth);
            }
            if ($meta->permissions !== []) {
                $tags['perm'] = $meta->permissions;
            }
        }

        return $tags;
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function discoverActionMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() === AbstractService::class) {
                continue;
            }
            if (!str_ends_with($method->getName(), 'Action')) {
                continue;
            }
            if ($method->getName() === 'processAction') {
                continue;
            }
            $methods[] = $method;
        }

        return $methods;
    }

    private function actionNameFromMethod(string $methodName): string
    {
        $base = preg_replace('/Action$/', '', $methodName) ?? $methodName;

        return Support::toSnakeCase($base);
    }

    private function inferAuth(string $actionName, array $actionsRequiringAuth, bool $serviceRequiresAuth): string
    {
        if ($serviceRequiresAuth || in_array($actionName, $actionsRequiringAuth, true)) {
            return 'required';
        }

        return 'none';
    }

    private function firstLine(string $doc): ?string
    {
        $lines = preg_split('/\R/', trim(preg_replace('/^\/\*\*|\*\/$/', '', $doc) ?? ''));
        if (!$lines) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim(ltrim(trim($line), '*'));
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }

            return $line;
        }

        return null;
    }
}
