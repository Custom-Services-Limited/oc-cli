<?php

namespace OpenCart\CLI\Tests\Integration;

use OpenCart\CLI\Application;
use PHPUnit\Framework\TestCase;

class CommandExpansionDefinitionsTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function testExpandedCommandSetIsRegistered(): void
    {
        $expectedCommands = [
            'category:list',
            'category:create',
            'order:list',
            'order:view',
            'order:update-status',
            'cache:clear',
            'cache:rebuild',
            'product:update',
            'product:delete',
            'user:list',
            'user:create',
            'user:delete',
            'db:check',
            'db:repair',
            'db:optimize',
            'db:cleanup',
        ];

        $commands = $this->application->all();

        foreach ($expectedCommands as $name) {
            $this->assertArrayHasKey($name, $commands, "Missing command registration for {$name}");
        }
    }

    public function testExpandedCommandsHaveExpectedDescriptions(): void
    {
        $this->assertSame('Create a category', $this->application->find('category:create')->getDescription());
        $this->assertSame('List orders', $this->application->find('order:list')->getDescription());
        $this->assertSame('Clear OpenCart caches', $this->application->find('cache:clear')->getDescription());
        $this->assertSame('Update an existing product', $this->application->find('product:update')->getDescription());
        $this->assertSame('Create an admin user', $this->application->find('user:create')->getDescription());
        $this->assertSame('Check OpenCart database tables', $this->application->find('db:check')->getDescription());
    }
}
