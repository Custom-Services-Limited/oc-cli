<?php

namespace OpenCart\CLI\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Application;

class DatabaseCommandsTest extends TestCase
{
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function testDatabaseCommandsAreRegistered()
    {
        $commands = $this->application->all();

        $this->assertArrayHasKey('db:info', $commands);
        $this->assertArrayHasKey('db:backup', $commands);
        $this->assertArrayHasKey('db:restore', $commands);
    }

    public function testDatabaseCommandsHaveCorrectNames()
    {
        $dbInfo = $this->application->find('db:info');
        $dbBackup = $this->application->find('db:backup');
        $dbRestore = $this->application->find('db:restore');

        $this->assertEquals('db:info', $dbInfo->getName());
        $this->assertEquals('db:backup', $dbBackup->getName());
        $this->assertEquals('db:restore', $dbRestore->getName());
    }

    public function testDatabaseCommandsHaveCorrectDescriptions()
    {
        $dbInfo = $this->application->find('db:info');
        $dbBackup = $this->application->find('db:backup');
        $dbRestore = $this->application->find('db:restore');

        $this->assertEquals('Display database connection information', $dbInfo->getDescription());
        $this->assertEquals('Create a database backup', $dbBackup->getDescription());
        $this->assertEquals('Restore database from backup', $dbRestore->getDescription());
    }

    public function testAllDatabaseCommandsHaveOpenCartRootOption()
    {
        $commands = ['db:info', 'db:backup', 'db:restore'];

        foreach ($commands as $commandName) {
            $command = $this->application->find($commandName);
            $definition = $command->getDefinition();

            $this->assertTrue(
                $definition->hasOption('opencart-root'),
                "Command {$commandName} should have --opencart-root option"
            );
        }
    }
}
