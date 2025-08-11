<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Extension\ListCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtensionListCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new ListCommand();
        $this->command->setApplication($this->application);
    }

    public function testExtensionListCommandName()
    {
        $this->assertEquals('extension:list', $this->command->getName());
    }

    public function testExtensionListCommandDescription()
    {
        $this->assertEquals('List installed extensions', $this->command->getDescription());
    }

    public function testExtensionListCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('type'));

        $typeArg = $definition->getArgument('type');
        $this->assertFalse($typeArg->isRequired());
    }

    public function testExtensionListCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
        $this->assertEquals('f', $formatOption->getShortcut());
    }

    public function testExtensionListCommandWithoutOpenCart()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionListCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput(['--opencart-root' => '/nonexistent/path']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testExtensionListCommandWithType()
    {
        $input = new ArrayInput(['type' => 'module']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionListCommandJsonFormat()
    {
        $input = new ArrayInput(['--format' => 'json']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testExtensionListCommandYamlFormat()
    {
        $input = new ArrayInput(['--format' => 'yaml']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testExtensionListCommandWithTypeAndFormat()
    {
        $input = new ArrayInput([
            'type' => 'payment',
            '--format' => 'json'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test options parsing
        $this->assertEquals(1, $result);
    }

    public function testExtensionListCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testExtensionListCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('format', $options);
        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('type', $arguments);
    }

    public function testExtensionListCommandDefaultValues()
    {
        $definition = $this->command->getDefinition();

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());

        $typeArg = $definition->getArgument('type');
        $this->assertNull($typeArg->getDefault());
    }

    public function testExtensionListCommandFormatValidation()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertEquals('f', $definition->getOption('format')->getShortcut());
    }

    public function testExtensionListCommandAllOptions()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        $optionNames = array_keys($options);
        $this->assertContains('format', $optionNames);
        $this->assertContains('opencart-root', $optionNames);

        // Check that we have basic required options
        $this->assertGreaterThan(1, count($optionNames));
    }
}
