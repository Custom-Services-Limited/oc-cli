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

class EnableCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:enable')
            ->setDescription('Enable an extension')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'Extension code or name to enable'
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

        $extensionIdentifier = $this->input->getArgument('extension');

        // Find the extension
        $extension = $this->findExtension($db, $extensionIdentifier);
        if (!$extension) {
            $this->io->error("Extension '{$extensionIdentifier}' not found.");
            return 1;
        }

        // Check if already enabled
        if ($this->isExtensionEnabled($db, $extension)) {
            $this->io->warning("Extension '{$extension['name']}' is already enabled.");
            return 0;
        }

        $this->io->title('Enabling Extension');
        $this->io->text("Extension: {$extension['name']} ({$extension['code']})");

        try {
            if ($this->enableExtension($db, $extension)) {
                $this->io->success("Extension '{$extension['name']}' enabled successfully.");
            } else {
                $this->io->error("Failed to enable extension '{$extension['name']}'.");
                return 1;
            }
        } catch (\Exception $e) {
            $this->io->error("Enable failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function findExtension($db, $identifier)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // Search by code or name
        $sql = "
            SELECT 
                ei.extension_install_id,
                ei.type,
                ei.code,
                ei.name,
                ei.version,
                ei.author
            FROM {$prefix}extension_install ei
            WHERE ei.code = '" . $db->escape($identifier) . "' OR ei.name = '" . $db->escape($identifier) . "'
            LIMIT 1
        ";

        $result = $db->query($sql);

        return $result && $result->num_rows ? $result->row : null;
    }

    private function isExtensionEnabled($db, $extension)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $extensionInstallId = (int)$extension['extension_install_id'];
        $extensionCode = $db->escape($extension['code']);

        $sql = "
            SELECT extension_id 
            FROM {$prefix}extension 
            WHERE extension_install_id = {$extensionInstallId} AND code = '{$extensionCode}'
        ";

        $result = $db->query($sql);

        return $result && $result->num_rows > 0;
    }

    private function enableExtension($db, $extension)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // Insert into extension table to enable
        $extensionInstallId = (int)$extension['extension_install_id'];
        $extensionType = $db->escape($extension['type']);
        $extensionCode = $db->escape($extension['code']);

        $sql = "
            INSERT INTO {$prefix}extension (extension_install_id, type, code) 
            VALUES (
                {$extensionInstallId},
                '{$extensionType}',
                '{$extensionCode}'
            )
        ";

        $db->query($sql);

        return $db->countAffected() > 0;
    }
}
