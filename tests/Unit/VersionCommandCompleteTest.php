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

class VersionCommandCompleteTest extends TestCase
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

    public function testVersionCommandAliases()
    {
        // Test that both 'core:version' and 'version' work
        $this->assertEquals('core:version', $this->command->getName());
        $this->assertContains('version', $this->command->getAliases());
    }

    public function testVersionCommandDescription()
    {
        $this->assertEquals('Display version information', $this->command->getDescription());
    }

    public function testVersionCommandOptions()
    {
        $definition = $this->command->getDefinition();

        // Test opencart-root option (inherited from base Command)
        $this->assertTrue($definition->hasOption('opencart-root'));
        $openCartRootOption = $definition->getOption('opencart-root');
        $this->assertTrue($openCartRootOption->isValueRequired());

        // Test opencart option
        $this->assertTrue($definition->hasOption('opencart'));
        $openCartOption = $definition->getOption('opencart');
        $this->assertEquals('o', $openCartOption->getShortcut());
        $this->assertFalse($openCartOption->isValueRequired());

        // Test format option
        $this->assertTrue($definition->hasOption('format'));
        $formatOption = $definition->getOption('format');
        $this->assertEquals('f', $formatOption->getShortcut());
        $this->assertTrue($formatOption->isValueRequired());
        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testGetVersionInfoStructure()
    {
        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should contain all expected components
        $this->assertStringContainsString('Oc-cli', $output);
        $this->assertStringContainsString('Php', $output);
        $this->assertStringContainsString('Os', $output);
        $this->assertStringContainsString('Opencart', $output);
    }

    public function testOpenCartVersionDetectionWithCorruptedFiles()
    {
        $tempDir = $this->createTempOpenCartWithCorruptedFiles();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("Not detected\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testVersionDetectionWithNestedConfigStructure()
    {
        $tempDir = $this->createTempOpenCartWithNestedConfig();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("2.2.0.0\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testVersionDetectionWithVersionInAdminConfig()
    {
        $tempDir = $this->createTempOpenCartWithAdminVersion();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("3.0.2.0\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testTableDisplayWithOpenCartDetected()
    {
        $tempDir = $this->createTempOpenCartWithVersion('4.0.1.1');

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute([]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            // Should show OpenCart root note
            $this->assertStringContainsString('OpenCart root:', $output);
            // Handle macOS /var -> /private/var symlink - check for both versions
            $normalizedTempDir = str_replace('/private', '', $tempDir);

            // Check if the unique part of the temp directory name is in the output
            // Extract the last 8 characters of the directory name for a unique check
            $tempDirBasename = basename($tempDir);
            $uniquePart = substr($tempDirBasename, -8);
            $this->assertStringContainsString($uniquePart, $output);
            $this->assertStringContainsString('4.0.1.1', $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testTableDisplayWithoutOpenCart()
    {
        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show warning message
        $this->assertStringContainsString('No OpenCart installation detected', $output);
        $this->assertStringContainsString('Not detected', $output);
    }

    public function testVersionDetectionWithMixedQuoteStyles()
    {
        $tempDir = $this->createTempOpenCartWithMixedQuotes();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("2.1.0.1\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testVersionDetectionWithCommentsAndWhitespace()
    {
        $tempDir = $this->createTempOpenCartWithComplexFile();

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("3.0.3.7\n", $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testJsonFormatWithOpenCartDetected()
    {
        $tempDir = $this->createTempOpenCartWithVersion('4.0.0.0');

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--format' => 'json']);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            $json = json_decode($output, true);
            $this->assertIsArray($json);
            $this->assertEquals('4.0.0.0', $json['opencart']);
            $this->assertEquals('1.0.2', $json['oc-cli']);
            $this->assertArrayHasKey('php', $json);
            $this->assertArrayHasKey('os', $json);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testYamlFormatWithOpenCartDetected()
    {
        $tempDir = $this->createTempOpenCartWithVersion('3.0.3.8');

        $originalDir = getcwd();
        chdir($tempDir);

        try {
            $this->commandTester->execute(['--format' => 'yaml']);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            $this->assertStringContainsString('oc-cli: 1.0.2', $output);
            $this->assertStringContainsString('opencart: 3.0.3.8', $output);
            $this->assertStringContainsString('php: ' . PHP_VERSION, $output);
        } finally {
            chdir($originalDir);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testOpenCartRootOptionWithValidPath()
    {
        $tempDir = $this->createTempOpenCartWithVersion('4.0.2.0');

        try {
            // Test from a different directory using --opencart-root
            $this->commandTester->execute(['--opencart-root' => $tempDir]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            // Should detect OpenCart version from the specified path
            $this->assertStringContainsString('4.0.2.0', $output);
            $this->assertStringContainsString('OpenCart root:', $output);

            // Check if the unique part of the temp directory name is in the output
            $tempDirBasename = basename($tempDir);
            $uniquePart = substr($tempDirBasename, -8);
            $this->assertStringContainsString($uniquePart, $output);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testOpenCartRootOptionWithInvalidPath()
    {
        $this->commandTester->execute(['--opencart-root' => '/nonexistent/path']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show "Not detected" for OpenCart
        $this->assertStringContainsString('Not detected', $output);
        $this->assertStringContainsString('No OpenCart installation detected', $output);
    }

    public function testOpenCartRootOptionWithJsonFormat()
    {
        $tempDir = $this->createTempOpenCartWithVersion('3.0.4.0');

        try {
            $this->commandTester->execute([
                '--opencart-root' => $tempDir,
                '--format' => 'json'
            ]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            $json = json_decode($output, true);
            $this->assertIsArray($json);
            $this->assertEquals('3.0.4.0', $json['opencart']);
            $this->assertEquals('1.0.2', $json['oc-cli']);
            $this->assertArrayHasKey('php', $json);
            $this->assertArrayHasKey('os', $json);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testOpenCartRootOptionWithOpenCartOnlyFlag()
    {
        $tempDir = $this->createTempOpenCartWithVersion('2.3.0.2');

        try {
            $this->commandTester->execute([
                '--opencart-root' => $tempDir,
                '--opencart' => true
            ]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            // Should only show OpenCart version
            $this->assertEquals("2.3.0.2\n", $output);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * Create temporary OpenCart with corrupted files
     */
    private function createTempOpenCartWithCorruptedFiles()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        // Create corrupted PHP file
        $content = "<?php\n";
        $content .= "// This file is corrupted\n";
        $content .= "define('VERSION', 'CORRUPTED\n"; // Missing closing quote and parenthesis
        $content .= "some random text\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with nested config structure
     */
    private function createTempOpenCartWithNestedConfig()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system/config', 0755, true);

        // Create startup file without version
        $startupContent = "<?php\n// OpenCart startup file\n";
        file_put_contents($tempDir . '/system/startup.php', $startupContent);

        // Create config file with version in nested directory
        $configContent = "<?php\n";
        $configContent .= "// Config file\n";
        $configContent .= "define('VERSION', '2.2.0.0');\n";
        $configContent .= "// Other config\n";
        file_put_contents($tempDir . '/system/config/config.php', $configContent);

        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with version in admin config
     */
    private function createTempOpenCartWithAdminVersion()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);
        mkdir($tempDir . '/admin/config', 0755, true);

        // Create startup file without version
        file_put_contents($tempDir . '/system/startup.php', "<?php\n// No version here\n");

        // Create admin config file with version
        $adminConfigContent = "<?php\n";
        $adminConfigContent .= "define('VERSION', '3.0.2.0');\n";
        file_put_contents($tempDir . '/admin/config/admin.php', $adminConfigContent);

        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with mixed quote styles
     */
    private function createTempOpenCartWithMixedQuotes()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        $content = "<?php\n";
        $content .= "// File with mixed quotes\n";
        $content .= 'define("VERSION", \'2.1.0.1\');' . "\n"; // Double quotes for define, single for value
        $content .= "// End of file\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
        touch($tempDir . '/config.php');

        return $tempDir;
    }

    /**
     * Create temporary OpenCart with complex file structure
     */
    private function createTempOpenCartWithComplexFile()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);

        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * OpenCart startup file\n";
        $content .= " * Version: 3.0.3.7\n";
        $content .= " */\n";
        $content .= "\n";
        $content .= "// Some constants\n";
        $content .= "define('DEBUG', false);\n";
        $content .= "\n";
        $content .= "/* Version definition */\n";
        $content .= "define('VERSION', '3.0.3.7'); // Current version\n";
        $content .= "\n";
        $content .= "// More code here\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
        touch($tempDir . '/config.php');

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
