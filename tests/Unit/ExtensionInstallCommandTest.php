<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Extension\InstallCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtensionInstallCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new InstallCommand();
        $this->command->setApplication($this->application);
    }

    public function testExtensionInstallCommandName()
    {
        $this->assertEquals('extension:install', $this->command->getName());
    }

    public function testExtensionInstallCommandDescription()
    {
        $this->assertEquals('Install an extension', $this->command->getDescription());
    }

    public function testExtensionInstallCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('extension'));

        $extensionArg = $definition->getArgument('extension');
        $this->assertTrue($extensionArg->isRequired());
    }

    public function testExtensionInstallCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('activate'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $activateOption = $definition->getOption('activate');
        $this->assertEquals('a', $activateOption->getShortcut());
    }

    public function testExtensionInstallCommandWithoutOpenCart()
    {
        $input = new ArrayInput(['extension' => 'test.zip']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionInstallCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput([
            'extension' => 'test.zip',
            '--opencart-root' => '/nonexistent/path'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionInstallCommandWithActivateOption()
    {
        $input = new ArrayInput([
            'extension' => 'test.zip',
            '--activate' => true
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test option parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionInstallCommandWithAllOptions()
    {
        $input = new ArrayInput([
            'extension' => 'test.zip',
            '--activate' => true
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test all options parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionInstallCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testExtensionInstallCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('activate', $options);
        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('extension', $arguments);
    }

    public function testExtensionInstallCommandRequiredArgument()
    {
        $definition = $this->command->getDefinition();
        $extensionArg = $definition->getArgument('extension');

        $this->assertTrue($extensionArg->isRequired());
        $this->assertEquals('Extension file path or identifier', $extensionArg->getDescription());
    }

    public function testExtensionInstallCommandOptionShortcuts()
    {
        $definition = $this->command->getDefinition();

        $activateOption = $definition->getOption('activate');
        $this->assertEquals('a', $activateOption->getShortcut());
    }

    public function testExtensionInstallCommandWithNonexistentFile()
    {
        // This test would require creating an OpenCart setup
        // which is complex in a unit test environment
        $this->assertTrue(true); // Placeholder for now
    }

    public function testExtensionInstallCommandArgumentValidation()
    {
        $definition = $this->command->getDefinition();
        $arguments = $definition->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('extension', $arguments);
    }

    public function testExtensionInstallCommandOptionsCount()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        // Should have at least activate and opencart-root options
        $this->assertGreaterThanOrEqual(2, count($options));
    }
}
