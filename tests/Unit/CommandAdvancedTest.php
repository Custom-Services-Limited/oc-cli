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
use OpenCart\CLI\Command;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandAdvancedTest extends TestCase
{
    /**
     * @var AdvancedTestableCommand
     */
    private $command;

    /**
     * @var Application
     */
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new AdvancedTestableCommand();
        $this->command->setApplication($this->application);
    }

    public function testQueryWithParametersAndPreparedStatementFailure()
    {
        $tempDir = $this->createTempDirectoryWithInvalidDatabase();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // This should return null because database connection will fail
            $result = $this->command->queryPublic("SELECT * FROM test WHERE id = ?", [1]);
            $this->assertNull($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testQueryWithEmptyParametersArray()
    {
        $tempDir = $this->createTempDirectoryWithInvalidDatabase();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Test with empty params array (should use direct query path)
            $result = $this->command->queryPublic("SELECT 1", []);
            $this->assertNull($result); // Will be null due to invalid DB config
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testGetOpenCartConfigWithMissingConstants()
    {
        $tempDir = $this->createTempDirectoryWithPartialConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            $config = $this->command->getOpenCartConfigPublic();

            // Should return array with null values for missing constants
            $this->assertIsArray($config);
            $this->assertNull($config['db_username']);
            $this->assertNull($config['db_password']);
            $this->assertEquals(3306, $config['db_port']); // Default value
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testGetDatabaseConnectionWithValidConfigButConnectionError()
    {
        $tempDir = $this->createTempDirectoryWithBadDbConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Should return null due to connection error
            $connection = $this->command->getDatabaseConnectionPublic();
            $this->assertNull($connection);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testFormatBytesWithExtremeValues()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        // Test with 1024 exactly (edge case)
        $this->assertEquals('1 KB', $this->command->formatBytesPublic(1024));

        // Test with very large numbers
        $this->assertEquals('1 TB', $this->command->formatBytesPublic(1099511627776)); // 1024^4

        // Test with decimal precision edge cases
        $this->assertEquals('1023 B', $this->command->formatBytesPublic(1023));
        $this->assertEquals('1 KB', $this->command->formatBytesPublic(1025, 0));

        // Test negative numbers (edge case)
        $this->assertEquals('0 B', $this->command->formatBytesPublic(-100));
    }

    public function testExecuteMethodWithDifferentInputOutput()
    {
        $input = new ArrayInput(['--help' => true]);
        $output = new BufferedOutput();

        $result = $this->command->executePublic($input, $output);

        // Should return 0 (success) and set internal properties
        $this->assertEquals(0, $result);
        $this->assertSame($input, $this->command->getInputPublic());
        $this->assertSame($output, $this->command->getOutputPublic());
    }

    public function testRequireOpenCartWithErrorMessage()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        // Test that it returns false and outputs error message
        $result = $this->command->requireOpenCartPublic(true);
        $this->assertFalse($result);

        // Check that an error message was written to output
        $outputContent = $output->fetch();
        $this->assertStringContainsString('This command must be run from an OpenCart installation directory', $outputContent);
    }

    public function testGetOpenCartConfigWithFilePermissionIssues()
    {
        $tempDir = $this->createTempDirectoryWithUnreadableConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            // Should handle file permission issues gracefully
            $config = $this->command->getOpenCartConfigPublic();

            // May return null or throw exception depending on system
            $this->assertTrue($config === null || is_array($config));
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * Create temporary directory with invalid database configuration
     */
    private function createTempDirectoryWithInvalidDatabase()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', 'invalid_host_that_does_not_exist');\n";
        $configContent .= "define('DB_USERNAME', 'invalid_user');\n";
        $configContent .= "define('DB_PASSWORD', 'invalid_pass');\n";
        $configContent .= "define('DB_DATABASE', 'invalid_db');\n";
        $configContent .= "define('DB_PORT', 3306);\n";

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create temporary directory with partial config (missing some constants)
     */
    private function createTempDirectoryWithPartialConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', 'localhost');\n";
        $configContent .= "define('DB_DATABASE', 'opencart');\n";
        // Missing DB_USERNAME, DB_PASSWORD, etc.

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create temporary directory with bad database config that will cause connection error
     */
    private function createTempDirectoryWithBadDbConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', '127.0.0.1');\n";
        $configContent .= "define('DB_USERNAME', 'nonexistent_user');\n";
        $configContent .= "define('DB_PASSWORD', 'wrong_password');\n";
        $configContent .= "define('DB_DATABASE', 'nonexistent_database');\n";
        $configContent .= "define('DB_PORT', 3306);\n";

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create temporary directory with unreadable config file
     */
    private function createTempDirectoryWithUnreadableConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        touch($tempDir . '/system/startup.php');

        $configContent = "<?php\n";
        $configContent .= "define('DB_HOSTNAME', 'localhost');\n";

        file_put_contents($tempDir . '/config.php', $configContent);

        // Make file unreadable (if possible on this system)
        chmod($tempDir . '/config.php', 0000);

        return $tempDir;
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTempDirectory($dir)
    {
        if (is_dir($dir)) {
            // Restore permissions before cleanup
            if (file_exists($dir . '/config.php')) {
                chmod($dir . '/config.php', 0644);
            }
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

/**
 * Advanced testable command for comprehensive testing
 */
class AdvancedTestableCommand extends Command
{
    protected function configure()
    {
        $this->setName('test:advanced-command');
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

    public function requireOpenCartPublic($require = true)
    {
        return $this->requireOpenCart($require);
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

    public function formatBytesPublic($bytes, $precision = 2)
    {
        return $this->formatBytes($bytes, $precision);
    }

    public function setOpenCartRootPublic($path)
    {
        $this->openCartRoot = $path;
    }

    public function getInputPublic()
    {
        return $this->input;
    }

    public function getOutputPublic()
    {
        return $this->output;
    }
}
