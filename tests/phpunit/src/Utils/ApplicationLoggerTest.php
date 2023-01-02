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
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\PsrHandler;
use Monolog\Level as MonLvl;
use Monolog\Logger as Monolog;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Psr\Log\LogLevel as LVL;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use function PHPUnit\Framework\assertDirectoryExists;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 *
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 */
class ApplicationLoggerTest extends LogTestCase
{
    /** @var vfsStreamDirectory */
    private $root;


    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $this->root = vfsStream::setup();
        $this->logger = new TestLogger();
    }
    /**
     * @return array<string,mixed>
     */
    public function dataGetVerbosity(): array
    {
        return [
            '<default>   ' => [[                     ] , OutputInterface::VERBOSITY_NORMAL],
            '-q          ' => [[ '-q'                ] , OutputInterface::VERBOSITY_QUIET],
            '--quiet     ' => [[ '--quiet'           ] , OutputInterface::VERBOSITY_QUIET],
            '-v          ' => [[ '-v'                ] , OutputInterface::VERBOSITY_VERBOSE],
            '--verbose 1 ' => [[ '--verbose', 1      ] , OutputInterface::VERBOSITY_VERBOSE],
            '--verbose=1 ' => [[ '--verbose=1'       ] , OutputInterface::VERBOSITY_VERBOSE],
            '-vv         ' => [[ '-vv'               ] , OutputInterface::VERBOSITY_VERY_VERBOSE],
            '--verbose 2 ' => [[ '--verbose', 2      ] , OutputInterface::VERBOSITY_VERY_VERBOSE],
            '--verbose=2 ' => [[ '--verbose=2'       ] , OutputInterface::VERBOSITY_VERY_VERBOSE],
            '-vvv        ' => [[ '-vvv'              ] , OutputInterface::VERBOSITY_DEBUG],
            '--verbose 3 ' => [[ '--verbose', 3      ] , OutputInterface::VERBOSITY_DEBUG],
            '--verbose=3 ' => [[ '--verbose=3'       ] , OutputInterface::VERBOSITY_DEBUG],
            // check that parameters after -- are not taken into account
            '-- -q       ' => [[ '--', '-q'          ] , OutputInterface::VERBOSITY_NORMAL],
            '-- --verb   ' => [[ '--', '--verbose'   ] , OutputInterface::VERBOSITY_NORMAL],
            '-- --verb3  ' => [[ '--', '--verbose=3' ] , OutputInterface::VERBOSITY_NORMAL],
            '-- --verb2  ' => [[ '--', '--verbose=2' ] , OutputInterface::VERBOSITY_NORMAL],
            '-- --verb1  ' => [[ '--', '--verbose=1' ] , OutputInterface::VERBOSITY_NORMAL],
            '-- -vvv     ' => [[ '--', '-vvv'        ] , OutputInterface::VERBOSITY_NORMAL],
            '-- -vv      ' => [[ '--', '-vv'         ] , OutputInterface::VERBOSITY_NORMAL],
            '-- -v       ' => [[ '--', '-v'          ] , OutputInterface::VERBOSITY_NORMAL],
        ];
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
        array_unshift($opts, './tests');
        self::assertEquals($expected, ApplicationLogger::getVerbosity(new ArgvInput($opts)));
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

        return [
            'no logfile   ' => [ [$ld => null]                   , null               , null     , null     ],
            'all defaults ' => [ [$ld => '.']                    , "./test.log"       , $dof     , $ddf     ],
            'log/tests.log' => [ [$ld => 'log']                  , "log/test.log"     , $dof     , $ddf     ],
            './app.log    ' => [ [$ld => '.', $fn => 'app.log']  , "./app.log"        , $dof     , $ddf     ],
            'log/app.log  ' => [ [$ld => 'log', $fn => 'app.log'], "log/app.log"      , $dof     , $ddf     ],
            'dateFormat   ' => [ [$ld => '.', $df => $customDf ] , "./test.log"       , $dof     , $customDf],
            'outputFormat ' => [ [$ld => '.', $of => $customOf ] , "./test.log"       , $customOf, $ddf     ],
            'mkdir        ' => [ [$ld => 'VFS:/log' ]            , 'VFS:/log/test.log', $dof     , $ddf     ],
        ];
    }
    /**
     * Tests buildLogger method
     *
     * @covers \DgfipSI1\Application\Utils\ApplicationLogger::configureLogger
     *
     * @param array<string,string|null> $opts
     * @param string|null               $filename
     * @param string|null               $outputFormat
     * @param string|null               $dateFormat
     *
     * @return void
     *
     * @dataProvider dataBuildLogger
     */
    public function testLogger($opts, $filename, $outputFormat, $dateFormat): void
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
        /** @var Monolog $logger */
        $logger = new Monolog('application_logger');
        $consoleLogger = $this->logger;
        $consoleHandler = new PsrHandler($consoleLogger, MonLvl::fromName('DEBUG'));
        $logger->pushHandler($consoleHandler);
        /**
         *    TEST   configureLogger
         */
        $msg = '';
        ApplicationLogger::configureLogger($logger, $conf, (string) getcwd());
        self::assertEmpty($msg, $msg);
        /** @var array<\Monolog\Handler\HandlerInterface> $handlers */
        $handlers = $logger->getHandlers();
        if (null === $filename) {
            self::assertEquals(1, sizeof($handlers));
            self::assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
        } else {
            self::assertDirectoryExists(''.$conf->get(CONF::LOG_DIRECTORY));
            self::assertEquals(2, sizeof($handlers));
            self::assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[1]);
            self::assertInstanceOf('\Monolog\Handler\StreamHandler', $handlers[0]);
            /** @var \Monolog\Handler\StreamHandler $sh */
            $sh = $handlers[0];
            if (strpos($filename, "VFS:") === false) {
                self::assertEquals(realpath('.')."/$filename", $sh->getUrl());
            } else {
                self::assertEquals(str_replace('VFS:', $root->url(), $filename), $sh->getUrl());
            }
            $context = [ 'name' => 'new ApplicationLogger', 'file' =>  str_replace('VFS:', $root->url(), $filename)];
            $this->assertInfoInContextLog('starting file logger', $context);

            $procProp = (new ReflectionClass($sh::class))->getProperty('processors');
            $procProp->setAccessible(true);
            /** @var array<mixed> $processors */
            $processors = $procProp->getValue($sh);
            self::assertEquals(1, sizeof($processors));


            /** @var \Monolog\Formatter\LineFormatter $formatter */
            $formatter = $sh->getFormatter();

            $formaterClass = new \ReflectionClass('Monolog\Formatter\LineFormatter');
            $of = $formaterClass->getProperty('format');
            $of->setAccessible(true);
            self::assertEquals($outputFormat, $of->getValue($formatter), "Output format does not match expected");
            $df = $formaterClass->getProperty('dateFormat');
            $df->setAccessible(true);
            self::assertEquals($dateFormat, $df->getValue($formatter), "Date format does not match expected");
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
        ApplicationLogger::configureLogger($logger, new ConfigHelper(), (string) getcwd());
        $this->assertAlertInLog('Advanced logger configuration applies only to Monolog');
        $this->assertLogEmpty();
    }
    /**
     * Data provider for iniLogger
     *
     * @return array<string,array<mixed>>
     */
    public function initData()
    {
        return [
            '-q  ' => [ OutputInterface::VERBOSITY_QUIET , 'WARNING' ],
            '--  ' => [ OutputInterface::VERBOSITY_NORMAL, 'NOTICE'  ],
            '-v  ' => [OutputInterface::VERBOSITY_VERBOSE, 'INFO'],
            '-vv ' => [ OutputInterface::VERBOSITY_VERY_VERBOSE, 'DEBUG'],
            '-vvv' => [ OutputInterface::VERBOSITY_DEBUG, 'DEBUG'],

        ];
    }
    /**
     * @covers DgfipSI1\Application\Utils\ApplicationLogger::initLogger
     *
     * @dataProvider initData
     *
     * @param int    $verbosity
     * @param string $level
     *
     * @return void
     */
    public function testInitLogger($verbosity, $level)
    {
        $output = new ConsoleOutput();
        $output->setVerbosity($verbosity);

        /** @var \Monolog\Logger $logger */
        $logger = ApplicationLogger::initLogger($output);
        /** @var array<HandlerInterface> $handlers */
        $handlers = $logger->getHandlers();
        self::assertEquals(1, sizeof($handlers));
        self::assertInstanceOf(PsrHandler::class, $handlers[0]);
        /** @var PsrHandler $h1 */
        $h1 = $handlers[0];
        self::assertEquals($level, $h1->getLevel()->getName());

        $logProp = (new ReflectionClass(PsrHandler::class))->getProperty('logger');
        $logProp->setAccessible(true);
        /** @var \Consolidation\Log\Logger $logger */
        $logger = $logProp->getValue($h1);
        self::assertInstanceOf(\Consolidation\Log\Logger::class, $logger);
        self::assertInstanceOf(\Robo\Log\RoboLogStyle::class, $logger->getLogOutputStyler());

        $vlMapProp = (new ReflectionClass($logger::class))->getProperty('verbosityLevelMap');
        $vlMapProp->setAccessible(true);
        /** @var array<string,int> $vlMap */
        $vlMap = $vlMapProp->getValue($logger);

        foreach ([LVL::EMERGENCY, LVL::ALERT, LVL::CRITICAL, LVL::ERROR, LVL::WARNING, LVL::NOTICE] as $lvl) {
            self::assertEquals(OutputInterface::VERBOSITY_NORMAL, $vlMap[$lvl]);
        }
        self::assertEquals(OutputInterface::VERBOSITY_VERBOSE, $vlMap[LVL::INFO]);
        self::assertEquals(OutputInterface::VERBOSITY_DEBUG, $vlMap[LVL::DEBUG]);
    }
    /**
     * data provider for ensureDirectoryExists
     *
     * @return array<string,array<mixed>>
     */
    public function directoryData()
    {
                       //
        return [            //    directory               exists  relative excpt°
            'abs_existing  ' => [ '/tmp'                 , true  , false  , false ],
            'vfs_existing  ' => [ 'vfs://root/logs/'     , true  , false  , false ],
            'vfs_create    ' => [ 'vfs://root/absent/'   , false , false  , false ],
            'local_existing' => [ 'tests/data/logs/'     , true  , true   , false ],
            'local_create  ' => [ 'tests/data/logs/foo'  , false , true   , false ],
            'error         ' => [ 'tests/data/:/:/logs'  , false , true   , true  ],
        ];
    }
    /**
     * @covers DgfipSI1\Application\Utils\ApplicationLogger::ensureDirectoryExists
     *
     * @dataProvider directoryData
     *
     * @param string $dir
     * @param bool   $exists
     * @param bool   $relative
     * @param bool   $error
     *
     * @return void

     */
    public function testEnsureDirectoryExists($dir, $exists, $relative, $error)
    {
        // prepare directory structure
        mkdir($this->root->url().DIRECTORY_SEPARATOR.'logs');
        $dataLogDir = getcwd().'/tests/data/';
        self::assertDirectoryExists($dataLogDir);
        $fs = new Filesystem();
        $fs->remove("$dataLogDir/logs");
        mkdir("$dataLogDir/logs");

        $errorDir =  getcwd().'/tests/data/:/:/logs';
        $msg = '';
        try {
            ApplicationLogger::ensureDirectoryExists($dir, (string) getcwd(), $this->logger);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if ($error) {
            self::assertMatchesRegularExpression('/Can\'t create log directory/', $msg);

            return;
        }
            self::assertEquals('', $msg);
        if ($relative) {
            $dir = getcwd()."/$dir";
        }
        if (!$exists) {
            $this->assertInfoInLog("Creating log directory : $dir");
        }
        self::assertDirectoryExists($dir);

        // test that we have restored error Handler

        $fs->remove("$dataLogDir/logs");
    }
}
