<?php

namespace OpenCart\CLI\Commands\Database;

use Symfony\Component\Console\Input\InputOption;

class CleanupCommand extends AbstractMaintenanceCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('db:cleanup')
            ->setDescription('Clean transient OpenCart database tables')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (table, json, yaml)',
                'table'
            );
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

        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'] ?? 'oc_';

        $targets = [
            $prefix . 'session',
            $prefix . 'api_session',
            $prefix . 'customer_online',
        ];

        $results = [];
        foreach ($targets as $table) {
            if (!$this->tableExists($db, $table)) {
                $results[] = [
                    'table' => $table,
                    'operation' => 'cleanup',
                    'status' => 'skipped',
                    'message' => 'Table not present.',
                ];
                continue;
            }

            $countResult = $db->query("SELECT COUNT(*) AS total FROM `" . str_replace('`', '``', $table) . "`");
            $count = (int) (($countResult && isset($countResult->row['total'])) ? $countResult->row['total'] : 0);

            $db->query("DELETE FROM `" . str_replace('`', '``', $table) . "`");

            $results[] = [
                'table' => $table,
                'operation' => 'cleanup',
                'status' => 'ok',
                'message' => 'Removed ' . $count . ' transient rows.',
            ];
        }

        $this->renderMaintenanceResults('Database Cleanup', $results);

        return 0;
    }
}
