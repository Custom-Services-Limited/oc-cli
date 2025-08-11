<?php

namespace OpenCart\CLI\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\Application;

class ExtensionCommandsTest extends TestCase
{
    private $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function testExtensionCommandsAreRegistered()
    {
        $commands = $this->application->all();

        $this->assertArrayHasKey('extension:list', $commands);
        $this->assertArrayHasKey('extension:install', $commands);
        $this->assertArrayHasKey('extension:enable', $commands);
        $this->assertArrayHasKey('extension:disable', $commands);
        $this->assertArrayHasKey('modification:list', $commands);
    }

    public function testExtensionCommandsHaveCorrectNames()
    {
        $extensionList = $this->application->find('extension:list');
        $extensionInstall = $this->application->find('extension:install');
        $extensionEnable = $this->application->find('extension:enable');
        $extensionDisable = $this->application->find('extension:disable');
        $modificationList = $this->application->find('modification:list');

        $this->assertEquals('extension:list', $extensionList->getName());
        $this->assertEquals('extension:install', $extensionInstall->getName());
        $this->assertEquals('extension:enable', $extensionEnable->getName());
        $this->assertEquals('extension:disable', $extensionDisable->getName());
        $this->assertEquals('modification:list', $modificationList->getName());
    }

    public function testExtensionCommandsHaveCorrectDescriptions()
    {
        $extensionList = $this->application->find('extension:list');
        $extensionInstall = $this->application->find('extension:install');
        $extensionEnable = $this->application->find('extension:enable');
        $extensionDisable = $this->application->find('extension:disable');
        $modificationList = $this->application->find('modification:list');

        $this->assertEquals('List installed extensions', $extensionList->getDescription());
        $this->assertEquals('Install an extension', $extensionInstall->getDescription());
        $this->assertEquals('Enable an extension', $extensionEnable->getDescription());
        $this->assertEquals('Disable an extension', $extensionDisable->getDescription());
        $this->assertEquals('List installed modifications', $modificationList->getDescription());
    }

    public function testAllExtensionCommandsHaveOpenCartRootOption()
    {
        $commands = [
            'extension:list', 'extension:install', 'extension:enable',
            'extension:disable', 'modification:list'
        ];

        foreach ($commands as $commandName) {
            $command = $this->application->find($commandName);
            $definition = $command->getDefinition();

            $this->assertTrue(
                $definition->hasOption('opencart-root'),
                "Command {$commandName} should have --opencart-root option"
            );
        }
    }

    public function testExtensionListCommandHasFormatOption()
    {
        $command = $this->application->find('extension:list');
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testModificationListCommandHasFormatOption()
    {
        $command = $this->application->find('modification:list');
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $formatOption = $definition->getOption('format');
        $this->assertEquals('table', $formatOption->getDefault());
    }

    public function testExtensionInstallCommandHasActivateOption()
    {
        $command = $this->application->find('extension:install');
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('activate'));
        $activateOption = $definition->getOption('activate');
        $this->assertEquals('a', $activateOption->getShortcut());
    }

    public function testExtensionCommandsHaveRequiredArguments()
    {
        // extension:install, extension:enable, extension:disable should have required extension argument
        $commandsWithRequiredArgs = ['extension:install', 'extension:enable', 'extension:disable'];

        foreach ($commandsWithRequiredArgs as $commandName) {
            $command = $this->application->find($commandName);
            $definition = $command->getDefinition();

            $this->assertTrue($definition->hasArgument('extension'));
            $extensionArg = $definition->getArgument('extension');
            $this->assertTrue($extensionArg->isRequired());
        }
    }

    public function testExtensionListCommandHasOptionalTypeArgument()
    {
        $command = $this->application->find('extension:list');
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('type'));
        $typeArg = $definition->getArgument('type');
        $this->assertFalse($typeArg->isRequired());
    }
}
