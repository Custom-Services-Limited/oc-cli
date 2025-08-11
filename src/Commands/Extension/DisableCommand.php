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

class DisableCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:disable')
            ->setDescription('Disable an extension')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'Extension code or name to disable'
            );
    }

    protected function handle()
    {
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        $extensionIdentifier = $this->input->getArgument('extension');

        // Find the extension
        $extension = $this->findExtension($connection, $extensionIdentifier);
        if (!$extension) {
            $this->io->error("Extension '{$extensionIdentifier}' not found.");
            $connection->close();
            return 1;
        }

        // Check if already disabled
        if (!$this->isExtensionEnabled($connection, $extension)) {
            $this->io->warning("Extension '{$extension['name']}' is already disabled.");
            $connection->close();
            return 0;
        }

        $this->io->title('Disabling Extension');
        $this->io->text("Extension: {$extension['name']} ({$extension['code']})");

        try {
            if ($this->disableExtension($connection, $extension)) {
                $this->io->success("Extension '{$extension['name']}' disabled successfully.");
            } else {
                $this->io->error("Failed to disable extension '{$extension['name']}'.");
                $connection->close();
                return 1;
            }
        } catch (\Exception $e) {
            $this->io->error("Disable failed: " . $e->getMessage());
            $connection->close();
            return 1;
        }

        $connection->close();
        return 0;
    }

    private function findExtension($connection, $identifier)
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
            WHERE ei.code = ? OR ei.name = ?
            LIMIT 1
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    private function isExtensionEnabled($connection, $extension)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $sql = "
            SELECT extension_id 
            FROM {$prefix}extension 
            WHERE extension_install_id = ? AND code = ?
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bind_param('is', $extension['extension_install_id'], $extension['code']);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() !== null;
    }

    private function disableExtension($connection, $extension)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // Remove from extension table to disable
        $sql = "
            DELETE FROM {$prefix}extension 
            WHERE extension_install_id = ? AND code = ?
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bind_param(
            'is',
            $extension['extension_install_id'],
            $extension['code']
        );

        return $stmt->execute();
    }
}
