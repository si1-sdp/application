<?php
declare(strict_types=1);

/*
 * This file is part of drupalindus
 */

namespace DgfipSI1\ApplicationTests\Config;

use PHPUnit\Framework\TestCase;
use DgfipSI1\Application\Config\BaseSchema as CONF;
use DgfipSI1\ConfigHelper\ConfigHelper;
use Exception;

/**
 * @covers DgfipSI1\Application\Config\BaseSchema::getConfigTreeBuilder
 */
class BaseSchemaTest extends TestCase
{
    /**
     * data provider for testSchemaErrors
     *
     * @return array<string,array<mixed>>
     */
    public function shemaErrorsData()
    {
        return [                           // errors
            CONF::OPTIONS.".1"             => [ CONF::OPTIONS,     'foo'    ],
        ];
    }
    /**
     * @dataProvider shemaErrorsData
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function testSchemaErrors($key, $value)
    {
        $conf = new ConfigHelper(new CONF());
        $msg = '';
        try {
            $conf->set($key, $value);
            $conf->build();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression("/$key/", $msg);
    }

    /**
     * data provider for testSchemaNominal
     *
     * @return array<string,array<mixed>>
     */
    public function shemaNominalData()
    {
        return [                       // not default but nominal value
            CONF::OPTIONS.".1"             => [ CONF::OPTIONS,    []                ],
            CONF::OPTIONS.".2"             => [ CONF::OPTIONS,    [ 'foo' => 'bar'] ],
        ];
    }
    /**
     * @dataProvider shemaNominalData
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function testSchemaNominal($key, $value)
    {
        $conf = new ConfigHelper(new CONF());
        self::assertNotEquals($value, $conf->get($key));
        $conf->set($key, $value);
        $conf->build();
        self::assertEquals($value, $conf->get($key));
    }

    /**
     *
     * @return void
     */
    public function testSchemaDefaultValues()
    {
        $conf = new ConfigHelper(new CONF());
        $conf->build();
        self::assertEquals(null, $conf->get(CONF::OPTIONS));
    }
}
