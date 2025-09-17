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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:list')
            ->setDescription('List installed extensions')
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Extension type (module, payment, shipping, etc.)'
            )
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

        $type = $this->input->getArgument('type');
        $extensions = $this->getExtensions($db, $type);

        if (empty($extensions)) {
            $message = $type
                ? "No extensions found for type '{$type}'."
                : 'No extensions found.';
            $this->io->warning($message);
            return 0;
        }

        $this->displayExtensions($extensions);
        return 0;
    }

    private function getExtensions($db, $type = null)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $sql = "
            SELECT 
                e.type,
                e.code,
                'enabled' as status
            FROM {$prefix}extension e
        ";

        if ($type) {
            $sql .= " WHERE e.type = '" . $db->escape($type) . "'";
        }

        $result = $db->query($sql);

        $extensions = [];
        if ($result && !empty($result->rows)) {
            foreach ($result->rows as $row) {
                $extensions[] = [
                    'type' => $row['type'],
                    'code' => $row['code'],
                    'status' => $row['status']
                ];
            }
        }

        return $extensions;
    }

    private function displayExtensions($extensions)
    {
        $format = $this->input->getOption('format');

        switch ($format) {
            case 'json':
                $this->io->writeln(json_encode($extensions, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($extensions as $i => $extension) {
                    $this->io->writeln("- extension_{$i}:");
                    foreach ($extension as $key => $value) {
                        $this->io->writeln("    {$key}: {$value}");
                    }
                }
                break;
            default:
                $this->io->title('Installed Extensions');

                $rows = [];
                foreach ($extensions as $extension) {
                    $statusIcon = $extension['status'] === 'enabled' ? '✓' : '✗';
                    $rows[] = [
                        $extension['type'],
                        $extension['code'],
                        $statusIcon . ' ' . ucfirst($extension['status'])
                    ];
                }

                $this->io->table(
                    ['Type', 'Code', 'Status'],
                    $rows
                );
                break;
        }
    }
}
