<?php

namespace OpenCart\CLI\Commands\Database;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractMaintenanceCommand extends Command
{
    protected function configureMaintenanceCommand(string $name, string $description): void
    {
        parent::configure();

        $this
            ->setName($name)
            ->setDescription($description)
            ->addArgument(
                'tables',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Specific table names to target. Defaults to all OpenCart tables.'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (table, json, yaml)',
                'table'
            );
    }

    /**
     * @param object $db
     * @return array<int, string>
     */
    protected function resolveTargetTables($db): array
    {
        $tables = (array) $this->input->getArgument('tables');
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'] ?? 'oc_';

        if (!empty($tables)) {
            $resolved = [];

            foreach ($tables as $table) {
                $normalised = $this->normaliseTableName((string) $table, $prefix);

                if (!$this->tableExists($db, $normalised)) {
                    $this->io->warning("Skipping missing table: {$normalised}");
                    continue;
                }

                $resolved[] = $normalised;
            }

            return array_values(array_unique($resolved));
        }

        $likePrefix = strtr($prefix, ['\\' => '\\\\', '%' => '\%', '_' => '\_']);
        $escapedPrefix = $db->escape($likePrefix) . '%';
        $result = $db->query("SHOW TABLES LIKE '{$escapedPrefix}' ESCAPE '\\\\'");

        if (!$result || empty($result->rows)) {
            return [];
        }

        $resolved = [];
        foreach ($result->rows as $row) {
            if (is_array($row)) {
                $resolved[] = (string) reset($row);
            }
        }

        sort($resolved);

        return $resolved;
    }

    protected function normaliseTableName(string $table, string $prefix): string
    {
        if (strpos($table, $prefix) === 0) {
            return $table;
        }

        return $prefix . $table;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    protected function renderMaintenanceResults(string $title, array $rows): void
    {
        $format = $this->input->getOption('format');

        switch ($format) {
            case 'json':
                $this->io->writeln(json_encode($rows, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($rows as $index => $row) {
                    $this->io->writeln("- result_{$index}:");
                    foreach ($row as $key => $value) {
                        $this->io->writeln('    ' . $key . ': ' . $this->stringifyValue($value));
                    }
                }
                break;
            default:
                $this->io->title($title);

                $tableRows = [];
                foreach ($rows as $row) {
                    $tableRows[] = [
                        $row['table'] ?? 'N/A',
                        $row['operation'] ?? 'N/A',
                        $row['status'] ?? 'N/A',
                        $row['message'] ?? 'N/A',
                    ];
                }

                $this->io->table(['Table', 'Operation', 'Status', 'Message'], $tableRows);
        }
    }

    /**
     * @param mixed $value
     */
    private function stringifyValue($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
