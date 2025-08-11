<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Database\BackupCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class BackupCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new BackupCommand();
        $this->command->setApplication($this->application);
    }

    public function testBackupCommandName()
    {
        $this->assertEquals('db:backup', $this->command->getName());
    }

    public function testBackupCommandDescription()
    {
        $this->assertEquals('Create a database backup', $this->command->getDescription());
    }

    public function testBackupCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('filename'));

        $filenameArg = $definition->getArgument('filename');
        $this->assertFalse($filenameArg->isRequired());
    }

    public function testBackupCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('compress'));
        $this->assertTrue($definition->hasOption('tables'));
        $this->assertTrue($definition->hasOption('output-dir'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $compressOption = $definition->getOption('compress');
        $this->assertEquals('c', $compressOption->getShortcut());

        $tablesOption = $definition->getOption('tables');
        $this->assertEquals('t', $tablesOption->getShortcut());

        $outputDirOption = $definition->getOption('output-dir');
        $this->assertEquals('o', $outputDirOption->getShortcut());
        $this->assertEquals(getcwd(), $outputDirOption->getDefault());
    }

    public function testBackupCommandWithoutOpenCart()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testBackupCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput(['--opencart-root' => '/nonexistent/path']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testBackupCommandWithFilename()
    {
        $input = new ArrayInput(['filename' => 'test-backup.sql']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test argument parsing
        $this->assertEquals(1, $result);
    }

    public function testBackupCommandWithCompressOption()
    {
        $input = new ArrayInput(['--compress' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test option parsing
        $this->assertEquals(1, $result);
    }

    public function testBackupCommandWithTablesOption()
    {
        $input = new ArrayInput(['--tables' => 'oc_product,oc_category']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test option parsing
        $this->assertEquals(1, $result);
    }

    public function testBackupCommandWithOutputDirOption()
    {
        $input = new ArrayInput(['--output-dir' => '/tmp']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test option parsing
        $this->assertEquals(1, $result);
    }

    public function testBackupCommandAllOptionsAndArguments()
    {
        $input = new ArrayInput([
            'filename' => 'custom-backup.sql',
            '--compress' => true,
            '--tables' => 'oc_product',
            '--output-dir' => '/tmp'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test all options parsing
        $this->assertEquals(1, $result);
    }

    public function testBackupCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testBackupCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('compress', $options);
        $this->assertArrayHasKey('tables', $options);
        $this->assertArrayHasKey('output-dir', $options);
        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('filename', $arguments);
    }

    public function testBackupCommandDefaultValues()
    {
        $definition = $this->command->getDefinition();

        $outputDirOption = $definition->getOption('output-dir');
        $this->assertEquals(getcwd(), $outputDirOption->getDefault());

        $filenameArg = $definition->getArgument('filename');
        $this->assertNull($filenameArg->getDefault());
    }
}
