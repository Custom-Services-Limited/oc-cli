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
use Symfony\Component\Console\Tester\CommandTester;
use OpenCart\CLI\Commands\Core\VersionCommand;
use OpenCart\CLI\Tests\Helpers\TestHelper;

class VersionCommandTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void
    {
        $this->application = new Application();
        // Note: VersionCommand is already added in Application's getDefaultCommands()

        $command = $this->application->find('core:version');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->tempDir) {
            TestHelper::cleanupTempDirectory($this->tempDir);
        }
    }

    public function testVersionCommandExecutesSuccessfully()
    {
        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Version Information', $output);
        $this->assertStringContainsString('Oc-cli', $output);
        $this->assertStringContainsString('Php', $output);
    }

    public function testVersionCommandWithOpenCartDetection()
    {
        // Create a temporary OpenCart installation
        $this->tempDir = TestHelper::createTempOpenCartInstallation();

        // Change to the temporary directory
        $originalDir = getcwd();
        chdir($this->tempDir);

        try {
            $this->commandTester->execute([]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertStringContainsString('3.0.3.8', $output); // Default version in test helper
            $this->assertStringContainsString('OpenCart root:', $output);
        } finally {
            chdir($originalDir);
        }
    }

    public function testVersionCommandWithoutOpenCart()
    {
        // Run in a directory without OpenCart
        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Not detected', $output);
        $this->assertStringContainsString('No OpenCart installation detected', $output);
    }

    public function testVersionCommandWithOpenCartOnlyOption()
    {
        // Create a temporary OpenCart installation
        $this->tempDir = TestHelper::createTempOpenCartInstallation();

        // Change to the temporary directory
        $originalDir = getcwd();
        chdir($this->tempDir);

        try {
            $this->commandTester->execute(['--opencart' => true]);

            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $output = $this->commandTester->getDisplay();
            $this->assertEquals("3.0.3.8\n", $output);
        } finally {
            chdir($originalDir);
        }
    }

    public function testVersionCommandWithJsonFormat()
    {
        $this->commandTester->execute(['--format' => 'json']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('oc-cli', $json);
        $this->assertArrayHasKey('php', $json);
        $this->assertArrayHasKey('os', $json);
        $this->assertArrayHasKey('opencart', $json);
    }

    public function testVersionCommandWithYamlFormat()
    {
        $this->commandTester->execute(['--format' => 'yaml']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('oc-cli:', $output);
        $this->assertStringContainsString('php:', $output);
        $this->assertStringContainsString('os:', $output);
        $this->assertStringContainsString('opencart:', $output);
    }

    public function testVersionCommandWithOpenCartOnlyAndJsonFormat()
    {
        // Create a temporary OpenCart installation
        $this->tempDir = TestHelper::createTempOpenCartInstallation();

        // Change to the temporary directory
        $originalDir = getcwd();
        chdir($this->tempDir);

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

            // Should only contain opencart key when --opencart option is used
            $this->assertCount(1, $json);
        } finally {
            chdir($originalDir);
        }
    }

    public function testVersionCommandAliasWorks()
    {
        $command = $this->application->find('version');
        $this->assertInstanceOf(VersionCommand::class, $command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Version Information', $output);
    }

    public function testVersionCommandDetectsMultipleOpenCartVersionFormats()
    {
        // Test different OpenCart versions
        $versions = ['2.3.0.2', '3.0.3.8', '4.0.1.1'];

        foreach ($versions as $version) {
            $this->tempDir = TestHelper::createTempOpenCartInstallation([], $version);

            $originalDir = getcwd();
            chdir($this->tempDir);

            try {
                $this->commandTester->execute(['--opencart' => true]);

                $this->assertEquals(0, $this->commandTester->getStatusCode());
                $output = $this->commandTester->getDisplay();
                $this->assertEquals($version . "\n", $output);
            } finally {
                chdir($originalDir);
                TestHelper::cleanupTempDirectory($this->tempDir);
            }
        }
    }
}
