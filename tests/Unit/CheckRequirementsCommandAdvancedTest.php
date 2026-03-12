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

use OpenCart\CLI\Application;
use OpenCart\CLI\Commands\Core\CheckRequirementsCommand;
use OpenCart\CLI\Tests\Helpers\InvokesNonPublicMembers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckRequirementsCommandAdvancedTest extends TestCase
{
    use InvokesNonPublicMembers;

    /**
     * @var CheckRequirementsCommand
     */
    private $command;

    protected function setUp(): void
    {
        $application = new Application();
        $this->command = new CheckRequirementsCommand();
        $this->command->setApplication($application);
    }

    public function testConvertToBytesVariants()
    {
        $this->assertEquals(512, $this->invokeMethod($this->command, 'convertToBytes', '512'));
        $this->assertEquals(2048, $this->invokeMethod($this->command, 'convertToBytes', '2k'));
        $this->assertEquals(4096, $this->invokeMethod($this->command, 'convertToBytes', '4K'));
        $this->assertEquals(16777216, $this->invokeMethod($this->command, 'convertToBytes', '16m'));
        $this->assertEquals(33554432, $this->invokeMethod($this->command, 'convertToBytes', '32M'));
        $this->assertEquals(2147483648, $this->invokeMethod($this->command, 'convertToBytes', '2g'));
        $this->assertEquals(1073741824, $this->invokeMethod($this->command, 'convertToBytes', '1G'));
        $this->assertEquals(134217728, $this->invokeMethod($this->command, 'convertToBytes', ' 128M '));
        $this->assertEquals(0, $this->invokeMethod($this->command, 'convertToBytes', '0'));
        $this->assertEquals(100, $this->invokeMethod($this->command, 'convertToBytes', '100x'));
        $this->assertEquals(1048576, $this->invokeMethod($this->command, 'convertToBytes', '1.5M'));
        $this->assertEquals(-10, $this->invokeMethod($this->command, 'convertToBytes', '-10'));
    }

    public function testHasFailuresScenarios()
    {
        $this->assertFalse($this->invokeMethod($this->command, 'hasFailures', []));
        $this->assertFalse($this->invokeMethod($this->command, 'hasFailures', ['php' => [], 'extensions' => []]));
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'hasFailures',
                ['php' => [['name' => 'PHP Version', 'status' => true, 'message' => 'OK']]]
            )
        );
        $this->assertTrue(
            $this->invokeMethod(
                $this->command,
                'hasFailures',
                ['php' => [['name' => 'Memory Limit', 'status' => false, 'message' => 'Too low']]]
            )
        );
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'hasFailures',
                ['extensions' => [[
                    'name' => 'Extension: mcrypt (recommended)',
                    'status' => false,
                    'message' => 'Missing',
                    'required' => false,
                ]]]
            )
        );
    }

    public function testCheckPhpRequirementsAndExtensionsStructure()
    {
        $requirements = $this->invokeMethod($this->command, 'checkPhpRequirements');
        $extensions = $this->invokeMethod($this->command, 'checkPhpExtensions');

        $this->assertNotEmpty($requirements);
        $this->assertContains('PHP Version >= 7.4', array_column($requirements, 'name'));
        $this->assertContains('Extension: curl (required)', array_column($extensions, 'name'));
        $this->assertContains('Extension: mysqli (recommended)', array_column($extensions, 'name'));
    }

    public function testCheckMemoryAndExecutionTimeReturnBooleans()
    {
        $this->assertIsBool($this->invokeMethod($this->command, 'checkMemoryLimit'));
        $this->assertIsBool($this->invokeMethod($this->command, 'checkExecutionTime'));
    }

    public function testDisplayTableOutput()
    {
        $input = new ArrayInput(['command' => 'core:check-requirements']);
        $output = new BufferedOutput();

        $this->setProperty($this->command, 'input', $input);
        $this->setProperty($this->command, 'output', $output);
        $this->setProperty($this->command, 'io', new SymfonyStyle($input, $output));

        $this->invokeMethod(
            $this->command,
            'displayTable',
            ['php' => [
                ['name' => 'PHP Version', 'status' => true, 'message' => 'OK'],
                ['name' => 'Memory Limit', 'status' => false, 'message' => 'Too low'],
            ]]
        );

        $outputContent = $output->fetch();
        $this->assertStringContainsString('PHP Version', $outputContent);
        $this->assertStringContainsString('Memory Limit', $outputContent);
    }

    public function testCheckRequirementsStructure()
    {
        $result = $this->invokeMethod($this->command, 'checkRequirements');

        $this->assertArrayHasKey('php', $result);
        $this->assertArrayHasKey('extensions', $result);
        $this->assertArrayHasKey('permissions', $result);
        $this->assertArrayHasKey('database', $result);
    }
}
