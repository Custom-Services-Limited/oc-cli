<?php

namespace OpenCart\CLI\Commands\Database;

class CheckCommand extends AbstractMaintenanceCommand
{
    protected function configure()
    {
        $this->configureMaintenanceCommand('db:check', 'Check OpenCart database tables');
    }

    protected function handle()
    {
        return $this->runMaintenanceOperation('CHECK');
    }

    protected function runMaintenanceOperation(string $operation): int
    {
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $db = $this->getDatabaseConnection();
        if (!$db) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        $tables = $this->resolveTargetTables($db);
        if (empty($tables)) {
            $this->io->warning('No matching tables found.');
            return 0;
        }

        $results = [];
        $errorCount = 0;

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', $table);
            $query = $db->query($operation . " TABLE `{$escapedTable}`");

            if (!$query || empty($query->rows)) {
                $errorCount++;
                $results[] = [
                    'table' => $table,
                    'operation' => strtolower($operation),
                    'status' => 'error',
                    'message' => 'No response from database engine.',
                ];
                continue;
            }

            foreach ($query->rows as $row) {
                $msgType = strtolower((string) ($row['Msg_type'] ?? 'status'));
                $message = (string) ($row['Msg_text'] ?? 'OK');
                $status = $msgType === 'error' ? 'error' : ($msgType === 'warning' ? 'warning' : 'ok');

                if ($status === 'error') {
                    $errorCount++;
                }

                $results[] = [
                    'table' => $table,
                    'operation' => strtolower($operation),
                    'status' => $status,
                    'message' => $message,
                ];
            }
        }

        $this->renderMaintenanceResults('Database Table Check', $results);

        return $errorCount === count($results) ? 1 : 0;
    }
}
