<?php

/**
 * Credential file loader for CLI scripts.
 *
 * Web requests get their secrets from Apache SetEnv (see .htaccess.production).
 * CLI scripts have no such environment, so they read a KEY=value file that is
 * kept outside version control.
 *
 * @package CirculationDashboard
 */

namespace CirculationDashboard;

class Credentials
{
    /**
     * Accepted alternative spellings.
     *
     * The developer credential file documented in docs/operations-reference.md
     * uses PROD_DB_* names; Apache and the web code use DB_*. Both are honoured
     * so one file works everywhere.
     *
     * @var array<string, string[]>
     */
    private const ALIASES = [
        'DB_USER'     => ['PROD_DB_USERNAME'],
        'DB_PASSWORD' => ['PROD_DB_PASSWORD'],
        'DB_NAME'     => ['PROD_DB_DATABASE'],
        'DB_SOCKET'   => ['PROD_DB_SOCKET'],
    ];

    /** @var array<string, array<string, string>> Parsed files, keyed by path */
    private static array $cache = [];

    /**
     * Load a credential file into a key/value map.
     *
     * Accepts "KEY=value", "export KEY=value", blank lines, and # comments.
     * Surrounding single or double quotes are stripped from values.
     *
     * @param string|null $path Explicit path, or null to search the default locations
     * @return array<string, string>
     * @throws \RuntimeException If the file cannot be found or read
     */
    public static function load(?string $path = null): array
    {
        $path = $path ?? self::defaultPath();

        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        if (!is_readable($path)) {
            throw new \RuntimeException(
                "Credential file not readable at {$path}. "
                . 'Create it with the required keys and chmod it to 600.'
            );
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim(preg_replace('/^export\s+/', '', trim($key)));
            $value = trim($value);
            // Strip one matching pair of surrounding quotes
            if (strlen($value) >= 2 && $value[0] === $value[-1] && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }

        return self::$cache[$path] = $values;
    }

    /**
     * Fetch a required credential, failing loudly when it is absent.
     *
     * @param string $key Credential name
     * @param string|null $path Explicit credential file path
     * @return string
     * @throws \RuntimeException If the key is missing or empty
     */
    public static function require(string $key, ?string $path = null): string
    {
        $value = self::get($key, null, $path);
        if ($value === null) {
            $file = $path ?? self::defaultPath();
            $names = implode(' or ', self::candidates($key));
            throw new \RuntimeException("Required credential {$names} is missing from {$file}.");
        }

        return $value;
    }

    /**
     * Fetch an optional credential.
     *
     * @param string $key Credential name
     * @param string|null $default Value to return when the key is absent
     * @param string|null $path Explicit credential file path
     * @return string|null
     */
    public static function get(string $key, ?string $default = null, ?string $path = null): ?string
    {
        // An environment variable wins, so production can inject without a file.
        foreach (self::candidates($key) as $name) {
            $fromEnv = getenv($name);
            if (is_string($fromEnv) && $fromEnv !== '') {
                return $fromEnv;
            }
        }

        try {
            $values = self::load($path);
        } catch (\RuntimeException) {
            return $default;
        }

        foreach (self::candidates($key) as $name) {
            if (($values[$name] ?? '') !== '') {
                return $values[$name];
            }
        }

        return $default;
    }

    /**
     * The key itself followed by any accepted alternative spellings.
     *
     * @param string $key Credential name
     * @return string[]
     */
    private static function candidates(string $key): array
    {
        return array_merge([$key], self::ALIASES[$key] ?? []);
    }

    /**
     * Locate the credential file.
     *
     * Checks CIRCULATION_CREDENTIALS, then the deployed web root, then the
     * repository root — so the same code works on the NAS and in a checkout.
     *
     * @return string First candidate that exists, or the deployed path for the error message
     */
    private static function defaultPath(): string
    {
        $override = getenv('CIRCULATION_CREDENTIALS');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $candidates = [
            dirname(__DIR__) . '/.env.credentials',          // /volume1/web/circulation/
            dirname(__DIR__, 2) . '/.env.credentials',       // repository root
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
