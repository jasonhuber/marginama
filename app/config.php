<?php
declare(strict_types=1);

// Load environment variables from .env (located one level above this file).
// Simple parser: KEY=VALUE per line, `#` comments, no interpolation, no quoting rules.

(function (): void {
    $envPath = dirname(__DIR__) . '/.env';
    if (!is_readable($envPath)) {
        return;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = ltrim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
            $val = substr($val, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
})();

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return $v;
}

function app_url(): string {
    return rtrim(env('APP_URL', 'http://localhost:8000') ?? 'http://localhost:8000', '/');
}

function is_production(): bool {
    return env('APP_ENV', 'development') === 'production';
}
