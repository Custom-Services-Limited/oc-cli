<?php

namespace OpenCart\CLI\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BinarySmokeTest extends TestCase
{
    public function testBinOcListRawDoesNotEmitWarnings()
    {
        $process = new Process([PHP_BINARY, 'bin/oc', 'list', '--raw'], dirname(__DIR__, 2), ['APP_ENV' => '']);
        $process->run();
        $combinedOutput = $process->getOutput() . $process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $combinedOutput);
        $this->assertStringNotContainsString('Deprecated:', $combinedOutput);
        $this->assertStringContainsString('core:version', $combinedOutput);
    }
}
