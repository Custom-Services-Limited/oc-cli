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

namespace OpenCart\CLI\Commands\Extension;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputOption;

class ModificationListCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('modification:list')
            ->setDescription('List installed modifications')
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

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        $modifications = $this->getModifications($connection);

        if (empty($modifications)) {
            $this->io->warning('No modifications found.');
            $connection->close();
            return 0;
        }

        $this->displayModifications($modifications);
        $connection->close();
        return 0;
    }

    private function getModifications($connection)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $sql = "
            SELECT 
                modification_id,
                name,
                code,
                author,
                version,
                link,
                status,
                date_added
            FROM {$prefix}modification
            ORDER BY name ASC
        ";

        $result = $connection->query($sql);

        $modifications = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $modifications[] = [
                    'id' => $row['modification_id'],
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'author' => $row['author'],
                    'version' => $row['version'],
                    'link' => $row['link'],
                    'status' => $row['status'] ? 'enabled' : 'disabled',
                    'date_added' => $row['date_added']
                ];
            }
        }

        return $modifications;
    }

    private function displayModifications($modifications)
    {
        $format = $this->input->getOption('format');

        switch ($format) {
            case 'json':
                $this->io->writeln(json_encode($modifications, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($modifications as $i => $modification) {
                    $this->io->writeln("- modification_{$i}:");
                    foreach ($modification as $key => $value) {
                        $this->io->writeln("    {$key}: {$value}");
                    }
                }
                break;
            default:
                $this->io->title('Installed Modifications');

                $rows = [];
                foreach ($modifications as $modification) {
                    $statusIcon = $modification['status'] === 'enabled' ? '✓' : '✗';
                    $rows[] = [
                        $modification['id'],
                        $modification['name'],
                        $modification['code'],
                        $modification['author'],
                        $modification['version'],
                        $statusIcon . ' ' . ucfirst($modification['status']),
                        $modification['date_added']
                    ];
                }

                $this->io->table(
                    ['ID', 'Name', 'Code', 'Author', 'Version', 'Status', 'Date Added'],
                    $rows
                );
                break;
        }
    }
}
