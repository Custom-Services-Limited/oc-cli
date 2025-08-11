<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Extension\ModificationListCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ModificationListCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new ModificationListCommand();
        $this->command->setApplication($this->application);
    }

    public function testModificationListCommandName()
    {
        $this->assertEquals('modification:list', $this->command->getName());
    }

    public function testModificationListCommandDescription()
    {
        $this->assertEquals('List installed modifications', $this->command->getDescription());
    }

    public function testModificationListCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
        $this->assertEquals('f', $formatOption->getShortcut());
    }

    public function testModificationListCommandWithoutOpenCart()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testModificationListCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput(['--opencart-root' => '/nonexistent/path']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testModificationListCommandJsonFormat()
    {
        $input = new ArrayInput(['--format' => 'json']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testModificationListCommandYamlFormat()
    {
        $input = new ArrayInput(['--format' => 'yaml']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testModificationListCommandTableFormat()
    {
        $input = new ArrayInput(['--format' => 'table']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testModificationListCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testModificationListCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        $this->assertArrayHasKey('format', $options);
        $this->assertArrayHasKey('opencart-root', $options);
    }

    public function testModificationListCommandDefaultValues()
    {
        $definition = $this->command->getDefinition();

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testModificationListCommandFormatValidation()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertEquals('f', $definition->getOption('format')->getShortcut());
    }

    public function testModificationListCommandAllOptions()
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
