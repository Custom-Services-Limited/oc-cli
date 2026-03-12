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
        $capturedPayload = null;
        $productModel = new class (&$capturedPayload) {
            private $payload;
            public function __construct(&$payload)
            {
                $this->payload = &$payload;
            }
            public function getProducts($data = [])
            {
                return [];
            }
            public function addProduct($data)
            {
                $this->payload = $data;

                return 99;
            }
        };
        $runtime = new class ($productModel) {
            private $productModel;
            public function __construct($productModel)
            {
                $this->productModel = $productModel;
            }
            public function model($route)
            {
                return $this->productModel;
            }
            public function registry()
            {
                return new class {
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
                };
            }
            public function database()
            {
                return new FakeDb(function () {
                    return [];
                });
            }
        };

        $command = new class ($runtime) extends CreateCommand {
            private $runtime;
            public function __construct($runtime)
            {
                parent::__construct();
                $this->runtime = $runtime;
            }
            protected function requireOpenCartThreeRuntime()
            {
                return true;
            }
            protected function getAdminRuntime()
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
        $this->assertNotNull($capturedPayload);
        $this->assertSame('DEMO-1', $capturedPayload['sku']);
        $this->assertSame('Demo Product', $capturedPayload['product_description'][2]['name']);
        $this->assertSame(
            'Demo Product',
            $capturedPayload['product_description'][2]['meta_title']
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
        $runtime = new class ($productModel, $db) {
            private $productModel;
            private $db;
            public function __construct($productModel, $db)
            {
                $this->productModel = $productModel;
                $this->db = $db;
            }
            public function model($route)
            {
                return $this->productModel;
            }
            public function database()
            {
                return $this->db;
            }
            public function getDatabasePrefix()
            {
                return 'oc_';
            }
            public function registry()
            {
                return new class {
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
                };
            }
        };

        $command = new class ($runtime) extends ProductListCommand {
            private $runtime;
            public function __construct($runtime)
            {
                parent::__construct();
                $this->runtime = $runtime;
            }
            protected function requireOpenCartThreeRuntime()
            {
                return true;
            }
            protected function getAdminRuntime()
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
