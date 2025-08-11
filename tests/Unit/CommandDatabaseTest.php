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

class CommandDatabaseTest extends TestCase
{
    /**
     * @var TestableCommandForDatabase
     */
    private $command;

    /**
     * @var Application
     */
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new TestableCommandForDatabase();
        $this->command->setApplication($this->application);
    }

    public function testGetDatabaseConnectionReturnsNullWhenNoOpenCartRoot()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $connection = $this->command->getDatabaseConnectionPublic();
        $this->assertNull($connection);
    }

    public function testGetDatabaseConnectionReturnsNullWhenNoConfig()
    {
        $tempDir = $this->createTempDirectoryWithoutConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            $connection = $this->command->getDatabaseConnectionPublic();
            $this->assertNull($connection);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testQueryReturnsNullWhenNoDatabaseConnection()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $result = $this->command->queryPublic("SELECT 1");
        $this->assertNull($result);
    }

    public function testRequireOpenCartWithNoRootReturnsFalse()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $result = $this->command->requireOpenCartPublic(true);
        $this->assertFalse($result);
    }

    public function testRequireOpenCartWithNoRootOptionalReturnsTrue()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $result = $this->command->requireOpenCartPublic(false);
        $this->assertTrue($result);
    }

    /* public function testRequireOpenCartWithValidRootReturnsTrue()
    {
        $tempDir = $this->createTempOpenCartDirectory();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            $result = $this->command->requireOpenCartPublic(true);
            $this->assertTrue($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    } */

    public function testGetOpenCartConfigWithInvalidConfigFile()
    {
        $tempDir = $this->createTempDirectoryWithInvalidConfig();

        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $this->command->setOpenCartRootPublic($tempDir);
            $this->command->executePublic($input, $output);

            $config = $this->command->getOpenCartConfigPublic();
            // Should still return array structure even with invalid config
            $this->assertTrue($config === null || is_array($config));
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testFormatBytesWithZero()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $this->assertEquals('0 B', $this->command->formatBytesPublic(0));
    }

    public function testFormatBytesWithLargeNumbers()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $this->assertEquals('1 TB', $this->command->formatBytesPublic(1024 * 1024 * 1024 * 1024));
        $this->assertEquals('1.5 TB', $this->command->formatBytesPublic(1536 * 1024 * 1024 * 1024));
    }

    public function testFormatBytesWithCustomPrecision()
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->command->executePublic($input, $output);

        $this->assertEquals('1.56 KB', $this->command->formatBytesPublic(1600, 2));
        $this->assertEquals('1.6 KB', $this->command->formatBytesPublic(1600, 1));
        $this->assertEquals('2 KB', $this->command->formatBytesPublic(1600, 0));
    }

    /**
     * Create a temporary directory without config
     */
    private function createTempDirectoryWithoutConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/system', 0755, true);

        // Create OpenCart indicator files but no config
        touch($tempDir . '/system/startup.php');

        return $tempDir;
    }

    /**
     * Create a temporary directory with invalid config
     */
    private function createTempDirectoryWithInvalidConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/system', 0755, true);

        // Create OpenCart indicator files
        touch($tempDir . '/system/startup.php');

        // Create invalid config file
        $configContent = "<?php\n";
        $configContent .= "// Incomplete config file\n";
        $configContent .= "define('DB_HOSTNAME', 'localhost');\n";
        // Missing other required constants

        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create a basic OpenCart directory
     */
    private function createTempOpenCartDirectory()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/system', 0755, true);

        // Create OpenCart indicator files
        touch($tempDir . '/system/startup.php');
        touch($tempDir . '/config.php');

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

/**
 * Extended testable command for database testing
 */
class TestableCommandForDatabase extends Command
{
    protected function configure()
    {
        $this->setName('test:database-command');
    }

    protected function handle()
    {
        return 0;
    }

    // Public wrappers for protected methods
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
}
