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

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenCart\CLI\Commands\Core\VersionCommand;

class Application extends BaseApplication
{
    const VERSION = '1.0.0';
    const NAME = 'OC-CLI';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->addCommands($this->getDefaultCommands());
    }

    /**
     * Get the default commands for the application
     *
     * @return array
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new VersionCommand();

        return $commands;
    }

    /**
     * Get the long version of the application
     *
     * @return string
     */
    public function getLongVersion()
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment>',
            $this->getName(),
            $this->getVersion()
        );
    }

    /**
     * Run the application
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        return parent::run($input, $output);
    }

    /**
     * Detect OpenCart installation in current directory
     *
     * @param string $path
     * @return bool
     */
    public function detectOpenCart($path = '.')
    {
        $indicators = [
            'system/startup.php',
            'system/config/catalog.php',
            'admin/config.php',
            'config.php'
        ];

        foreach ($indicators as $indicator) {
            if (file_exists($path . '/' . $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get OpenCart root directory
     *
     * @param string $startPath
     * @return string|null
     */
    public function getOpenCartRoot($startPath = '.')
    {
        $path = realpath($startPath);

        while ($path && $path !== '/') {
            if ($this->detectOpenCart($path)) {
                return $path;
            }
            $path = dirname($path);
        }

        return null;
    }
}
