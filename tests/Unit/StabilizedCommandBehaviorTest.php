<?php

namespace OpenCart\CLI\Tests\Unit;

use OpenCart\CLI\Application;
use OpenCart\CLI\Commands\Core\ConfigCommand;
use OpenCart\CLI\Commands\Extension\DisableCommand;
use OpenCart\CLI\Commands\Extension\EnableCommand;
use OpenCart\CLI\Commands\Extension\InstallCommand;
use OpenCart\CLI\Commands\Extension\ListCommand as ExtensionListCommand;
use OpenCart\CLI\Commands\Product\CreateCommand;
use OpenCart\CLI\Commands\Product\ListCommand as ProductListCommand;
use OpenCart\CLI\Tests\Helpers\FakeDb;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class StabilizedCommandBehaviorTest extends TestCase
{
    public function testConfigCommandListsSharedSettingsAndWarnsOnAdminFlag()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'SELECT `key`, `value` FROM `oc_setting`') !== false) {
                return [
                    ['key' => 'config_name', 'value' => 'Demo Store'],
                    ['key' => 'config_email', 'value' => 'owner@example.com'],
                ];
            }

            return [];
        });

        $command = new class ($db) extends ConfigCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
        };
        $command->setApplication(new Application());

        $tester = new CommandTester($command);
        $tester->execute(['--admin' => true, '--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('deprecated', strtolower($tester->getDisplay()));
        $this->assertStringContainsString('Demo Store', $tester->getDisplay());
    }

    public function testExtensionListCommandShowsEnabledExtensions()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'FROM oc_extension e') !== false) {
                return [
                    ['type' => 'payment', 'code' => 'paypal', 'status' => 'enabled'],
                    ['type' => 'module', 'code' => 'featured', 'status' => 'enabled'],
                ];
            }

            return [];
        });

        $command = new class ($db) extends ExtensionListCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
                return $table === 'oc_extension';
            }
        };
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"paypal"', $tester->getDisplay());
        $this->assertStringContainsString('"featured"', $tester->getDisplay());
    }

    public function testExtensionEnableCommandSupportsTypeCodeIdentifiers()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'SELECT extension_id FROM oc_extension') !== false) {
                return [];
            }
            if (strpos($sql, 'INSERT INTO oc_extension') !== false) {
                return 1;
            }

            return [];
        });

        $command = new class ($db) extends EnableCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
                return $table === 'oc_extension';
            }
        };
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute(['extension' => 'payment:paypal']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('enabled successfully', strtolower($tester->getDisplay()));
    }

    public function testExtensionDisableCommandRemovesEnabledRows()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'SELECT type, code FROM oc_extension WHERE code =') !== false) {
                return [['type' => 'payment', 'code' => 'paypal']];
            }
            if (strpos($sql, 'SELECT extension_id FROM oc_extension') !== false) {
                return [['extension_id' => 10]];
            }
            if (strpos($sql, 'DELETE FROM oc_extension') !== false) {
                return 1;
            }

            return [];
        });

        $command = new class ($db) extends DisableCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
                return $table === 'oc_extension';
            }
        };
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute(['extension' => 'paypal']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('disabled successfully', strtolower($tester->getDisplay()));
    }

    public function testExtensionInstallImportsOcmodXml()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ocmod');
        $xmlFile = $tempFile . '.xml';
        rename($tempFile, $xmlFile);
        file_put_contents(
            $xmlFile,
            <<<XML
<modification>
  <name>Demo Modification</name>
  <code>demo_mod</code>
  <version>1.2.3</version>
  <author>Custom Services</author>
</modification>
XML
        );

        $db = new FakeDb(function ($sql, FakeDb $db) {
            if (strpos($sql, 'SELECT modification_id FROM oc_modification') !== false) {
                return [];
            }
            if (strpos($sql, 'INSERT INTO oc_modification') !== false) {
                $db->setLastId(55);
                return 1;
            }

            return [];
        });

        $command = new class ($db) extends InstallCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
                return $table === 'oc_modification';
            }
        };
        $command->setApplication(new Application());

        try {
            $tester = new CommandTester($command);
            $tester->execute(['extension' => $xmlFile, '--activate' => true]);

            $this->assertSame(0, $tester->getStatusCode());
            $this->assertStringContainsString('Demo Modification', $tester->getDisplay());
        } finally {
            unlink($xmlFile);
        }
    }

    public function testProductCreateUsesResolvedLanguageAndDefaultsSkuToModel()
    {
        $db = new FakeDb(function ($sql, FakeDb $db) {
            if (strpos($sql, "SELECT `value` FROM oc_setting") !== false) {
                return [['value' => '2']];
            }
            if (strpos($sql, 'SELECT COUNT(*) AS count FROM oc_product WHERE model =') !== false) {
                return [['count' => 0]];
            }
            if (strpos($sql, 'INSERT INTO oc_product (') !== false) {
                $db->setLastId(99);
                return 1;
            }
            if (strpos($sql, 'INSERT INTO oc_product_description') !== false) {
                return 1;
            }
            if (strpos($sql, 'INSERT INTO oc_product_to_store') !== false) {
                return 1;
            }
            if (strpos($sql, 'START TRANSACTION') !== false || strpos($sql, 'COMMIT') !== false) {
                return 0;
            }

            return [];
        });

        $command = new class ($db) extends CreateCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
                return $table === 'oc_product_to_store';
            }
        };
        $command->setApplication(new Application());

        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'Demo Product',
            'model' => 'DEMO-1',
            'price' => '19.99',
            '--status' => '1',
            '--format' => 'json',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"product_id": 99', $tester->getDisplay());
        $this->assertTrue($this->queryContains($db->queries, "INSERT INTO oc_product_description"));
        $this->assertTrue($this->queryContains($db->queries, "VALUES (\n    99,\n    2,"));
        $this->assertTrue($this->queryContains($db->queries, "'DEMO-1'"));
    }

    public function testProductListReturnsJsonResults()
    {
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, "SELECT `value` FROM oc_setting") !== false) {
                return [['value' => '2']];
            }
            if (strpos($sql, 'SELECT DISTINCT') !== false) {
                return [[
                    'product_id' => 12,
                    'name' => 'Demo Product',
                    'model' => 'DEMO-1',
                    'price' => '19.9900',
                    'status' => 1,
                    'category_name' => 'Featured',
                    'quantity' => 8,
                    'date_added' => '2026-03-12 10:00:00',
                ]];
            }

            return [];
        });

        $command = new class ($db) extends ProductListCommand {
            private $db;
            public function __construct($db)
            {
                parent::__construct();
                $this->db = $db;
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
        };
        $command->setApplication(new Application());

        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"Demo Product"', $tester->getDisplay());
        $this->assertStringContainsString('"category": "Featured"', $tester->getDisplay());
    }

    /**
     * @param list<string> $queries
     * @param string $needle
     * @return bool
     */
    private function queryContains(array $queries, $needle)
    {
        foreach ($queries as $query) {
            if (strpos($query, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
