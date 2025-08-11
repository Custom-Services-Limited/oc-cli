<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Extension\DisableCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtensionDisableCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new DisableCommand();
        $this->command->setApplication($this->application);
    }

    public function testExtensionDisableCommandName()
    {
        $this->assertEquals('extension:disable', $this->command->getName());
    }

    public function testExtensionDisableCommandDescription()
    {
        $this->assertEquals('Disable an extension', $this->command->getDescription());
    }

    public function testExtensionDisableCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('extension'));

        $extensionArg = $definition->getArgument('extension');
        $this->assertTrue($extensionArg->isRequired());
    }

    public function testExtensionDisableCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('opencart-root'));
    }

    public function testExtensionDisableCommandWithoutOpenCart()
    {
        $input = new ArrayInput(['extension' => 'test_module']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionDisableCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput([
            'extension' => 'test_module',
            '--opencart-root' => '/nonexistent/path'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionDisableCommandWithExtensionCode()
    {
        $input = new ArrayInput(['extension' => 'payment_paypal']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionDisableCommandWithExtensionName()
    {
        $input = new ArrayInput(['extension' => 'PayPal Payment']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionDisableCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testExtensionDisableCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('extension', $arguments);
    }

    public function testExtensionDisableCommandRequiredArgument()
    {
        $definition = $this->command->getDefinition();
        $extensionArg = $definition->getArgument('extension');

        $this->assertTrue($extensionArg->isRequired());
        $this->assertEquals('Extension code or name to disable', $extensionArg->getDescription());
    }

    public function testExtensionDisableCommandArgumentValidation()
    {
        $definition = $this->command->getDefinition();
        $arguments = $definition->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('extension', $arguments);
    }

    public function testExtensionDisableCommandOptionsCount()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        // Should have at least opencart-root option plus base options
        $this->assertGreaterThanOrEqual(1, count($options));
    }

    public function testExtensionDisableCommandAllArguments()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        $optionNames = array_keys($options);
        $this->assertContains('opencart-root', $optionNames);
    }
}
