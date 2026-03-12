<?php

namespace OpenCart\CLI\Tests\Integration;

use OpenCart\CLI\Commands\Database\CheckCommand;
use OpenCart\CLI\Commands\Database\CleanupCommand;
use OpenCart\CLI\Commands\Database\OptimizeCommand;
use OpenCart\CLI\Commands\Database\RepairCommand;
use PHPUnit\Framework\TestCase;

class DatabaseMaintenanceCommandDefinitionsTest extends TestCase
{
    public function testCheckCommandDefinition()
    {
        $command = new CheckCommand();

        $this->assertSame('db:check', $command->getName());
        $this->assertSame('Check OpenCart database tables', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('tables'));
        $this->assertTrue($command->getDefinition()->hasOption('format'));
    }

    public function testRepairCommandDefinition()
    {
        $command = new RepairCommand();

        $this->assertSame('db:repair', $command->getName());
        $this->assertSame('Repair OpenCart database tables', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('tables'));
        $this->assertTrue($command->getDefinition()->hasOption('format'));
    }

    public function testOptimizeCommandDefinition()
    {
        $command = new OptimizeCommand();

        $this->assertSame('db:optimize', $command->getName());
        $this->assertSame('Optimize OpenCart database tables', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('tables'));
        $this->assertTrue($command->getDefinition()->hasOption('format'));
    }

    public function testCleanupCommandDefinition()
    {
        $command = new CleanupCommand();

        $this->assertSame('db:cleanup', $command->getName());
        $this->assertSame('Clean transient OpenCart database tables', $command->getDescription());
        $this->assertFalse($command->getDefinition()->hasArgument('tables'));
        $this->assertTrue($command->getDefinition()->hasOption('format'));
    }
}
