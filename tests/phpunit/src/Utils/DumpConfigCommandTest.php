<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Utils;

use DgfipSI1\Application\Utils\DumpconfigCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 *
 * @uses DgfipSI1\Application\Config\ConfiguredApplicationTrait::setConfig
 * @uses DgfipSI1\Application\Config\ConfiguredApplicationTrait::getConfig
 */
class DumpConfigCommandTest extends LogTestCase
{
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
    }
    /**
     * test setInputOptions
     *
     * @covers \DgfipSI1\Application\Utils\DumpConfigCommand
     *
     * @return void
     */
    public function testDumper()
    {
        $this->expectOutputString("schema:               []\n");
        $cmd = new DumpconfigCommand();
        $cmd->setConfig(new ConfigHelper());
        $cmd->execute(new ArgvInput(), new NullOutput());
    }
}
