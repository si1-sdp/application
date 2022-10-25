<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\Application;
use DgfipSI1\Application\ApplicationContainer;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\ApplicationTypeException;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use DgfipSI1\ApplicationTests\AppTestConfigSchema;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 *
 * @uses \DgfipSI1\Application\Application
 * @uses \DgfipSI1\Application\ApplicationSchema
 * @uses \DgfipSI1\Application\ApplicationContainer
 *
 */
class ApplicationTest extends LogTestCase
{
    /** @var ClassLoader $loader */
    protected $loader;

    /** @var \ReflectionClass<Application> */
    protected $class;

    /** @var \ReflectionProperty */
    protected $lg;

    /** @var vfsStreamDirectory */
    private $root;
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
        $this->class = new \ReflectionClass('\DgfipSI1\Application\Application');
        $this->lg = $this->class->getProperty('logger');
        $this->lg->setAccessible(true);
        $this->logger = new TestLogger();
        $this->root = vfsStream::setup();
    }
    /**
     * initialize tested class for logger
     *
     * @param Application   $application
     * @param array<string> $testedClasses
     *
     * @return void
     */
    public function initLogger($application, $testedClasses)
    {
        $this->logger->setCallers($testedClasses);
        $this->lg->setValue($application, $this->logger);
    }
    /**
     * Checks that log is empty at end of test
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();
    }
    /**
     *  test constructor
     *
     * @covers \DgfipSI1\Application\Application::__construct
     * @covers \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     *
     */
    public function testConstructor(): void
    {
        $inputProp = $this->class->getProperty('input');
        $inputProp->setAccessible(true);
        $outputProp = $this->class->getProperty('output');
        $outputProp->setAccessible(true);
        $containerProp = $this->class->getProperty('container');
        $containerProp->setAccessible(true);
        $configProp = $this->class->getProperty('config');
        $configProp->setAccessible(true);

        $app = new Application($this->loader);
        $app->config()->build();
        $input = $inputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Input\ArgvInput', $input);
        $output = $outputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Output\ConsoleOutput', $output);
        $container = $containerProp->getValue($app);
        $this->assertInstanceOf(ApplicationContainer::class, $container);
        $config = $configProp->getValue($app);
        $this->assertInstanceOf('DgfipSI1\ConfigHelper\ConfigHelper', $config);

        $schema    = new AppTestConfigSchema();
        $app = new Application($this->loader, confSchema: $schema);
        $app->config()->build();
        $this->assertEquals(AppTestConfigSchema::DUMPED_SCHEMA, $app->config()->dumpSchema());
    }
    /**
     * @covers DgfipSI1\Application\Application::setupApplicationConfig
     *
     */
    public function testSetupApplicationConfig(): void
    {
        /* make setupApplicationConfig available */
        $ac = $this->class->getMethod('setupApplicationConfig');
        $ac->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);

        /* test with no config */
        $app = new Application($this->loader);
        $this->initLogger($app, ['setupApplicationConfig']);
        $ac->invokeArgs($app, []);
        $this->assertDebugInLog("No default configuration loaded");
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* setup a config  */
        $_SERVER['PWD'] = $this->root->url();
        $configFile = $this->root->url().DIRECTORY_SEPARATOR.".application-config.yml";

        /* test with correct config */
        $contents = "dgfip_si1:\n  application:\n    name: test_app\n    version: '0.99.0'\n";
        file_put_contents($configFile, $contents);

        $ac->invokeArgs($app, []);
        $this->assertDebugInLog("Configuration file {file} loaded.");
        /** @var ConfigHelper $config */
        $config = $ic->getValue($app);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        $this->assertEquals($configFile, $config->get('dgfip_si1.runtime.config_file'));
        $this->assertEquals('test_app', $config->get(CONF::APPLICATION_NAME));
        $this->assertEquals('0.99.0', $config->get(CONF::APPLICATION_VERSION));

        /* setup a bad config and test with it */
        $app = new Application($this->loader);
        $this->initLogger($app, ['setupApplicationConfig']);

        $contents = "dgfip_si1:\n  application:\n    test: test_app\n";
        file_put_contents($configFile, $contents);
        $ac->invokeArgs($app, []);
        /** @var ConfigInterface $config */
        $config = $ic->getValue($app);
        $this->assertNull($config->get('dgfip_si1.runtime.config_file'));
        $this->assertWarningInLog('Error loading configuration');
        $this->assertDebugInLog("No default configuration loaded");
    }
    /**
     *  test getters
     *
     * @covers \DgfipSI1\Application\Application::logger
     * @covers \DgfipSI1\Application\Application::config
     * @covers \DgfipSI1\Application\Application::container
     *
     */
    public function testGetters(): void
    {
        $app = new Application($this->loader);

        $this->assertInstanceOf(\Consolidation\Log\Logger::class, $app->logger());
        $monolog = new \Monolog\Logger('test');
        $app->container()->addShared('logger', $monolog);
        $this->assertInstanceOf(\Monolog\Logger::class, $app->logger());

        $container = $app->container();
        $this->assertInstanceOf(ApplicationContainer::class, $container);
        $config = $app->config();
        $this->assertInstanceOf(ConfigHelper::class, $config);
    }
    /**
     * Tests application name and version handling
     *
     * @covers \DgfipSI1\Application\Application::setName
     * @covers \DgfipSI1\Application\Application::setVersion
     * @covers \DgfipSI1\Application\Application::setApplicationNameAndVersion
     *
     * @return void
     */
    public function testAppNameAndVersion(): void
    {
        $fin = $this->class->getMethod('finalize');
        $fin->setAccessible(true);
        /** test without name or version */
        $app = new Application($this->loader);
        $app->findRoboCommands('roboTestCommands');
        $msg = '';
        try {
            $fin->invokeArgs($app, []);
            /** @phpstan-ignore-next-line - ignore 'dead catch' error  */
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        /** test name and version setters */
        $this->assertEquals('Application name missing', $msg);
        $app->setName('tests');
        $msg = '';
        try {
            $fin->invokeArgs($app, []);
            /** @phpstan-ignore-next-line - ignore 'dead catch' error  */
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('Version missing', $msg);
        $app->setVersion('tests');
        $fin->invokeArgs($app, []);

        /** test name and version via config */
        $app = new Application($this->loader);
        $app->findRoboCommands('roboTestCommands');

        $ic = $this->class->getproperty('intConfig');
        $ic->setAccessible(true);
        /** @var ConfigInterface $conf */
        $conf = $ic->getValue($app);

        $conf->setDefault(CONF::APPLICATION_NAME, 'test');
        $conf->setDefault(CONF::APPLICATION_VERSION, '1.0.0');
        $fin->invokeArgs($app, []);
    }
    /**
     * @covers \DgfipSI1\Application\Application::configSetup
     *
     * @return void
     */
    public function testConfigSetup(): void
    {
        $cs = $this->class->getMethod('configSetup');
        $cs->setAccessible(true);

        /* TEST 1 - Nominal case without input */
        $app = new Application($this->loader);
        $this->initLogger($app, ['configSetup']);
        $cs->invokeArgs($app, []);

        $opt = $app->getDefinition()->getOption('config');
        $this->assertTrue($opt->isValueRequired());

        /* TEST 2 - Bad input (no value) */
        $app = new Application($this->loader, [ 'test', '--config' ]);
        $this->initLogger($app, ['configSetup']);
        $cs->invokeArgs($app, []);

        /* TEST 3 - Non existing file */
        $configFile = $this->root->url().DIRECTORY_SEPARATOR."config.yml";
        $app = new Application($this->loader, [ 'test', '--config', $configFile ]);
        $this->initLogger($app, ['configSetup']);
        $msg = '';
        try {
            $cs->invokeArgs($app, []);
        } catch (ConfigFileNotFoundException $e) {   /** @phpstan-ignore-line */
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression("/Configuration file '.*' not found/", $msg);

        /* TEST 4 - Load existing file */
        file_put_contents($configFile, "foo:\n  bar: 999\n");
        $cs->invokeArgs($app, []);
        $this->assertEquals(999, $app->config()->get('foo.bar'));
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
     * @covers \DgfipSI1\Application\Application::getVerbosity
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
        $gv = $this->class->getMethod('getVerbosity');
        $gv->setAccessible(true);
        $app = new Application($this->loader);

        $opts = array_merge([ './tests' ], $opts);
        $this->assertEquals($expected, $gv->invokeArgs($app, [ new ArgvInput($opts) ]));
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
     * @covers \DgfipSI1\Application\Application::buildLogger
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
        $bl = $this->class->getMethod('buildLogger');
        $bl->setAccessible(true);
        $fin = $this->class->getMethod('finalize');
        $fin->setAccessible(true);
        $ic = $this->class->getproperty('intConfig');
        $ic->setAccessible(true);
        $app = new Application($this->loader);
        /** @var ConfigInterface $conf */
        $conf = $ic->getValue($app);
        $app->findRoboCommands('roboTestCommands');
        $conf->set(CONF::APPLICATION_NAME, 'test');
        $conf->set(CONF::APPLICATION_VERSION, '1.0.0');
        foreach ($opts as $param => $value) {
            if (is_string($value)) {
                $value = str_replace('VFS:', $root->url(), $value);
            }
            $app->config()->set($param, $value);
        }
        $msg = '';
        try {
            $fin->invokeArgs($app, []);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if ($throwException) {
            $this->assertNotEmpty($msg, $msg);
        } else {
            $this->assertEquals('', $msg);
            $this->assertEmpty($msg);
            /** @var \Monolog\Logger $logger */
            $logger = $app->logger();
            $this->assertInstanceOf('\Monolog\Logger', $logger);
            /** @var array<\Monolog\Handler\HandlerInterface> $handlers */
            $handlers = $logger->getHandlers();
            if (null === $filename) {
                $this->assertEquals(1, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
            } else {
                $this->assertDirectoryExists(''.$app->config()->get(CONF::LOG_DIRECTORY));
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
     * Test configure commands
     *
     * @covers \DgfipSI1\Application\Application::configureCommands
     *
     */
    public function testConfigureCommands(): void
    {
        $app   = new Application($this->loader);
        $cc = $this->class->getMethod('configureCommands');
        $cc->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $at = $this->class->getProperty('appType');
        $at->setAccessible(true);
        $roboType = $this->class->getConstant('ROBO_APPLICATION');
        /** @var string $symfonyCmd */
        $symfonyCmd = $this->class->getConstant('SYMFONY_SUBCLASS');

        // we want discover log to check namespace
        $this->initLogger($app, ['configureCommands', 'discoverPsr4Classes']);


        /* TEST 1 -  appType to robo, no appType in configuration */
        $at->setValue($app, $roboType);
        // test with default arguments
        $cc->invokeArgs($app, []);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* TEST 2 - appType to robo, configuration type = symfony */
        $conf = new ConfigHelper(new CONF());
        $conf->setDefault(CONF::APPLICATION_TYPE, 'symfony');
        $ic->setValue($app, $conf);
        $msg = '';
        try {
            $cc->invokeArgs($app, []);
        } catch (ApplicationTypeException $e) {   /** @phpstan-ignore-line */
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Type mismatched - findRoboCommands lauched/', $msg);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* TEST 3 - appType to empty, configuration type = symfony, namespace = default */
        $at->setValue($app, null);
        $cc->invokeArgs($app, []);
        $this->assertNoticeInLog("1/2 - 0 classe(s) found.");
        $this->assertWarningInLog("No classes subClassing $symfonyCmd found in namespace 'Commands'", true);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* TEST 4 - appType to empty, configuration type = symfony, namespace = symfonyTestCommands */
        $conf->setDefault(CONF::APPLICATION_NAMESPACE, 'symfonyTestCommands');
        $at->setValue($app, null);
        $cc->invokeArgs($app, []);
        $this->assertDebugInLog('1/2 - search {namespace} namespace - found {class}');
        $this->assertNoticeInLog("1/2 - 1 classe(s) found.");
        $this->assertDebugInLog('2/2 - Filter : {class} matches');
        $this->assertNoticeInLog("2/2 - 1 classe(s) found in namespace 'symfonyTestCommands'", true);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

       /* TEST 5 - appType to empty, configuration type = robo, namespace = 'Foo' */
        $conf->setDefault(CONF::APPLICATION_NAMESPACE, 'foo');
        $conf->setDefault(CONF::APPLICATION_TYPE, 'robo');
        $at->setValue($app, null);
        $cc->invokeArgs($app, []);
        $this->assertNoticeInLog('1/2 - 0 classe(s) found.');
        $this->assertWarningInLog("No classes subClassing \Robo\Tasks found in namespace 'foo'", true);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();
    }

    /**
     *  test addSharedService
     *
     * @covers \DgfipSI1\Application\Application::addSharedService
     *
     */
    public function testAddSharedService(): void
    {
        $app   = new Application($this->loader);
        $asc = $this->class->getMethod('addSharedService');
        $asc->setAccessible(true);

        $lg = $this->class->getProperty('logger');
        $lg->setAccessible(true);
        $this->logger = new TestLogger(['addSharedService']);
        $lg->setValue($app, $this->logger);

        // test with default arguments
        $object = new \DgfipSI1\ApplicationTests\symfonyTestCommands\HelloWorldCommand();
        $asc->invokeArgs($app, [ $object ]);
        $this->assertDebugInLog("Adding service hello to container");
        $this->assertArrayHasKey('hello', $app->container()->getServices());
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        // test with non default id and tag
        $asc->invokeArgs($app, [ $object , 'description', 'myTag']);
        $this->assertDebugInLog("Adding service A symfony command hello world example to container");
        $this->assertDebugInLog("Add tag myTag to service A symfony command hello");
        $this->assertArrayHasKey('A symfony command hello world example', $app->container()->getServices());
        $this->assertEquals(1, count($app->container()->getDefinitions(tag: 'myTag')));
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        // test with non object
        $msg = '';
        try {
            $asc->invokeArgs($app, [ "string" , 'description', 'myTag']);
        } catch (LogicException $e) {   /** @phpstan-ignore-line */
            $msg = $e->getMessage();
        }
        $this->assertEquals('invalid Service provided', $msg);

        // test with bad name attribute
        $msg = '';
        try {
            $asc->invokeArgs($app, [ $object , 'unknown', 'myTag']);
        } catch (LogicException $e) {   /** @phpstan-ignore-line */
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/invalid service id/', $msg);
    }
    /**
     *  test robo class finders
     *
     * @covers \DgfipSI1\Application\Application::findRoboCommands
     * @covers \DgfipSI1\Application\Application::discoverRoboCommands
     *
     */
    public function testRoboClassFinder(): void
    {
        /** Initialize reflection properties */
        $cc = $this->class->getProperty('commandClasses');
        $cc->setAccessible(true);
        $lg = $this->class->getProperty('logger');
        $lg->setAccessible(true);
        $this->logger = new TestLogger(['findRoboCommands', 'discoverRoboCommands']);


        /** test findRoboCommand error handling */
        $app   = new Application($this->loader);
        $lg->setValue($app, $this->logger);
        $app->findSymfonyCommands('test');
        $msg = '';
        try {
            $app->findRoboCommands('test');
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Can\'t initialize robo command/', $msg);
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        /** test findRoboCommand nominal handling */
        $app   = new Application($this->loader);
        $lg->setValue($app, $this->logger);

        /** check commandClasses is empty */
        $this->assertNull($cc->getValue($app));
        /** find  */
        $app->findRoboCommands('roboTestCommands');
        $this->assertEquals(['DgfipSI1\ApplicationTests\roboTestCommands\AppTestRoboFile'], $cc->getValue($app));
        $this->assertNoticeInLog("robo command(s) found");
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();
    }
       /**
     *  test symfony finders
     *
     * @covers \DgfipSI1\Application\Application::findSymfonyCommands
     * @covers \DgfipSI1\Application\Application::configureAndRegisterServices
     *
     */
    public function testSymfonyClassFinders(): void
    {
        /** Initialize reflection properties */
        $rcs = $this->class->getMethod('configureAndRegisterServices');
        $rcs->setAccessible(true);
        $symfoClass = $this->class->getConstant('SYMFONY_SUBCLASS');
        $symfoApp   = $this->class->getConstant('SYMFONY_APPLICATION');

        /** test findSymfonyCommand error handling */
        $app   = new Application($this->loader);
        $this->initLogger($app, ['findSymfonyCommands', 'configureAndRegisterServices']);
        $app->findRoboCommands('test');
        $msg = '';
        try {
            $app->findSymfonyCommands('test');
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Can\'t initialize symfony command/', $msg);
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        /** test findSymfonyCommand nominal case */

        /** test findSymfonyCommand and configureAndRegisterServices */
        $app   = new Application($this->loader);
        $this->initLogger($app, ['findSymfonyCommands', 'configureAndRegisterServices']);
        $app->findSymfonyCommands('symfonyTestCommands');
        /** find symfony commands */
        $this->assertNoticeInLog("service(s) found");
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        /** test symfony errors */
        $returnedValue = $rcs->invokeArgs($app, [ 'symfonyBadCommands', $symfoClass ]);
        $this->assertWarningInLog('Service could not be added');
        $this->assertWarningInLog('No service found');
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();
    }

    /**
     *  test discoverPsr4Classes
     *
     * @covers \DgfipSI1\Application\Application::discoverPsr4Classes
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
    */
    public function testDiscoverPsr4Classes(): void
    {
        $disc = $this->class->getMethod('discoverPsr4Classes');
        $disc->setAccessible(true);
        $lg = $this->class->getProperty('logger');
        $lg->setAccessible(true);
        //$this->logger = new TestLogger(['discoverPsr4Classes']);
        $this->logger = new TestLogger(['discoverPsr4Classes']);


        $symfoClass = $this->class->getConstant('SYMFONY_SUBCLASS');
        $roboClass  = $this->class->getConstant('ROBO_SUBCLASS');

        /** test nominal case : discover one class  */
        $app   = new Application($this->loader);
        $lg->setValue($app, $this->logger);
        $returnedValue = $disc->invokeArgs($app, [ 'roboTestCommands', $roboClass ]);
        /** check results */
        $this->assertEquals(['DgfipSI1\ApplicationTests\roboTestCommands\AppTestRoboFile'], $returnedValue);
        // $this->assertDebugInLog("1/2 - search");
        $this->assertDebugInLog("1/2 - search {namespace} namespace - found");
        $this->assertNoticeInLog("1/2 - 1 classe(s) found.");
        $this->assertDebugInLog("2/2 - Filter : {class} matches");
        $this->assertNoticeInLog("2/2 - 1 classe(s) found in namespace");
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        /** test class in error */
        $app   = new Application($this->loader);
        $lg->setValue($app, $this->logger);
        $returnedValue = $disc->invokeArgs($app, [ 'symfonyBadCommands', $roboClass ]);
        $this->assertEquals([], $returnedValue);

        $this->assertDebugInLog("1/2 - search {namespace} namespace - found");
        $this->assertNoticeInLog("1/2 - 2 classe(s) found.");
        $this->assertWarningInLog('2/2 Class "DgfipSI1\ApplicationTests\symfonyBadCommands\BadCommand" does not exist');
        $this->assertWarningInLog("No classes subClassing {subClass} found in namespace '{namespace}'");
        // $this->assertNoticeInLog("1/2 - 1 classe(s) found.");

        // $this->showNoDebugLogs();
        // $this->showDebugLogs();
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();
    }
    /**
     *  test roboRun
     *
     * @covers \DgfipSI1\Application\Application::go
     *
     */
    public function testRoboRun(): void
    {
        $this->expectOutputString('Hello !');
        $app = new Application($this->loader, [ './test', '--quiet', 'hello:test']);
        $app->setName('test');
        $app->setVersion('1.00');
        $app->findRoboCommands('roboTestCommands');
        /* check that calling findSymphonyCommands throws an error */
        $rc = $app->go();
        $this->assertEquals(0, $rc);
    }
    /**
     *  test symfonyRun
     *
     * @covers \DgfipSI1\Application\Application::go
     * @covers \DgfipSI1\Application\Application::finalize
     *
     */
    public function testSymfonyRun(): void
    {
        $this->expectOutputString('Hello world !!');
        $app = new Application($this->loader, [ './test', 'hello']);

        $app->setName('test');
        $app->setVersion('1.00');
        $app->findSymfonyCommands('symfonyTestCommands');
        $rc = $app->go();
        $this->assertEquals(0, $rc);
    }
}
