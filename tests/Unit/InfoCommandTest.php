<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Database\InfoCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class InfoCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new InfoCommand();
        $this->command->setApplication($this->application);
    }

    public function testInfoCommandName()
    {
        $this->assertEquals('db:info', $this->command->getName());
    }

    public function testInfoCommandDescription()
    {
        $this->assertEquals('Display database connection information', $this->command->getDescription());
    }

    public function testInfoCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testInfoCommandWithoutOpenCart()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testInfoCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput(['--opencart-root' => '/nonexistent/path']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testInfoCommandJsonFormat()
    {
        $input = new ArrayInput(['--format' => 'json']);
        $output = new BufferedOutput();

        // This will fail due to no OpenCart but we can test format option
        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testInfoCommandYamlFormat()
    {
        $input = new ArrayInput(['--format' => 'yaml']);
        $output = new BufferedOutput();

        // This will fail due to no OpenCart but we can test format option
        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testInfoCommandTableFormat()
    {
        $input = new ArrayInput(['--format' => 'table']);
        $output = new BufferedOutput();

        // This will fail due to no OpenCart but we can test format option
        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testInfoCommandDefaultFormat()
    {
        $definition = $this->command->getDefinition();
        $formatOption = $definition->getOption('format');

        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testInfoCommandFormatValidation()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertEquals('f', $definition->getOption('format')->getShortcut());
    }

    public function testInfoCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testInfoCommandAllOptions()
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
