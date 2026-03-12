<?php

namespace OpenCart\CLI\Tests\Unit;

use OpenCart\CLI\Application;
use OpenCart\CLI\Commands\Database\CheckCommand;
use OpenCart\CLI\Commands\Database\CleanupCommand;
use OpenCart\CLI\Commands\Database\OptimizeCommand;
use OpenCart\CLI\Commands\Database\RepairCommand;
use OpenCart\CLI\Tests\Helpers\FakeDb;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DatabaseMaintenanceCommandsTest extends TestCase
{
    public function testCheckCommandDiscoversPrefixedTablesAndReportsOk()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, "SHOW TABLES LIKE 'oc_%'") !== false) {
                return [
                    ['Tables_in_demo (oc_%)' => 'oc_product'],
                    ['Tables_in_demo (oc_%)' => 'oc_order'],
                ];
            }

            if (strpos($sql, 'CHECK TABLE `oc_product`') !== false) {
                return [['Msg_type' => 'status', 'Msg_text' => 'OK']];
            }

            if (strpos($sql, 'CHECK TABLE `oc_order`') !== false) {
                return [['Msg_type' => 'status', 'Msg_text' => 'OK']];
            }

            return [];
        });

        $command = $this->makeCheckCommand($db);
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"table": "oc_product"', $tester->getDisplay());
        $this->assertStringContainsString('"table": "oc_order"', $tester->getDisplay());
    }

    public function testRepairCommandTreatsEngineNotesAsNonFatal()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, "SHOW TABLES LIKE 'oc_%'") !== false) {
                return [['Tables_in_demo (oc_%)' => 'oc_session']];
            }

            if (strpos($sql, 'REPAIR TABLE `oc_session`') !== false) {
                return [['Msg_type' => 'note', 'Msg_text' => "The storage engine doesn't support repair"]];
            }

            return [];
        });

        $command = $this->makeRepairCommand($db);
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("doesn't support repair", $tester->getDisplay());
        $this->assertStringContainsString('"status": "ok"', $tester->getDisplay());
    }

    public function testOptimizeCommandCanTargetExplicitTables()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, "SHOW TABLES LIKE 'oc_product'") !== false) {
                return [['Tables_in_demo (oc_product)' => 'oc_product']];
            }

            if (strpos($sql, 'OPTIMIZE TABLE `oc_product`') !== false) {
                return [['Msg_type' => 'status', 'Msg_text' => 'Table is already up to date']];
            }

            return [];
        });

        $command = $this->makeOptimizeCommand($db, [
            'oc_product' => true,
        ]);
        $tester = new CommandTester($command);
        $tester->execute(['tables' => ['product'], '--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"table": "oc_product"', $tester->getDisplay());
        $this->assertStringContainsString('already up to date', $tester->getDisplay());
    }

    public function testCleanupCommandOnlyTouchesTransientTables()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'SELECT COUNT(*) AS total FROM `oc_session`') !== false) {
                return [['total' => 3]];
            }

            if (strpos($sql, 'SELECT COUNT(*) AS total FROM `oc_api_session`') !== false) {
                return [['total' => 2]];
            }

            if (strpos($sql, 'SELECT COUNT(*) AS total FROM `oc_customer_online`') !== false) {
                return [['total' => 1]];
            }

            return 1;
        });

        $command = $this->makeCleanupCommand($db, [
            'oc_session' => true,
            'oc_api_session' => true,
            'oc_customer_online' => true,
        ]);
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $display = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Removed 3 transient rows', $display);
        $this->assertStringContainsString('Removed 2 transient rows', $display);
        $this->assertStringContainsString('Removed 1 transient rows', $display);
        $this->assertStringNotContainsString('oc_product', $display);

        $queries = $db->queries;
        $this->assertContains('DELETE FROM `oc_session`', $queries);
        $this->assertContains('DELETE FROM `oc_api_session`', $queries);
        $this->assertContains('DELETE FROM `oc_customer_online`', $queries);
    }

    public function testCleanupCommandSkipsMissingTransientTables()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'SELECT COUNT(*) AS total FROM `oc_session`') !== false) {
                return [['total' => 0]];
            }

            return 1;
        });

        $command = $this->makeCleanupCommand($db, [
            'oc_session' => true,
            'oc_api_session' => false,
            'oc_customer_online' => false,
        ]);
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $display = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"table": "oc_api_session"', $display);
        $this->assertStringContainsString('"status": "skipped"', $display);
    }

    /**
     * @param array<string, bool> $existingTables
     * @return object
     */
    private function makeCheckCommand(FakeDb $db, array $existingTables = [])
    {
        $command = new class ($db, $existingTables) extends CheckCommand {
            private $db;
            private $existingTables;

            public function __construct(FakeDb $db, array $existingTables)
            {
                parent::__construct();
                $this->db = $db;
                $this->existingTables = $existingTables;
            }

            protected function requireOpenCart($require = true)
            {
                return true;
            }

            protected function getDatabaseConnection()
            {
                return $this->db;
            }

            protected function getOpenCartConfig()
            {
                return ['db_prefix' => 'oc_'];
            }

            protected function tableExists($db, $table)
            {
                return isset($this->existingTables[$table]) ? $this->existingTables[$table] : true;
            }
        };

        $command->setApplication(new Application());

        return $command;
    }

    /**
     * @param array<string, bool> $existingTables
     * @return object
     */
    private function makeRepairCommand(FakeDb $db, array $existingTables = [])
    {
        $command = new class ($db, $existingTables) extends RepairCommand {
            private $db;
            private $existingTables;

            public function __construct(FakeDb $db, array $existingTables)
            {
                parent::__construct();
                $this->db = $db;
                $this->existingTables = $existingTables;
            }

            protected function requireOpenCart($require = true)
            {
                return true;
            }

            protected function getDatabaseConnection()
            {
                return $this->db;
            }

            protected function getOpenCartConfig()
            {
                return ['db_prefix' => 'oc_'];
            }

            protected function tableExists($db, $table)
            {
                return isset($this->existingTables[$table]) ? $this->existingTables[$table] : true;
            }
        };

        $command->setApplication(new Application());

        return $command;
    }

    /**
     * @param array<string, bool> $existingTables
     * @return object
     */
    private function makeOptimizeCommand(FakeDb $db, array $existingTables = [])
    {
        $command = new class ($db, $existingTables) extends OptimizeCommand {
            private $db;
            private $existingTables;

            public function __construct(FakeDb $db, array $existingTables)
            {
                parent::__construct();
                $this->db = $db;
                $this->existingTables = $existingTables;
            }

            protected function requireOpenCart($require = true)
            {
                return true;
            }

            protected function getDatabaseConnection()
            {
                return $this->db;
            }

            protected function getOpenCartConfig()
            {
                return ['db_prefix' => 'oc_'];
            }

            protected function tableExists($db, $table)
            {
                return isset($this->existingTables[$table]) ? $this->existingTables[$table] : true;
            }
        };

        $command->setApplication(new Application());

        return $command;
    }

    /**
     * @param array<string, bool> $existingTables
     * @return object
     */
    private function makeCleanupCommand(FakeDb $db, array $existingTables = [])
    {
        $command = new class ($db, $existingTables) extends CleanupCommand {
            private $db;
            private $existingTables;

            public function __construct(FakeDb $db, array $existingTables)
            {
                parent::__construct();
                $this->db = $db;
                $this->existingTables = $existingTables;
            }

            protected function requireOpenCart($require = true)
            {
                return true;
            }

            protected function getDatabaseConnection()
            {
                return $this->db;
            }

            protected function getOpenCartConfig()
            {
                return ['db_prefix' => 'oc_'];
            }

            protected function tableExists($db, $table)
            {
                return isset($this->existingTables[$table]) ? $this->existingTables[$table] : true;
            }
        };

        $command->setApplication(new Application());

        return $command;
    }
}
