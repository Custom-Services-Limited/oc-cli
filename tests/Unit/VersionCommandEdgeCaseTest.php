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
use OpenCart\CLI\Commands\Core\VersionCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Tester\CommandTester;

class VersionCommandEdgeCaseTest extends TestCase
{
    /**
     * @var VersionCommand
     */
    private $command;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var Application
     */
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new VersionCommand();
        $this->command->setApplication($this->application);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testVersionDetectionWithMalformedVersionFile()
    {
        $tempDir = $this->createTempOpenCartWithMalformedVersion();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("3.0.0.0\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testVersionDetectionWithMultipleVersionFormats()
    {
        // Test various version string formats that might exist in OpenCart files
        $versionFormats = [
            "define('VERSION', '3.0.3.8');",
            'define("VERSION", "4.0.1.1");',
        ];

        foreach ($versionFormats as $versionFormat) {
            $tempDir = $this->createTempOpenCartWithCustomVersion($versionFormat);

            $originalDir = getcwd();
            chdir($tempDir);

            try {
                $this->commandTester->execute(['--opencart' => true]);

                $this->assertEquals(0, $this->commandTester->getStatusCode());
                $output = $this->commandTester->getDisplay();

                // Extract expected version from the format using a more specific regex
                preg_match("/define\s*\(\s*['\"]VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $versionFormat, $matches);
                $expectedVersion = $matches[1];

                $this->assertEquals("{$expectedVersion}\n", $output);
            } finally {
                chdir($originalDir);
                $this->cleanupTempDirectory($tempDir);
            }
        }
    }

    public function testVersionDetectionFallbackToIndexFile()
    {
        // Test when system/startup.php doesn't have version but index.php does
        $tempDir = $this->createTempOpenCartWithVersionInIndex();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("2.0.0.0\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testVersionDetectionFallbackToConfigDirs()
    {
        // Test when version is found in config directories
        $tempDir = $this->createTempOpenCartWithVersionInConfigDir();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("1.5.6.4\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testVersionInfoWithAllEmptyVersions()
    {
        // Test when no version can be detected anywhere
        $tempDir = $this->createTempOpenCartWithoutVersion();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute([]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertStringContainsString('Not detected', $output);
            $this->assertStringContainsString('Opencart    Not detected', $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testDisplayTableWithVariousVersionStates()
    {
        // Test table display with edge case version info
        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should contain table headers
        $this->assertStringContainsString('Component', $output);
        $this->assertStringContainsString('Version', $output);

        // Should contain various components
        $this->assertStringContainsString('Oc-cli', $output);
        $this->assertStringContainsString('Php', $output);
        $this->assertStringContainsString('Os', $output);
        $this->assertStringContainsString('Opencart', $output);
    }

    public function testJsonFormatWithMissingOpenCart()
    {
        $this->commandTester->execute(['--format' => 'json']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertEquals('Not detected', $json['opencart']);
    }

    public function testYamlFormatWithMissingOpenCart()
    {
        $this->commandTester->execute(['--format' => 'yaml']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('opencart: Not detected', $output);
    }

    public function testInvalidFormatDefaultsToTable()
    {
        $this->commandTester->execute(['--format' => 'invalid-format']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should default to table format
        $this->assertStringContainsString('Version Information', $output);
        $this->assertStringContainsString('Component', $output);
    }

    public function testVersionCommandWithBothOptionsAndFormat()
    {
        $tempDir = $this->createTempOpenCartWithVersion('3.0.3.8');

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute([
                '--opencart' => true,
                '--format' => 'json'
            ]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            $json = json_decode($output, true);
            $this->assertIsArray($json);
            $this->assertArrayHasKey('opencart', $json);
            $this->assertEquals('3.0.3.8', $json['opencart']);
            $this->assertCount(1, $json); // Should only have opencart key
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * Create temporary OpenCart with malformed version file
     */
    private function createTempOpenCartWithMalformedVersion()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        // Create startup file with malformed version
        $content = "<?php\n";
        $content .= "// This file has no valid VERSION define\n";
        $content .= "define('SOME_OTHER_CONSTANT', 'value');\n";
        $content .= "// VERSION is commented out: define('VERSION', '3.0.0.0');\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with custom version format
     */
    private function createTempOpenCartWithCustomVersion($versionDefine)
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        $content = "<?php\n";
        $content .= "// OpenCart startup file\n";
        $content .= $versionDefine . "\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with version in index.php instead of startup.php
     */
    private function createTempOpenCartWithVersionInIndex()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        // Create startup file without version
        $startupContent = "<?php\n";
        $startupContent .= "// OpenCart startup file without version\n";
        file_put_contents($tempDir . '/system/startup.php', $startupContent);

        // Create index file with version
        $indexContent = "<?php\n";
        $indexContent .= "define('VERSION', '2.0.0.0');\n";
        $indexContent .= "// Rest of index file\n";
        file_put_contents($tempDir . '/index.php', $indexContent);

        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with version in config directory
     */
    private function createTempOpenCartWithVersionInConfigDir()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system/config', 0755, true);

        // Create startup file without version
        $startupContent = "<?php\n";
        $startupContent .= "// OpenCart startup file without version\n";
        file_put_contents($tempDir . '/system/startup.php', $startupContent);

        // Create config file with version
        $configContent = "<?php\n";
        $configContent .= "define('VERSION', '1.5.6.4');\n";
        $configContent .= "// Config file\n";
        file_put_contents($tempDir . '/system/config/catalog.php', $configContent);

        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart without any version information
     */
    private function createTempOpenCartWithoutVersion()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        // Create files without version information
        $startupContent = "<?php\n// OpenCart startup file without version\n";
        file_put_contents($tempDir . '/system/startup.php', $startupContent);

        $configContent = "<?php\n// Config file\n";
        file_put_contents($tempDir . '/config.php', $configContent);

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with specific version
     */
    private function createTempOpenCartWithVersion($version)
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        $content = "<?php\n";
        $content .= "define('VERSION', '$version');\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
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
