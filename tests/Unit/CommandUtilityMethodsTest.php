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

use OpenCart\CLI\Application;
use OpenCart\CLI\Tests\Helpers\InvokesNonPublicMembers;
use OpenCart\CLI\Tests\Helpers\TestableCommand;
use PHPUnit\Framework\TestCase;

class CommandUtilityMethodsTest extends TestCase
{
    use InvokesNonPublicMembers;

    /**
     * @var TestableCommand
     */
    private $command;

    protected function setUp(): void
    {
        $application = new Application();
        $this->command = new TestableCommand();
        $this->command->setApplication($application);
    }

    public function testExtractConfigValueWithSingleQuotes()
    {
        $content = "define('DB_HOSTNAME', 'localhost');";

        $this->assertEquals(
            'localhost',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'DB_HOSTNAME'
            )
        );
    }

    public function testExtractConfigValueWithDoubleQuotes()
    {
        $content = 'define("DB_USERNAME", "testuser");';

        $this->assertEquals(
            'testuser',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'DB_USERNAME'
            )
        );
    }

    public function testExtractConfigValueWithMixedQuotes()
    {
        $content = "define('DB_PASSWORD', \"secret123\");";

        $this->assertEquals(
            'secret123',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'DB_PASSWORD'
            )
        );
    }

    public function testExtractConfigValueWithSpaces()
    {
        $content = "define( 'DB_DATABASE' , 'opencart_db' );";

        $this->assertEquals(
            'opencart_db',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'DB_DATABASE'
            )
        );
    }

    public function testExtractConfigValueWithSpecialCharacters()
    {
        $content = "define('DB_PREFIX', 'oc_123_');";

        $this->assertEquals(
            'oc_123_',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'DB_PREFIX'
            )
        );
    }

    public function testExtractConfigValueWithEmptyValue()
    {
        $content = "define('HTTPS_SERVER', '');";

        $this->assertEquals(
            '',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'HTTPS_SERVER'
            )
        );
    }

    public function testExtractConfigValueNotFound()
    {
        $content = "define('DB_HOSTNAME', 'localhost');";

        $this->assertNull(
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'NONEXISTENT'
            )
        );
    }

    public function testExtractConfigValueCaseInsensitive()
    {
        $content = "DEFINE('db_hostname', 'localhost');";

        $this->assertEquals(
            'localhost',
            $this->invokeMethod(
                $this->command,
                'extractConfigValue',
                $content,
                'db_hostname'
            )
        );
    }

    public function testExtractConfigValueMultipleDefinitions()
    {
        $content = "define('DB_HOSTNAME', 'localhost');\ndefine('DB_PORT', '3306');";

        $this->assertEquals(
            'localhost',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_HOSTNAME')
        );
        $this->assertEquals(
            '3306',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_PORT')
        );
    }

    public function testFormatBytesZero()
    {
        $this->assertEquals('0 B', $this->command->formatBytesPublic(0));
    }

    public function testFormatBytesBytes()
    {
        $this->assertEquals('512 B', $this->command->formatBytesPublic(512));
    }

    public function testFormatBytesKilobytes()
    {
        $this->assertEquals('1 KB', $this->command->formatBytesPublic(1024));
    }

    public function testFormatBytesMegabytes()
    {
        $this->assertEquals('1 MB', $this->command->formatBytesPublic(1048576));
    }

    public function testFormatBytesGigabytes()
    {
        $this->assertEquals('1 GB', $this->command->formatBytesPublic(1073741824));
    }

    public function testFormatBytesTerabytes()
    {
        $this->assertEquals('1 TB', $this->command->formatBytesPublic(1099511627776));
    }

    public function testFormatBytesWithDecimals()
    {
        $this->assertEquals('1.5 KB', $this->command->formatBytesPublic(1536));
    }

    public function testFormatBytesWithCustomPrecision()
    {
        $this->assertEquals('2 KB', $this->command->formatBytesPublic(1536, 0));
    }

    public function testFormatBytesLargeNumber()
    {
        $this->assertEquals('2.5 GB', $this->command->formatBytesPublic(2684354560));
    }

    public function testFormatBytesMaxUnit()
    {
        $this->assertEquals('1024 TB', $this->command->formatBytesPublic(1125899906842624));
    }

    public function testExtractConfigValueIgnoresComments()
    {
        $content = "// define('DB_HOSTNAME', 'commented');\ndefine('DB_HOSTNAME', 'actual');";

        $this->assertEquals(
            'actual',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_HOSTNAME')
        );
    }

    public function testExtractConfigValueWithComplexContent()
    {
        $content = "<?php\n" .
            "// Database\n" .
            "define('DB_DRIVER', 'mysqli');\n" .
            "define('DB_HOSTNAME', 'localhost');\n" .
            "define('DB_USERNAME', 'root');\n" .
            "define('DB_PASSWORD', '');\n" .
            "define('DB_DATABASE', 'opencart');\n" .
            "define('DB_PORT', '3306');\n" .
            "define('DB_PREFIX', 'oc_');\n";

        $this->assertEquals(
            'mysqli',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_DRIVER')
        );
        $this->assertEquals(
            'localhost',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_HOSTNAME')
        );
        $this->assertEquals(
            'root',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_USERNAME')
        );
        $this->assertEquals(
            '',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_PASSWORD')
        );
        $this->assertEquals(
            'opencart',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_DATABASE')
        );
        $this->assertEquals(
            '3306',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_PORT')
        );
        $this->assertEquals(
            'oc_',
            $this->invokeMethod($this->command, 'extractConfigValue', $content, 'DB_PREFIX')
        );
    }
}
