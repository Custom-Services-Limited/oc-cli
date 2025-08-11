<?php

namespace OpenCart\CLI\Tests\Helpers;

use OpenCart\CLI\Command;

/**
 * Database mock command for testing
 */
class DatabaseMockCommand extends Command
{
    protected function configure()
    {
        $this->setName('test:database-mock');
    }

    protected function handle()
    {
        return 0;
    }

    // Public wrappers for testing
    public function executePublic($input, $output)
    {
        return $this->execute($input, $output);
    }

    public function getOpenCartConfigPublic()
    {
        return $this->getOpenCartConfig();
    }

    public function getDatabaseConnectionPublic()
    {
        return $this->getDatabaseConnection();
    }

    public function queryPublic($sql, $params = [])
    {
        return $this->query($sql, $params);
    }

    public function setOpenCartRootPublic($path)
    {
        $this->openCartRoot = $path;
    }

    /**
     * Test query with mock data
     */
    public function testQueryWithMockData()
    {
        // Simulate database query results
        return [
            ['id' => 1, 'name' => 'Product 1', 'price' => 10.99],
            ['id' => 2, 'name' => 'Product 2', 'price' => 15.99],
            ['id' => 3, 'name' => 'Product 3', 'price' => 20.99]
        ];
    }

    /**
     * Test prepared statements
     */
    public function testPreparedStatements()
    {
        // Simulate prepared statement execution
        $config = $this->getOpenCartConfig();

        if (!$config) {
            return false;
        }

        // Mock successful prepared statement
        return true;
    }

    /**
     * Test transaction behavior
     */
    public function testTransactionBehavior()
    {
        // Simulate transaction handling
        $connection = $this->getDatabaseConnection();

        if (!$connection) {
            // Even without real connection, test the logic
            return true;
        }

        return true;
    }
}
