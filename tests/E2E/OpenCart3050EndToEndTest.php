<?php

namespace OpenCart\CLI\Tests\E2E;

use mysqli;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class OpenCart3050EndToEndTest extends TestCase
{
    /**
     * @var string
     */
    private static $repoRoot;

    /**
     * @var string
     */
    private static $openCartRoot;

    /**
     * @var string
     */
    private static $artifactsDir;

    /**
     * @var array<string, string|int>
     */
    private static $dbConfig;

    public static function setUpBeforeClass(): void
    {
        if (!getenv('OC_E2E_ROOT') || !getenv('OC_E2E_DB_PREFIX')) {
            self::markTestSkipped('E2E environment not provisioned. Run composer test:e2e.');
        }

        self::$repoRoot = dirname(__DIR__, 2);
        self::$openCartRoot = (string) getenv('OC_E2E_ROOT');
        self::$artifactsDir = (string) getenv('OC_E2E_ARTIFACTS_DIR');
        self::$dbConfig = [
            'host' => (string) getenv('OC_E2E_DB_HOST'),
            'name' => (string) getenv('OC_E2E_DB_NAME'),
            'user' => (string) getenv('OC_E2E_DB_USER'),
            'pass' => (string) getenv('OC_E2E_DB_PASS'),
            'port' => (int) getenv('OC_E2E_DB_PORT'),
            'prefix' => (string) getenv('OC_E2E_DB_PREFIX'),
        ];
    }

    public function testOcCliAgainstProvisionedOpenCart3050(): void
    {
        $versionOutput = trim($this->runOc([
            'core:version',
            '--opencart',
            '--opencart-root=' . self::$openCartRoot,
        ]));
        $this->assertSame('3.0.5.0', $versionOutput);

        $versionJson = $this->runOcJson([
            'core:version',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame('3.0.5.0', $versionJson['opencart']);

        $requirements = $this->runOcJson([
            'core:check-requirements',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($requirements['permissions']);
        $this->assertTrue($this->findRequirementStatus($requirements['database'], 'Database Connection'));

        $dbInfo = $this->runOcJson([
            'db:info',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame('Connected', $dbInfo['connection_status']);
        $this->assertSame(self::$dbConfig['name'], $dbInfo['database']);
        $this->assertSame(self::$dbConfig['prefix'], $dbInfo['prefix']);

        $configList = $this->runOcJson([
            'core:config',
            'list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertArrayHasKey('config_name', $configList);
        $this->assertArrayHasKey('config_email', $configList);

        $adminOutput = $this->runOc([
            'core:config',
            'list',
            '--admin',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('deprecated', strtolower($adminOutput));

        $baselineStoreName = trim($this->runOc([
            'core:config',
            'get',
            'config_name',
            '--opencart-root=' . self::$openCartRoot,
        ]));
        $this->assertNotSame('', $baselineStoreName);

        $productList = $this->runOcJson([
            'product:list',
            '--format=json',
            '--limit=10',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($productList);

        $iphoneResults = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=iPhone',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertContains('iPhone', array_column($iphoneResults, 'name'));

        $macbookResults = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=MacBook',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertContains('MacBook', array_column($macbookResults, 'name'));

        $categoryList = $this->runOcJson([
            'category:list',
            '--format=json',
            '--limit=10',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($categoryList);

        $extensions = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasExtension($extensions, 'payment', 'cod'));
        $this->assertTrue($this->hasExtension($extensions, 'shipping', 'flat'));
        $this->assertTrue($this->hasExtension($extensions, 'module', 'featured'));

        $backupPath = self::$artifactsDir . '/baseline.sql';
        $backupOutput = $this->runOc([
            'db:backup',
            basename($backupPath),
            '--output-dir=' . self::$artifactsDir,
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertFileExists($backupPath);
        $this->assertStringContainsString('Backup created successfully', $backupOutput);

        $seededOrder = $this->seedDeterministicOrder();
        $this->seedTransientRows();

        $newStoreName = 'OC-CLI E2E Store';
        $setConfigOutput = $this->runOc([
            'core:config',
            'set',
            'config_name',
            $newStoreName,
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('Configuration', $setConfigOutput);
        $this->assertSame(
            $newStoreName,
            trim($this->runOc([
                'core:config',
                'get',
                'config_name',
                '--opencart-root=' . self::$openCartRoot,
            ]))
        );

        $createdCategoryName = 'OC-CLI E2E Category';
        $categoryCreateOutput = $this->runOc([
            'category:create',
            $createdCategoryName,
            '--description=Seeded from E2E',
            '--keyword=oc-cli-e2e-category',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('created successfully', strtolower($categoryCreateOutput));

        $createdCategory = $this->runOcJson([
            'category:list',
            '--name=' . $createdCategoryName,
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($createdCategory);

        $userList = $this->runOcJson([
            'user:list',
            '--format=json',
            '--limit=10',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($userList);

        $userCreateOutput = $this->runOc([
            'user:create',
            'oc_cli_e2e',
            'oc-cli-e2e@example.test',
            'Secret123!',
            '--firstname=OC',
            '--lastname=CLI',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('created successfully', strtolower($userCreateOutput));

        $createdUser = $this->fetchSingleRow(
            "SELECT user_id FROM `" . self::$dbConfig['prefix'] . "user` WHERE username = ? LIMIT 1",
            ['oc_cli_e2e']
        );
        $this->assertNotNull($createdUser);

        $userListAfterCreate = $this->runOcJson([
            'user:list',
            '--format=json',
            '--limit=20',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertContains('oc_cli_e2e', array_column($userListAfterCreate, 'username'));

        $userDeleteOutput = $this->runOc([
            'user:delete',
            'oc_cli_e2e',
            '--force',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('deleted successfully', strtolower($userDeleteOutput));
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT user_id FROM `" . self::$dbConfig['prefix'] . "user` WHERE username = ? LIMIT 1",
                ['oc_cli_e2e']
            )
        );

        $disableOutput = $this->runOc([
            'extension:disable',
            'shipping:flat',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('disabled successfully', strtolower($disableOutput));
        $extensionsAfterDisable = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertFalse($this->hasExtension($extensionsAfterDisable, 'shipping', 'flat'));

        $enableOutput = $this->runOc([
            'extension:enable',
            'shipping:flat',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('enabled successfully', strtolower($enableOutput));
        $extensionsAfterEnable = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasExtension($extensionsAfterEnable, 'shipping', 'flat'));

        $productModel = 'E2E-RESTORE-001';
        $createProductJson = $this->runOcJson([
            'product:create',
            'OC-CLI Restore Product',
            $productModel,
            '19.99',
            '--quantity=5',
            '--status=enabled',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame('OC-CLI Restore Product', $createProductJson['name']);
        $this->assertSame($productModel, $createProductJson['model']);

        $productRow = $this->fetchSingleRow(
            "SELECT product_id, sku FROM `" . self::$dbConfig['prefix'] . "product` WHERE model = ? LIMIT 1",
            [$productModel]
        );
        $this->assertNotNull($productRow);
        $this->assertSame($productModel, $productRow['sku']);

        $searchResults = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=' . $productModel,
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertCount(1, $searchResults);
        $this->assertSame('OC-CLI Restore Product', $searchResults[0]['name']);

        $updateOutput = $this->runOc([
            'product:update',
            (string) $productRow['product_id'],
            '--name=OC-CLI Updated Product',
            '--price=29.99',
            '--quantity=7',
            '--status=disabled',
            '--category=' . (string) $createdCategory[0]['category_id'],
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('updated successfully', strtolower($updateOutput));

        $updatedProduct = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=E2E-RESTORE-001',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertCount(1, $updatedProduct);
        $this->assertSame('OC-CLI Updated Product', $updatedProduct[0]['name']);
        $this->assertSame('disabled', $updatedProduct[0]['status']);

        $deleteOutput = $this->runOc([
            'product:delete',
            (string) $productRow['product_id'],
            '--force',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('deleted successfully', strtolower($deleteOutput));
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT product_id FROM `" . self::$dbConfig['prefix'] . "product` WHERE product_id = ? LIMIT 1",
                [(string) $productRow['product_id']]
            )
        );

        $orderList = $this->runOcJson([
            'order:list',
            '--format=json',
            '--id=' . $seededOrder['order_id'],
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertCount(1, $orderList);
        $this->assertSame((int) $seededOrder['order_id'], $orderList[0]['order_id']);

        $orderView = $this->runOcJson([
            'order:view',
            (string) $seededOrder['order_id'],
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame((int) $seededOrder['order_id'], (int) $orderView['order']['order_id']);
        $this->assertNotEmpty($orderView['products']);

        $updateOrderStatusOutput = $this->runOc([
            'order:update-status',
            (string) $seededOrder['order_id'],
            'Processing',
            '--comment=Updated by OC-CLI E2E',
            '--notify',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('updated to status', strtolower($updateOrderStatusOutput));

        $updatedOrder = $this->fetchSingleRow(
            "SELECT order_status_id FROM `" . self::$dbConfig['prefix'] . "order` WHERE order_id = ? LIMIT 1",
            [(string) $seededOrder['order_id']]
        );
        $this->assertSame((string) $seededOrder['processing_status_id'], (string) $updatedOrder['order_status_id']);

        $quantityAfterStatusUpdate = $this->fetchSingleRow(
            "SELECT quantity FROM `" . self::$dbConfig['prefix'] . "product` WHERE product_id = ? LIMIT 1",
            [(string) $seededOrder['product_id']]
        );
        $this->assertSame(
            (string) ($seededOrder['original_quantity'] - 1),
            (string) $quantityAfterStatusUpdate['quantity']
        );

        $historyRow = $this->fetchSingleRow(
            "SELECT comment FROM `" . self::$dbConfig['prefix'] . "order_history` WHERE order_id = ? ORDER BY order_history_id DESC LIMIT 1",
            [(string) $seededOrder['order_id']]
        );
        $this->assertSame('Updated by OC-CLI E2E', $historyRow['comment']);

        $modificationFile = self::$artifactsDir . '/demo.ocmod.xml';
        file_put_contents(
            $modificationFile,
            <<<XML
<modification>
  <name>OC-CLI E2E Modification</name>
  <code>oc_cli_e2e_mod</code>
  <version>1.0.0</version>
  <author>OC-CLI</author>
  <file path="catalog/controller/startup/startup.php">
    <operation>
      <search><![CDATA[class ControllerStartupStartup extends Controller {]]></search>
      <add position="after"><![CDATA[
// OC-CLI E2E modification
]]></add>
    </operation>
  </file>
</modification>
XML
        );

        $installOutput = $this->runOc([
            'extension:install',
            $modificationFile,
            '--activate',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('Imported modification', $installOutput);

        $modifications = $this->runOcJson([
            'modification:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasModification($modifications, 'oc_cli_e2e_mod', 'enabled'));

        $checkResults = $this->runOcJson([
            'db:check',
            'session',
            'order',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($checkResults);

        $repairResults = $this->runOcJson([
            'db:repair',
            'session',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($repairResults);

        $optimizeResults = $this->runOcJson([
            'db:optimize',
            'session',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($optimizeResults);

        $cleanupResults = $this->runOcJson([
            'db:cleanup',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($cleanupResults);
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT session_id FROM `" . self::$dbConfig['prefix'] . "session` LIMIT 1",
                []
            )
        );

        $cacheArtifacts = $this->createCacheArtifacts();
        $clearCacheOutput = $this->runOc([
            'cache:clear',
            '--type=all',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('cache cleared successfully', strtolower($clearCacheOutput));
        foreach ($cacheArtifacts as $artifact) {
            $this->assertFileDoesNotExist($artifact);
        }

        $rebuildOutput = $this->runOc([
            'cache:rebuild',
            '--type=all',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('cache rebuilt successfully', strtolower($rebuildOutput));
        $modifiedStartup = self::$openCartRoot . '/system/storage/modification/catalog/controller/startup/startup.php';
        $this->assertFileExists($modifiedStartup);
        $this->assertStringContainsString('OC-CLI E2E modification', (string) file_get_contents($modifiedStartup));

        $restoreOutput = $this->runOc([
            'db:restore',
            $backupPath,
            '--force',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('Database restored successfully', $restoreOutput);

        $restoredStoreName = trim($this->runOc([
            'core:config',
            'get',
            'config_name',
            '--opencart-root=' . self::$openCartRoot,
        ]));
        $this->assertSame($baselineStoreName, $restoredStoreName);

        $postRestoreProductList = $this->runOc(
            [
                'product:list',
                '--search=' . $productModel,
                '--opencart-root=' . self::$openCartRoot,
            ],
            0
        );
        $this->assertStringContainsString('No products found', $postRestoreProductList);
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT product_id FROM `" . self::$dbConfig['prefix'] . "product` WHERE model = ? LIMIT 1",
                [$productModel]
            )
        );

        $postRestoreModifications = $this->runOc([
            'modification:list',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('No modifications found', $postRestoreModifications);

        $postRestoreExtensions = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasExtension($postRestoreExtensions, 'shipping', 'flat'));
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT category_id FROM `" . self::$dbConfig['prefix'] . "category_description` WHERE name = ? LIMIT 1",
                [$createdCategoryName]
            )
        );
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT order_id FROM `" . self::$dbConfig['prefix'] . "order` WHERE order_id = ? LIMIT 1",
                [(string) $seededOrder['order_id']]
            )
        );
    }

    private function runOc(array $arguments, int $expectedExitCode = 0): string
    {
        $process = new Process(
            array_merge([PHP_BINARY, 'bin/oc'], $arguments),
            self::$repoRoot,
            ['APP_ENV' => '']
        );
        $process->setTimeout(120);
        $process->run();

        $combinedOutput = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame($expectedExitCode, $process->getExitCode(), $combinedOutput);

        return $combinedOutput;
    }

    /**
     * @return array<mixed>
     */
    private function runOcJson(array $arguments, int $expectedExitCode = 0): array
    {
        $output = $this->runOc($arguments, $expectedExitCode);
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded, $output);

        return $decoded;
    }

    /**
     * @param array<array<string, mixed>> $requirements
     */
    private function findRequirementStatus(array $requirements, string $name): bool
    {
        foreach ($requirements as $requirement) {
            if (($requirement['name'] ?? null) === $name) {
                return (bool) $requirement['status'];
            }
        }

        return false;
    }

    /**
     * @param array<array<string, mixed>> $extensions
     */
    private function hasExtension(array $extensions, string $type, string $code): bool
    {
        foreach ($extensions as $extension) {
            if (($extension['type'] ?? null) === $type && ($extension['code'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array<string, mixed>> $modifications
     */
    private function hasModification(array $modifications, string $code, string $status): bool
    {
        foreach ($modifications as $modification) {
            if (($modification['code'] ?? null) === $code && ($modification['status'] ?? null) === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $parameters
     * @return array<string, string>|null
     */
    private function fetchSingleRow(string $sql, array $parameters): ?array
    {
        $connection = $this->openDatabaseConnection();
        $escapedParameters = array_map([$connection, 'real_escape_string'], $parameters);

        foreach ($escapedParameters as $parameter) {
            $quoted = "'" . $parameter . "'";
            $sql = preg_replace('/\?/', $quoted, $sql, 1);
        }

        $this->assertIsString($sql);
        $result = $connection->query($sql);
        $this->assertNotFalse($result, (string) $connection->error);
        $row = $result instanceof \mysqli_result ? $result->fetch_assoc() : null;

        if ($result instanceof \mysqli_result) {
            $result->free();
        }
        $connection->close();

        return $row ?: null;
    }

    private function openDatabaseConnection(): mysqli
    {
        $connection = mysqli_init();
        $this->assertInstanceOf(mysqli::class, $connection);

        $connected = $connection->real_connect(
            (string) self::$dbConfig['host'],
            (string) self::$dbConfig['user'],
            (string) self::$dbConfig['pass'],
            (string) self::$dbConfig['name'],
            (int) self::$dbConfig['port']
        );

        $this->assertTrue($connected, (string) $connection->connect_error);
        $connection->set_charset('utf8mb4');

        return $connection;
    }

    /**
     * @return array<string, int>
     */
    private function seedDeterministicOrder(): array
    {
        $product = $this->fetchSingleRow(
            "SELECT p.product_id, p.model, p.quantity, p.price, pd.name " .
            "FROM `" . self::$dbConfig['prefix'] . "product` p " .
            "INNER JOIN `" . self::$dbConfig['prefix'] . "product_description` pd ON (p.product_id = pd.product_id) " .
            "WHERE p.status = '1' AND p.subtract = '1' AND pd.language_id = '1' ORDER BY p.product_id ASC LIMIT 1",
            []
        );
        $this->assertNotNull($product);

        $initialStatus = $this->fetchSingleRow(
            "SELECT order_status_id FROM `" . self::$dbConfig['prefix'] . "order_status` WHERE name = ? LIMIT 1",
            ['Canceled']
        );
        $processingStatus = $this->fetchSingleRow(
            "SELECT order_status_id FROM `" . self::$dbConfig['prefix'] . "order_status` WHERE name = ? LIMIT 1",
            ['Processing']
        );
        $configName = $this->fetchSingleRow(
            "SELECT value FROM `" . self::$dbConfig['prefix'] . "setting` WHERE `key` = 'config_name' LIMIT 1",
            []
        );
        $configUrl = $this->fetchSingleRow(
            "SELECT value FROM `" . self::$dbConfig['prefix'] . "setting` WHERE `key` = 'config_url' LIMIT 1",
            []
        );
        $configLanguageId = $this->fetchSingleRow(
            "SELECT value FROM `" . self::$dbConfig['prefix'] . "setting` WHERE `key` = 'config_language_id' LIMIT 1",
            []
        );
        $storeUrl = $configUrl['value'] ?? 'http://localhost/';
        $languageId = (int) ($configLanguageId['value'] ?? 1);

        $total = (float) $product['price'] + 5.00;
        $connection = $this->openDatabaseConnection();
        $prefix = self::$dbConfig['prefix'];

        $connection->query(
            "INSERT INTO `{$prefix}order` SET " .
            "invoice_prefix = 'INV-2026-00', store_id = 0, store_name = '" . $connection->real_escape_string((string) $configName['value']) . "', " .
            "store_url = '" . $connection->real_escape_string((string) $storeUrl) . "', customer_id = 0, customer_group_id = 1, " .
            "firstname = 'CLI', lastname = 'Seed', email = 'seed@example.test', telephone = '0000000000', fax = '', custom_field = '', " .
            "payment_firstname = 'CLI', payment_lastname = 'Seed', payment_company = '', payment_address_1 = '1 Test Street', payment_address_2 = '', " .
            "payment_city = 'Test City', payment_postcode = '10000', payment_country = 'United Kingdom', payment_country_id = 222, payment_zone = 'Test', payment_zone_id = 0, payment_address_format = '', payment_custom_field = '', payment_method = 'Cash On Delivery', payment_code = 'cod', " .
            "shipping_firstname = 'CLI', shipping_lastname = 'Seed', shipping_company = '', shipping_address_1 = '1 Test Street', shipping_address_2 = '', " .
            "shipping_city = 'Test City', shipping_postcode = '10000', shipping_country = 'United Kingdom', shipping_country_id = 222, shipping_zone = 'Test', shipping_zone_id = 0, shipping_address_format = '', shipping_custom_field = '', shipping_method = 'Flat Shipping Rate', shipping_code = 'flat.flat', comment = '', total = '" . $total . "', affiliate_id = 0, commission = 0, marketing_id = 0, tracking = '', language_id = '" . $languageId . "', currency_id = 1, currency_code = 'GBP', currency_value = 1.00000000, ip = '127.0.0.1', forwarded_ip = '', user_agent = 'OC-CLI E2E', accept_language = 'en-GB', order_status_id = '" . (int) $initialStatus['order_status_id'] . "', date_added = NOW(), date_modified = NOW()"
        );
        $this->assertSame('', $connection->error);
        $orderId = $connection->insert_id;

        $connection->query(
            "INSERT INTO `{$prefix}order_product` SET order_id = {$orderId}, product_id = " . (int) $product['product_id'] . ", name = '" . $connection->real_escape_string((string) $product['name']) . "', model = '" . $connection->real_escape_string((string) $product['model']) . "', quantity = 1, price = '" . (float) $product['price'] . "', total = '" . (float) $product['price'] . "', tax = '0.0000', reward = 0"
        );
        $connection->query(
            "INSERT INTO `{$prefix}order_total` SET order_id = {$orderId}, code = 'sub_total', title = 'Sub-Total', value = '" . (float) $product['price'] . "', sort_order = 1"
        );
        $connection->query(
            "INSERT INTO `{$prefix}order_total` SET order_id = {$orderId}, code = 'shipping', title = 'Flat Shipping Rate', value = '5.0000', sort_order = 3"
        );
        $connection->query(
            "INSERT INTO `{$prefix}order_total` SET order_id = {$orderId}, code = 'total', title = 'Total', value = '" . $total . "', sort_order = 9"
        );
        $connection->query(
            "INSERT INTO `{$prefix}order_history` SET order_id = {$orderId}, order_status_id = " . (int) $initialStatus['order_status_id'] . ", notify = 0, comment = 'Seeded order', date_added = NOW()"
        );
        $connection->close();

        return [
            'order_id' => (int) $orderId,
            'product_id' => (int) $product['product_id'],
            'original_quantity' => (int) $product['quantity'],
            'processing_status_id' => (int) $processingStatus['order_status_id'],
        ];
    }

    private function seedTransientRows(): void
    {
        $prefix = self::$dbConfig['prefix'];
        $connection = $this->openDatabaseConnection();
        $connection->query(
            "INSERT INTO `{$prefix}session` SET session_id = 'e2e-session', data = '', expire = DATE_ADD(NOW(), INTERVAL 1 HOUR)"
        );
        $connection->query(
            "INSERT INTO `{$prefix}api_session` SET session_id = 'e2e-api', api_id = 1, ip = '127.0.0.1', date_added = NOW(), date_modified = NOW()"
        );
        $connection->query(
            "INSERT INTO `{$prefix}customer_online` SET ip = '127.0.0.1', customer_id = 0, url = '/', referer = '', date_added = NOW()"
        );
        $connection->close();
    }

    /**
     * @return array<int, string>
     */
    private function createCacheArtifacts(): array
    {
        $artifacts = [];

        $dataCache = self::$openCartRoot . '/system/storage/cache/cache.e2e.9999999999';
        file_put_contents($dataCache, 'cache');
        $artifacts[] = $dataCache;

        $themeDir = self::$openCartRoot . '/system/storage/cache/template/e2e';
        if (!is_dir($themeDir)) {
            mkdir($themeDir, 0777, true);
        }
        $themeFile = $themeDir . '/artifact.twig';
        file_put_contents($themeFile, 'theme');
        $artifacts[] = $themeFile;

        $adminSassDir = self::$openCartRoot . '/admin/view/stylesheet/sass';
        if (!is_dir($adminSassDir)) {
            mkdir($adminSassDir, 0777, true);
        }
        $adminSassSource = $adminSassDir . '/_bootstrap.scss';
        file_put_contents($adminSassSource, '// sass');
        $adminBootstrap = self::$openCartRoot . '/admin/view/stylesheet/bootstrap.css';
        file_put_contents($adminBootstrap, 'compiled');
        $artifacts[] = $adminBootstrap;

        return $artifacts;
    }
}
