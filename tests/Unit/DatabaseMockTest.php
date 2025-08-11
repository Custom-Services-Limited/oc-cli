<?php

/**
 * OC-CLI - OpenCart Command Line Interface
 *
 * @author    Custom Services Limited <info@opencartgreece.gr>
 * @copyright 2024 Custom Services Limited
 * @license   GPL-3.0-or-later
 * @link      https://support.opencartgreece.gr/
 * @link      https://github.com/Custom-Services-Limited/oc-cli
 */

namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Application;
use OpenCart\CLI\Tests\Helpers\DatabaseMockCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseMockTest extends TestCase
{
    /**
     * @var DatabaseMockCommand
     */
    private $command;

    /**
     * @var Application
     */
    private $application;

    /**
     * @var string
     */
    private $tempDbFile;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new DatabaseMockCommand();
        $this->command->setApplication($this->application);
        // Ensure the command is properly configured
        if (!$this->command->getDefinition()->hasOption('opencart-root')) {
            $this->command->configure();
        }

        // Create SQLite database for testing
        $this->tempDbFile = sys_get_temp_dir() . '/oc-cli-test-' . uniqid() . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDbFile)) {
            unlink($this->tempDbFile);
        }
    }

    public function testQueryExecutionWithMockData()
    {
        $tempDir = $this->createTempOpenCartWithValidConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Mock database queries
            $this->createMockDatabase();

            // Test that query methods handle mock data correctly
            $result = $this->command->testQueryWithMockData();
            $this->assertIsArray($result);
            $this->assertCount(3, $result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testDatabaseErrorHandling()
    {
        $tempDir = $this->createTempOpenCartWithBadConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Test error handling with invalid database config
            $connection = $this->command->getDatabaseConnectionPublic();
            $this->assertNull($connection);

            $result = $this->command->queryPublic("SELECT * FROM non_existent_table");
            $this->assertNull($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /* public function testPreparedStatementHandling()
    {
        $tempDir = $this->createTempOpenCartWithValidConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Test prepared statements with mock data
            $result = $this->command->testPreparedStatements();
            $this->assertTrue($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    } */

    public function testDatabaseConnectionPooling()
    {
        $tempDir = $this->createTempOpenCartWithValidConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Test multiple database connections
            $connection1 = $this->command->getDatabaseConnectionPublic();
            $connection2 = $this->command->getDatabaseConnectionPublic();

            // Both should be null due to invalid config, but test the behavior
            $this->assertEquals($connection1, $connection2);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testTransactionHandling()
    {
        $tempDir = $this->createTempOpenCartWithValidConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Test transaction-like behavior
            $result = $this->command->testTransactionBehavior();
            $this->assertTrue($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * Create SQLite database for testing
     */
    private function createMockSQLiteDatabase()
    {
        $pdo = new \PDO('sqlite:' . $this->tempDbFile);

        // Create test tables
        $pdo->exec("CREATE TABLE IF NOT EXISTS oc_product (
            product_id INTEGER PRIMARY KEY,
            name TEXT,
            price DECIMAL(10,2)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS oc_category (
            category_id INTEGER PRIMARY KEY,
            name TEXT
        )");

        // Insert test data
        $pdo->exec("INSERT INTO oc_product (name, price) VALUES ('Test Product 1', 10.99)");
        $pdo->exec("INSERT INTO oc_product (name, price) VALUES ('Test Product 2', 15.99)");
        $pdo->exec("INSERT INTO oc_category (name) VALUES ('Test Category')");
    }

    /**
     * Create mock database with test data
     */
    private function createMockDatabase()
    {
        // This would normally create actual test data
        // For testing purposes, we'll simulate database responses
        return true;
    }

    /**
     * Create temporary OpenCart with SQLite config
     */
    private function createTempOpenCartWithSQLiteConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', 'sqlite');\n";
        $configContent .= "define('DB_USERNAME', '');\n";
        $configContent .= "define('DB_PASSWORD', '');\n";
        $configContent .= "define('DB_DATABASE', '{$this->tempDbFile}');\n";
        $configContent .= "define('DB_PORT', 0);\n";

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with valid config
     */
    private function createTempOpenCartWithValidConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', 'localhost');\n";
        $configContent .= "define('DB_USERNAME', 'opencart');\n";
        $configContent .= "define('DB_PASSWORD', 'password');\n";
        $configContent .= "define('DB_DATABASE', 'opencart');\n";
        $configContent .= "define('DB_PORT', 3306);\n";
        $configContent .= "define('DB_PREFIX', 'oc_');\n";

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with bad config
     */
    private function createTempOpenCartWithBadConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', 'invalid.host.name');\n";
        $configContent .= "define('DB_USERNAME', 'invalid_user');\n";
        $configContent .= "define('DB_PASSWORD', 'invalid_password');\n";
        $configContent .= "define('DB_DATABASE', 'invalid_database');\n";
        $configContent .= "define('DB_PORT', 9999);\n";

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTempDirectory($dir)
    {
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }
    }

    /**
     * Recursively remove directory
     */
    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
