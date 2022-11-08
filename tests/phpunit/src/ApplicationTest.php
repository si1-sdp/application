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
use DgfipSI1\Application\ApplicationSchema as CONF;
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
use Symfony\Component\Console\Output\OutputInterface;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 *
 * @uses \DgfipSI1\Application\Application
 * @uses \DgfipSI1\Application\ApplicationSchema
 * @uses \DgfipSI1\Application\ApplicationContainer
 * @uses \DgfipSI1\Application\Config\BaseSchema
 * @uses \DgfipSI1\Application\Listeners\InputOptionsToConfig
 * @uses \DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses \DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses \DgfipSI1\Application\ApplicationLogger
 * @uses \DgfipSI1\Application\Utils\ClassDiscoverer
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
     * @covers \DgfipSI1\Application\Config\BaseSchema::getConfigTreeBuilder
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
        /** build to force getConfigTreeBuilder launching */
        $app->config()->build();
        $input = $inputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Input\ArgvInput', $input);
        $output = $outputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Output\ConsoleOutput', $output);
        $container = $containerProp->getValue($app);
        $this->assertInstanceOf(ApplicationContainer::class, $container);
        $config = $configProp->getValue($app);
        $this->assertInstanceOf('DgfipSI1\ConfigHelper\ConfigHelper', $config);
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

        $this->assertEquals($configFile, $config->get(CONF::RUNTIME_INT_CONFIG));
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

        /* TEST 2 - Bad input (no value for --config) */
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
            /** @phpstan-ignore-next-line */
        } catch (ConfigFileNotFoundException $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression("/Configuration file '.*' not found/", $msg);

        /* TEST 4 - Load existing file */
        file_put_contents($configFile, "foo:\n  bar: 999\n");
        $app->config()->setCheckOption(false);  // don't check config
        $cs->invokeArgs($app, []);
        $this->assertEquals(999, $app->config()->get('foo.bar'));
    }
    /**
     * Test discover commands
     *
     * @covers \DgfipSI1\Application\Application::discoverCommands
     *
     */
    public function testDiscoverCommands(): void
    {
        $app   = new Application($this->loader);
        $cc = $this->class->getMethod('discoverCommands');
        $cc->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $at = $this->class->getProperty('appType');
        $at->setAccessible(true);
        $roboType = $this->class->getConstant('ROBO_APPLICATION');
        /** @var string $symfonyCmd */
        $symfonyCmd = $this->class->getConstant('SYMFONY_SUBCLASS');

        // we want discover log to check namespace
        $testedMethods = ['discoverCommands', 'discoverPsr4Classes', 'discoverClassesInNamespace', 'filterClasses'];
        $this->initLogger($app, $testedMethods);


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
            /** @phpstan-ignore-next-line */
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Type mismatched - findRoboCommands lauched/', $msg);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* TEST 3 - appType to empty, configuration type = symfony, namespace = default */
        $at->setValue($app, null);
        $cc->invokeArgs($app, []);
        $this->assertInfoInLog("1/2 - 0 classe(s) found.");
        $this->assertWarningInLog("No classes subClassing or implementing $symfonyCmd found", true);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* TEST 4 - appType to empty, configuration type = symfony, namespace = symfonyTestCommands */
        $conf->setDefault(CONF::APPLICATION_NAMESPACE, 'symfonyTestCommands');
        $at->setValue($app, null);
        $cc->invokeArgs($app, []);
        $this->assertDebugInLog('1/2 - search {namespace} namespace - found {class}');
        $this->assertInfoInLog("1/2 - 1 classe(s) found.");
        $this->assertDebugInLog('2/2 - Filter : {class} matches');
        $this->assertInfoInLog("2/2 - 1 classe(s) found in namespace 'symfonyTestCommands'", true);
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

       /* TEST 5 - appType to empty, configuration type = robo, namespace = 'Foo' */
        $conf->setDefault(CONF::APPLICATION_NAMESPACE, 'foo');
        $conf->setDefault(CONF::APPLICATION_TYPE, 'robo');
        $at->setValue($app, null);
        $cc->invokeArgs($app, []);
        $this->assertInfoInLog('1/2 - 0 classe(s) found.');
        $this->assertWarningInLog("No classes subClassing or implementing \Robo\Tasks found", true);
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
            /** @phpstan-ignore-next-line */
        } catch (LogicException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('invalid Service provided', $msg);

        // test with bad name attribute
        $msg = '';
        try {
            $asc->invokeArgs($app, [ $object , 'unknown', 'myTag']);
            /** @phpstan-ignore-next-line */
        } catch (LogicException $e) {
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
     *  test roboRun
     *
     * @covers \DgfipSI1\Application\Application::go
     * @covers \DgfipSI1\Application\Application::finalize
     *
     */
    public function testRoboRun(): void
    {
        $m = \Mockery::mock('overload:Robo\Runner')->makePartial();
        $m->shouldReceive('setContainer');
        $m->shouldReceive('run')->andReturn(0);  /** @phpstan-ignore-line  */

        $app = new Application($this->loader, [ './test', 'hello:test']);
        $app->setName('test');
        $app->setVersion('1.00');
        $app->findRoboCommands('roboTestCommands');
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
