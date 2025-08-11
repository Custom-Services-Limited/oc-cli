<?php

/**
 * OC-CLI - OpenCart Command Line Interface
 *
 * @author    Custom Services Limited <info@opencartgreece.gr>
 * @copyright 2024 Custom Services Limited
 * @license   GPL-3.0-or-later
 * @link      https://support.opencartgreece.gr/
 * @link      https://github.com/Custom-Services-Limited/oc-cli
 */

namespace OpenCart\CLI\Commands\Database;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputOption;

class InfoCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('db:info')
            ->setDescription('Display database connection information')
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

        $config = $this->getOpenCartConfig();
        if (!$config) {
            $this->io->error('Could not read OpenCart configuration.');
            return 1;
        }

        // Test database connection
        $connection = $this->getDatabaseConnection();
        $connectionStatus = $connection ? 'Connected' : 'Failed';

        // Get database size and table count if connected
        $databaseSize = null;
        $tableCount = null;
        $serverVersion = null;

        if ($connection) {
            // Get server version
            $serverVersion = $connection->server_info;

            // Get database size
            $result = $connection->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = '{$config['db_database']}'
            ");

            if ($result && $row = $result->fetch_assoc()) {
                $databaseSize = $row['size_mb'] . ' MB';
            }

            // Get table count
            $result = $connection->query("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = '{$config['db_database']}'
            ");

            if ($result && $row = $result->fetch_assoc()) {
                $tableCount = $row['count'];
            }

            $connection->close();
        }

        $info = [
            'hostname' => $config['db_hostname'],
            'port' => $config['db_port'],
            'username' => $config['db_username'],
            'database' => $config['db_database'],
            'prefix' => $config['db_prefix'],
            'connection_status' => $connectionStatus,
            'server_version' => $serverVersion,
            'database_size' => $databaseSize,
            'table_count' => $tableCount,
        ];

        $format = $this->input->getOption('format');

        switch ($format) {
            case 'json':
                $this->io->writeln(json_encode($info, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($info as $key => $value) {
                    $this->io->writeln($key . ': ' . ($value ?: 'null'));
                }
                break;
            default:
                $this->io->title('Database Information');

                $rows = [];
                foreach ($info as $key => $value) {
                    $label = ucwords(str_replace('_', ' ', $key));
                    $rows[] = [$label, $value ?: 'N/A'];
                }

                $this->io->table(['Property', 'Value'], $rows);
                break;
        }

        return 0;
    }
}
