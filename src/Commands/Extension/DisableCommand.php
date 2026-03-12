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
use OpenCart\CLI\Support\ExtensionHelper;
use Symfony\Component\Console\Input\InputArgument;

class DisableCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:disable')
            ->setDescription('Disable an extension entry from the extension table')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'Extension identifier. Use type:code when multiple entries share a code.'
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
        $table = $config['db_prefix'] . 'extension';
        if (!$this->tableExists($db, $table)) {
            $this->io->error('The extension table is not available for this OpenCart installation.');
            return 1;
        }

        $identifier = $this->input->getArgument('extension');
        $target = ExtensionHelper::resolveTypeAndCode($db, $table, $identifier);
        if ($target === null || !$this->extensionExists($db, $table, $target['type'], $target['code'])) {
            $this->io->error("Extension '{$identifier}' is not enabled.");
            return 1;
        }

        $db->query(
            "DELETE FROM {$table} WHERE type = '" . $db->escape($target['type']) .
            "' AND code = '" . $db->escape($target['code']) . "'"
        );

        if ($db->countAffected() < 1) {
            $this->io->error("Failed to disable extension '{$target['type']}:{$target['code']}'.");
            return 1;
        }

        $this->io->success("Extension '{$target['type']}:{$target['code']}' disabled successfully.");

        return 0;
    }

    private function extensionExists($db, $table, $type, $code)
    {
        $result = $db->query(
            "SELECT extension_id FROM {$table} WHERE type = '" . $db->escape($type) .
            "' AND code = '" . $db->escape($code) . "' LIMIT 1"
        );

        return $result && $result->num_rows > 0;
    }
}
