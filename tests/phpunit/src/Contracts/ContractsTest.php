<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Contracts;

use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\RuntimeException;
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
 */
class ContractsTest extends TestCase implements ContainerAwareInterface
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use ContainerAwareTrait;
    /**
     * test ConfigAwareTrait
     *
     * @covers DgfipSI1\Application\Contracts\ConfigAwareTrait
     */
    public function testConfigAwareTrait(): void
    {
        $config = new ConfigHelper();
        $this->setConfig($config);
        $this->assertEquals($config, $this->getConfig());
    }
    /**
     * test LoggerAwareTrait
     *
     * @covers DgfipSI1\Application\Contracts\LoggerAwareTrait
     */
    public function testLoggerAwareTrait(): void
    {
        $this->setContainer(new Container());
        $msg = '';
        try {
            $this->getLogger();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('No logger implementation has been set.', $msg);

        $logger1 = new Logger('test1');
        $this->getContainer()->addShared('logger', $logger1);
        $this->assertEquals($logger1, $this->getLogger());

        $logger2 = new Logger('test2');
        $this->setLogger($logger2);
        $this->assertEquals($logger2, $this->getLogger());
    }
}
