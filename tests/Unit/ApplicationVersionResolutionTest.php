<?php

namespace OpenCart\CLI\Tests\Unit;

use OpenCart\CLI\Application;
use PHPUnit\Framework\TestCase;

class ApplicationVersionResolutionTest extends TestCase
{
    public function testEnvironmentVersionOverrideWins()
    {
        $projectRoot = dirname(__DIR__, 2);
        $command = sprintf(
            'cd %s && OC_CLI_VERSION=v9.8.7 php -r %s',
            escapeshellarg($projectRoot),
            escapeshellarg(
                'require "vendor/autoload.php";'
                . '$app = new \OpenCart\CLI\Application();'
                . 'echo $app->getVersion();'
            )
        );

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertSame('9.8.7', implode('', $output));
    }

    public function testResolvedVersionIsNeverEmpty()
    {
        $this->assertNotSame('', Application::resolveVersion(true));
    }
}
