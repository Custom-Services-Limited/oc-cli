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

class RestoreCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('db:restore')
            ->setDescription('Restore database from backup')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED,
                'Backup filename to restore from'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            )
            ->addOption(
                'ignore-errors',
                'i',
                InputOption::VALUE_NONE,
                'Continue execution even if errors occur'
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

        $filename = $this->input->getArgument('filename');

        // Check if file exists
        if (!file_exists($filename)) {
            $this->io->error("Backup file not found: {$filename}");
            $connection->close();
            return 1;
        }

        // Check if file is readable
        if (!is_readable($filename)) {
            $this->io->error("Backup file is not readable: {$filename}");
            $connection->close();
            return 1;
        }

        $this->io->title('Database Restore');
        $this->io->text("Source: {$filename}");
        $this->io->text("Database: {$config['db_database']}");

        // Get file size for progress indication
        $fileSize = $this->formatBytes(filesize($filename));
        $this->io->text("File size: {$fileSize}");

        // Confirm restore if not forced
        if (!$this->input->getOption('force')) {
            $this->io->warning('This will replace all data in the current database!');
            if (!$this->io->confirm('Are you sure you want to continue?', false)) {
                $this->io->text('Restore cancelled.');
                $connection->close();
                return 0;
            }
        }

        // Perform restore
        try {
            $this->restoreFromBackup($connection, $filename);
            $this->io->success('Database restored successfully.');
        } catch (\Exception $e) {
            $this->io->error("Restore failed: " . $e->getMessage());
            $connection->close();
            return 1;
        }

        $connection->close();
        return 0;
    }

    private function restoreFromBackup($connection, $filename)
    {
        $ignoreErrors = $this->input->getOption('ignore-errors');

        // Determine if file is compressed
        $isCompressed = substr($filename, -3) === '.gz';

        // Open file handle
        $handle = $isCompressed ? gzopen($filename, 'r') : fopen($filename, 'r');

        if (!$handle) {
            throw new \Exception("Could not open backup file: {$filename}");
        }

        $readFunc = $isCompressed ? 'gzgets' : 'fgets';
        $eofFunc = $isCompressed ? 'gzeof' : 'feof';

        $this->io->text('Starting restore...');

        // Disable foreign key checks
        $connection->query('SET FOREIGN_KEY_CHECKS=0');

        $lineNumber = 0;
        $queryBuffer = '';
        $queriesExecuted = 0;
        $errors = [];

        while (!$eofFunc($handle)) {
            $line = $readFunc($handle);
            $lineNumber++;

            if ($line === false) {
                break;
            }

            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || substr($line, 0, 2) === '--' || substr($line, 0, 2) === '/*') {
                continue;
            }

            // Add line to query buffer
            $queryBuffer .= $line;

            // Check if query is complete (ends with semicolon)
            if (substr($line, -1) === ';') {
                $query = trim($queryBuffer);
                $queryBuffer = '';

                if (!empty($query)) {
                    $result = $connection->query($query);

                    if ($result === false) {
                        $error = "Line {$lineNumber}: " . $connection->error;
                        $errors[] = $error;

                        if (!$ignoreErrors) {
                            throw new \Exception("SQL Error at line {$lineNumber}: " . $connection->error);
                        }

                        $this->io->warning("SQL Error at line {$lineNumber}: " . $connection->error);
                    } else {
                        $queriesExecuted++;

                        // Show progress every 100 queries
                        if ($queriesExecuted % 100 === 0) {
                            $this->io->text("Executed {$queriesExecuted} queries...");
                        }
                    }
                }
            }
        }

        // Re-enable foreign key checks
        $connection->query('SET FOREIGN_KEY_CHECKS=1');

        // Close file handle
        if ($isCompressed) {
            gzclose($handle);
        } else {
            fclose($handle);
        }

        $this->io->text("Total queries executed: {$queriesExecuted}");

        if (!empty($errors)) {
            $this->io->warning("Encountered " . count($errors) . " errors during restore:");
            foreach (array_slice($errors, 0, 5) as $error) {
                $this->io->text("  - {$error}");
            }

            if (count($errors) > 5) {
                $this->io->text("  ... and " . (count($errors) - 5) . " more errors");
            }
        }
    }
}
