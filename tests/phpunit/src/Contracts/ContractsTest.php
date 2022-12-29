<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Contracts;

use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\Application\Utils\DirectoryStasher;
use DgfipSI1\ConfigHelper\ConfigHelper;
use League\Container\Container;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 *  tests of *Trait
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\DirectoryStasher
 */
class ContractsTest extends TestCase
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    /**
     * test ConfigAwareTrait
     *
     * @covers DgfipSI1\Application\Contracts\ConfigAwareTrait
     */
    public function testConfigAwareTrait(): void
    {
        $config = new ConfigHelper();
        $ds = new DirectoryStasher();
        $ret = $ds->setConfig($config);
        self::assertEquals($config, $ds->getConfig());
        self::assertEquals($ds, $ret);
    }
    /**
     * test LoggerAwareTrait
     *
     * @covers DgfipSI1\Application\Contracts\LoggerAwareTrait
     */
    public function testLoggerAwareTrait(): void
    {
        $msg = '';
        try {
            $this->getLogger();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('No logger implementation has been set.', $msg);

        $ds = new DirectoryStasher();
        $msg = '';
        try {
            $ds->getLogger();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('No container implementation has been set.', $msg);

        $logger1 = new Logger('test1');
        $ds->setContainer(new Container());
        $ds->getContainer()->addShared('logger', $logger1);
        self::assertEquals($logger1, $ds->getLogger());

        $logger2 = new Logger('test2');
        $ret = $ds->setLogger($logger2);
        self::assertEquals($logger2, $ds->getLogger());
        self::assertEquals($ds, $ret);
    }
}
