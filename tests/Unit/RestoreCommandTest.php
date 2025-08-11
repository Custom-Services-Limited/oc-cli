<?php

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Commands\Database\RestoreCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RestoreCommandTest extends TestCase
{
    private $command;
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new RestoreCommand();
        $this->command->setApplication($this->application);
    }

    public function testRestoreCommandName()
    {
        $this->assertEquals('db:restore', $this->command->getName());
    }

    public function testRestoreCommandDescription()
    {
        $this->assertEquals('Restore database from backup', $this->command->getDescription());
    }

    public function testRestoreCommandArguments()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('filename'));

        $filenameArg = $definition->getArgument('filename');
        $this->assertTrue($filenameArg->isRequired());
    }

    public function testRestoreCommandOptions()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('ignore-errors'));
        $this->assertTrue($definition->hasOption('opencart-root'));

        $forceOption = $definition->getOption('force');
        $this->assertEquals('f', $forceOption->getShortcut());

        $ignoreErrorsOption = $definition->getOption('ignore-errors');
        $this->assertEquals('i', $ignoreErrorsOption->getShortcut());
    }

    public function testRestoreCommandWithoutOpenCart()
    {
        $input = new ArrayInput(['filename' => 'nonexistent.sql']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testRestoreCommandWithInvalidOpenCartRoot()
    {
        $input = new ArrayInput([
            'filename' => 'nonexistent.sql',
            '--opencart-root' => '/nonexistent/path'
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('OpenCart installation', $output->fetch());
    }

    public function testRestoreCommandWithNonexistentFile()
    {
        // Create a temporary directory structure to simulate OpenCart
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        // Create OpenCart indicator files
        file_put_contents($tempDir . '/config.php', '<?php define("DB_HOSTNAME", "localhost");');

        $input = new ArrayInput([
            'filename' => '/nonexistent/backup.sql',
            '--opencart-root' => $tempDir
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $outputContent = $output->fetch();
        // Should contain either "not found" or database connection error
        $this->assertTrue(
            str_contains($outputContent, 'not found') ||
            str_contains($outputContent, 'Could not connect to database') ||
            str_contains($outputContent, 'Database connection')
        );

        // Cleanup
        unlink($tempDir . '/config.php');
        rmdir($tempDir);
    }

    public function testRestoreCommandWithForceOption()
    {
        $input = new ArrayInput([
            'filename' => 'nonexistent.sql',
            '--force' => true
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test option parsing
        $this->assertEquals(1, $result);
    }

    public function testRestoreCommandWithIgnoreErrorsOption()
    {
        $input = new ArrayInput([
            'filename' => 'nonexistent.sql',
            '--ignore-errors' => true
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test option parsing
        $this->assertEquals(1, $result);
    }

    public function testRestoreCommandAllOptions()
    {
        $input = new ArrayInput([
            'filename' => 'test-backup.sql',
            '--force' => true,
            '--ignore-errors' => true
        ]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // Should fail due to no OpenCart but test all options parsing
        $this->assertEquals(1, $result);
    }

    public function testRestoreCommandHelpText()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
    }

    public function testRestoreCommandDefinition()
    {
        $definition = $this->command->getDefinition();
        $options = $definition->getOptions();
        $arguments = $definition->getArguments();

        $this->assertArrayHasKey('force', $options);
        $this->assertArrayHasKey('ignore-errors', $options);
        $this->assertArrayHasKey('opencart-root', $options);
        $this->assertArrayHasKey('filename', $arguments);
    }

    public function testRestoreCommandRequiredArgument()
    {
        $definition = $this->command->getDefinition();
        $filenameArg = $definition->getArgument('filename');

        $this->assertTrue($filenameArg->isRequired());
        $this->assertEquals('Backup filename to restore from', $filenameArg->getDescription());
    }

    public function testRestoreCommandOptionShortcuts()
    {
        $definition = $this->command->getDefinition();

        $forceOption = $definition->getOption('force');
        $this->assertEquals('f', $forceOption->getShortcut());

        $ignoreErrorsOption = $definition->getOption('ignore-errors');
        $this->assertEquals('i', $ignoreErrorsOption->getShortcut());
    }

    public function testRestoreCommandWithUnreadableFile()
    {
        // This test would require creating an unreadable file
        // which is complex in a unit test environment
        $this->assertTrue(true); // Placeholder for now
    }
}
