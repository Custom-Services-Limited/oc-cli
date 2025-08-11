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
use OpenCart\CLI\Commands\Core\CheckRequirementsCommand;
use OpenCart\CLI\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CheckRequirementsCommandTest extends TestCase
{
    /**
     * @var CheckRequirementsCommand
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
        $this->command = new CheckRequirementsCommand();
        $this->command->setApplication($this->application);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCheckRequirementsCommandName()
    {
        $this->assertEquals('core:check-requirements', $this->command->getName());
    }

    public function testCheckRequirementsCommandDescription()
    {
        $this->assertEquals('Check system requirements', $this->command->getDescription());
    }

    public function testCheckRequirementsCommandOptions()
    {
        $definition = $this->command->getDefinition();

        // Test opencart-root option (inherited from base Command)
        $this->assertTrue($definition->hasOption('opencart-root'));
        $openCartRootOption = $definition->getOption('opencart-root');
        $this->assertTrue($openCartRootOption->isValueRequired());

        // Test format option
        $this->assertTrue($definition->hasOption('format'));
        $formatOption = $definition->getOption('format');
        $this->assertEquals('f', $formatOption->getShortcut());
        $this->assertTrue($formatOption->isValueRequired());
        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testCheckRequirementsBasicExecution()
    {
        $this->commandTester->execute([]);

        // Should return 1 because some requirements will fail (mcrypt, no OpenCart installation)
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should contain main sections
        $this->assertStringContainsString('System Requirements Check', $output);
        $this->assertStringContainsString('Php', $output);
        $this->assertStringContainsString('Extensions', $output);
        $this->assertStringContainsString('Permissions', $output);
        $this->assertStringContainsString('Database', $output);
    }

    public function testCheckRequirementsJsonFormat()
    {
        $this->commandTester->execute(['--format' => 'json']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('php', $json);
        $this->assertArrayHasKey('extensions', $json);
        $this->assertArrayHasKey('permissions', $json);
        $this->assertArrayHasKey('database', $json);

        // Check PHP requirements structure
        $this->assertIsArray($json['php']);
        $this->assertGreaterThan(0, count($json['php']));

        // Check that each requirement has the expected structure
        foreach ($json['php'] as $requirement) {
            $this->assertArrayHasKey('name', $requirement);
            $this->assertArrayHasKey('status', $requirement);
            $this->assertArrayHasKey('message', $requirement);
            $this->assertIsBool($requirement['status']);
        }
    }

    public function testCheckRequirementsYamlFormat()
    {
        $this->commandTester->execute(['--format' => 'yaml']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('php:', $output);
        $this->assertStringContainsString('extensions:', $output);
        $this->assertStringContainsString('permissions:', $output);
        $this->assertStringContainsString('database:', $output);
        $this->assertStringContainsString('- name:', $output);
        $this->assertStringContainsString('status:', $output);
        $this->assertStringContainsString('message:', $output);
    }

    public function testCheckRequirementsWithValidOpenCartRoot()
    {
        $tempDir = $this->createTempOpenCartInstallation();

        try {
            $this->commandTester->execute(['--opencart-root' => $tempDir]);

            // Should still return 1 because of missing extensions or permissions
            $this->assertEquals(1, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();

            // Should detect OpenCart installation
            $this->assertStringNotContainsString('No OpenCart installation detected', $output);
            $this->assertStringContainsString('Directory writable:', $output);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testCheckRequirementsWithInvalidOpenCartRoot()
    {
        $this->commandTester->execute(['--opencart-root' => '/nonexistent']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show that no OpenCart installation was detected
        $this->assertStringContainsString('No OpenCart installation detected', $output);
    }

    public function testPhpVersionCheck()
    {
        $this->commandTester->execute(['--format' => 'json']);
        $output = $this->commandTester->getDisplay();
        $json = json_decode($output, true);

        // Find PHP version requirement
        $phpVersionCheck = null;
        foreach ($json['php'] as $check) {
            if (strpos($check['name'], 'PHP Version') !== false) {
                $phpVersionCheck = $check;
                break;
            }
        }

        $this->assertNotNull($phpVersionCheck);
        $this->assertStringContainsString('Current: ' . PHP_VERSION, $phpVersionCheck['message']);
        // PHP version should pass since we're running tests
        $this->assertTrue($phpVersionCheck['status']);
    }

    public function testRequiredExtensionsCheck()
    {
        $this->commandTester->execute(['--format' => 'json']);
        $output = $this->commandTester->getDisplay();
        $json = json_decode($output, true);

        // Check that required extensions are listed
        $requiredExtensions = ['curl', 'gd', 'mbstring', 'zip', 'zlib', 'json', 'openssl'];
        $foundExtensions = [];

        foreach ($json['extensions'] as $check) {
            foreach ($requiredExtensions as $ext) {
                if (strpos($check['name'], $ext) !== false && strpos($check['name'], 'required') !== false) {
                    $foundExtensions[] = $ext;
                }
            }
        }

        $this->assertEquals(count($requiredExtensions), count($foundExtensions));
    }

    public function testRecommendedExtensionsCheck()
    {
        $this->commandTester->execute(['--format' => 'json']);
        $output = $this->commandTester->getDisplay();
        $json = json_decode($output, true);

        // Check that recommended extensions are listed
        $recommendedExtensions = ['mysqli', 'pdo_mysql', 'iconv', 'mcrypt'];
        $foundExtensions = [];

        foreach ($json['extensions'] as $check) {
            foreach ($recommendedExtensions as $ext) {
                if (strpos($check['name'], $ext) !== false && strpos($check['name'], 'recommended') !== false) {
                    $foundExtensions[] = $ext;
                }
            }
        }

        $this->assertEquals(count($recommendedExtensions), count($foundExtensions));
    }

    /**
     * Create temporary OpenCart installation for testing
     */
    private function createTempOpenCartInstallation()
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();

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
        file_put_contents($tempDir . '/config.php', "<?php\n// Config file\n");

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
