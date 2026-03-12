#!/usr/bin/env php
<?php

$projectRoot = dirname(__DIR__);
$targetFile = $projectRoot . '/src/BuildVersion.php';

if (in_array('--clean', $argv, true)) {
    if (is_file($targetFile)) {
        unlink($targetFile);
    }

    exit(0);
}

$version = $argv[1] ?? getenv('OC_CLI_VERSION') ?? '';
$version = trim((string) $version);

if ($version === '') {
    fwrite(STDERR, "Version is required.\n");
    exit(1);
}

if (preg_match('/^v(?=\d)/', $version) === 1) {
    $version = substr($version, 1);
}

$version = preg_replace('/[^0-9A-Za-z.+-]/', '', $version);

if ($version === '' || $version === null) {
    fwrite(STDERR, "Version could not be normalized.\n");
    exit(1);
}

$contents = <<<PHP
<?php

namespace OpenCart\\CLI;

final class BuildVersion
{
    public const VERSION = '{$version}';
}
PHP;

file_put_contents($targetFile, $contents . PHP_EOL);
