<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use \Mockery;
use Composer\Autoload\ClassLoader;
use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\Application;
use DgfipSI1\Application\ApplicationContainer;
use DgfipSI1\Application\ApplicationLogger;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\ApplicationTypeException;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
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
    /** @var vfsStreamDirectory */
    private $root;
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
        $this->root = vfsStream::setup();
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
     * @covers \DgfipSI1\Application\ApplicationLogger::getVerbosity
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
     * @covers \DgfipSI1\Application\ApplicationLogger::__construct
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
    public function testBuildLogger($opts, $filename, $outputFormat, $dateFormat, $throwException): void
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
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $verbosity = OutputInterface::VERBOSITY_DEBUG;
        $msg = '';
        try {
            $appLogger = new ApplicationLogger($conf, $output, $verbosity);
        } catch (\Exception $e) {
            $appLogger = null;
            $msg = $e->getMessage();
        }
        if ($throwException) {
            $this->assertNotEmpty($msg, $msg);
        } else {
            $this->assertEmpty($msg);
            $class = new \ReflectionClass(ApplicationLogger::class);
            $logProp = $class->getProperty('logger');
            $logProp->setAccessible(true);
            $logger = $logProp->getValue($appLogger);

            /** @var \Monolog\Logger $logger */
            $this->assertInstanceOf('\Monolog\Logger', $logger);
            /** @var array<\Monolog\Handler\HandlerInterface> $handlers */
            $handlers = $logger->getHandlers();
            if (null === $filename) {
                $this->assertEquals(1, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
            } else {
                $this->assertDirectoryExists(''.$conf->get(CONF::LOG_DIRECTORY));
                $this->assertEquals(2, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
                $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $handlers[1]);

                /** @var \Monolog\Handler\StreamHandler $sh */
                $sh = $handlers[1];
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
     * @covers \DgfipSI1\Application\ApplicationLogger::debug
     * @covers \DgfipSI1\Application\ApplicationLogger::info
     * @covers \DgfipSI1\Application\ApplicationLogger::notice
     * @covers \DgfipSI1\Application\ApplicationLogger::warning
     * @covers \DgfipSI1\Application\ApplicationLogger::error
     * @covers \DgfipSI1\Application\ApplicationLogger::critical
     * @covers \DgfipSI1\Application\ApplicationLogger::alert
     * @covers \DgfipSI1\Application\ApplicationLogger::emergency
     * @covers \DgfipSI1\Application\ApplicationLogger::log
     *
     * @uses \DgfipSI1\Application\ApplicationLogger::__construct
     *
    */
    public function testLogging():void
    {
        $root = $this->root->url();

        $conf = new ConfigHelper(new ApplicationSchema());
        $conf->set(CONF::APPLICATION_NAME, 'test');
        $conf->set(CONF::LOG_DIRECTORY, $root);
        $conf->set(CONF::LOG_LEVEL, 'debug');
        $output = new NullOutput();
        $verbosity = OutputInterface::VERBOSITY_QUIET;
        $appLogger = new ApplicationLogger($conf, $output, $verbosity);
        $filename = $root.DIRECTORY_SEPARATOR."test.log";
        $n = 1;
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            $appLogger->$level("Level $level message");
            $n++;
            $content = file_get_contents($filename);
            $end = strtoupper($level)."||Level $level message\n";
            $this->assertEquals($end, substr("$content", -(strlen($end))));
            $this->assertEquals($n, count(explode("\n", "$content")));
        }
        $appLogger->log(\Monolog\Level::fromName('WARNING'), "Call log");
        $content = file_get_contents($filename);
        $end = "WARNING||Call log\n";
        $this->assertEquals($end, substr("$content", -(strlen($end))));
        $this->assertEquals($n+1, count(explode("\n", "$content")));
    }
}
