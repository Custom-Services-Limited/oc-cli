<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$repoRoot = dirname(__DIR__, 2);
$buildDir = $repoRoot . '/build/e2e';
$workspace = $buildDir . '/opencart-3.0.5.0';
$artifactsDir = $buildDir . '/artifacts';
$fixtureZip = $repoRoot . '/fixtures/opencart/opencart-3.0.5.0.zip';
$fixtureMetadataPath = $repoRoot . '/fixtures/opencart/opencart-3.0.5.0.json';
$statePath = $buildDir . '/state.json';
$envPath = $buildDir . '/env.sh';

$dbConfig = [
    'host' => requiredEnv('OC_E2E_DB_HOST'),
    'name' => requiredEnv('OC_E2E_DB_NAME'),
    'user' => requiredEnv('OC_E2E_DB_USER'),
    'pass' => requiredEnvAllowEmpty('OC_E2E_DB_PASS'),
    'port' => (int) requiredEnv('OC_E2E_DB_PORT'),
];

$prefix = getenv('OC_E2E_DB_PREFIX');
if (!$prefix) {
    $prefix = 'oc_e2e_' . substr(bin2hex(random_bytes(4)), 0, 8) . '_';
}

ensureDirectory($artifactsDir);
resetDirectory($workspace);
verifyFixture($fixtureZip, $fixtureMetadataPath);
extractUploadTree($fixtureZip, $workspace);
ensureRuntimeDirectories($workspace);

$serverConnection = waitForServerConnection($dbConfig, 60);
ensureDatabaseExists($serverConnection, $dbConfig['name']);
$serverConnection->close();

$databaseConnection = connectToDatabase($dbConfig, 5);
dropTablesWithPrefix($databaseConnection, $dbConfig['name'], $prefix);
$databaseConnection->close();

$installLog = $artifactsDir . '/install.log';
runInstaller($workspace, $dbConfig, $prefix, $installLog);
assertInstalled($workspace);

$state = [
    'repo_root' => $repoRoot,
    'workspace' => $workspace,
    'artifacts_dir' => $artifactsDir,
    'fixture_version' => '3.0.5.0',
    'db' => [
        'host' => $dbConfig['host'],
        'name' => $dbConfig['name'],
        'user' => $dbConfig['user'],
        'pass' => $dbConfig['pass'],
        'port' => $dbConfig['port'],
        'prefix' => $prefix,
    ],
];

file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
file_put_contents($envPath, buildEnvScript($state));

fwrite(STDOUT, "Provisioned OpenCart fixture at {$workspace}\n");
fwrite(STDOUT, "Environment file: {$envPath}\n");

function requiredEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        fail("Missing required environment variable: {$name}");
    }

    return $value;
}

function requiredEnvAllowEmpty(string $name): string
{
    $value = getenv($name);
    if ($value === false) {
        fail("Missing required environment variable: {$name}");
    }

    return $value;
}

function fail(string $message, int $exitCode = 1): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fail("Could not create directory: {$path}");
    }
}

function resetDirectory(string $path): void
{
    if (is_dir($path)) {
        deleteTree($path);
    }

    ensureDirectory($path);
}

function deleteTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

function verifyFixture(string $fixtureZip, string $fixtureMetadataPath): void
{
    if (!file_exists($fixtureZip)) {
        fail("Fixture archive not found: {$fixtureZip}");
    }

    if (!file_exists($fixtureMetadataPath)) {
        fail("Fixture metadata not found: {$fixtureMetadataPath}");
    }

    $metadata = json_decode((string) file_get_contents($fixtureMetadataPath), true);
    if (!is_array($metadata) || empty($metadata['sha256'])) {
        fail("Fixture metadata is invalid: {$fixtureMetadataPath}");
    }

    $actualHash = hash_file('sha256', $fixtureZip);
    if (!hash_equals($metadata['sha256'], $actualHash)) {
        fail(
            "Fixture archive checksum mismatch.\nExpected: {$metadata['sha256']}\nActual:   {$actualHash}"
        );
    }
}

function extractUploadTree(string $fixtureZip, string $workspace): void
{
    $zip = new ZipArchive();
    if ($zip->open($fixtureZip) !== true) {
        fail("Could not open fixture archive: {$fixtureZip}");
    }

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entryName = $zip->getNameIndex($index);
        if ($entryName === false || strpos($entryName, 'upload/') !== 0) {
            continue;
        }

        $relativePath = substr($entryName, strlen('upload/'));
        if ($relativePath === '') {
            continue;
        }

        $targetPath = $workspace . '/' . $relativePath;

        if (substr($entryName, -1) === '/') {
            ensureDirectory($targetPath);
            continue;
        }

        ensureDirectory(dirname($targetPath));

        $stream = $zip->getStream($entryName);
        if ($stream === false) {
            $zip->close();
            fail("Could not extract archive entry: {$entryName}");
        }

        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false || file_put_contents($targetPath, $contents) === false) {
            $zip->close();
            fail("Could not write extracted file: {$targetPath}");
        }

        chmod($targetPath, 0664);
    }

    $zip->close();
}

function ensureRuntimeDirectories(string $workspace): void
{
    $directories = [
        $workspace . '/image/cache',
        $workspace . '/image/catalog',
        $workspace . '/system/storage/cache',
        $workspace . '/system/storage/logs',
        $workspace . '/system/storage/download',
        $workspace . '/system/storage/upload',
        $workspace . '/system/storage/modification',
        $workspace . '/system/storage/session',
    ];

    foreach ($directories as $directory) {
        ensureDirectory($directory);
    }
}

