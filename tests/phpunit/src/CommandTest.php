<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\CommandTests;

use DgfipSI1\Application\Command;
use DgfipSI1\testLogger\LogTestCase;

/**
 */
class AbstractApplicationTest extends LogTestCase
{
    /**
     * data provider for getConfName tests
     *
     * @return array<string,array<mixed>>
     */
    public function confNameData()
    {
        return [
            'plainname'  => [ 'cmdname'        , 'cmdname'         ],
            'with_sep '  => [ 'cmd-name'       , 'cmd_name'        ],
            'with_grp '  => [ 'cmd:name'       , 'cmd_name'        ],
            'composed '  => [ 'group:cmd-name' , 'group_cmd_name'  ],
        ];
    }

    /**
     *  test constructor
     * @dataProvider confNameData
     *
     * @covers \DgfipSI1\Application\Command
     *
     * @param string $name
     * @param string $expected
     *
     * @return void
     */
    public function testGetConfName($name, $expected): void
    {
        self::assertEquals($expected, Command::getConfName($name));
    }
}
