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

class InstallCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Import an OCMOD XML package into the modification table')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'Path to an OCMOD XML or .ocmod file'
            )
            ->addOption(
                'activate',
                'a',
                InputOption::VALUE_NONE,
                'Enable the imported modification immediately'
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

        $config = $this->getOpenCartConfig();
        $table = $config['db_prefix'] . 'modification';
        if (!$this->tableExists($db, $table)) {
            $version = $this->getOpenCartVersion();
            $message = 'The modification table is not available for this OpenCart installation.';

            if ($version && version_compare($version, '4.0.0', '>=')) {
                $message .= ' OpenCart 4 package import is not supported in this v1 stabilization pass.';
            }

            $this->io->error($message);
            return 1;
        }

        $packagePath = $this->input->getArgument('extension');
        if (!$this->validateExtensionFile($packagePath)) {
            return 1;
        }

        try {
            $modification = $this->extractModificationData($packagePath);
            $modificationId = $this->installModification(
                $db,
                $table,
                $modification,
                $this->input->getOption('activate')
            );
        } catch (\Exception $e) {
            $this->io->error('Import failed: ' . $e->getMessage());
            return 1;
        }

        $this->io->success(
            sprintf(
                "Imported modification '%s' (ID: %d)%s.",
                $modification['name'],
                $modificationId,
                $this->input->getOption('activate') ? ' and enabled it' : ''
            )
        );

        return 0;
    }

    private function validateExtensionFile($path)
    {
        if (!file_exists($path)) {
            $this->io->error("Extension file not found: {$path}");
            return false;
        }

        if (!is_readable($path)) {
            $this->io->error("Extension file is not readable: {$path}");
            return false;
        }

        $allowedExtensions = ['xml', 'ocmod'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            $this->io->error('Only OCMOD XML imports are supported in v1. Use .xml or .ocmod files.');
            return false;
        }

        return true;
    }

    private function extractModificationData($path)
    {
        $xmlContent = file_get_contents($path);
        if ($xmlContent === false) {
            throw new \RuntimeException('Could not read the modification file.');
        }

        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new \RuntimeException('The supplied file is not valid XML.');
        }

        $name = trim((string) $xml->name);
        if ($name === '') {
            $name = pathinfo(basename($path), PATHINFO_FILENAME);
        }

        $code = trim((string) $xml->code);
        if ($code === '') {
            $code = preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
        }

        return [
            'name' => $name,
            'code' => $code,
            'author' => trim((string) $xml->author) ?: 'Unknown',
            'version' => trim((string) $xml->version) ?: '1.0.0',
            'link' => trim((string) $xml->link),
            'xml' => $xmlContent,
        ];
    }

    private function installModification($db, $table, array $modification, $activate)
    {
        $code = $db->escape($modification['code']);
        $existing = $db->query("SELECT modification_id FROM {$table} WHERE code = '{$code}' LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            throw new \RuntimeException("A modification with code '{$modification['code']}' is already installed.");
        }

        $status = $activate ? 1 : 0;
        $db->query(
            "INSERT INTO {$table} (name, code, author, version, link, xml, status, date_added) VALUES (" .
            "'" . $db->escape($modification['name']) . "', " .
            "'{$code}', " .
            "'" . $db->escape($modification['author']) . "', " .
            "'" . $db->escape($modification['version']) . "', " .
            "'" . $db->escape($modification['link']) . "', " .
            "'" . $db->escape($modification['xml']) . "', " .
            "{$status}, NOW())"
        );

        return $db->getLastId();
    }
}
