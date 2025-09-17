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

class ApplicationTest extends TestCase
{
    public function testApplicationCanBeInstantiated()
    {
        $app = new Application();

        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals(Application::NAME, $app->getName());
        $this->assertEquals(Application::VERSION, $app->getVersion());
    }

    public function testApplicationHasDefaultCommands()
    {
        $app = new Application();

        // Test that required commands are registered
        $this->assertTrue($app->has('help'));
        $this->assertTrue($app->has('list'));
        $this->assertTrue($app->has('core:version'));
        $this->assertTrue($app->has('version')); // Alias
    }

    public function testDetectOpenCartReturnsFalseForNonOpenCartDirectory()
    {
        $app = new Application();

        $this->assertFalse($app->detectOpenCart('/tmp'));
        $this->assertFalse($app->detectOpenCart('/nonexistent/path'));
    }

    public function testGetOpenCartRootReturnsNullForNonOpenCartDirectory()
    {
        $app = new Application();

        $this->assertNull($app->getOpenCartRoot('/tmp'));
        $this->assertNull($app->getOpenCartRoot('/nonexistent/path'));
    }

    public function testDetectOpenCartReturnsTrueForValidOpenCartDirectory()
    {
        $app = new Application();
        $tempDir = $this->createTempOpenCartDirectory();

        try {
            $this->assertTrue($app->detectOpenCart($tempDir));
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testGetOpenCartRootReturnsCorrectPathForValidDirectory()
    {
        $app = new Application();
        $tempDir = $this->createTempOpenCartDirectory();

        try {
            $result = $app->getOpenCartRoot($tempDir);
            $this->assertEquals(realpath($tempDir), realpath($result));
        } finally {
            $this->cleanupTempDirectory($tempDir);
        }
    }

    public function testLongVersionFormat()
    {
        $app = new Application();
        $longVersion = $app->getLongVersion();

        $this->assertStringContainsString('OC-CLI', $longVersion);
        $this->assertStringContainsString('1.0.2', $longVersion);
    }

    /**
     * Create a temporary directory with OpenCart indicators
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
