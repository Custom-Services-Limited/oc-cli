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

class VersionCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('core:version')
            ->setAliases(['version'])
            ->setDescription('Display version information')
            ->addOption(
                'opencart',
                'o',
                InputOption::VALUE_NONE,
                'Show OpenCart version only'
            )
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
        $openCartOnly = $this->input->getOption('opencart');

        $versions = $this->getVersionInfo();

        if ($openCartOnly) {
            if ($format === 'json') {
                $this->output->writeln(json_encode(['opencart' => $versions['opencart']]));
            } else {
                $this->output->writeln($versions['opencart']);
            }
            return 0;
        }

        switch ($format) {
            case 'json':
                $this->output->writeln(json_encode($versions, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($versions as $key => $value) {
                    $this->output->writeln($key . ': ' . $value);
                }
                break;
            default:
                $this->displayTable($versions);
                break;
        }

        return 0;
    }

    /**
     * Get version information
     *
     * @return array
     */
    protected function getVersionInfo()
    {
        $versions = [
            'oc-cli' => $this->getApplication()->getVersion(),
            'php' => PHP_VERSION,
            'os' => php_uname('s') . ' ' . php_uname('r'),
        ];

        $openCartVersion = parent::getOpenCartVersion();
        if ($openCartVersion) {
            $versions['opencart'] = $openCartVersion;
        } else {
            $versions['opencart'] = 'Not detected';
        }

        return $versions;
    }
    /**
     * Display version information as a table
     *
     * @param array $versions
     */
    protected function displayTable($versions)
    {
        $this->io->title('Version Information');

        $rows = [];
        foreach ($versions as $component => $version) {
            $rows[] = [ucfirst($component), $version];
        }

        $this->io->table(['Component', 'Version'], $rows);

        if ($this->openCartRoot) {
            $this->io->newLine();
            $this->io->writeln(
                sprintf('<comment>OpenCart root:</comment> %s', $this->openCartRoot)
            );
        } else {
            $this->io->warning('No OpenCart installation detected in current directory or parent directories.');
        }
    }
}
