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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class BackupCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('db:backup')
            ->setDescription('Create a database backup')
            ->addArgument(
                'filename',
                InputArgument::OPTIONAL,
                'Backup filename (auto-generated if not provided)'
            )
            ->addOption(
                'compress',
                'c',
                InputOption::VALUE_NONE,
                'Compress the backup using gzip'
            )
            ->addOption(
                'tables',
                't',
                InputOption::VALUE_REQUIRED,
                'Backup specific tables only (comma-separated)'
            )
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output directory for backup files',
                getcwd()
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

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        // Generate filename if not provided
        $filename = $this->input->getArgument('filename');
        if (!$filename) {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "opencart_backup_{$timestamp}.sql";
        }

        // Add .gz extension if compressing
        $compress = $this->input->getOption('compress');
        if ($compress && substr($filename, -3) !== '.gz') {
            $filename .= '.gz';
        }

        $outputDir = $this->input->getOption('output-dir');
        $fullPath = rtrim($outputDir, '/') . '/' . $filename;

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                $this->io->error("Could not create output directory: {$outputDir}");
                return 1;
            }
        }

        $this->io->title('Creating Database Backup');
        $this->io->text("Database: {$config['db_database']}");
        $this->io->text("Output: {$fullPath}");

        // Get tables to backup
        $tables = $this->getTablesToBackup($connection, $config);
        if (empty($tables)) {
            $this->io->error('No tables found to backup.');
            $connection->close();
            return 1;
        }

        $this->io->text("Tables: " . count($tables));

        // Create backup
        try {
            $this->createBackup($connection, $config, $tables, $fullPath, $compress);
            $this->io->success("Backup created successfully: {$fullPath}");
        } catch (\Exception $e) {
            $this->io->error("Backup failed: " . $e->getMessage());
            $connection->close();
            return 1;
        }

        $connection->close();
        return 0;
    }

    private function getTablesToBackup($connection, $config)
    {
        $specificTables = $this->input->getOption('tables');

        if ($specificTables) {
            return array_map('trim', explode(',', $specificTables));
        }

        // Get all tables with the OpenCart prefix
        $prefix = $config['db_prefix'];
        $result = $connection->query("SHOW TABLES LIKE '{$prefix}%'");

        $tables = [];
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    private function createBackup($connection, $config, $tables, $filePath, $compress)
    {
        // Open file handle
        $handle = $compress ? gzopen($filePath, 'w') : fopen($filePath, 'w');

        if (!$handle) {
            throw new \Exception("Could not open file for writing: {$filePath}");
        }

        $writeFunc = $compress ? 'gzwrite' : 'fwrite';

        // Write header
        $header = "-- OpenCart Database Backup\n";
        $header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- Database: {$config['db_database']}\n\n";
        $header .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $writeFunc($handle, $header);

        // Backup each table
        foreach ($tables as $table) {
            $this->io->text("Backing up table: {$table}");

            // Get table structure
            $createResult = $connection->query("SHOW CREATE TABLE `{$table}`");
            if ($createResult && $createRow = $createResult->fetch_array()) {
                $writeFunc($handle, "-- Table structure for {$table}\n");
                $writeFunc($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                $writeFunc($handle, $createRow[1] . ";\n\n");
            }

            // Get table data
            $dataResult = $connection->query("SELECT * FROM `{$table}`");
            if ($dataResult && $dataResult->num_rows > 0) {
                $writeFunc($handle, "-- Data for table {$table}\n");

                while ($row = $dataResult->fetch_assoc()) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $connection->real_escape_string($value) . "'";
                        }
                    }

                    $columns = '`' . implode('`, `', array_keys($row)) . '`';
                    $valuesStr = implode(', ', $values);
                    $writeFunc($handle, "INSERT INTO `{$table}` ({$columns}) VALUES ({$valuesStr});\n");
                }

                $writeFunc($handle, "\n");
            }
        }

        // Write footer
        $footer = "SET FOREIGN_KEY_CHECKS=1;\n";
        $writeFunc($handle, $footer);

        // Close file
        if ($compress) {
            gzclose($handle);
        } else {
            fclose($handle);
        }
    }
}
