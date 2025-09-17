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
     * @var object|null
     */
    protected $dbConnection;

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
        $this->addOption(
            'db-host',
            null,
            InputOption::VALUE_REQUIRED,
            'Database hostname'
        );
        $this->addOption(
            'db-user',
            null,
            InputOption::VALUE_REQUIRED,
            'Database username'
        );
        $this->addOption(
            'db-pass',
            null,
            InputOption::VALUE_REQUIRED,
            'Database password'
        );
        $this->addOption(
            'db-name',
            null,
            InputOption::VALUE_REQUIRED,
            'Database name'
        );
        $this->addOption(
            'db-port',
            null,
            InputOption::VALUE_REQUIRED,
            'Database port (default: 3306)'
        );
        $this->addOption(
            'db-prefix',
            null,
            InputOption::VALUE_REQUIRED,
            'Database table prefix (default: oc_)'
        );
        $this->addOption(
            'db-driver',
            null,
            InputOption::VALUE_REQUIRED,
            'Database driver (default: mysqli)'
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
        // If database connection parameters are provided, we don't need OpenCart root
        if ($this->usingCliDatabaseOptions()) {
            return true;
        }

        if (!$this->openCartRoot) {
            if ($require) {
                $this->io->error(
                    'This command must be run from an OpenCart installation directory ' .
                    'or provide database connection options (--db-host, --db-user, --db-pass, --db-name).'
                );
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
        // Check if database connection parameters are provided via command line options
        if ($this->input && $this->input->hasOption('db-host') && $this->input->getOption('db-host')) {
            return [
                'db_hostname' => $this->input->getOption('db-host'),
                'db_username' => $this->input->getOption('db-user'),
                'db_password' => $this->input->getOption('db-pass'),
                'db_database' => $this->input->getOption('db-name'),
                'db_port' => $this->input->getOption('db-port') ?: 3306,
                'db_prefix' => $this->input->getOption('db-prefix') ?: 'oc_',
                'db_driver' => $this->input->getOption('db-driver') ?: 'mysqli',
                'http_server' => '',
                'https_server' => '',
                'dir_system' => $this->openCartRoot ? rtrim($this->openCartRoot, '/\\') . '/system/' : null,
            ];
        }

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
            'db_driver' => $this->extractConfigValue($content, 'DB_DRIVER') ?: 'mysqli',
            'http_server' => $this->extractConfigValue($content, 'HTTP_SERVER'),
            'https_server' => $this->extractConfigValue($content, 'HTTPS_SERVER'),
            'dir_system' => $this->extractConfigValue($content, 'DIR_SYSTEM') ?: ($this->openCartRoot ? rtrim($this->openCartRoot, '/\\') . '/system/' : null),
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
     * @return \DB|null
     */
    protected function getDatabaseConnection()
    {
        if ($this->dbConnection) {
            return $this->dbConnection;
        }

        $config = $this->getOpenCartConfig();
        if (!$config) {
            return null;
        }

        $systemDir = isset($config['dir_system']) ? rtrim($config['dir_system'], '/\\') . '/' : null;

        if ($systemDir && is_dir($systemDir) && file_exists($systemDir . 'library/db.php')) {
            if (!defined('DIR_SYSTEM')) {
                if ($systemDir) {
                    define('DIR_SYSTEM', $systemDir);
                }
            }

            if (!defined('DIR_SYSTEM')) {
                $this->io->error('DIR_SYSTEM constant is not defined. Unable to bootstrap OpenCart database layer.');
                return null;
            }

            require_once $systemDir . 'library/db.php';

            $driver = $config['db_driver'] ?: 'mysqli';
            $driverFile = $systemDir . 'library/db/' . $driver . '.php';

            if (!file_exists($driverFile)) {
                $this->io->error("OpenCart database driver not found: {$driverFile}");
                return null;
            }

            require_once $driverFile;

            try {
                $this->dbConnection = new \DB(
                    $driver,
                    $config['db_hostname'],
                    $config['db_username'],
                    $config['db_password'],
                    $config['db_database'],
                    (int)$config['db_port']
                );
            } catch (\Exception $e) {
                $this->io->error('Database connection exception: ' . $e->getMessage());
                return null;
            }

            return $this->dbConnection;
        }

        if ($this->usingCliDatabaseOptions()) {
            try {
                $this->dbConnection = new LegacyDbAdapter($config);
            } catch (\Exception $e) {
                $this->io->error('Database connection failed: ' . $e->getMessage());
                return null;
            }

            return $this->dbConnection;
        }

        $this->io->error('Unable to locate OpenCart database library (system/library/db.php).');
        return null;
    }


    /**
     * Execute a database query
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    protected function query($sql, $params = [])
    {
        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            return null;
        }

        if (!empty($params)) {
            $sql = $this->buildParameterizedSql($connection, $sql, $params);
        }

        return $connection->query($sql);
    }

    /**
     * Replace parameter placeholders with escaped values
     *
     * @param object $connection
     * @param string $sql
     * @param array $params
     * @return string
     */
    protected function buildParameterizedSql($connection, $sql, array $params)
    {
        foreach ($params as $param) {
            $replacement = 'NULL';

            if ($param !== null) {
                if (is_int($param) || is_float($param)) {
                    $replacement = (string)$param;
                } elseif (is_bool($param)) {
                    $replacement = $param ? '1' : '0';
                } else {
                    $replacement = "'" . $connection->escape((string)$param) . "'";
                }
            }

            $sql = preg_replace('/\?/', $replacement, $sql, 1);
        }

        return $sql;
    }

    /**
     * Check whether CLI database connection options are present
     *
     * @return bool
     */
    protected function usingCliDatabaseOptions(): bool
    {
        return $this->input
            && $this->input->hasOption('db-host')
            && $this->input->getOption('db-host');
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

class LegacyDbAdapter
{
    /**
     * @var \mysqli
     */
    private $connection;

    /**
     * @var int
     */
    private $affectedRows = 0;

    /**
     * LegacyDbAdapter constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        $connection = \mysqli_init();
        if (!$connection) {
            throw new \Exception('Unable to initialise mysqli.');
        }

        ini_set('default_socket_timeout', '2');

        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $connection->options(MYSQLI_OPT_READ_TIMEOUT, 2);
        }

        $hostname = $config['db_hostname'] === 'localhost' ? '127.0.0.1' : $config['db_hostname'];

        if (
            !$connection->real_connect(
                $hostname,
                $config['db_username'],
                $config['db_password'],
                $config['db_database'],
                (int)$config['db_port']
            )
        ) {
            throw new \Exception($connection->connect_error ?: 'Connection failed');
        }

        $this->connection = $connection;
    }

    /**
     * Execute a query and return an OpenCart-style result object
     *
     * @param string $sql
     * @return object
     * @throws \Exception
     */
    public function query($sql)
    {
        $result = $this->connection->query($sql);

        if ($result instanceof \mysqli_result) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $result->free();

            $this->affectedRows = $this->connection->affected_rows;

            return (object) [
                'row' => $data ? $data[0] : [],
                'rows' => $data,
                'num_rows' => count($data),
            ];
        }

        if ($result === true) {
            $this->affectedRows = $this->connection->affected_rows;

            return (object) [
                'row' => [],
                'rows' => [],
                'num_rows' => 0,
            ];
        }

        throw new \Exception($this->connection->error ?: 'Unknown database error');
    }

    /**
     * Escape a string value
     *
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return $this->connection->real_escape_string($value);
    }

    /**
     * Get number of affected rows
     *
     * @return int
     */
    public function countAffected()
    {
        return $this->affectedRows;
    }

    /**
     * Get last insert ID
     *
     * @return int
     */
    public function getLastId()
    {
        return $this->connection->insert_id;
    }

    public function __destruct()
    {
        if ($this->connection instanceof \mysqli) {
            $this->connection->close();
        }
    }
}