function waitForServerConnection(array $dbConfig, int $timeoutSeconds): mysqli
{
    $deadline = time() + $timeoutSeconds;
    $lastError = 'Database server did not accept connections.';

    do {
        try {
            return connectServer($dbConfig);
        } catch (RuntimeException $exception) {
            $lastError = $exception->getMessage();
            usleep(500000);
        }
    } while (time() < $deadline);

    fail("Timed out waiting for database server: {$lastError}");
}

function connectServer(array $dbConfig): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = mysqli_init();

    if (!$connection instanceof mysqli) {
        throw new RuntimeException('Unable to initialise mysqli.');
    }

    if (!@$connection->real_connect(
        $dbConfig['host'],
        $dbConfig['user'],
        $dbConfig['pass'],
        null,
        $dbConfig['port']
    )) {
        throw new RuntimeException($connection->connect_error ?: 'Unknown server connection error.');
    }

    return $connection;
}

function connectToDatabase(array $dbConfig, int $attempts): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $lastError = 'Unknown database connection error.';

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $connection = mysqli_init();

        if (!$connection instanceof mysqli) {
            fail('Unable to initialise mysqli.');
        }

        if (@$connection->real_connect(
            $dbConfig['host'],
            $dbConfig['user'],
            $dbConfig['pass'],
            $dbConfig['name'],
            $dbConfig['port']
        )) {
            return $connection;
        }

        $lastError = $connection->connect_error ?: $lastError;
        usleep(500000);
    }

    fail("Could not connect to database '{$dbConfig['name']}': {$lastError}");
}

function ensureDatabaseExists(mysqli $connection, string $databaseName): void
{
    $escapedName = $connection->real_escape_string($databaseName);
    $sql = "CREATE DATABASE IF NOT EXISTS `{$escapedName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    if ($connection->query($sql) === false) {
        $exists = $connection->query("SHOW DATABASES LIKE '{$escapedName}'");
        if (!$exists instanceof mysqli_result || $exists->num_rows < 1) {
            fail("Could not create or access database '{$databaseName}': " . $connection->error);
        }
    }
}

function dropTablesWithPrefix(mysqli $connection, string $databaseName, string $prefix): void
{
    $escapedDatabase = $connection->real_escape_string($databaseName);
    $likePrefix = addcslashes($prefix, '\\_%') . '%';
    $escapedPrefix = $connection->real_escape_string($likePrefix);

    $result = $connection->query(
        "SELECT table_name FROM information_schema.tables " .
        "WHERE table_schema = '{$escapedDatabase}' AND table_name LIKE '{$escapedPrefix}' ESCAPE '\\\\'"
    );

    if (!$result instanceof mysqli_result) {
        fail('Could not inspect existing E2E tables: ' . $connection->error);
    }

    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['table_name'];
    }

    if (empty($tables)) {
        return;
    }

    $connection->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $escapedTable = str_replace('`', '``', $table);
        if ($connection->query("DROP TABLE IF EXISTS `{$escapedTable}`") === false) {
            fail("Could not drop temporary E2E table '{$table}': " . $connection->error);
        }
    }
    $connection->query('SET FOREIGN_KEY_CHECKS=1');
}

function runInstaller(string $workspace, array $dbConfig, string $prefix, string $installLog): void
{
    $process = new Process(
        [
            PHP_BINARY,
            'cli_install.php',
            'install',
            '--db_hostname', $dbConfig['host'],
            '--db_username', $dbConfig['user'],
            '--db_password', $dbConfig['pass'],
            '--db_database', $dbConfig['name'],
            '--db_prefix', $prefix,
            '--db_driver', 'mysqli',
            '--db_port', (string) $dbConfig['port'],
            '--username', 'admin',
            '--password', 'admin123!',
            '--email', 'e2e@example.invalid',
            '--http_server', 'http://localhost/',
        ],
        $workspace . '/install'
    );
    $process->setTimeout(300);
    $process->run();

    file_put_contents($installLog, $process->getOutput() . $process->getErrorOutput());

    if (!$process->isSuccessful()) {
        fail(
            "OpenCart CLI install failed. See {$installLog}\n" . $process->getOutput() . $process->getErrorOutput()
        );
    }
}

function assertInstalled(string $workspace): void
{
    $requiredPaths = [
        $workspace . '/config.php',
        $workspace . '/admin/config.php',
        $workspace . '/system/startup.php',
    ];

    foreach ($requiredPaths as $path) {
        if (!file_exists($path)) {
            fail("Expected installed path is missing: {$path}");
        }
    }
}

function buildEnvScript(array $state): string
{
    $lines = [
        '#!/usr/bin/env bash',
        'export OC_E2E_ROOT=' . shellExport($state['workspace']),
        'export OC_E2E_ARTIFACTS_DIR=' . shellExport($state['artifacts_dir']),
        'export OC_E2E_FIXTURE_VERSION=' . shellExport($state['fixture_version']),
        'export OC_E2E_DB_HOST=' . shellExport($state['db']['host']),
        'export OC_E2E_DB_NAME=' . shellExport($state['db']['name']),
        'export OC_E2E_DB_USER=' . shellExport($state['db']['user']),
        'export OC_E2E_DB_PASS=' . shellExport($state['db']['pass']),
        'export OC_E2E_DB_PORT=' . shellExport((string) $state['db']['port']),
        'export OC_E2E_DB_PREFIX=' . shellExport($state['db']['prefix']),
    ];

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function shellExport(string $value): string
{
    return "'" . str_replace("'", "'\"'\"'", $value) . "'";
}
