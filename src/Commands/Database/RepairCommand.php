<?php

namespace OpenCart\CLI\Commands\Database;

class RepairCommand extends AbstractMaintenanceCommand
{
    protected function configure()
    {
        $this->configureMaintenanceCommand('db:repair', 'Repair OpenCart database tables');
    }

    protected function handle()
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
            $query = $db->query("REPAIR TABLE `{$escapedTable}`");

            if (!$query || empty($query->rows)) {
                $errorCount++;
                $results[] = [
                    'table' => $table,
                    'operation' => 'repair',
                    'status' => 'error',
                    'message' => 'No response from database engine.',
                ];
                continue;
            }

            foreach ($query->rows as $row) {
                $message = (string) ($row['Msg_text'] ?? 'OK');
                $msgType = strtolower((string) ($row['Msg_type'] ?? 'status'));
                $status = $msgType === 'error' ? 'error' : ($msgType === 'warning' ? 'warning' : 'ok');

                if ($status === 'error') {
                    $errorCount++;
                }

                $results[] = [
                    'table' => $table,
                    'operation' => 'repair',
                    'status' => $status,
                    'message' => $message,
                ];
            }
        }

        $this->renderMaintenanceResults('Database Table Repair', $results);

        return $errorCount === count($results) ? 1 : 0;
    }
}
