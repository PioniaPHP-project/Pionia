<?php

namespace Pionia\Scaffolding;

/**
 * Supported Vite templates for frontend scaffolding.
 */
final class FrontendFramework
{
    /** @var list<string> */
    public const ALL = ['react', 'react-ts', 'vue', 'vue-ts', 'svelte', 'svelte-ts'];

    public const DEFAULT = 'react-ts';

    /**
     * @param list<string> $tokens Flags, env values, or framework names
     */
    public static function resolveFromTokens(array $tokens): ?string
    {
        foreach ($tokens as $token) {
            if (!is_string($token) || $token === '') {
                continue;
            }

            $normalized = strtolower(ltrim($token, '-'));

            if (in_array($normalized, self::ALL, true)) {
                return $normalized;
            }
        }

        return null;
    }

    public static function isValid(string $framework): bool
    {
        return in_array(strtolower($framework), self::ALL, true);
    }

    public static function listForDisplay(): string
    {
        return implode(', ', self::ALL);
    }

    /**
     * Interactive picker — only numbered selections from {@see self::ALL} are accepted.
     */
    public static function chooseFromTerminal(): string
    {
        if (!function_exists('posix_isatty') || !@posix_isatty(STDIN)) {
            return self::DEFAULT;
        }

        $maxAttempts = 3;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            fwrite(STDOUT, PHP_EOL . 'Choose a Vite template:' . PHP_EOL);
            foreach (self::ALL as $index => $framework) {
                $default = $framework === self::DEFAULT ? ' (default)' : '';
                fwrite(STDOUT, '  [' . ($index + 1) . "] {$framework}{$default}" . PHP_EOL);
            }

            $defaultIndex = array_search(self::DEFAULT, self::ALL, true);
            $prompt = 'Selection [' . ((int) $defaultIndex + 1) . ']: ';
            fwrite(STDOUT, $prompt);
            $answer = fgets(STDIN);
            $choice = is_string($answer) ? trim($answer) : '';

            if ($choice === '') {
                return self::DEFAULT;
            }

            if (ctype_digit($choice)) {
                $index = (int) $choice - 1;
                if (isset(self::ALL[$index])) {
                    return self::ALL[$index];
                }
            }

            fwrite(STDOUT, 'Invalid choice. Enter a number from the list.' . PHP_EOL);
        }

        return self::DEFAULT;
    }
}
