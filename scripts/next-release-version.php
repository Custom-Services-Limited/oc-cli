#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use OpenCart\CLI\Support\ReleaseVersion;

$baseline = getenv('OC_CLI_RELEASE_BASELINE');
$tags = [];

exec('git tag --list', $tags);

$latest = ReleaseVersion::selectLatest(
    $tags,
    is_string($baseline) && trim($baseline) !== '' ? $baseline : ReleaseVersion::BASELINE
);

echo ReleaseVersion::next($latest) . PHP_EOL;
