<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Product\ListCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ProductListCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new ListCommand();
        $this->command->setApplication($this->application);
    }

    public function testProductListCommandName()
    {
        $this->assertEquals('product:list', $this->command->getName());
    }

    public function testProductListCommandDescription()
    {
        $this->assertEquals('List products', $this->command->getDescription());
    }

    public function testProductListCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('category'));

        $categoryArg = $definition->getArgument('category');
        $this->assertFalse($categoryArg->isRequired());
        $this->assertEquals('Filter by category name or ID', $categoryArg->getDescription());
    }

    public function testProductListCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('status'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('search'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
        $this->assertEquals('f', $formatOption->getShortcut());

        $statusOption = $definition->getOption('status');
        $this->assertEquals('all', $statusOption->getDefault());
        $this->assertEquals('s', $statusOption->getShortcut());

        $limitOption = $definition->getOption('limit');
        $this->assertEquals(50, $limitOption->getDefault());
        $this->assertEquals('l', $limitOption->getShortcut());

        $searchOption = $definition->getOption('search');
        $this->assertNull($searchOption->getDefault());
    }

    public function testProductListCommandWithoutOpenCart()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testProductListCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput(['--opencart-root' => '/nonexistent/path']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testProductListCommandWithCategory()
    {
        $input = new ArrayInput(['category' => 'Electronics']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandWithNumericCategory()
    {
        $input = new ArrayInput(['category' => '123']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandJsonFormat()
    {
        $input = new ArrayInput(['--format' => 'json']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandYamlFormat()
    {
        $input = new ArrayInput(['--format' => 'yaml']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandWithStatusEnabled()
    {
        $input = new ArrayInput(['--status' => 'enabled']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandWithStatusDisabled()
    {
        $input = new ArrayInput(['--status' => 'disabled']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandWithLimit()
    {
        $input = new ArrayInput(['--limit' => '10']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandWithSearch()
    {
        $input = new ArrayInput(['--search' => 'iPhone']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandWithAllOptions()
    {
        $input = new ArrayInput([
            'category' => 'Electronics',
            '--format' => 'json',
            '--status' => 'enabled',
            '--limit' => '25',
            '--search' => 'phone'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testProductListCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testProductListCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('format', $options);
        $this->assertArrayHasKey('status', $options);
        $this->assertArrayHasKey('limit', $options);
        $this->assertArrayHasKey('search', $options);
        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('category', $arguments);
    }

    public function testProductListCommandDefaultValues()
    {
        $definition = $this->command->getDefinition();

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());

        $statusOption = $definition->getOption('status');
        $this->assertEquals('all', $statusOption->getDefault());

        $limitOption = $definition->getOption('limit');
        $this->assertEquals(50, $limitOption->getDefault());

        $categoryArg = $definition->getArgument('category');
        $this->assertNull($categoryArg->getDefault());

        $searchOption = $definition->getOption('search');
        $this->assertNull($searchOption->getDefault());
    }

    public function testProductListCommandOptionShortcuts()
    {
        $definition = $this->command->getDefinition();

        $this->assertEquals('f', $definition->getOption('format')->getShortcut());
        $this->assertEquals('s', $definition->getOption('status')->getShortcut());
        $this->assertEquals('l', $definition->getOption('limit')->getShortcut());
        $this->assertNull($definition->getOption('search')->getShortcut());
    }

    public function testProductListCommandFormatValidation()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertEquals('f', $definition->getOption('format')->getShortcut());
        $this->assertEquals('Output format (table, json, yaml)', $definition->getOption('format')->getDescription());
    }

    public function testProductListCommandStatusValidation()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('status'));
        $this->assertEquals('s', $definition->getOption('status')->getShortcut());
        $this->assertEquals(
            'Filter by status (enabled, disabled, all)',
            $definition->getOption('status')->getDescription()
        );
    }

    public function testProductListCommandAllOptions()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        $optionNames = array_keys($options);
        $this->assertContains('format', $optionNames);
        $this->assertContains('status', $optionNames);
        $this->assertContains('limit', $optionNames);
        $this->assertContains('search', $optionNames);
        $this->assertContains('opencart-root', $optionNames);

        $this->assertGreaterThan(4, count($optionNames));
    }
}
