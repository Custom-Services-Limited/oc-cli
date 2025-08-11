<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Extension\EnableCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtensionEnableCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new EnableCommand();
        $this->command->setApplication($this->application);
    }

    public function testExtensionEnableCommandName()
    {
        $this->assertEquals('extension:enable', $this->command->getName());
    }

    public function testExtensionEnableCommandDescription()
    {
        $this->assertEquals('Enable an extension', $this->command->getDescription());
    }

    public function testExtensionEnableCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('extension'));

        $extensionArg = $definition->getArgument('extension');
        $this->assertTrue($extensionArg->isRequired());
    }

    public function testExtensionEnableCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('opencart-root'));
    }

    public function testExtensionEnableCommandWithoutOpenCart()
    {
        $input = new ArrayInput(['extension' => 'test_module']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionEnableCommandWithInvalidOpenCartRoot()
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

    public function testExtensionEnableCommandWithExtensionCode()
    {
        $input = new ArrayInput(['extension' => 'payment_paypal']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionEnableCommandWithExtensionName()
    {
        $input = new ArrayInput(['extension' => 'PayPal Payment']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionEnableCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testExtensionEnableCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('extension', $arguments);
    }

    public function testExtensionEnableCommandRequiredArgument()
    {
        $definition = $this->command->getDefinition();
        $extensionArg = $definition->getArgument('extension');

        $this->assertTrue($extensionArg->isRequired());
        $this->assertEquals('Extension code or name to enable', $extensionArg->getDescription());
    }

    public function testExtensionEnableCommandArgumentValidation()
    {
        $definition = $this->command->getDefinition();
        $arguments = $definition->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('extension', $arguments);
    }

    public function testExtensionEnableCommandOptionsCount()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        // Should have at least opencart-root option plus base options
        $this->assertGreaterThanOrEqual(1, count($options));
    }

    public function testExtensionEnableCommandAllArguments()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        $optionNames = array_keys($options);
        $this->assertContains('opencart-root', $optionNames);
    }
}
