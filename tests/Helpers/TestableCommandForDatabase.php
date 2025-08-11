<?php

namespace OpenCart\CLI\Tests\Helpers;

use OpenCart\CLI\Command;

/**
 * Extended testable command for database testing
 */
class TestableCommandForDatabase extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setName('test:database-command');
    }

    protected function handle()
    {
        return 0;
    }

    // Public wrappers for protected methods
    public function executePublic($input, $output)
    {
        return $this->execute($input, $output);
    }

    public function requireOpenCartPublic($require = true)
    {
        return $this->requireOpenCart($require);
    }

    public function getOpenCartConfigPublic()
    {
        return $this->getOpenCartConfig();
    }

    public function getDatabaseConnectionPublic()
    {
        return $this->getDatabaseConnection();
    }

    public function queryPublic($sql, $params = [])
    {
        return $this->query($sql, $params);
    }

    public function formatBytesPublic($bytes, $precision = 2)
    {
        return $this->formatBytes($bytes, $precision);
    }

    public function setOpenCartRootPublic($path)
    {
        $this->openCartRoot = $path;
    }
}
