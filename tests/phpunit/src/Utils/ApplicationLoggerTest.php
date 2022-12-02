<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Utils\ApplicationLogger;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 *
 * @uses DgfipSI1\Application\ApplicationSchema
 */
class ApplicationLoggerTest extends LogTestCase
{
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
    }
    /**
     * @return array<string,mixed>
     */
    public function dataGetVerbosity(): array
    {
        $data['<default>   '] = [[ ]               , OutputInterface::VERBOSITY_NORMAL];
        $data['-q          '] = [[ '-q' ]          , OutputInterface::VERBOSITY_QUIET];
        $data['--quiet     '] = [[ '--quiet']      , OutputInterface::VERBOSITY_QUIET];
        $data['-v          '] = [[ '-v']           , OutputInterface::VERBOSITY_VERBOSE];
        $data['--verbose 1 '] = [[ '--verbose', 1] , OutputInterface::VERBOSITY_VERBOSE];
        $data['--verbose=1 '] = [[ '--verbose=1']  , OutputInterface::VERBOSITY_VERBOSE];
        $data['-vv         '] = [[ '-vv']          , OutputInterface::VERBOSITY_VERY_VERBOSE];
        $data['--verbose 2 '] = [[ '--verbose', 2] , OutputInterface::VERBOSITY_VERY_VERBOSE];
        $data['--verbose=2 '] = [[ '--verbose=2']  , OutputInterface::VERBOSITY_VERY_VERBOSE];
        $data['-vvv        '] = [[ '-vvv']         , OutputInterface::VERBOSITY_DEBUG];
        $data['--verbose 3 '] = [[ '--verbose', 3] , OutputInterface::VERBOSITY_DEBUG];
        $data['--verbose=3 '] = [[ '--verbose=3']  , OutputInterface::VERBOSITY_DEBUG];

        return $data;
    }
    /**
     * Tests getVerbosity method
     *
     * @covers \DgfipSI1\Application\Utils\ApplicationLogger::getVerbosity
     *
     * @param array<string|int> $opts
     * @param int               $expected
     *
     * @return void
     *
     * @dataProvider dataGetVerbosity
     */
    public function testGetVerbosity($opts, $expected): void
    {
        $opts = array_merge([ './tests' ], $opts);
        $this->assertEquals($expected, ApplicationLogger::getVerbosity(new ArgvInput($opts)));
    }

    /**
     * @return array<string,mixed>
     */
    public function dataBuildLogger(): array
    {
        $ld = CONF::LOG_DIRECTORY;
        $fn = CONF::LOG_FILENAME;
        $of = CONF::LOG_OUTPUT_FORMAT;
        $df = CONF::LOG_DATE_FORMAT;
        $dof = CONF::DEFAULT_OUTPUT_FORMAT;
        $ddf = CONF::DEFAULT_DATE_FORMAT;

        $customDf = 'Y:m:d at H:i:s';
        $customOf = "%context.name%|%message%\n";

        $data['no logfile   '] = [ [$ld => null]                   , null               , null     , null     , false];
        $data['all defaults '] = [ [$ld => '.']                    , "./test.log"       , $dof     , $ddf     , false];
        $data['log/tests.log'] = [ [$ld => 'log']                  , "log/test.log"     , $dof     , $ddf     , false];
        $data['./app.log    '] = [ [$ld => '.', $fn => 'app.log']  , "./app.log"        , $dof     , $ddf     , false];
        $data['log/app.log  '] = [ [$ld => 'log', $fn => 'app.log'], "log/app.log"      , $dof     , $ddf     , false];
        $data['dateFormat   '] = [ [$ld => '.', $df => $customDf ] , "./test.log"       , $dof     , $customDf, false];
        $data['outputFormat '] = [ [$ld => '.', $of => $customOf ] , "./test.log"       , $customOf, $ddf     , false];
        $data['exception    '] = [ [$ld => '/foo/bar' ]            , null               , null     , null     , true ];
        $data['mkdir        '] = [ [$ld => 'VFS:/log' ]            , 'VFS:/log/test.log', $dof     , $ddf     , false];

        return $data;
    }
    /**
     * Tests buildLogger method
     *
     * @covers \DgfipSI1\Application\Utils\ApplicationLogger::initLogger
     * @covers \DgfipSI1\Application\Utils\ApplicationLogger::configureLogger
     *
     * @param array<string,string|null> $opts
     * @param string|null               $filename
     * @param string|null               $outputFormat
     * @param string|null               $dateFormat
     * @param bool                      $throwException
     *
     * @return void
     *
     * @dataProvider dataBuildLogger
     */
    public function testLogger($opts, $filename, $outputFormat, $dateFormat, $throwException): void
    {
        $root = vfsStream::setup();
        $conf = new ConfigHelper(new ApplicationSchema());
        $conf->set(CONF::APPLICATION_NAME, 'test');
        $conf->set(CONF::APPLICATION_VERSION, '1.0.0');
        foreach ($opts as $param => $value) {
            if (is_string($value)) {
                $value = str_replace('VFS:', $root->url(), $value);
            }
            $conf->set($param, $value);
        }
        /**
         *    TEST   initLogger
         */
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $logger = ApplicationLogger::initLogger($output);
        /** @var \Monolog\Logger $logger */
        $this->assertInstanceOf('\Monolog\Logger', $logger);
        /** @var array<\Monolog\Handler\HandlerInterface> $handlers */
        $handlers = $logger->getHandlers();
        $this->assertEquals(1, sizeof($handlers));
        $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);

        $verbosity = OutputInterface::VERBOSITY_DEBUG;
        $output->setVerbosity($verbosity);
        /**
         *    TEST   configureLogger
         */
        $msg = '';
        try {
            ApplicationLogger::configureLogger($logger, $conf);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if ($throwException) {
            $this->assertNotEmpty($msg, $msg);
        } else {
            $this->assertEmpty($msg);
            /** @var array<\Monolog\Handler\HandlerInterface> $handlers */
            $handlers = $logger->getHandlers();
            if (null === $filename) {
                $this->assertEquals(1, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
            } else {
                $this->assertDirectoryExists(''.$conf->get(CONF::LOG_DIRECTORY));
                $this->assertEquals(2, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[1]);
                $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $handlers[0]);

                /** @var \Monolog\Handler\StreamHandler $sh */
                $sh = $handlers[0];
                if (strpos($filename, "VFS:") === false) {
                    $this->assertEquals(realpath('.')."/$filename", $sh->getUrl());
                } else {
                    $this->assertEquals(str_replace('VFS:', $root->url(), $filename), $sh->getUrl());
                }
                /** @var \Monolog\Formatter\LineFormatter $formatter */
                $formatter = $sh->getFormatter();

                $formaterClass = new \ReflectionClass('Monolog\Formatter\LineFormatter');
                $of = $formaterClass->getProperty('format');
                $of->setAccessible(true);
                $this->assertEquals($outputFormat, $of->getValue($formatter), "Output format does not match expected");
                $df = $formaterClass->getProperty('dateFormat');
                $df->setAccessible(true);
                $this->assertEquals($dateFormat, $df->getValue($formatter), "Date format does not match expected");
            }
        }
    }
    /**
     * Tests buildLogger method
     *
     * @covers \DgfipSI1\Application\Utils\ApplicationLogger::configureLogger
     *
     * @return void
     *
     */
    public function testLoggerAlert(): void
    {
        $logger = new TestLogger();
        $this->logger = $logger;
        ApplicationLogger::configureLogger($logger, new ConfigHelper());
        $this->assertAlertInLog('Advanced logger configuration applies only to Monolog');
        $this->assertLogEmpty();
    }
}
