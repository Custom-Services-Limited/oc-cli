<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Product\CreateCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ProductCreateCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new CreateCommand();
        $this->command->setApplication($this->application);
    }

    public function testProductCreateCommandName()
    {
        $this->assertEquals('product:create', $this->command->getName());
    }

    public function testProductCreateCommandDescription()
    {
        $this->assertEquals('Create a new product', $this->command->getDescription());
    }

    public function testProductCreateCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('name'));
        $this->assertTrue($definition->hasArgument('model'));
        $this->assertTrue($definition->hasArgument('price'));

        $nameArg = $definition->getArgument('name');
        $this->assertFalse($nameArg->isRequired());
        $this->assertEquals('Product name', $nameArg->getDescription());

        $modelArg = $definition->getArgument('model');
        $this->assertFalse($modelArg->isRequired());
        $this->assertEquals('Product model/SKU', $modelArg->getDescription());

        $priceArg = $definition->getArgument('price');
        $this->assertFalse($priceArg->isRequired());
        $this->assertEquals('Product price', $priceArg->getDescription());
    }

    public function testProductCreateCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('description'));
        $this->assertTrue($definition->hasOption('category'));
        $this->assertTrue($definition->hasOption('quantity'));
        $this->assertTrue($definition->hasOption('status'));
        $this->assertTrue($definition->hasOption('weight'));
        $this->assertTrue($definition->hasOption('sku'));
        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('interactive'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $descriptionOption = $definition->getOption('description');
        $this->assertEquals('d', $descriptionOption->getShortcut());
        $this->assertEquals('Product description', $descriptionOption->getDescription());

        $categoryOption = $definition->getOption('category');
        $this->assertEquals('c', $categoryOption->getShortcut());
        $this->assertEquals('Category name or ID', $categoryOption->getDescription());

        $quantityOption = $definition->getOption('quantity');
        $this->assertNull($quantityOption->getShortcut());
        $this->assertEquals(0, $quantityOption->getDefault());

        $statusOption = $definition->getOption('status');
        $this->assertEquals('s', $statusOption->getShortcut());
        $this->assertEquals('enabled', $statusOption->getDefault());

        $weightOption = $definition->getOption('weight');
        $this->assertEquals('w', $weightOption->getShortcut());
        $this->assertEquals(0, $weightOption->getDefault());

        $formatOption = $definition->getOption('format');
        $this->assertEquals('f', $formatOption->getShortcut());
        $this->assertEquals('table', $formatOption->getDefault());

        $interactiveOption = $definition->getOption('interactive');
        $this->assertEquals('i', $interactiveOption->getShortcut());
        $this->assertFalse($interactiveOption->getDefault());
    }

    public function testProductCreateCommandWithoutOpenCart()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testProductCreateCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput(['--opencart-root' => '/nonexistent/path']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testProductCreateCommandWithDatabaseOptions()
    {
        $input = new ArrayInput([
            '--db-host' => 'localhost',
            '--db-user' => 'test',
            '--db-pass' => 'test',
            '--db-name' => 'test'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to connection, but validates options parsing
        $this->assertEquals(1, $result);
        $this->assertStringContainsString('Could not connect to database', $output->fetch());
    }

    public function testProductCreateCommandWithAllArguments()
    {
        $input = new ArrayInput([
            'name' => 'Test Product',
            'model' => 'TEST001',
            'price' => '99.99'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandWithOptions()
    {
        $input = new ArrayInput([
            'name' => 'Test Product',
            'model' => 'TEST001',
            'price' => '99.99',
            '--description' => 'Test description',
            '--category' => 'Electronics',
            '--quantity' => '10',
            '--status' => 'disabled',
            '--weight' => '1.5',
            '--sku' => 'TEST-SKU-001'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test options parsing
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandJsonFormat()
    {
        $input = new ArrayInput([
            'name' => 'Test Product',
            'model' => 'TEST001',
            'price' => '99.99',
            '--format' => 'json'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandYamlFormat()
    {
        $input = new ArrayInput([
            'name' => 'Test Product',
            'model' => 'TEST001',
            'price' => '99.99',
            '--format' => 'yaml'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test format option
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandInteractiveMode()
    {
        $input = new ArrayInput([
            '--interactive'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test interactive option
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandWithCategoryById()
    {
        $input = new ArrayInput([
            'name' => 'Test Product',
            'model' => 'TEST001',
            'price' => '99.99',
            '--category' => '20'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test category by ID
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandWithCategoryByName()
    {
        $input = new ArrayInput([
            'name' => 'Test Product',
            'model' => 'TEST001',
            'price' => '99.99',
            '--category' => 'Electronics'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test category by name
        $this->assertEquals(1, $result);
    }

    public function testProductCreateCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testProductCreateCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('description', $options);
        $this->assertArrayHasKey('category', $options);
        $this->assertArrayHasKey('quantity', $options);
        $this->assertArrayHasKey('status', $options);
        $this->assertArrayHasKey('weight', $options);
        $this->assertArrayHasKey('sku', $options);
        $this->assertArrayHasKey('format', $options);
        $this->assertArrayHasKey('interactive', $options);
        $this->assertArrayHasKey('opencart-root', $options);

        $this->assertArrayHasKey('name', $arguments);
        $this->assertArrayHasKey('model', $arguments);
        $this->assertArrayHasKey('price', $arguments);
    }

    public function testProductCreateCommandDefaultValues()
    {
        $definition = $this->command->getDefinition();

        $quantityOption = $definition->getOption('quantity');
        $this->assertEquals(0, $quantityOption->getDefault());

        $statusOption = $definition->getOption('status');
        $this->assertEquals('enabled', $statusOption->getDefault());

        $weightOption = $definition->getOption('weight');
        $this->assertEquals(0, $weightOption->getDefault());

        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());

        $nameArg = $definition->getArgument('name');
        $this->assertNull($nameArg->getDefault());

        $modelArg = $definition->getArgument('model');
        $this->assertNull($modelArg->getDefault());

        $priceArg = $definition->getArgument('price');
        $this->assertNull($priceArg->getDefault());
    }

    public function testProductCreateCommandOptionShortcuts()
    {
        $definition = $this->command->getDefinition();

        $this->assertEquals('d', $definition->getOption('description')->getShortcut());
        $this->assertEquals('c', $definition->getOption('category')->getShortcut());
        $this->assertNull($definition->getOption('quantity')->getShortcut());
        $this->assertEquals('s', $definition->getOption('status')->getShortcut());
        $this->assertEquals('w', $definition->getOption('weight')->getShortcut());
        $this->assertEquals('f', $definition->getOption('format')->getShortcut());
        $this->assertEquals('i', $definition->getOption('interactive')->getShortcut());
        $this->assertNull($definition->getOption('sku')->getShortcut());
    }

    public function testProductCreateCommandOptionDescriptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertEquals(
            'Product description',
            $definition->getOption('description')->getDescription()
        );
        $this->assertEquals(
            'Category name or ID',
            $definition->getOption('category')->getDescription()
        );
        $this->assertEquals(
            'Product quantity',
            $definition->getOption('quantity')->getDescription()
        );
        $this->assertEquals(
            'Product status (enabled|disabled)',
            $definition->getOption('status')->getDescription()
        );
        $this->assertEquals(
            'Product weight',
            $definition->getOption('weight')->getDescription()
        );
        $this->assertEquals(
            'Product SKU',
            $definition->getOption('sku')->getDescription()
        );
        $this->assertEquals(
            'Output format (table, json, yaml)',
            $definition->getOption('format')->getDescription()
        );
        $this->assertEquals(
            'Interactive mode - prompt for missing values',
            $definition->getOption('interactive')->getDescription()
        );
    }

    public function testProductCreateCommandAllOptions()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();

        $optionNames = array_keys($options);
        $this->assertContains('description', $optionNames);
        $this->assertContains('category', $optionNames);
        $this->assertContains('quantity', $optionNames);
        $this->assertContains('status', $optionNames);
        $this->assertContains('weight', $optionNames);
        $this->assertContains('sku', $optionNames);
        $this->assertContains('format', $optionNames);
        $this->assertContains('interactive', $optionNames);
        $this->assertContains('opencart-root', $optionNames);

        // Should have database connection options from parent
        $this->assertContains('db-host', $optionNames);
        $this->assertContains('db-user', $optionNames);
        $this->assertContains('db-pass', $optionNames);
        $this->assertContains('db-name', $optionNames);
        $this->assertContains('db-port', $optionNames);
        $this->assertContains('db-prefix', $optionNames);

        $this->assertGreaterThan(10, count($optionNames));
    }
}
