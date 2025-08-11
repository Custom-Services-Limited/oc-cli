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

namespace OpenCart\CLI\Tests;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Application;

class ApplicationTest extends TestCase
{
    public function testApplicationCanBeInstantiated()
    {
        $app = new Application();
        
        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals('OC-CLI', $app->getName());
        $this->assertEquals('1.0.0', $app->getVersion());
    }

    public function testApplicationHasDefaultCommands()
    {
        $app = new Application();
        
        $this->assertTrue($app->has('help'));
        $this->assertTrue($app->has('list'));
        $this->assertTrue($app->has('core:version'));
        $this->assertTrue($app->has('version')); // Alias
    }

    public function testDetectOpenCartReturnsFalseForNonOpenCartDirectory()
    {
        $app = new Application();
        
        $this->assertFalse($app->detectOpenCart('/tmp'));
    }

    public function testGetOpenCartRootReturnsNullForNonOpenCartDirectory()
    {
        $app = new Application();
        
        $this->assertNull($app->getOpenCartRoot('/tmp'));
    }
}