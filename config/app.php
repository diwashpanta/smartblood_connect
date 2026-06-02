<?php

declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', realpath(__DIR__ . '/..'));

function load_env_file(string $path): void
{
    static $loaded = false;
    if ($loaded || !is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    $loaded = true;
}

function resolve_python_bin(): string
{
    $configured = trim((string) (getenv('SMARTBLOOD_PYTHON') ?: ''));
    $normalized = strtolower($configured);
    $isGenericCommand = in_array($normalized, ['', 'python', 'python3', 'py'], true);

    $venvWindows = BASE_PATH . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    if ($isGenericCommand && DIRECTORY_SEPARATOR === '\\' && is_file($venvWindows)) {
        return $venvWindows;
    }

    $venvUnix = BASE_PATH . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';
    if ($isGenericCommand && is_file($venvUnix)) {
        return $venvUnix;
    }

    if ($configured !== '') {
        return $configured;
    }

    return 'python';
}

load_env_file(BASE_PATH . DIRECTORY_SEPARATOR . '.env');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kathmandu');

defined('APP_NAME') || define('APP_NAME', 'SmartBlood Connect');
defined('APP_BASE_URL') || define('APP_BASE_URL', rtrim(getenv('APP_BASE_URL') ?: '/' . basename((string) BASE_PATH), '/'));
defined('PYTHON_BIN') || define('PYTHON_BIN', resolve_python_bin());
defined('MAP_PROVIDER') || define('MAP_PROVIDER', strtolower((string) (getenv('MAP_PROVIDER') ?: 'osm')));
defined('MAP_GOOGLE_API_KEY') || define('MAP_GOOGLE_API_KEY', (string) (getenv('MAP_GOOGLE_API_KEY') ?: ''));
defined('MAP_OSM_TILE_URL') || define('MAP_OSM_TILE_URL', (string) (getenv('MAP_OSM_TILE_URL') ?: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'));
defined('MAP_DEFAULT_LAT') || define('MAP_DEFAULT_LAT', (float) (getenv('MAP_DEFAULT_LAT') ?: 27.7172));
defined('MAP_DEFAULT_LNG') || define('MAP_DEFAULT_LNG', (float) (getenv('MAP_DEFAULT_LNG') ?: 85.3240));
defined('MAP_DEFAULT_ZOOM') || define('MAP_DEFAULT_ZOOM', (int) (getenv('MAP_DEFAULT_ZOOM') ?: 12));
