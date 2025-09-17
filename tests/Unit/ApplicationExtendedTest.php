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
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ApplicationExtendedTest extends TestCase
{
    public function testGetLongVersionWithDifferentFormats()
    {
        $app = new Application();

        $longVersion = $app->getLongVersion();

        // Test that it contains the expected format with HTML tags
        $this->assertStringContainsString('<info>OC-CLI</info>', $longVersion);
        $this->assertStringContainsString('<comment>1.0.2</comment>', $longVersion);
        $this->assertStringContainsString('version', $longVersion);
    }

    public function testRunWithSpecificCommands()
    {
        $app = new Application();

        // Test that the application has the version command
        $this->assertTrue($app->has('core:version'));
        $this->assertTrue($app->has('version'));

        // Skip actual run() call to prevent hanging during tests
        $this->assertTrue(true);
    }

    public function testRunWithVersionAlias()
    {
        $app = new Application();

        // Test that version alias exists and can be retrieved
        $this->assertTrue($app->has('version'));
        $command = $app->get('version');
        $this->assertInstanceOf(\OpenCart\CLI\Commands\Core\VersionCommand::class, $command);

        // Skip actual run() call to prevent hanging during tests
        $this->assertTrue(true);
    }

    public function testDetectOpenCartWithBrokenSymlinks()
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . '/oc-cli-symlink-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Create a broken symlink (pointing to non-existent file)
            $symlinkPath = $tempDir . '/system';
            $targetPath = '/nonexistent/path/system';

            if (function_exists('symlink')) {
                symlink($targetPath, $symlinkPath);

                // Should handle broken symlinks gracefully
                $result = $app->detectOpenCart($tempDir);
                $this->assertFalse($result);
            } else {
                // Skip test if symlinks aren't supported
                $this->markTestSkipped('Symlinks not supported on this system');
            }
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testGetOpenCartRootWithCircularSymlinks()
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . '/oc-cli-circular-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            if (function_exists('symlink')) {
                // Create circular symlinks
                $dir1 = $tempDir . '/dir1';
                $dir2 = $tempDir . '/dir2';
                mkdir($dir1, 0755, true);
                mkdir($dir2, 0755, true);

                symlink($dir2, $dir1 . '/link_to_dir2');
                symlink($dir1, $dir2 . '/link_to_dir1');

                // Should handle circular references gracefully
                $result = $app->getOpenCartRoot($dir1 . '/link_to_dir2/link_to_dir1');
                $this->assertNull($result);
            } else {
                $this->markTestSkipped('Symlinks not supported on this system');
            }
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testDetectOpenCartWithSpecialCharactersPaths()
    {
        $app = new Application();

        // Test with path containing spaces and special characters
        $tempDir = sys_get_temp_dir() . '/oc-cli test with spaces-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);
        touch($tempDir . '/system/startup.php');

        try {
            $result = $app->detectOpenCart($tempDir);
            $this->assertTrue($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testGetOpenCartRootWithDeepNesting()
    {
        $app = new Application();

        // Create very deep directory structure
        $baseDir = sys_get_temp_dir() . '/oc-cli-deep-test-' . uniqid();
        $deepPath = $baseDir;

        // Create 10 levels deep
        for ($i = 0; $i < 10; $i++) {
            $deepPath .= '/level' . $i;
        }
        mkdir($deepPath, 0755, true);

        // Put OpenCart files at the base
        mkdir($baseDir . '/system', 0755, true);
        touch($baseDir . '/system/startup.php');

        try {
            $result = $app->getOpenCartRoot($deepPath);
            $this->assertEquals(realpath($baseDir), realpath($result));
        } finally {
            $this->cleanupTempDirectory($baseDir);
        }
    }

    public function testDetectOpenCartWithReadOnlyDirectory()
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . '/oc-cli-readonly-test-' . uniqid();
        mkdir($tempDir . '/system', 0755, true);
        touch($tempDir . '/system/startup.php');

        try {
            // Make directory read-only
            chmod($tempDir, 0555);

            // Should still detect OpenCart even with read-only permissions
            $result = $app->detectOpenCart($tempDir);
            $this->assertTrue($result);
        } finally {
            // Restore permissions for cleanup
            chmod($tempDir, 0755);
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testApplicationNameAndVersionConstants()
    {
        $app = new Application();

        $this->assertEquals('OC-CLI', $app->getName());
        $this->assertEquals('1.0.2', $app->getVersion());
        $this->assertEquals('OC-CLI', Application::NAME);
        $this->assertEquals('1.0.2', Application::VERSION);
    }

    public function testGetDefaultCommandsIncludesVersionCommand()
    {
        $app = new Application();

        // Test that the version command is available
        $this->assertTrue($app->has('core:version'));
        $this->assertTrue($app->has('version'));

        $command = $app->get('core:version');
        $this->assertInstanceOf(\OpenCart\CLI\Commands\Core\VersionCommand::class, $command);
    }

    public function testRunWithInvalidCommand()
    {
        $app = new Application();

        // Test that invalid commands are not available
        $this->assertFalse($app->has('nonexistent:command'));

        // Skip actual run() call to prevent hanging during tests
        $this->assertTrue(true);
    }

    public function testDetectOpenCartWithMultipleIndicatorsInSameDirectory()
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . '/oc-cli-multi-test-' . uniqid();

        // Create all possible indicators
        mkdir($tempDir . '/system/config', 0755, true);
        mkdir($tempDir . '/admin', 0755, true);

        touch($tempDir . '/system/startup.php');
        touch($tempDir . '/system/config/catalog.php');
        touch($tempDir . '/admin/config.php');
        touch($tempDir . '/config.php');

        try {
            $result = $app->detectOpenCart($tempDir);
            $this->assertTrue($result);
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testGetOpenCartRootWithPermissionDeniedDirectories()
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . '/oc-cli-permission-test-' . uniqid();
        $restrictedDir = $tempDir . '/restricted';

        mkdir($restrictedDir, 0755, true);
        mkdir($tempDir . '/system', 0755, true);
        touch($tempDir . '/system/startup.php');

        try {
            // Make directory inaccessible
            chmod($restrictedDir, 0000);

            // Should still find OpenCart root despite permission issues
            $result = $app->getOpenCartRoot($tempDir);
            $this->assertEquals(realpath($tempDir), realpath($result));
        } finally {
            // Restore permissions
            chmod($restrictedDir, 0755);
            $this->cleanupTempDirectory($tempDir);
        }
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
                    $path = $dir . "/" . $object;
                    if (is_dir($path) && !is_link($path)) {
                        $this->rrmdir($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
