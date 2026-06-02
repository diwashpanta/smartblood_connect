<?php

declare(strict_types=1);

function sb_shell_arg(string $arg): string
{
    return '"' . str_replace('"', '\"', $arg) . '"';
}

function sb_run_python_script(string $scriptPath, array $payload): ?array
{
    if (!is_file($scriptPath)) {
        return null;
    }
    $encoded = base64_encode((string) json_encode($payload));
    $command = sb_shell_arg(PYTHON_BIN) . ' ' . sb_shell_arg($scriptPath) . ' ' . sb_shell_arg($encoded) . ' 2>&1';
    $raw = @shell_exec($command);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode(trim($raw), true);
    return is_array($decoded) ? $decoded : null;
}

function sb_ml_predict_donor(array $features): ?array
{
    $script = BASE_PATH . DIRECTORY_SEPARATOR . 'ml' . DIRECTORY_SEPARATOR . 'predict_donor.py';
    return sb_run_python_script($script, $features);
}

function sb_ml_rank_donors(array $request, array $donors): ?array
{
    $script = BASE_PATH . DIRECTORY_SEPARATOR . 'ml' . DIRECTORY_SEPARATOR . 'donor_matching.py';
    $result = sb_run_python_script($script, [
        'request' => $request,
        'donors' => $donors,
    ]);
    if (!is_array($result)) {
        return null;
    }
    return array_values(array_filter($result, static fn ($row) => is_array($row)));
}

