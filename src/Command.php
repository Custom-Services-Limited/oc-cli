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

namespace OpenCart\CLI;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class Command extends BaseCommand
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var string|null
     */
    protected $openCartRoot;

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        $this->openCartRoot = $this->getApplication()->getOpenCartRoot();

        return $this->handle();
    }

    /**
     * Handle the command execution
     *
     * @return int
     */
    abstract protected function handle();

    /**
     * Check if we're in an OpenCart installation
     *
     * @param bool $require
     * @return bool
     */
    protected function requireOpenCart($require = true)
    {
        if (!$this->openCartRoot) {
            if ($require) {
                $this->io->error('This command must be run from an OpenCart installation directory.');
                return false;
            }
        }

        return true;
    }

    /**
     * Get OpenCart configuration
     *
     * @return array|null
     */
    protected function getOpenCartConfig()
    {
        if (!$this->openCartRoot) {
            return null;
        }

        $configFile = $this->openCartRoot . '/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        $config = [];
        include $configFile;

        return [
            'db_hostname' => defined('DB_HOSTNAME') ? DB_HOSTNAME : null,
            'db_username' => defined('DB_USERNAME') ? DB_USERNAME : null,
            'db_password' => defined('DB_PASSWORD') ? DB_PASSWORD : null,
            'db_database' => defined('DB_DATABASE') ? DB_DATABASE : null,
            'db_port' => defined('DB_PORT') ? DB_PORT : 3306,
            'db_prefix' => defined('DB_PREFIX') ? DB_PREFIX : '',
            'http_server' => defined('HTTP_SERVER') ? HTTP_SERVER : null,
            'https_server' => defined('HTTPS_SERVER') ? HTTPS_SERVER : null,
        ];
    }

    /**
     * Get database connection
     *
     * @return \mysqli|null
     */
    protected function getDatabaseConnection()
    {
        $config = $this->getOpenCartConfig();
        if (!$config) {
            return null;
        }

        try {
            $connection = new \mysqli(
                $config['db_hostname'],
                $config['db_username'],
                $config['db_password'],
                $config['db_database'],
                $config['db_port']
            );

            if ($connection->connect_error) {
                return null;
            }

            return $connection;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Execute a database query
     *
     * @param string $sql
     * @param array $params
     * @return \mysqli_result|bool|null
     */
    protected function query($sql, $params = [])
    {
        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            return null;
        }

        if (!empty($params)) {
            $stmt = $connection->prepare($sql);
            if ($stmt) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                $connection->close();
                return $result;
            }
        } else {
            $result = $connection->query($sql);
            $connection->close();
            return $result;
        }

        $connection->close();
        return null;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}