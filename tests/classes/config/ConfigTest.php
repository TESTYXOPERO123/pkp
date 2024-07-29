<?php

/**
 * @file tests/classes/config/ConfigTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConfigTest
 *
 * @ingroup tests_classes_config
 *
 * @see Config
 *
 * @brief Tests for the Config class.
 */

namespace PKP\tests\classes\config;

use PKP\config\Config;
use PKP\core\Core;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

#[CoversMethod(Config::class, 'getConfigFileName')]
#[CoversMethod(Config::class, 'setConfigFileName')]
#[CoversMethod(Config::class, 'reloadData')]
#[CoversMethod(Config::class, 'getVar')]
#[CoversMethod(Config::class, 'getData')]
#[CoversMethod(Config::class, 'hasVar')]
#[CoversMethod(Config::class, 'isSensitive')]
class ConfigTest extends PKPTestCase
{
    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'configData', 'configFile'];
    }

    
    public function testGetDefaultConfigFileName()
    {
        $expectedResult = Core::getBaseDir() . '/config.inc.php';
        self::assertEquals($expectedResult, Config::getConfigFileName());
    }

    public function testSetConfigFileName()
    {
        Config::setConfigFileName('some_config');
        self::assertEquals('some_config', Config::getConfigFileName());
    }

    public function testReloadDataWithNonExistentConfigFile()
    {
        Config::setConfigFileName('some_config');
        $this->expectExceptionMessage('Cannot read configuration file some_config');
        Config::reloadData();
    }

    public function testReloadDataAndGetData()
    {
        Config::setConfigFileName('lib/pkp/tests/config/config.TEMPLATE.mysql.inc.php');
        $result = Config::reloadData();
        $expectedResult = [
            'installed' => true,
            'base_url' => 'https://pkp.sfu.ca/ojs',
            'session_cookie_name' => 'OJSSID',
            'session_lifetime' => 30,
            'date_format_short' => 'Y-m-d',
            'date_format_long' => 'F j, Y',
            'datetime_format_short' => 'Y-m-d h:i A',
            'datetime_format_long' => 'F j, Y - h:i A',
            'allowed_hosts' => '["mydomain.org"]',
            'time_format' => 'h:i A',
        ];

        // We'll only check part of the configuration data to
        // keep the test less verbose.
        self::assertEquals($expectedResult, $result['general']);

        $result = & Config::getData();
        self::assertEquals($expectedResult, $result['general']);
    }

    public function testGetVar()
    {
        Config::setConfigFileName('lib/pkp/tests/config/config.TEMPLATE.mysql.inc.php');
        self::assertEquals('mysqli', Config::getVar('database', 'driver'));
        self::assertNull(Config::getVar('general', 'non-existent-config-var'));
        self::assertNull(Config::getVar('non-existent-config-section', 'non-existent-config-var'));
    }

    public function testGetVarFromOtherConfig()
    {
        Config::setConfigFileName('lib/pkp/tests/config/config.TEMPLATE.pgsql.inc.php');
        self::assertEquals('pgsql', Config::getVar('database', 'driver'));
    }

    public function testHasVar()
    {
        Config::setConfigFileName('lib/pkp/tests/config/config.mysql.inc.php');
        self::assertTrue(Config::hasVar('general', 'installed'));
    }

    public function testIsSensitive()
    {
        Config::setConfigFileName('lib/pkp/tests/config/config.mysql.inc.php');
        self::assertTrue(Config::isSensitive('database', 'password'));
        self::assertFalse(Config::isSensitive('database', 'driver'));
    }
}
