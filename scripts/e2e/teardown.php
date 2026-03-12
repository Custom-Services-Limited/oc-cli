<?php

declare(strict_types=1);

if ($argc < 2 || !file_exists($argv[1])) {
    exit(0);
}

$state = json_decode((string) file_get_contents($argv[1]), true);
if (!is_array($state) || empty($state['db'])) {
    exit(0);
}

$db = $state['db'];
mysqli_report(MYSQLI_REPORT_OFF);
$connection = mysqli_init();

if (!$connection instanceof mysqli) {
    exit(0);
}

if (!$connection->real_connect($db['host'], $db['user'], $db['pass'], $db['name'], (int) $db['port'])) {
    exit(0);
}

$escapedDatabase = $connection->real_escape_string($db['name']);
$likePrefix = addcslashes((string) $db['prefix'], '\\_%') . '%';
$escapedPrefix = $connection->real_escape_string($likePrefix);
$result = $connection->query(
    "SELECT table_name FROM information_schema.tables " .
    "WHERE table_schema = '{$escapedDatabase}' AND table_name LIKE '{$escapedPrefix}' ESCAPE '\\\\'"
);

if ($result instanceof mysqli_result) {
    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['table_name'];
    }

    $connection->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $escapedTable = str_replace('`', '``', $table);
        $connection->query("DROP TABLE IF EXISTS `{$escapedTable}`");
    }
    $connection->query('SET FOREIGN_KEY_CHECKS=1');
}

$connection->close();
