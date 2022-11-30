<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Contracts;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\ApplicationLogger;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ConfigHelper\ConfigHelper;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 *  tests of *Trait
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\ApplicationLogger
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
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
        $msg = '';
        try {
            $this->getLogger();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('No logger implementation has been set.', $msg);
        $logger = new Logger('test');
        $this->setLogger($logger);
        $this->assertEquals($logger, $this->getLogger());
    }
}
