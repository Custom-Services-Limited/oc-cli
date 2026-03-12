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

namespace OpenCart\CLI\Tests\Unit;

use OpenCart\CLI\Application;
use OpenCart\CLI\Commands\Core\ConfigCommand;
use OpenCart\CLI\Tests\Helpers\InvokesNonPublicMembers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigCommandDisplayTest extends TestCase
{
    use InvokesNonPublicMembers;

    /**
     * @var ConfigCommand
     */
    private $command;

    /**
     * @var BufferedOutput
     */
    private $output;

    protected function setUp(): void
    {
        $application = new Application();
        $this->command = new ConfigCommand();
        $this->command->setApplication($application);

        $input = new ArrayInput(['command' => 'core:config']);
        $this->output = new BufferedOutput();

        $this->setProperty($this->command, 'input', $input);
        $this->setProperty($this->command, 'output', $this->output);
        $this->setProperty($this->command, 'io', new SymfonyStyle($input, $this->output));
    }

    public function testDisplayConfigTableWithEmptyConfig()
    {
        $this->invokeMethod($this->command, 'displayConfigTable', [], false);

        $this->assertStringContainsString('No configuration found', $this->output->fetch());
    }

    public function testDisplayConfigTableWithConfig()
    {
        $this->invokeMethod(
            $this->command,
            'displayConfigTable',
            [
                'config_name' => 'Test Store',
                'config_meta_title' => 'My Store',
                'config_email' => 'test@example.com',
            ],
            false
        );

        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('Configuration', $outputContent);
        $this->assertStringContainsString('config_name', $outputContent);
        $this->assertStringContainsString('Test Store', $outputContent);
    }

    public function testDisplayConfigTableWithDeprecatedAdminFlagStillShowsSharedConfig()
    {
        $this->invokeMethod(
            $this->command,
            'displayConfigTable',
            [
                'config_admin_limit' => '20',
                'config_compression' => '9',
            ],
            true
        );

        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('Configuration', $outputContent);
        $this->assertStringContainsString('config_admin_limit', $outputContent);
    }

    public function testDisplayConfigTableHandlesLongValues()
    {
        $this->invokeMethod(
            $this->command,
            'displayConfigTable',
            ['config_description' => str_repeat('A', 100)],
            false
        );

        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('config_description', $outputContent);
        $this->assertStringContainsString('...', $outputContent);
    }

    public function testDisplayConfigTableWithSpecialCharacters()
    {
        $this->invokeMethod(
            $this->command,
            'displayConfigTable',
            [
                'config_name' => 'Ståre with Spëcial Chàrs',
                'config_currency' => 'USD ($)',
            ],
            false
        );

        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('Ståre with Spëcial Chàrs', $outputContent);
        $this->assertStringContainsString('USD ($)', $outputContent);
    }
}
