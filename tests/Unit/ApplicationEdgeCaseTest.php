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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ApplicationEdgeCaseTest extends TestCase
{
    public function testDetectOpenCartWithVariousFileIndicators()
    {
        $app = new Application();

        // Test with system/startup.php only
        $tempDir1 = $this->createTempDirWithFile('system/startup.php');
        $this->assertTrue($app->detectOpenCart($tempDir1));
        $this->cleanupTempDirectory($tempDir1);

        // Test with system/config/catalog.php only
        $tempDir2 = $this->createTempDirWithFile('system/config/catalog.php');
        $this->assertTrue($app->detectOpenCart($tempDir2));
        $this->cleanupTempDirectory($tempDir2);

        // Test with admin/config.php only
        $tempDir3 = $this->createTempDirWithFile('admin/config.php');
        $this->assertTrue($app->detectOpenCart($tempDir3));
        $this->cleanupTempDirectory($tempDir3);

        // Test with config.php only
        $tempDir4 = $this->createTempDirWithFile('config.php');
        $this->assertTrue($app->detectOpenCart($tempDir4));
        $this->cleanupTempDirectory($tempDir4);
    }

    public function testDetectOpenCartWithNonExistentPath()
    {
        $app = new Application();
        
        $this->assertFalse($app->detectOpenCart('/completely/nonexistent/path/that/should/not/exist'));
    }

    public function testDetectOpenCartWithEmptyDirectory()
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . '/oc-cli-empty-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $this->assertFalse($app->detectOpenCart($tempDir));
        } finally {
            rmdir($tempDir);
        }
    }

    public function testGetOpenCartRootWithNestedStructure()
    {
        $app = new Application();
        
        // Create nested directory structure
        $rootDir = sys_get_temp_dir() . '/oc-cli-nested-test-' . uniqid();
        $subDir = $rootDir . '/some/deep/nested/directory';
        mkdir($subDir, 0755, true);
        mkdir($rootDir . '/system', 0755, true);
        
        // Create OpenCart indicator in root
        touch($rootDir . '/system/startup.php');

        try {
            // Test from nested directory
            $result = $app->getOpenCartRoot($subDir);
            $this->assertEquals(realpath($rootDir), realpath($result));
        } finally {
            $this->rrmdir($rootDir);
        }
    }

    public function testGetOpenCartRootWithMultipleLevels()
    {
        $app = new Application();
        
        // Create structure with multiple potential OpenCart dirs
        $baseDir = sys_get_temp_dir() . '/oc-cli-multi-test-' . uniqid();
        $fakeOcDir = $baseDir . '/fake-opencart';
        $realOcDir = $baseDir . '/real-opencart';
        $nestedDir = $realOcDir . '/subdirectory';
        
        mkdir($nestedDir, 0755, true);
        mkdir($fakeOcDir . '/system', 0755, true);
        mkdir($realOcDir . '/system', 0755, true);
        
        // Create indicators in both, but we should find the nearest one
        touch($fakeOcDir . '/config.php');
        touch($realOcDir . '/system/startup.php');

        try {
            // Test from nested directory - should find realOcDir first
            $result = $app->getOpenCartRoot($nestedDir);
            $this->assertEquals(realpath($realOcDir), realpath($result));
        } finally {
            $this->rrmdir($baseDir);
        }
    }

    public function testGetOpenCartRootAtFilesystemRoot()
    {
        $app = new Application();
        
        // Test behavior at filesystem root (should return null)
        $result = $app->getOpenCartRoot('/');
        $this->assertNull($result);
    }

    public function testRunMethodWithVariousInputs()
    {
        $app = new Application();
        
        // Test with null input/output (default behavior)
        $exitCode = $app->run(null, null);
        $this->assertEquals(0, $exitCode);
    }

    public function testRunMethodWithArrayInput()
    {
        $app = new Application();
        $input = new ArrayInput(['command' => 'list']);
        $output = new BufferedOutput();
        
        $exitCode = $app->run($input, $output);
        $this->assertEquals(0, $exitCode);
        
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Available commands', $outputContent);
    }

    public function testApplicationVersionAndNameConstants()
    {
        $this->assertEquals('1.0.0', Application::VERSION);
        $this->assertEquals('OC-CLI', Application::NAME);
    }

    public function testDetectOpenCartWithSymlinksAndAliases()
    {
        $app = new Application();
        
        // Create a directory with opencart indicator
        $realDir = sys_get_temp_dir() . '/oc-cli-real-' . uniqid();
        mkdir($realDir . '/system', 0755, true);
        touch($realDir . '/system/startup.php');

        try {
            // Test with realpath resolution
            $result = $app->detectOpenCart($realDir . '/.');
            $this->assertTrue($result);
            
            $result2 = $app->detectOpenCart($realDir . '/..');
            // This should be false as parent won't have opencart files
            $this->assertFalse($result2);
        } finally {
            $this->rrmdir($realDir);
        }
    }

    public function testGetOpenCartRootWithRelativePaths()
    {
        $app = new Application();
        
        $originalDir = getcwd();
        $testDir = sys_get_temp_dir() . '/oc-cli-relative-test-' . uniqid();
        mkdir($testDir . '/system', 0755, true);
        touch($testDir . '/system/startup.php');

        try {
            chdir($testDir);
            
            // Test with relative path
            $result = $app->getOpenCartRoot('.');
            $this->assertEquals(realpath($testDir), realpath($result));
            
            // Test with relative parent path
            chdir('..');
            $result2 = $app->getOpenCartRoot(basename($testDir));
            $this->assertEquals(realpath($testDir), realpath($result2));
            
        } finally {
            chdir($originalDir);
            $this->rrmdir($testDir);
        }
    }

    /**
     * Create temporary directory with specific file
     */
    private function createTempDirWithFile($filePath)
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();
        $fullPath = $tempDir . '/' . $filePath;
        $dirPath = dirname($fullPath);
        
        mkdir($dirPath, 0755, true);
        touch($fullPath);
        
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