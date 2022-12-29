<?php
declare(strict_types=1);

/*
 * This file is part of drupalindus
 */

namespace DgfipSI1\ApplicationTests;

use PHPUnit\Framework\TestCase;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\ConfigHelper\ConfigHelper;
use Exception;

/**
 * @covers \DgfipSI1\Application\ApplicationSchema
 */
class SchemaTest extends TestCase
{
    /**
     * data provider for testSchemaErrors
     *
     * @return array<string,array<mixed>>
     */
    public function shemaErrorsData()
    {
        return [                           // errors
            CONF::APPLICATION_NAME         => [ CONF::APPLICATION_VERSION,     []    ],
            CONF::APPLICATION_VERSION      => [ CONF::APPLICATION_VERSION,     []    ],
            CONF::APPLICATION_NAMESPACE    => [ CONF::APPLICATION_NAMESPACE,   []    ],

            CONF::LOG_DIRECTORY            => [ CONF::LOG_DIRECTORY,           []    ],
            CONF::LOG_FILENAME             => [ CONF::LOG_FILENAME,            []    ],
            CONF::LOG_DATE_FORMAT          => [ CONF::LOG_DATE_FORMAT,         []    ],
            CONF::LOG_OUTPUT_FORMAT        => [ CONF::LOG_OUTPUT_FORMAT,       []    ],
            CONF::LOG_LEVEL                => [ CONF::LOG_LEVEL,               []    ],

            CONF::CONFIG_DIRECTORY         => [ CONF::CONFIG_DIRECTORY,        []    ],
            CONF::CONFIG_PATH_PATTERNS     => [ CONF::CONFIG_PATH_PATTERNS,    'foo' ],
            CONF::CONFIG_NAME_PATTERNS     => [ CONF::CONFIG_NAME_PATTERNS,    0     ],
            CONF::CONFIG_SORT_BY_NAME      => [ CONF::CONFIG_SORT_BY_NAME,     'foo' ],
            CONF::CONFIG_SEARCH_RECURSIVE  => [ CONF::CONFIG_SEARCH_RECURSIVE, 'foo' ],

            CONF::PHAR_EXCLUDES            => [ CONF::PHAR_EXCLUDES,           0     ],
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
            CONF::APPLICATION_NAME         => [ CONF::APPLICATION_VERSION,     'app'                    ],
            CONF::APPLICATION_VERSION      => [ CONF::APPLICATION_VERSION,     '1.0'                    ],
            CONF::APPLICATION_NAMESPACE    => [ CONF::APPLICATION_NAMESPACE,   '/foo'                   ],

            CONF::LOG_DIRECTORY            => [ CONF::LOG_DIRECTORY,           '/foo'                   ],
            CONF::LOG_FILENAME             => [ CONF::LOG_FILENAME,            'bar.log'                ],
            CONF::LOG_DATE_FORMAT          => [ CONF::LOG_DATE_FORMAT,         "H:i:s"                  ],
            CONF::LOG_OUTPUT_FORMAT        => [ CONF::LOG_OUTPUT_FORMAT,       "%datetime%|%message%\n" ],
            CONF::LOG_LEVEL.'debug'        => [ CONF::LOG_LEVEL,               'debug'                  ],
            CONF::LOG_LEVEL.'info'         => [ CONF::LOG_LEVEL,               'info'                   ],
            CONF::LOG_LEVEL.'warning'      => [ CONF::LOG_LEVEL,               'warning'                ],
            CONF::LOG_LEVEL.'error'        => [ CONF::LOG_LEVEL,               'error'                  ],
            CONF::LOG_LEVEL.'critical'     => [ CONF::LOG_LEVEL,               'critical'               ],
            CONF::LOG_LEVEL.'alert'        => [ CONF::LOG_LEVEL,               'alert'                  ],
            CONF::LOG_LEVEL.'emergency'    => [ CONF::LOG_LEVEL,               'emergency'              ],

            CONF::CONFIG_DIRECTORY         => [ CONF::CONFIG_DIRECTORY,        '/foo'                   ],
            CONF::CONFIG_PATH_PATTERNS     => [ CONF::CONFIG_PATH_PATTERNS,    ['config', 'conf$']      ],
            CONF::CONFIG_NAME_PATTERNS     => [ CONF::CONFIG_NAME_PATTERNS,    ['*.yml', '*.twig']      ],
            CONF::CONFIG_SORT_BY_NAME      => [ CONF::CONFIG_SORT_BY_NAME,     true                     ],
            CONF::CONFIG_SEARCH_RECURSIVE  => [ CONF::CONFIG_SEARCH_RECURSIVE, true                     ],

            CONF::PHAR_EXCLUDES            => [ CONF::PHAR_EXCLUDES,           ['tmp/']                 ],
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
        self::assertEquals(null, $conf->get(CONF::APPLICATION_NAME));
        self::assertEquals(null, $conf->get(CONF::APPLICATION_VERSION));
        self::assertEquals(CONF::DEFAULT_NAMESPACE, $conf->get(CONF::APPLICATION_NAMESPACE));

        self::assertEquals(null, $conf->get(CONF::LOG_DIRECTORY));
        self::assertEquals(null, $conf->get(CONF::LOG_FILENAME));
        self::assertEquals(CONF::DEFAULT_DATE_FORMAT, $conf->get(CONF::LOG_DATE_FORMAT));
        self::assertEquals(conf::DEFAULT_OUTPUT_FORMAT, $conf->get(CONF::LOG_OUTPUT_FORMAT));
        self::assertEquals('notice', $conf->get(CONF::LOG_LEVEL));

        self::assertEquals(null, $conf->get(CONF::CONFIG_DIRECTORY));
        self::assertEquals([], $conf->get(CONF::CONFIG_PATH_PATTERNS));
        self::assertEquals(['config.yml'], $conf->get(CONF::CONFIG_NAME_PATTERNS));
        self::assertEquals(false, $conf->get(CONF::CONFIG_SORT_BY_NAME));
        self::assertEquals(false, $conf->get(CONF::CONFIG_SEARCH_RECURSIVE));

        self::assertEquals(null, $conf->get(CONF::PHAR_EXCLUDES));
        self::assertEquals([], $conf->get(CONF::GLOBAL_OPTIONS));
    }
    /** data provider for input option schema testing
     *
     * @return array<string,array<mixed>>
    */
    public function inputOptionsData()
    {
        return [             //    type      short  default  desc. required error
            'array -S-D-ok' => [  'array',  'S',   ['foo'], 'Desc', null,  null                      ],
            'scalar-S-D-ok' => [  'scalar', 'S',   'foo'  , 'Desc', null,  null                      ],
            'scalar-/-/-ok' => [  'scalar', null,  null   , 'Desc', null,  null                      ],
            'scalar-/-/-KO' => [  'scalar', null,  null   , 'Desc', true,  "Required option"         ],
            'scalar-S-/-KO' => [  'scalar', 'SS',  null   , 'Desc', true,  "should be a one letter"  ],
        ];
    }
    /**
     *  @dataProvider inputOptionsData
     *
     * @param string      $type
     * @param string|null $short
     * @param mixed       $default
     * @param string      $desc
     * @param bool|null   $required
     * @param string|null $error
     *
     * @return void
     */
    public function testInputOptions($type, $short, $default, $desc, $required, $error)
    {
        $opt = [ 'type' => $type, 'description' => $desc ];
        if (null !== $short) {
            $opt['short_option'] = $short;
        }
        if (null !== $default) {
            $opt['default'] = $default;
        }
        if (null !== $required) {
            $opt['required'] = $required;
        }
        $conf = new ConfigHelper(new CONF());
        $conf->set(CONF::GLOBAL_OPTIONS.".test_opt", $opt);
        $msg = '';
        try {
            $conf->build();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if (null === $error) {
            self::assertEquals('', $msg);
        } else {
            self::assertMatchesRegularExpression("/$error/", $msg);
        }
    }
}
