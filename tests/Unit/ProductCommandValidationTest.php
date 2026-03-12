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
use OpenCart\CLI\Commands\Product\CreateCommand;
use OpenCart\CLI\Tests\Helpers\InvokesNonPublicMembers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProductCommandValidationTest extends TestCase
{
    use InvokesNonPublicMembers;

    /**
     * @var CreateCommand
     */
    private $command;

    /**
     * @var BufferedOutput
     */
    private $output;

    protected function setUp(): void
    {
        $application = new Application();
        $this->command = new CreateCommand();
        $this->command->setApplication($application);

        $input = new ArrayInput(['command' => 'product:create']);
        $this->output = new BufferedOutput();

        $this->setProperty($this->command, 'input', $input);
        $this->setProperty($this->command, 'output', $this->output);
        $this->setProperty($this->command, 'io', new SymfonyStyle($input, $this->output));
    }

    public function testValidateProductDataValidData()
    {
        $this->assertTrue(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => 'Test Product', 'model' => 'TEST001', 'price' => '19.99', 'status' => 'enabled']
            )
        );
    }

    public function testValidateProductDataRejectsMissingName()
    {
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => '', 'model' => 'TEST001', 'price' => '19.99', 'status' => 'enabled']
            )
        );

        $this->assertStringContainsString('Product name is required', $this->output->fetch());
    }

    public function testValidateProductDataRejectsWhitespaceOnlyName()
    {
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => '   ', 'model' => 'TEST001', 'price' => '19.99', 'status' => 'enabled']
            )
        );
    }

    public function testValidateProductDataRejectsMissingModel()
    {
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => 'Test Product', 'model' => '', 'price' => '19.99', 'status' => 'enabled']
            )
        );
    }

    public function testValidateProductDataRejectsInvalidPrice()
    {
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => 'Test Product', 'model' => 'TEST001', 'price' => 'invalid', 'status' => 'enabled']
            )
        );
    }

    public function testValidateProductDataRejectsNegativePrice()
    {
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => 'Test Product', 'model' => 'TEST001', 'price' => '-10.50', 'status' => 'enabled']
            )
        );
    }

    public function testValidateProductDataAcceptsDisabledAndZeroPrice()
    {
        $this->assertTrue(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => 'Free Product', 'model' => 'FREE001', 'price' => '0', 'status' => 'disabled']
            )
        );
    }

    public function testValidateProductDataRejectsInvalidStatus()
    {
        $this->assertFalse(
            $this->invokeMethod(
                $this->command,
                'validateProductData',
                ['name' => 'Test Product', 'model' => 'TEST001', 'price' => '19.99', 'status' => 'invalid_status']
            )
        );
    }

    public function testNormaliseStatusAliases()
    {
        $this->assertEquals('enabled', $this->invokeMethod($this->command, 'normaliseStatus', '1'));
        $this->assertEquals('enabled', $this->invokeMethod($this->command, 'normaliseStatus', 'true'));
        $this->assertEquals('disabled', $this->invokeMethod($this->command, 'normaliseStatus', '0'));
        $this->assertEquals('disabled', $this->invokeMethod($this->command, 'normaliseStatus', 'false'));
    }
}
