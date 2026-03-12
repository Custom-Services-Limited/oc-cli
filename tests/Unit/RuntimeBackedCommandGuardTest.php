<?php

namespace OpenCart\CLI\Tests\Unit;

use OpenCart\CLI\Application;
use OpenCart\CLI\Commands\Cache\ClearCommand;
use OpenCart\CLI\Commands\Order\UpdateStatusCommand;
use OpenCart\CLI\Tests\Helpers\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RuntimeBackedCommandGuardTest extends TestCase
{
    public function testRuntimeBackedCommandsRejectDatabaseOnlyOptions(): void
    {
        $command = new ClearCommand();
        $command->setApplication(new Application());

        $input = new ArrayInput([
            '--db-host' => 'localhost',
            '--db-user' => 'oc',
            '--db-pass' => 'oc',
            '--db-name' => 'opencart',
        ]);
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('real OpenCart installation root', $output->fetch());
    }

    public function testRuntimeBackedCommandsFailFastOnUnsupportedVersion(): void
    {
        $root = TestHelper::createTempOpenCartInstallation([], '2.3.0.2');

        try {
            $command = new UpdateStatusCommand();
            $command->setApplication(new Application());

            $input = new ArrayInput([
                '--opencart-root' => $root,
                'order-id' => '1',
                'status' => 'Processing',
            ]);
            $output = new BufferedOutput();

            $result = $command->run($input, $output);

            $this->assertSame(1, $result);
            $this->assertStringContainsString('supported on OpenCart 3.x only', $output->fetch());
        } finally {
            TestHelper::cleanupTempDirectory($root);
        }
    }
}
