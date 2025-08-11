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

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Core\ConfigCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigCommandTest extends TestCase
{
    /**
     * @var ConfigCommand
     */
    private $command;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var Application
     */
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new ConfigCommand();
        $this->command->setApplication($this->application);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testConfigCommandName()
    {
        $this->assertEquals('core:config', $this->command->getName());
    }

    public function testConfigCommandDescription()
    {
        $this->assertEquals('Manage OpenCart configuration', $this->command->getDescription());
    }

    public function testConfigCommandOptions()
    {
        $definition = $this->command->getDefinition();

        // Test opencart-root option (inherited from base Command)
        $this->assertTrue($definition->hasOption('opencart-root'));
        $openCartRootOption = $definition->getOption('opencart-root');
        $this->assertTrue($openCartRootOption->isValueRequired());

        // Test format option
        $this->assertTrue($definition->hasOption('format'));
        $formatOption = $definition->getOption('format');
        $this->assertEquals('f', $formatOption->getShortcut());
        $this->assertTrue($formatOption->isValueRequired());
        $this->assertEquals('table', $formatOption->getDefault());

        // Test admin option
        $this->assertTrue($definition->hasOption('admin'));
        $adminOption = $definition->getOption('admin');
        $this->assertEquals('a', $adminOption->getShortcut());
        $this->assertFalse($adminOption->isValueRequired());
    }

    public function testConfigCommandArguments()
    {
        $definition = $this->command->getDefinition();

        // Test action argument
        $this->assertTrue($definition->hasArgument('action'));
        $actionArgument = $definition->getArgument('action');
        $this->assertFalse($actionArgument->isRequired());
        $this->assertEquals('list', $actionArgument->getDefault());

        // Test key argument
        $this->assertTrue($definition->hasArgument('key'));
        $keyArgument = $definition->getArgument('key');
        $this->assertFalse($keyArgument->isRequired());

        // Test value argument
        $this->assertTrue($definition->hasArgument('value'));
        $valueArgument = $definition->getArgument('value');
        $this->assertFalse($valueArgument->isRequired());
    }

    public function testConfigCommandWithoutOpenCart()
    {
        // Test default action (list) without OpenCart installation
        $this->commandTester->execute([]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('This command must be run from an OpenCart installation directory', $output);
    }

    public function testConfigCommandGetActionWithoutOpenCart()
    {
        $this->commandTester->execute(['action' => 'get', 'key' => 'config_name']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('This command must be run from an OpenCart installation directory', $output);
    }

    public function testConfigCommandSetActionWithoutOpenCart()
    {
        $this->commandTester->execute(['action' => 'set', 'key' => 'config_name', 'value' => 'test']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('This command must be run from an OpenCart installation directory', $output);
    }

    public function testConfigCommandWithInvalidOpenCartRoot()
    {
        $this->commandTester->execute(['--opencart-root' => '/nonexistent']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('This command must be run from an OpenCart installation directory', $output);
    }

    public function testGetActionWithoutKey()
    {
        $this->commandTester->execute(['action' => 'get']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Key is required for get action', $output);
    }

    public function testSetActionWithoutKey()
    {
        $this->commandTester->execute(['action' => 'set']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Key is required for set action', $output);
    }

    public function testSetActionWithoutValue()
    {
        $this->commandTester->execute(['action' => 'set', 'key' => 'config_name']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Value is required for set action', $output);
    }

    public function testConfigCommandHelpMessages()
    {
        // Test that help messages are descriptive
        $this->commandTester->execute(['action' => 'get']);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Key is required', $output);

        $this->commandTester->execute(['action' => 'set', 'key' => 'test']);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Value is required', $output);
    }

    public function testConfigCommandArgumentDefaults()
    {
        // When no action is provided, should default to 'list'
        $definition = $this->command->getDefinition();
        $actionArgument = $definition->getArgument('action');
        $this->assertEquals('list', $actionArgument->getDefault());
    }

    public function testConfigCommandSupportsAllFormats()
    {
        // Test that the command supports different formats
        $definition = $this->command->getDefinition();
        $formatOption = $definition->getOption('format');

        // Should have format option with default 'table'
        $this->assertEquals('table', $formatOption->getDefault());

        // The description should mention the supported formats
        $description = $formatOption->getDescription();
        $this->assertStringContainsString('table', $description);
        $this->assertStringContainsString('json', $description);
        $this->assertStringContainsString('yaml', $description);
    }

    public function testAdminOptionConfiguration()
    {
        $definition = $this->command->getDefinition();
        $adminOption = $definition->getOption('admin');

        // Should be a flag (no value required)
        $this->assertFalse($adminOption->isValueRequired());
        $this->assertFalse($adminOption->acceptValue());

        // Should have proper description
        $description = $adminOption->getDescription();
        $this->assertStringContainsString('admin', strtolower($description));
        $this->assertStringContainsString('catalog', strtolower($description));
    }

    public function testValidActionsRecognized()
    {
        // Test list action (should fail on OpenCart installation check)
        $this->commandTester->execute(['action' => 'list']);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenCart installation', $output);
        $this->assertStringNotContainsString('Invalid action', $output);

        // Test get action with key (should fail on OpenCart installation check)
        $this->commandTester->execute(['action' => 'get', 'key' => 'test_key']);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenCart installation', $output);
        $this->assertStringNotContainsString('Invalid action', $output);

        // Test set action with key and value (should fail on OpenCart installation check)
        $this->commandTester->execute(['action' => 'set', 'key' => 'test_key', 'value' => 'test_value']);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenCart installation', $output);
        $this->assertStringNotContainsString('Invalid action', $output);
    }
}
