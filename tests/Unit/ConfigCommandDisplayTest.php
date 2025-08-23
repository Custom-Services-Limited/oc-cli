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
use OpenCart\CLI\Commands\Core\ConfigCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use ReflectionClass;

class ConfigCommandDisplayTest extends TestCase
{
    /**
     * @var ConfigCommand
     */
    private $command;

    /**
     * @var Application
     */
    private $application;

    /**
     * @var ReflectionClass
     */
    private $reflection;

    /**
     * @var BufferedOutput
     */
    private $output;

    /**
     * @var ArrayInput
     */
    private $input;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->command = new ConfigCommand();
        $this->command->setApplication($this->application);
        $this->reflection = new ReflectionClass($this->command);
        
        $this->input = new ArrayInput(['command' => 'core:config']);
        $this->output = new BufferedOutput();
        
        // Set up command properties
        $this->setCommandProperty('input', $this->input);
        $this->setCommandProperty('output', $this->output);
        $this->setCommandProperty('io', new SymfonyStyle($this->input, $this->output));
    }

    private function setCommandProperty($propertyName, $value)
    {
        $property = $this->reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($this->command, $value);
    }

    public function testDisplayConfigTableWithEmptyConfig()
    {
        $method = $this->reflection->getMethod('displayConfigTable');
        $method->setAccessible(true);
        
        $config = [];
        
        $method->invoke($this->command, $config, false);
        
        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('No configuration found', $outputContent);
    }

    public function testDisplayConfigTableWithConfig()
    {
        $method = $this->reflection->getMethod('displayConfigTable');
        $method->setAccessible(true);
        
        $config = [
            'config_name' => 'Test Store',
            'config_meta_title' => 'My Store',
            'config_email' => 'test@example.com'
        ];
        
        $method->invoke($this->command, $config, false);
        
        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('Test Store', $outputContent);
        $this->assertStringContainsString('config_name', $outputContent);
    }

    public function testDisplayConfigTableWithAdminConfig()
    {
        $method = $this->reflection->getMethod('displayConfigTable');
        $method->setAccessible(true);
        
        $config = [
            'config_admin_limit' => '20',
            'config_compression' => '9'
        ];
        
        $method->invoke($this->command, $config, true);
        
        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('Admin Configuration', $outputContent);
    }

    public function testDisplayConfigTableStructure()
    {
        $method = $this->reflection->getMethod('displayConfigTable');
        $method->setAccessible(true);
        
        $config = [
            'config_name' => 'Test Store',
            'config_owner' => 'John Doe',
            'config_address' => '123 Main St',
            'config_email' => 'test@example.com',
            'config_telephone' => '+1234567890'
        ];
        
        $method->invoke($this->command, $config, false);
        
        $outputContent = $this->output->fetch();
        
        // Check that all config items are displayed
        $this->assertStringContainsString('config_name', $outputContent);
        $this->assertStringContainsString('Test Store', $outputContent);
        $this->assertStringContainsString('config_owner', $outputContent);
        $this->assertStringContainsString('John Doe', $outputContent);
        $this->assertStringContainsString('config_email', $outputContent);
        $this->assertStringContainsString('test@example.com', $outputContent);
    }

    public function testDisplayConfigTableWithLongValues()
    {
        $method = $this->reflection->getMethod('displayConfigTable');
        $method->setAccessible(true);
        
        $longValue = str_repeat('A', 100);
        $config = [
            'config_description' => $longValue
        ];
        
        $method->invoke($this->command, $config, false);
        
        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('config_description', $outputContent);
        // Should handle long values appropriately
        $this->assertNotEmpty($outputContent);
    }

    public function testDisplayConfigTableWithSpecialCharacters()
    {
        $method = $this->reflection->getMethod('displayConfigTable');
        $method->setAccessible(true);
        
        $config = [
            'config_name' => 'Ståre with Spëcial Chàrs',
            'config_currency' => 'USD ($)',
            'config_meta' => 'Description with "quotes" and <tags>'
        ];
        
        $method->invoke($this->command, $config, false);
        
        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('Ståre with Spëcial Chàrs', $outputContent);
        $this->assertStringContainsString('USD ($)', $outputContent);
    }
}