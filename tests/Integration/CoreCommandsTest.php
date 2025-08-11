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

namespace OpenCart\CLI\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

class CoreCommandsTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var ApplicationTester
     */
    private $applicationTester;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->application->setAutoExit(false);
        $this->applicationTester = new ApplicationTester($this->application);
    }

    public function testCoreVersionCommandIntegration()
    {
        $this->applicationTester->run(['command' => 'core:version']);

        $this->assertEquals(0, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('Version Information', $output);
        $this->assertStringContainsString('Oc-cli', $output);
        $this->assertStringContainsString('1.0.0', $output);
        $this->assertStringContainsString('Php', $output);
        $this->assertStringContainsString(PHP_VERSION, $output);
    }

    public function testCoreVersionCommandWithAlias()
    {
        $this->applicationTester->run(['command' => 'version']);

        $this->assertEquals(0, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('Version Information', $output);
    }

    public function testCoreVersionCommandWithJsonFormat()
    {
        $this->applicationTester->run([
            'command' => 'core:version',
            '--format' => 'json'
        ]);

        $this->assertEquals(0, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('oc-cli', $json);
        $this->assertArrayHasKey('php', $json);
        $this->assertArrayHasKey('os', $json);
        $this->assertArrayHasKey('opencart', $json);
        $this->assertEquals('1.0.0', $json['oc-cli']);
    }

    public function testCoreCheckRequirementsCommandIntegration()
    {
        $this->applicationTester->run(['command' => 'core:check-requirements']);

        // May return 1 due to missing extensions or no OpenCart installation
        $this->assertContains($this->applicationTester->getStatusCode(), [0, 1]);
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('System Requirements Check', $output);
        $this->assertStringContainsString('Php', $output);
        $this->assertStringContainsString('Extensions', $output);
        $this->assertStringContainsString('PHP Version', $output);
    }

    public function testCoreCheckRequirementsCommandWithJsonFormat()
    {
        $this->applicationTester->run([
            'command' => 'core:check-requirements',
            '--format' => 'json'
        ]);

        $this->assertContains($this->applicationTester->getStatusCode(), [0, 1]);
        $output = $this->applicationTester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('php', $json);
        $this->assertArrayHasKey('extensions', $json);
        $this->assertArrayHasKey('permissions', $json);
        $this->assertArrayHasKey('database', $json);
    }

    public function testCoreConfigCommandWithoutOpenCart()
    {
        $this->applicationTester->run(['command' => 'core:config']);

        $this->assertEquals(1, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('OpenCart installation directory', $output);
    }

    public function testCoreConfigGetCommandWithoutKey()
    {
        $this->applicationTester->run([
            'command' => 'core:config',
            'action' => 'get'
        ]);

        $this->assertEquals(1, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('Key is required', $output);
    }

    public function testCoreConfigSetCommandWithoutValue()
    {
        $this->applicationTester->run([
            'command' => 'core:config',
            'action' => 'set',
            'key' => 'test_key'
        ]);

        $this->assertEquals(1, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('Value is required', $output);
    }

    public function testAllCoreCommandsHaveOpenCartRootOption()
    {
        $coreCommands = ['core:version', 'core:check-requirements', 'core:config'];

        foreach ($coreCommands as $commandName) {
            $command = $this->application->find($commandName);
            $definition = $command->getDefinition();

            $this->assertTrue(
                $definition->hasOption('opencart-root'),
                "Command {$commandName} should have --opencart-root option"
            );

            $option = $definition->getOption('opencart-root');
            $this->assertTrue(
                $option->isValueRequired(),
                "Command {$commandName} --opencart-root option should require a value"
            );
        }
    }

    public function testCoreCommandsWithInvalidOpenCartRoot()
    {
        $coreCommands = [
            'core:version' => 0,  // Version should still work, just show "Not detected"
            'core:check-requirements' => 1,  // Should fail some checks
            'core:config' => 1    // Should fail completely
        ];

        foreach ($coreCommands as $commandName => $expectedStatus) {
            $this->applicationTester->run([
                'command' => $commandName,
                '--opencart-root' => '/nonexistent/path'
            ]);

            $this->assertEquals(
                $expectedStatus,
                $this->applicationTester->getStatusCode(),
                "Command {$commandName} should return status {$expectedStatus} with invalid OpenCart root"
            );
        }
    }

    public function testCoreCommandsWithValidOpenCartRoot()
    {
        $tempDir = $this->createTempOpenCartInstallation();

        try {
            // Test version command with valid OpenCart root
            $this->applicationTester->run([
                'command' => 'core:version',
                '--opencart-root' => $tempDir
            ]);

            $this->assertEquals(0, $this->applicationTester->getStatusCode());
            $output = $this->applicationTester->getDisplay();
            $this->assertStringContainsString('4.0.0.0', $output);
            $this->assertStringContainsString('OpenCart root:', $output);

            // Test check-requirements command with valid OpenCart root
            $this->applicationTester->run([
                'command' => 'core:check-requirements',
                '--opencart-root' => $tempDir
            ]);

            // Should still return 1 due to missing extensions, but should not fail due to missing OpenCart
            $this->assertEquals(1, $this->applicationTester->getStatusCode());
            $output = $this->applicationTester->getDisplay();
            $this->assertStringNotContainsString('No OpenCart installation detected', $output);
            $this->assertStringContainsString('Directory writable:', $output);

            // Test config command with valid OpenCart root (will fail due to no database)
            $this->applicationTester->run([
                'command' => 'core:config',
                '--opencart-root' => $tempDir
            ]);

            $this->assertEquals(1, $this->applicationTester->getStatusCode());
            $output = $this->applicationTester->getDisplay();
            // Should fail on database connection, not OpenCart detection
            $this->assertStringContainsString('Failed to connect to database', $output);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testCoreCommandsHelpMessages()
    {
        $coreCommands = ['core:version', 'core:check-requirements', 'core:config'];

        foreach ($coreCommands as $commandName) {
            $this->applicationTester->run([
                'command' => 'help',
                'command_name' => $commandName
            ]);

            $this->assertEquals(0, $this->applicationTester->getStatusCode());
            $output = $this->applicationTester->getDisplay();

            $this->assertStringContainsString('--opencart-root', $output);
            $this->assertStringContainsString('Path to OpenCart installation directory', $output);
        }
    }

    public function testCoreCommandsList()
    {
        $this->applicationTester->run([
            'command' => 'list',
            'namespace' => 'core'
        ]);

        $this->assertEquals(0, $this->applicationTester->getStatusCode());
        $output = $this->applicationTester->getDisplay();

        $this->assertStringContainsString('core:version', $output);
        $this->assertStringContainsString('core:check-requirements', $output);
        $this->assertStringContainsString('core:config', $output);
        $this->assertStringContainsString('Display version information', $output);
        $this->assertStringContainsString('Check system requirements', $output);
        $this->assertStringContainsString('Manage OpenCart configuration', $output);
    }

    /**
     * Create temporary OpenCart installation for testing
     */
    private function createTempOpenCartInstallation()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-integration-test-' . uniqid();

        // Create basic OpenCart structure
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/system', 0755, true);
        mkdir($tempDir . '/image', 0755, true);
        mkdir($tempDir . '/image/cache', 0755, true);
        mkdir($tempDir . '/system/storage', 0755, true);
        mkdir($tempDir . '/system/storage/cache', 0755, true);
        mkdir($tempDir . '/system/storage/logs', 0755, true);

        // Create minimal OpenCart files
        file_put_contents($tempDir . '/system/startup.php', "<?php\ndefine('VERSION', '4.0.0.0');\n");
        file_put_contents(
            $tempDir . '/config.php',
            "<?php\n// Config file\ndefine('DB_HOSTNAME', 'localhost');\n" .
            "define('DB_USERNAME', 'test');\ndefine('DB_PASSWORD', 'test');\n" .
            "define('DB_DATABASE', 'test');\n"
        );

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
