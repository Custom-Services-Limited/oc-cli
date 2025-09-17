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
use Symfony\Component\Console\Input\InputOption;

class CheckRequirementsCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('core:check-requirements')
            ->setDescription('Check system requirements')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (table, json, yaml)',
                'table'
            );
    }

    /**
     * Handle the command execution
     *
     * @return int
     */
    protected function handle()
    {
        $format = $this->input->getOption('format');
        $requirements = $this->checkRequirements();

        switch ($format) {
            case 'json':
                $this->output->writeln(json_encode($requirements, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($requirements as $category => $checks) {
                    $this->output->writeln($category . ':');
                    foreach ($checks as $check) {
                        $this->output->writeln('  - name: ' . $check['name']);
                        $this->output->writeln('    status: ' . ($check['status'] ? 'pass' : 'fail'));
                        $this->output->writeln('    message: ' . $check['message']);
                    }
                }
                break;
            default:
                $this->displayTable($requirements);
                break;
        }

        $hasFailures = $this->hasFailures($requirements);
        return $hasFailures ? 1 : 0;
    }

    /**
     * Check system requirements
     *
     * @return array
     */
    protected function checkRequirements()
    {
        return [
            'php' => $this->checkPhpRequirements(),
            'extensions' => $this->checkPhpExtensions(),
            'permissions' => $this->checkFilePermissions(),
            'database' => $this->checkDatabaseRequirements(),
        ];
    }

    /**
     * Check PHP requirements
     *
     * @return array
     */
    protected function checkPhpRequirements()
    {
        $checks = [];

        $checks[] = [
            'name' => 'PHP Version >= 7.4',
            'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'message' => 'Current: ' . PHP_VERSION,
        ];

        $checks[] = [
            'name' => 'Memory Limit >= 128M',
            'status' => $this->checkMemoryLimit(),
            'message' => 'Current: ' . ini_get('memory_limit'),
        ];

        $checks[] = [
            'name' => 'Max Execution Time >= 30s',
            'status' => $this->checkExecutionTime(),
            'message' => 'Current: ' . ini_get('max_execution_time') . 's',
        ];

        return $checks;
    }

    /**
     * Check PHP extensions
     *
     * @return array
     */
    protected function checkPhpExtensions()
    {
        $required = ['curl', 'gd', 'mbstring', 'zip', 'zlib', 'json', 'openssl'];
        $recommended = ['mysqli', 'pdo_mysql', 'iconv', 'mcrypt'];

        $checks = [];

        foreach ($required as $ext) {
            $checks[] = [
                'name' => 'Extension: ' . $ext . ' (required)',
                'status' => extension_loaded($ext),
                'message' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        foreach ($recommended as $ext) {
            $checks[] = [
                'name' => 'Extension: ' . $ext . ' (recommended)',
                'status' => extension_loaded($ext),
                'message' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        return $checks;
    }

    /**
     * Check file permissions
     *
     * @return array
     */
    protected function checkFilePermissions()
    {
        $checks = [];

        if (!$this->openCartRoot) {
            $checks[] = [
                'name' => 'OpenCart Installation',
                'status' => false,
                'message' => 'No OpenCart installation detected',
            ];
            return $checks;
        }

        $writableDirs = [
            'image/',
            'image/cache/',
            'image/catalog/',
            'system/storage/',
            'system/storage/cache/',
            'system/storage/logs/',
            'system/storage/download/',
            'system/storage/upload/',
            'system/storage/modification/',
        ];

        foreach ($writableDirs as $dir) {
            $fullPath = $this->openCartRoot . '/' . $dir;
            $isWritable = is_dir($fullPath) && is_writable($fullPath);

            $checks[] = [
                'name' => 'Directory writable: ' . $dir,
                'status' => $isWritable,
                'message' => $isWritable ? 'Writable' : (is_dir($fullPath) ? 'Not writable' : 'Does not exist'),
            ];
        }

        $writableFiles = [
            'config.php',
            'admin/config.php',
        ];

        foreach ($writableFiles as $file) {
            $fullPath = $this->openCartRoot . '/' . $file;
            $isWritable = file_exists($fullPath) && is_writable($fullPath);

            $checks[] = [
                'name' => 'File writable: ' . $file,
                'status' => $isWritable,
                'message' => $isWritable ? 'Writable' : (file_exists($fullPath) ? 'Not writable' : 'Does not exist'),
            ];
        }

        return $checks;
    }

    /**
     * Check database requirements
     *
     * @return array
     */
    protected function checkDatabaseRequirements()
    {
        $checks = [];

        if (!$this->openCartRoot) {
            $checks[] = [
                'name' => 'Database Connection',
                'status' => false,
                'message' => 'No OpenCart installation to test',
            ];
            return $checks;
        }

        $db = $this->getDatabaseConnection();

        $checks[] = [
            'name' => 'Database Connection',
            'status' => $db !== null,
            'message' => $db ? 'Connected' : 'Failed to connect',
        ];

        if ($db) {
            $versionResult = $db->query('SELECT VERSION() AS version');
            $hasVersion = $versionResult && isset($versionResult->row['version']);
            $versionString = $hasVersion ? $versionResult->row['version'] : 'unknown';

            $numericVersion = 0;
            if ($versionString !== 'unknown') {
                $clean = preg_replace('/[^0-9.]/', '', $versionString);
                $parts = array_pad(explode('.', $clean), 3, 0);
                $numericVersion = ((int)$parts[0]) * 10000 + ((int)$parts[1]) * 100 + (int)$parts[2];
            }

            $checks[] = [
                'name' => 'MySQL Version >= 5.6',
                'status' => $numericVersion >= 50600,
                'message' => 'Current: ' . $versionString,
            ];
        }

        return $checks;
    }

    /**
     * Check memory limit
     *
     * @return bool
     */
    protected function checkMemoryLimit()
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return true;
        }

        $bytes = $this->convertToBytes($memoryLimit);
        return $bytes >= 128 * 1024 * 1024;
    }

    /**
     * Check execution time
     *
     * @return bool
     */
    protected function checkExecutionTime()
    {
        $maxTime = ini_get('max_execution_time');

        if ($maxTime === '0') {
            return true;
        }

        return intval($maxTime) >= 30;
    }

    /**
     * Convert memory string to bytes
     *
     * @param string $val
     * @return int
     */
    protected function convertToBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = intval($val);

        switch ($last) {
            case 'g':
                $val *= 1024;
                // fall through
            case 'm':
                $val *= 1024;
                // fall through
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Check if there are any failures
     *
     * @param array $requirements
     * @return bool
     */
    protected function hasFailures($requirements)
    {
        foreach ($requirements as $category) {
            foreach ($category as $check) {
                if (!$check['status']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Display requirements as a table
     *
     * @param array $requirements
     */
    protected function displayTable($requirements)
    {
        $this->io->title('System Requirements Check');

        foreach ($requirements as $category => $checks) {
            $this->io->section(ucfirst($category));

            $rows = [];
            foreach ($checks as $check) {
                $status = $check['status'] ? '<info>✓ PASS</info>' : '<error>✗ FAIL</error>';
                $rows[] = [$check['name'], $status, $check['message']];
            }

            $this->io->table(['Requirement', 'Status', 'Details'], $rows);
        }

        if ($this->hasFailures($requirements)) {
            $this->io->error('Some requirements are not met. Please fix the issues above.');
        } else {
            $this->io->success('All requirements are met!');
        }
    }
}
