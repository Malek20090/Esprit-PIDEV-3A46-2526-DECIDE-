<?php

declare(strict_types=1);

/**
 * Simple pre-deploy checks for Symfony student projects.
 *
 * Usage:
 *   php scripts/predeploy-check.php
 *   php scripts/predeploy-check.php --env-file=.env.local
 */

$projectRoot = dirname(__DIR__);
$envFile = '.env.local';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--env-file=')) {
        $envFile = substr($arg, strlen('--env-file='));
    }
}

$envPath = $projectRoot . DIRECTORY_SEPARATOR . $envFile;
$env = readEnvFile($envPath);

$results = [];

check('PHP version >= 8.1', static function (): bool {
    return version_compare(PHP_VERSION, '8.1.0', '>=');
}, $results, 'Current PHP: ' . PHP_VERSION);

check('Composer vendor installed', static function () use ($projectRoot): bool {
    return is_file($projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
}, $results, 'Expected: vendor/autoload.php');

$appEnv = getenv('APP_ENV') ?: ($env['APP_ENV'] ?? '');
$appDebug = getenv('APP_DEBUG') ?: ($env['APP_DEBUG'] ?? '');
$dbUrl = getenv('DATABASE_URL') ?: ($env['DATABASE_URL'] ?? '');

check('APP_ENV is prod', static function () use ($appEnv): bool {
    return strtolower(trim($appEnv)) === 'prod';
}, $results, 'APP_ENV=' . ($appEnv !== '' ? $appEnv : '(empty)'));

check('APP_DEBUG is 0/false', static function () use ($appDebug): bool {
    $v = strtolower(trim((string) $appDebug));
    return in_array($v, ['0', 'false', '(false)'], true);
}, $results, 'APP_DEBUG=' . ($appDebug !== '' ? $appDebug : '(empty)'));

check('DATABASE_URL exists', static function () use ($dbUrl): bool {
    return trim($dbUrl) !== '';
}, $results, 'DATABASE_URL=' . (trim($dbUrl) !== '' ? '[set]' : '[empty]'));

check('Web root is /public (public/index.php exists)', static function () use ($projectRoot): bool {
    return is_file($projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');
}, $results, 'Expected: public/index.php');

$console = $projectRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console';

checkCommand(
    'Symfony cache:clear --env=prod',
    'php ' . escapeshellarg($console) . ' cache:clear --env=prod --no-warmup',
    $projectRoot,
    $results
);

checkCommand(
    'Doctrine DB connection (SELECT 1)',
    'php ' . escapeshellarg($console) . ' doctrine:query:sql "SELECT 1" --env=prod',
    $projectRoot,
    $results
);

checkCommand(
    'Doctrine migrations status',
    'php ' . escapeshellarg($console) . ' doctrine:migrations:status --env=prod --no-interaction',
    $projectRoot,
    $results
);

echo PHP_EOL . '--- Summary ---' . PHP_EOL;
$hasFailure = false;
foreach ($results as $item) {
    echo ($item['ok'] ? '[OK]   ' : '[FAIL] ') . $item['label'] . PHP_EOL;
    if ($item['detail'] !== '') {
        echo '       ' . $item['detail'] . PHP_EOL;
    }
    if (!$item['ok']) {
        $hasFailure = true;
    }
}

echo PHP_EOL;
if ($hasFailure) {
    echo "Pre-deploy checks found issues. Fix FAIL items before deploying." . PHP_EOL;
    exit(1);
}

echo "All pre-deploy checks passed." . PHP_EOL;
exit(0);

function check(string $label, callable $fn, array &$results, string $detail = ''): void
{
    try {
        $ok = (bool) $fn();
    } catch (Throwable $e) {
        $ok = false;
        $detail = $detail !== '' ? $detail . ' | ' : '';
        $detail .= $e->getMessage();
    }

    $results[] = [
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
    ];
}

function checkCommand(string $label, string $command, string $cwd, array &$results): void
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    if (!is_resource($process)) {
        $results[] = [
            'label' => $label,
            'ok' => false,
            'detail' => 'Could not start command: ' . $command,
        ];
        return;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $ok = $exitCode === 0;

    $detail = 'Exit code: ' . $exitCode;
    if (!$ok) {
        $snippet = trim($stderr !== '' ? $stderr : $stdout);
        if ($snippet !== '') {
            $detail .= ' | ' . preg_replace('/\s+/', ' ', mb_substr($snippet, 0, 300));
        }
    }

    $results[] = [
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
    ];
}

/**
 * Minimal .env parser (KEY=VALUE lines, ignores comments/blanks).
 */
function readEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $out = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '#')) {
            continue;
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        $val = trim($val, "\"'");

        if ($key !== '') {
            $out[$key] = $val;
        }
    }

    return $out;
}

