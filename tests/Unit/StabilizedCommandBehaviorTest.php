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
use OpenCart\CLI\Support\OpenCartRuntime;
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
        $capture = (object) ['payload' => null];
        $productModel = new class ($capture) {
            private $capture;
            public function __construct($capture)
            {
                $this->capture = $capture;
            }
            public function getProducts($data = [])
            {
                return [];
            }
            public function addProduct($data)
            {
                $this->capture->payload = $data;

                return 99;
            }
        };
        $runtime = $this->getMockBuilder(OpenCartRuntime::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['model', 'registry', 'database'])
            ->getMock();
        $runtime->method('model')->willReturn($productModel);
        $runtime->method('registry')->willReturn(new class {
            public function get($key)
            {
                if ($key !== 'config') {
                    return null;
                }

                return new class {
                    public function get($name)
                    {
                        $values = [
                            'config_language_id' => 2,
                            'config_stock_status_id' => 7,
                            'config_weight_class_id' => 1,
                            'config_length_class_id' => 1,
                        ];

                        return $values[$name] ?? null;
                    }
                };
            }
        });
        $runtime->method('database')->willReturn(new FakeDb(function () {
            return [];
        }));

        $command = new class ($runtime) extends CreateCommand {
            private OpenCartRuntime $runtime;
            public function __construct(OpenCartRuntime $runtime)
            {
                parent::__construct();
                $this->runtime = $runtime;
            }
            protected function requireOpenCartThreeRuntime(): bool
            {
                return true;
            }
            protected function getAdminRuntime(): OpenCartRuntime
            {
                return $this->runtime;
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
        $this->assertNotNull($capture->payload);
        $this->assertSame('DEMO-1', $capture->payload['sku']);
        $this->assertSame('Demo Product', $capture->payload['product_description'][2]['name']);
        $this->assertSame(
            'Demo Product',
            $capture->payload['product_description'][2]['meta_title']
        );
    }

    public function testProductListReturnsJsonResults()
    {
        $productModel = new class {
            public function getProducts($data = [])
            {
                return [[
                    'product_id' => 12,
                    'name' => 'Demo Product',
                    'model' => 'DEMO-1',
                    'price' => '19.9900',
                    'status' => 1,
                    'quantity' => 8,
                    'date_added' => '2026-03-12 10:00:00',
                ]];
            }
        };
        $db = new FakeDb(function ($sql) {
            if (strpos($sql, 'FROM `oc_product_to_category`') !== false) {
                return [['name' => 'Featured']];
            }

            return [];
        });
        $runtime = $this->getMockBuilder(OpenCartRuntime::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['model', 'database', 'getDatabasePrefix', 'registry'])
            ->getMock();
        $runtime->method('model')->willReturn($productModel);
        $runtime->method('database')->willReturn($db);
        $runtime->method('getDatabasePrefix')->willReturn('oc_');
        $runtime->method('registry')->willReturn(new class {
            public function get($key)
            {
                if ($key !== 'config') {
                    return null;
                }

                return new class {
                    public function get($name)
                    {
                        if ($name === 'config_language_id') {
                            return 2;
                        }

                        return null;
                    }
                };
            }
        });

        $command = new class ($runtime) extends ProductListCommand {
            private OpenCartRuntime $runtime;
            public function __construct(OpenCartRuntime $runtime)
            {
                parent::__construct();
                $this->runtime = $runtime;
            }
            protected function requireOpenCartThreeRuntime(): bool
            {
                return true;
            }
            protected function getAdminRuntime(): OpenCartRuntime
            {
                return $this->runtime;
            }
        };
        $command->setApplication(new Application());

        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('"Demo Product"', $tester->getDisplay());
        $this->assertStringContainsString('"category": "Featured"', $tester->getDisplay());
    }
}
