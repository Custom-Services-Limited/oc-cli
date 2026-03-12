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

class EnableCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:enable')
            ->setDescription('Enable an extension entry in the extension table')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'Extension identifier. Use type:code for disabled extensions.'
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
        if ($target === null) {
            $this->io->error("Extension '{$identifier}' could not be resolved. Use type:code for disabled extensions.");
            return 1;
        }

        if ($this->extensionExists($db, $table, $target['type'], $target['code'])) {
            $this->io->warning("Extension '{$target['type']}:{$target['code']}' is already enabled.");
            return 0;
        }

        $db->query(
            "INSERT INTO {$table} (type, code) VALUES ('" .
            $db->escape($target['type']) . "', '" . $db->escape($target['code']) . "')"
        );

        if ($db->countAffected() < 1) {
            $this->io->error("Failed to enable extension '{$target['type']}:{$target['code']}'.");
            return 1;
        }

        $this->io->success("Extension '{$target['type']}:{$target['code']}' enabled successfully.");

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
