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
use Symfony\Component\Console\Input\InputOption;
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
     * Configure the command with global options
     */
    protected function configure()
    {
        $this->addOption(
            'opencart-root',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to OpenCart installation directory'
        );
    }

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

        // Check if the opencart-root option exists and is set
        $explicitRoot = null;
        if ($input->hasOption('opencart-root') && $input->getOption('opencart-root')) {
            $explicitRoot = $input->getOption('opencart-root');
        }

        if ($explicitRoot) {
            $this->openCartRoot = $this->getApplication()->getOpenCartRoot($explicitRoot);
        } else {
            $this->openCartRoot = $this->getApplication()->getOpenCartRoot();
        }

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

        // Read and parse the config file manually to avoid constant pollution
        $content = file_get_contents($configFile);
        if ($content === false) {
            return null;
        }

        $config = [
            'db_hostname' => $this->extractConfigValue($content, 'DB_HOSTNAME'),
            'db_username' => $this->extractConfigValue($content, 'DB_USERNAME'),
            'db_password' => $this->extractConfigValue($content, 'DB_PASSWORD'),
            'db_database' => $this->extractConfigValue($content, 'DB_DATABASE'),
            'db_port' => $this->extractConfigValue($content, 'DB_PORT') ?: 3306,
            'db_prefix' => $this->extractConfigValue($content, 'DB_PREFIX') ?: '',
            'http_server' => $this->extractConfigValue($content, 'HTTP_SERVER'),
            'https_server' => $this->extractConfigValue($content, 'HTTPS_SERVER'),
        ];

        return $config;
    }

    /**
     * Extract configuration value from PHP file content
     *
     * @param string $content
     * @param string $constant
     * @return string|null
     */
    protected function extractConfigValue($content, $constant)
    {
        // Match define('CONSTANT', 'value') or define("CONSTANT", "value")
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant, '/') . '[\'"]\s*,\s*[\'"]([^\'"]*)[\'"\s]*\)/i';

        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }

        return null;
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
            // Set connection timeout to prevent hanging in tests
            ini_set('default_socket_timeout', 2);

            // Create mysqli instance and set timeouts before connecting
            $connection = mysqli_init();
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            if (PHP_VERSION_ID >= 70200) {
                $connection->options(MYSQLI_OPT_READ_TIMEOUT, 2);
            }

            $success = $connection->real_connect(
                $config['db_hostname'] === 'localhost' ? '127.0.0.1' : $config['db_hostname'],
                $config['db_username'],
                $config['db_password'],
                $config['db_database'],
                $config['db_port']
            );

            if (!$success || $connection->connect_error) {
                $this->io->error("Database connection failed: " . ($connection->connect_error ?: 'Connection failed'));
                return null;
            }

            return $connection;
        } catch (\Exception $e) {
            $this->io->error("Database connection exception: " . $e->getMessage());
            $this->io->error("Error details: " . $e->getFile() . ":" . $e->getLine());
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

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
