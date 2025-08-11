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
use OpenCart\CLI\Commands\Core\VersionCommand;
use Symfony\Component\Console\Tester\CommandTester;

class RealOpenCartTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var string
     */
    private $testDataDir;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->testDataDir = sys_get_temp_dir() . '/oc-cli-real-opencart-' . uniqid();
        mkdir($this->testDataDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }

    public function testOpenCart3xFileStructure()
    {
        $this->createMockOpenCart3x();

        $result = $this->application->detectOpenCart($this->testDataDir);
        $this->assertTrue($result);

        $root = $this->application->getOpenCartRoot($this->testDataDir);
        $this->assertEquals(realpath($this->testDataDir), realpath($root));
    }

    public function testOpenCart3xVersionDetection()
    {
        $this->createMockOpenCart3x();

        $command = new VersionCommand();
        $command->setApplication($this->application);
        $commandTester = new CommandTester($command);

        $originalDir = getcwd();
        chdir($this->testDataDir);

        try {
            $commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $commandTester->getStatusCode());
            $output = $commandTester->getDisplay();
            $this->assertEquals("3.0.3.8\n", $output);
        } finally {
            chdir($originalDir);
        }
    }

    public function testOpenCart4xFileStructure()
    {
        $this->createMockOpenCart4x();

        $result = $this->application->detectOpenCart($this->testDataDir);
        $this->assertTrue($result);

        $root = $this->application->getOpenCartRoot($this->testDataDir);
        $this->assertEquals(realpath($this->testDataDir), realpath($root));
    }

    public function testOpenCart4xVersionDetection()
    {
        $this->createMockOpenCart4x();

        $command = new VersionCommand();
        $command->setApplication($this->application);
        $commandTester = new CommandTester($command);

        $originalDir = getcwd();
        chdir($this->testDataDir);

        try {
            $commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $commandTester->getStatusCode());
            $output = $commandTester->getDisplay();
            $this->assertEquals("4.0.1.1\n", $output);
        } finally {
            chdir($originalDir);
        }
    }

    public function testOpenCart2xFileStructure()
    {
        $this->createMockOpenCart2x();

        $result = $this->application->detectOpenCart($this->testDataDir);
        $this->assertTrue($result);

        $root = $this->application->getOpenCartRoot($this->testDataDir);
        $this->assertEquals(realpath($this->testDataDir), realpath($root));
    }

    public function testOpenCart2xVersionDetection()
    {
        $this->createMockOpenCart2x();

        $command = new VersionCommand();
        $command->setApplication($this->application);
        $commandTester = new CommandTester($command);

        $originalDir = getcwd();
        chdir($this->testDataDir);

        try {
            $commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $commandTester->getStatusCode());
            $output = $commandTester->getDisplay();
            $this->assertEquals("2.3.0.2\n", $output);
        } finally {
            chdir($originalDir);
        }
    }

    public function testOpenCart1xFileStructure()
    {
        $this->createMockOpenCart1x();

        $result = $this->application->detectOpenCart($this->testDataDir);
        $this->assertTrue($result);

        $root = $this->application->getOpenCartRoot($this->testDataDir);
        $this->assertEquals(realpath($this->testDataDir), realpath($root));
    }

    public function testOpenCart1xVersionDetection()
    {
        $this->createMockOpenCart1x();

        $command = new VersionCommand();
        $command->setApplication($this->application);
        $commandTester = new CommandTester($command);

        $originalDir = getcwd();
        chdir($this->testDataDir);

        try {
            $commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $commandTester->getStatusCode());
            $output = $commandTester->getDisplay();
            $this->assertEquals("1.5.6.5\n", $output);
        } finally {
            chdir($originalDir);
        }
    }

    public function testFullVersionInfoWithRealOpenCartStructure()
    {
        $this->createMockOpenCart3x();

        $command = new VersionCommand();
        $command->setApplication($this->application);
        $commandTester = new CommandTester($command);

        $originalDir = getcwd();
        chdir($this->testDataDir);

        try {
            $commandTester->execute(['--format' => 'json']);

            $this->assertEquals(0, $commandTester->getStatusCode());
            $output = $commandTester->getDisplay();

            $json = json_decode($output, true);
            $this->assertIsArray($json);
            $this->assertEquals('3.0.3.8', $json['opencart']);
            $this->assertEquals('1.0.0', $json['oc-cli']);
            $this->assertArrayHasKey('php', $json);
            $this->assertArrayHasKey('os', $json);
        } finally {
            chdir($originalDir);
        }
    }

    public function testConfigurationFileDetectionAcrossVersions()
    {
        $versions = [
            ['2x', '2.3.0.2'],
            ['3x', '3.0.3.8'],
            ['4x', '4.0.1.1']
        ];

        foreach ($versions as [$suffix, $expectedVersion]) {
            $testDir = $this->testDataDir . '_' . $suffix;
            mkdir($testDir, 0755, true);

            $method = 'createMockOpenCart' . $suffix;
            $this->$method($testDir);

            $result = $this->application->detectOpenCart($testDir);
            $this->assertTrue($result, "Failed to detect OpenCart $suffix");

            $this->cleanupDirectory($testDir);
        }
    }

    /**
     * Create mock OpenCart 3.x structure
     */
    private function createMockOpenCart3x($dir = null)
    {
        $dir = $dir ?: $this->testDataDir;

        // Create directory structure
        mkdir($dir . '/system/config', 0755, true);
        mkdir($dir . '/admin', 0755, true);
        mkdir($dir . '/catalog', 0755, true);
        mkdir($dir . '/image', 0755, true);

        // Create main config
        $configContent = "<?php\n";
        $configContent .= "// Configuration\n";
        $configContent .= "define('HTTP_SERVER', 'http://localhost/');\n";
        $configContent .= "define('HTTPS_SERVER', 'http://localhost/');\n";
        file_put_contents($dir . '/config.php', $configContent);

        // Create admin config
        $adminConfigContent = "<?php\n";
        $adminConfigContent .= "// Admin Configuration\n";
        $adminConfigContent .= "define('HTTP_SERVER', 'http://localhost/admin/');\n";
        file_put_contents($dir . '/admin/config.php', $adminConfigContent);

        // Create startup file with version
        $startupContent = "<?php\n";
        $startupContent .= "/**\n";
        $startupContent .= " * OpenCart 3.x startup file\n";
        $startupContent .= " */\n";
        $startupContent .= "\n";
        $startupContent .= "// Version\n";
        $startupContent .= "define('VERSION', '3.0.3.8');\n";
        $startupContent .= "\n";
        $startupContent .= "// Startup code\n";
        file_put_contents($dir . '/system/startup.php', $startupContent);

        // Create catalog config
        $catalogConfigContent = "<?php\n";
        $catalogConfigContent .= "// Catalog configuration\n";
        file_put_contents($dir . '/system/config/catalog.php', $catalogConfigContent);
    }

    /**
     * Create mock OpenCart 4.x structure
     */
    private function createMockOpenCart4x($dir = null)
    {
        $dir = $dir ?: $this->testDataDir;

        // Create directory structure (similar to 3.x but with some differences)
        mkdir($dir . '/system/config', 0755, true);
        mkdir($dir . '/admin', 0755, true);
        mkdir($dir . '/catalog', 0755, true);
        mkdir($dir . '/extension', 0755, true);
        mkdir($dir . '/image', 0755, true);

        // Create main config
        $configContent = "<?php\n";
        $configContent .= "// Configuration\n";
        $configContent .= "define('HTTP_SERVER', 'http://localhost/');\n";
        file_put_contents($dir . '/config.php', $configContent);

        // Create admin config
        file_put_contents($dir . '/admin/config.php', "<?php\n// Admin config\n");

        // Create startup file with version
        $startupContent = "<?php\n";
        $startupContent .= "/**\n";
        $startupContent .= " * OpenCart 4.x startup file\n";
        $startupContent .= " */\n";
        $startupContent .= "\n";
        $startupContent .= "// Version\n";
        $startupContent .= "define('VERSION', '4.0.1.1');\n";
        $startupContent .= "\n";
        $startupContent .= "// Framework startup\n";
        file_put_contents($dir . '/system/startup.php', $startupContent);

        // Create system config
        file_put_contents($dir . '/system/config/catalog.php', "<?php\n// System config\n");
    }

    /**
     * Create mock OpenCart 2.x structure
     */
    private function createMockOpenCart2x($dir = null)
    {
        $dir = $dir ?: $this->testDataDir;

        // Create directory structure
        mkdir($dir . '/system/config', 0755, true);
        mkdir($dir . '/admin', 0755, true);
        mkdir($dir . '/catalog', 0755, true);
        mkdir($dir . '/image', 0755, true);

        // Create main config
        file_put_contents($dir . '/config.php', "<?php\n// Config\n");

        // Create admin config
        file_put_contents($dir . '/admin/config.php', "<?php\n// Admin config\n");

        // Create startup file with version
        $startupContent = "<?php\n";
        $startupContent .= "// OpenCart 2.x\n";
        $startupContent .= "define('VERSION', '2.3.0.2');\n";
        file_put_contents($dir . '/system/startup.php', $startupContent);

        // Create catalog config
        file_put_contents($dir . '/system/config/catalog.php', "<?php\n// Catalog config\n");
    }

    /**
     * Create mock OpenCart 1.x structure
     */
    private function createMockOpenCart1x($dir = null)
    {
        $dir = $dir ?: $this->testDataDir;

        // Create directory structure (different from newer versions)
        mkdir($dir . '/system', 0755, true);
        mkdir($dir . '/admin', 0755, true);
        mkdir($dir . '/catalog', 0755, true);

        // Create main config
        file_put_contents($dir . '/config.php', "<?php\n// OpenCart 1.x config\n");

        // Create admin config
        file_put_contents($dir . '/admin/config.php', "<?php\n// Admin config\n");

        // Version might be in index.php for older versions
        $indexContent = "<?php\n";
        $indexContent .= "// OpenCart 1.x\n";
        $indexContent .= "define('VERSION', '1.5.6.5');\n";
        $indexContent .= "// Bootstrap\n";
        file_put_contents($dir . '/index.php', $indexContent);

        // Create basic startup
        file_put_contents($dir . '/system/startup.php', "<?php\n// Basic startup\n");
    }

    /**
     * Clean up test data
     */
    private function cleanupTestData()
    {
        if (is_dir($this->testDataDir)) {
            $this->cleanupDirectory($this->testDataDir);
        }
    }

    /**
     * Clean up directory
     */
    private function cleanupDirectory($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->cleanupDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
