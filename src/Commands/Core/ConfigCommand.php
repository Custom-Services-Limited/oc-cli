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

namespace OpenCart\CLI\Commands\Core;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ConfigCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('core:config')
            ->setDescription('Manage OpenCart configuration')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'Action to perform (get, set, list)',
                'list'
            )
            ->addArgument(
                'key',
                InputArgument::OPTIONAL,
                'Configuration key to get or set'
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'Value to set (only for set action)'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (table, json, yaml)',
                'table'
            )
            ->addOption(
                'admin',
                'a',
                InputOption::VALUE_NONE,
                'Use admin configuration instead of catalog'
            );
    }

    /**
     * Handle the command execution
     *
     * @return int
     */
    protected function handle()
    {
        $action = $this->input->getArgument('action');
        $key = $this->input->getArgument('key');
        $value = $this->input->getArgument('value');
        $format = $this->input->getOption('format');
        $isAdmin = $this->input->getOption('admin');

        // Validate arguments before checking OpenCart
        if ($action === 'get' && !$key) {
            $this->io->error('Key is required for get action');
            return 1;
        }

        if ($action === 'set') {
            if (!$key) {
                $this->io->error('Key is required for set action');
                return 1;
            }
            if ($value === null) {
                $this->io->error('Value is required for set action');
                return 1;
            }
        }

        // Now check for OpenCart installation
        if (!$this->requireOpenCart()) {
            return 1;
        }

        switch ($action) {
            case 'get':
                return $this->getConfig($key, $format, $isAdmin);
            case 'set':
                return $this->setConfig($key, $value, $isAdmin);
            case 'list':
            default:
                return $this->listConfig($format, $isAdmin);
        }
    }

    /**
     * Get configuration value
     *
     * @param string|null $key
     * @param string $format
     * @param bool $isAdmin
     * @return int
     */
    protected function getConfig($key, $format, $isAdmin)
    {
        if (!$key) {
            $this->io->error('Key is required for get action');
            return 1;
        }

        $config = $this->getConfigFromDatabase($isAdmin);
        if ($config === null) {
            $this->io->error('Failed to connect to database');
            return 1;
        }

        if (!isset($config[$key])) {
            $this->io->error("Configuration key '{$key}' not found");
            return 1;
        }

        $value = $config[$key];

        switch ($format) {
            case 'json':
                $this->output->writeln(json_encode([$key => $value]));
                break;
            case 'yaml':
                $this->output->writeln($key . ': ' . $value);
                break;
            default:
                $this->output->writeln($value);
                break;
        }

        return 0;
    }

    /**
     * Set configuration value
     *
     * @param string|null $key
     * @param string|null $value
     * @param bool $isAdmin
     * @return int
     */
    protected function setConfig($key, $value, $isAdmin)
    {
        if (!$key) {
            $this->io->error('Key is required for set action');
            return 1;
        }

        if ($value === null) {
            $this->io->error('Value is required for set action');
            return 1;
        }

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Failed to connect to database');
            return 1;
        }

        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];
        $table = $prefix . 'setting';

        $group = $isAdmin ? 'config' : 'config';
        $store_id = 0;

        $query = "UPDATE `{$table}` SET `value` = ? WHERE `code` = ? AND `key` = ? AND `store_id` = ?";
        $result = $this->query($query, [$value, $group, $key, $store_id]);

        if ($result === null) {
            $checkQuery = "SELECT COUNT(*) as count FROM `{$table}` WHERE `code` = ? AND `key` = ? AND `store_id` = ?";
            $checkResult = $this->query($checkQuery, [$group, $key, $store_id]);

            if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                $insertQuery = "INSERT INTO `{$table}` (`store_id`, `code`, `key`, `value`, `serialized`) " .
                              "VALUES (?, ?, ?, ?, 0)";
                $insertResult = $this->query($insertQuery, [$store_id, $group, $key, $value]);

                if ($insertResult) {
                    $this->io->success("Configuration '{$key}' set to '{$value}'");
                    return 0;
                }
            }

            $this->io->error("Failed to set configuration '{$key}'");
            return 1;
        }

        $this->io->success("Configuration '{$key}' updated to '{$value}'");
        return 0;
    }

    /**
     * List all configuration
     *
     * @param string $format
     * @param bool $isAdmin
     * @return int
     */
    protected function listConfig($format, $isAdmin)
    {
        $config = $this->getConfigFromDatabase($isAdmin);
        if ($config === null) {
            $this->io->error('Failed to connect to database');
            return 1;
        }

        switch ($format) {
            case 'json':
                $this->output->writeln(json_encode($config, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($config as $key => $value) {
                    $this->output->writeln($key . ': ' . $value);
                }
                break;
            default:
                $this->displayConfigTable($config, $isAdmin);
                break;
        }

        return 0;
    }

    /**
     * Get configuration from database
     *
     * @param bool $isAdmin
     * @return array|null
     */
    protected function getConfigFromDatabase($isAdmin)
    {
        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            return null;
        }

        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];
        $table = $prefix . 'setting';

        $store_id = 0;

        $query = "SELECT `key`, `value` FROM `{$table}` WHERE `store_id` = ? ORDER BY `key`";
        $result = $this->query($query, [$store_id]);

        if (!$result) {
            return null;
        }

        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    /**
     * Display configuration as a table
     *
     * @param array $config
     * @param bool $isAdmin
     */
    protected function displayConfigTable($config, $isAdmin)
    {
        $title = $isAdmin ? 'Admin Configuration' : 'Catalog Configuration';
        $this->io->title($title);

        if (empty($config)) {
            $this->io->warning('No configuration found');
            return;
        }

        $rows = [];
        foreach ($config as $key => $value) {
            $displayValue = strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
            $rows[] = [$key, $displayValue];
        }

        $this->io->table(['Key', 'Value'], $rows);
        $this->io->note('Total: ' . count($config) . ' configuration entries');

        if ($this->openCartRoot) {
            $this->io->note('OpenCart root: ' . $this->openCartRoot);
        }
    }
}
