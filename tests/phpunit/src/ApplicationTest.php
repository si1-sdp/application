<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Application;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\Application\RoboApplication;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\ApplicationTests\TestClasses\Commands\HelloWorldCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use Mockery;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use ReflectionClass;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 *
 * @uses \DgfipSI1\Application\AbstractApplication
 * @uses \DgfipSI1\Application\ApplicationSchema
 * @uses \DgfipSI1\Application\RoboApplication
 * @uses \DgfipSI1\Application\SymfonyApplication
 * @uses \DgfipSI1\Application\Config\BaseSchema
 * @uses \DgfipSI1\Application\Config\ConfigLoader
 * @uses \DgfipSI1\Application\Config\InputOptionsInjector
 * @uses \DgfipSI1\Application\Config\InputOptionsSetter
 * @uses \DgfipSI1\Application\Config\MappedOption
 * @uses \DgfipSI1\Application\Config\OptionType
 * @uses \DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses \DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses \DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses \DgfipSI1\Application\Utils\DiscovererDefinition
 * @uses \DgfipSI1\Application\Utils\ApplicationLogger
 * @uses \DgfipSI1\Application\Utils\MakePharCommand
 */
class ApplicationTest extends LogTestCase
{
    protected const COMMAND_NAMESPACE = 'TestClasses\\Commands';

    /** @var ClassLoader $loader */
    protected $loader;

    /** @var \ReflectionClass<ApplicationInterface> */
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
        $this->class = new \ReflectionClass(SymfonyApplication::class);
        $this->lg = $this->class->getProperty('logger');
        $this->lg->setAccessible(true);
        $this->logger = new TestLogger();
        $this->root = vfsStream::setup();
    }

    /**
     * initialize tested class for logger
     *
     * @param ApplicationInterface $application
     * @param array<string>        $testedClasses
     *
     * @return void
     */
    public function initLogger($application, $testedClasses = null)
    {
        $this->logger = new TestLogger();
        if (null !== $testedClasses) {
            $this->logger->setCallers($testedClasses);
        }
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
        \Mockery::close();
    }
    /**
     *  test constructor
     *
     * @covers \DgfipSI1\Application\AbstractApplication::__construct
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

        $app = new SymfonyApplication($this->loader);
        /** build to force getConfigTreeBuilder launching */
        $config = $app->getConfig();
        /** @var ConfigHelper $config */
        $config->build();
        $input = $inputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Input\ArgvInput', $input);
        $output = $outputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Output\ConsoleOutput', $output);
        $container = $containerProp->getValue($app);
        $this->assertInstanceOf(Container::class, $container);
        $config = $configProp->getValue($app);
        $this->assertInstanceOf('DgfipSI1\ConfigHelper\ConfigHelper', $config);
    }
   /**
     * @covers DgfipSI1\Application\SymfonyApplication::setupApplicationConfig
     * @covers DgfipSI1\Application\ApplicationSchema
     */
    public function testSetupApplicationPharConfig(): void
    {
        /* make setupApplicationConfig available */
        $ac = $this->class->getMethod('setupApplicationConfig');
        $ac->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $pr = $this->class->getProperty('pharRoot');
        $pr->setAccessible(true);

        $app = new SymfonyApplication($this->loader, ['./test']);
        $phar = 'phar://'.realpath(__DIR__.'/../../data/config/test.phar');
        $pr->setValue($app, $phar);
        $ac->invoke($app);
    }
    /**
     * @covers DgfipSI1\Application\SymfonyApplication::setupApplicationConfig
     * @covers DgfipSI1\Application\ApplicationSchema
     */
    public function testSetupApplicationNormalConfig(): void
    {
        /* make setupApplicationConfig available */
        $ac = $this->class->getMethod('setupApplicationConfig');
        $ac->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $hd = $this->class->getProperty('homeDir');
        $hd->setAccessible(true);

        /* test with no config */
        $app = new SymfonyApplication($this->loader);
        $this->initLogger($app, ['setupApplicationConfig']);
        $ac->invokeArgs($app, []);
        $this->assertDebugInLog("No default configuration loaded");
        $this->assertNoMoreProdMessages();
        $this->assertDebugLogEmpty();

        /* setup a config  */
        $hd->setValue($app, $this->root->url());
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

        $this->assertEquals('test_app', $config->get(CONF::APPLICATION_NAME));
        $this->assertEquals('0.99.0', $config->get(CONF::APPLICATION_VERSION));
    }
    /**
     * @return array<string,mixed>
     */
    public function dataBadConfig(): array
    {
        $baseContents  = "dgfip_si1:\n  application:\n    name: test_app\n  global_options:\n";
        $badShort = $baseContents."    test_opt:\n      short_option: FOO\n";
        $reqOpt   = $baseContents."    test_arg:\n      required: true\n";

        $data['long_short_opt'] = [ $badShort , 'Short option should be a one letter string'];
        $data['required_opt  '] = [ $reqOpt   , 'Required option only valid for argument'];

        return $data;
    }

    /**
     * @covers DgfipSI1\Application\SymfonyApplication::setupApplicationConfig
     * @covers DgfipSI1\Application\ApplicationSchema
     *
     * @param string $contents
     * @param string $error
     *
     * @dataProvider dataBadConfig
     */
    public function testSetupApplicationBadConfig($contents, $error): void
    {
        /* make setupApplicationConfig available */
        $ac = $this->class->getMethod('setupApplicationConfig');
        $ac->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $hd = $this->class->getProperty('homeDir');
        $hd->setAccessible(true);

        $app = new SymfonyApplication($this->loader, ['./test', 'hello', '-q']);
        /* setup a config  */
        $hd->setValue($app, $this->root->url());
        $configFile = $this->root->url().DIRECTORY_SEPARATOR.".application-config.yml";

        $this->logger = new TestLogger();
        $app->setLogger($this->logger);
        file_put_contents($configFile, $contents);
        $ac->invokeArgs($app, []);
        /** @var ConfigInterface $config */
        $config = $ic->getValue($app);
        $this->assertNull($config->get('dgfip_si1.runtime.config_file'));
        $this->assertWarningInLog($error);
        $this->assertDebugInLog("No default configuration loaded");
    }


    /**
     *  test getters
     *
     * @covers \DgfipSI1\Application\AbstractApplication::setNamespace
     * @covers \DgfipSI1\Application\AbstractApplication::getNamespace
     *
     */
    public function testGetSetNamespace(): void
    {
        $app = new SymfonyApplication($this->loader);

        // 01 - test getter via config
        $this->assertEquals(ApplicationSchema::DEFAULT_NAMESPACE, $app->getNamespace());

        // 02 - tagged namespace present but default not set => fallback to config
        $app->setNamespace('tagged_namespace', 'tag');
        $this->assertEquals(ApplicationSchema::DEFAULT_NAMESPACE, $app->getNamespace());

        /* 02 - set a default namespace :
         *   - default getters return default namespace
         *   - tagged getter on non existen tag falls back to default
         *   - tagged getter on existen tag returns tegged namespace
         */
        $app->setNamespace('default_namespace');
        $this->assertEquals('default_namespace', $app->getNamespace());
        $this->assertEquals('default_namespace', $app->getNamespace('not-here'));
        $this->assertEquals('tagged_namespace', $app->getNamespace('tag'));

        // 01 - test exception
        $conf = new ConfigHelper();
        $appClass = new ReflectionClass(AbstractApplication::class);
        $ic = $appClass->getProperty('intConfig');
        $ic->setAccessible(true);
        $ic->setValue($app, $conf);
        $ns = $appClass->getProperty('namespaces');
        $ns->setAccessible(true);
        $ns->setValue($app, []);

        $msg = '';
        try {
            $app->getNamespace();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('Can\'t determine namespace', $msg);
    }

    /**
     *  test getters
     *
     * @covers \DgfipSI1\Application\SymfonyApplication::__construct
     * @covers \DgfipSI1\Application\SymfonyApplication::getCommand
     * @covers \DgfipSI1\Application\SymfonyApplication::getCmdName
     * @covers \DgfipSI1\Application\SymfonyApplication::getEntryPoint
     * @covers \DgfipSI1\Application\SymfonyApplication::getHomeDir
     * @covers \DgfipSI1\Application\SymfonyApplication::getCurrentDir
     * @covers \DgfipSI1\Application\SymfonyApplication::getPharRoot
     * @covers \DgfipSI1\Application\SymfonyApplication::getPharExcludes
     *
     */
    public function testGetters(): void
    {
        $app = new SymfonyApplication($this->loader, ['./test', 'hello']);
        $cmd = new HelloWorldCommand();
        $app->add($cmd);
        $def = $app->getContainer()->addShared(HelloWorldCommand::class);
        $def->addTag((string) $cmd->getName());

        $this->assertEquals('hello', $app->getCmdName());
        $this->assertEquals('./test', $app->getEntryPoint());
        $this->assertEquals(getcwd(), $app->getHomeDir());
        $this->assertEquals(getcwd(), $app->getCurrentDir());
        $this->assertEquals(false, $app->getPharRoot());
        $this->assertEquals([], $app->getPharExcludes());

        $found = $app->getCommand((string) $cmd->getName());
        $this->assertTrue($found::class === HelloWorldCommand::class);
        $msg = '';
        try {
            $found = $app->getCommand('foo');
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('Error looking for command foo in container', $msg);
    }
    /**
     * Tests application name and version handling
     *
     * @covers \DgfipSI1\Application\AbstractApplication::setName
     * @covers \DgfipSI1\Application\AbstractApplication::setVersion
     * @covers \DgfipSI1\Application\AbstractApplication::setApplicationNameAndVersion
     *
     * @return void
     */
    public function testAppNameAndVersion(): void
    {
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $an = $this->class->getMethod('setName');
        $an->setAccessible(true);
        $av = $this->class->getMethod('setVersion');
        $av->setAccessible(true);
        $setav = $this->class->getMethod('setApplicationNameAndVersion');
        $setav->setAccessible(true);

        /** test with name and version set */
        $app = new SymfonyApplication($this->loader);
        $config = new ConfigHelper();
        $ic->setValue($app, $config);
        $an->invokeArgs($app, ['set-app']);
        $av->invokeArgs($app, ['set-ver']);
        $setav->invoke($app);
        $this->assertEquals('set-app', $config->get(CONF::APPLICATION_NAME));
        $this->assertEquals('set-ver', $config->get(CONF::APPLICATION_VERSION));
        $this->assertEquals('set-app', $app->getName());
        $this->assertEquals('set-ver', $app->getVersion());

        // test without name and version
        $app = new SymfonyApplication($this->loader);
        $config = new ConfigHelper();
        $ic->setValue($app, $config);
        $msg = '';
        try {
            $setav->invoke($app);
            /** @phpstan-ignore-next-line  false deadcatch on Reflection */
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Application name missing", $msg);

        // test with name but no version
        $config->set(CONF::APPLICATION_NAME, 'conf-app');
        $msg = '';
        try {
            $setav->invoke($app);
            /** @phpstan-ignore-next-line  false deadcatch on Reflection */
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Version missing", $msg);

        // test with name and version
        $config->set(CONF::APPLICATION_VERSION, 'conf-ver');
        $setav->invoke($app);
        $this->assertEquals('conf-app', $app->getName());
        $this->assertEquals('conf-ver', $app->getVersion());
    }
    /**
     *  test add/get MappedOptions
     *
     * @covers \DgfipSI1\Application\AbstractApplication::addMappedOption
     * @covers \DgfipSI1\Application\AbstractApplication::getMappedOptions
     *
     */
    public function testMappedOptions(): void
    {
        $app = new SymfonyApplication($this->loader);
        $this->assertEquals([], $app->getMappedOptions());
        $this->assertEquals([], $app->getMappedOptions('test_command'));
        $app->addMappedOption((new MappedOption('test_scalar', OptionType::Scalar))->setCommand('test_command'));
        $app->addMappedOption((new MappedOption('test_bool', OptionType::Boolean))->setCommand('test_command'));
        $app->addMappedOption(new MappedOption('test_global', OptionType::Scalar));
        $options = $app->getMappedOptions('test_command');
        $this->assertArrayHasKey('test_scalar', $options);
        $this->assertInstanceOf(MappedOption::class, $options['test_scalar']);
        $this->assertArrayHasKey('test_bool', $options);
        $this->assertInstanceOf(MappedOption::class, $options['test_bool']);
        $this->assertEquals(2, count($options));
        $options = $app->getMappedOptions();
        $this->assertArrayHasKey('test_global', $options);
        $this->assertInstanceOf(MappedOption::class, $options['test_global']);
        $this->assertEquals(1, count($options));
    }
    /**
     * @covers \DgfipSI1\Application\AbstractApplication::discoverClasses
     *
     */
    public function testDiscoverClasses(): void
    {
        $app = new SymfonyApplication($this->loader);
        $app->getContainer()->extend('class_discoverer')->setAlias('old_disc');
        /** @var Mock $discoverer */
        $discoverer = Mockery::mock(ClassDiscoverer::class);
        $discoverer->shouldAllowMockingProtectedMethods();
        $discoverer->makePartial();
        /** @phpstan-ignore-next-line */
        $discoverer->shouldReceive('addDiscoverer')->once()->with('ns', 'tag', [], [], null, true);
        $app->getContainer()->addShared('class_discoverer', $discoverer);

        $app->discoverClasses('ns', 'tag', []);
    }
    /**
     *  test roboRun
     *
     * @covers \DgfipSI1\Application\RoboApplication::go
     * @covers \DgfipSI1\Application\RoboApplication::finalize
     * @covers \DgfipSI1\Application\RoboApplication::registerCommands
     * @covers \DgfipSI1\Application\RoboApplication::configureContainer
     *
     */
    public function testRoboRun(): void
    {
        $m = \Mockery::mock('overload:Robo\Runner')->makePartial();
        $m->shouldReceive('setContainer');
        $m->shouldReceive('run')->andReturn(0);  /** @phpstan-ignore-line  */

        $app = new RoboApplication($this->loader, [ './test', 'hello:test', '-q']);
        // setup the logger

        // setup internal config
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $config = new ConfigHelper();
        $ic->setValue($app, $config);
        $config->set(CONF::APPLICATION_NAMESPACE, self::COMMAND_NAMESPACE);

        // set name and version
        $app->setName('test');
        $app->setVersion('1.00');

        $rc = $app->go();
        $this->assertEquals(0, $rc);
    }
    /**
     *  test symfonyRun
     *
     * @covers \DgfipSI1\Application\SymfonyApplication::go
     * @covers \DgfipSI1\Application\SymfonyApplication::finalize
     * @covers \DgfipSI1\Application\SymfonyApplication::registerCommands
     * @covers \DgfipSI1\Application\SymfonyApplication::configureContainer
     *
     */
    public function testSymfonyRun(): void
    {
        $this->expectOutputString('Hello world !!');
        $app = new SymfonyApplication($this->loader, [ './test', 'hello']);
        $this->initLogger($app);
        // setup internal config
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $config = new ConfigHelper();
        $ic->setValue($app, $config);
        $config->set(CONF::APPLICATION_NAMESPACE, self::COMMAND_NAMESPACE);

        // set name and version
        $app->setName('test');
        $app->setVersion('1.00');

        $rc = $app->go();
        $this->assertEquals('set via event', $app->getConfig()->get("options.test_event"));
        $this->assertAlertInLog('Advanced logger configuration applies only to Monolog');
        $this->assertInfoInLog('classe(s) found');
        $this->assertInfoInLog('command hello registered', true);
        $this->assertInfoInLog('Setting up command options for hello', true);
        $this->assertInfoInLog('Adding input options for command \'hello\'', true);
        $this->assertInfoInLog('Launching symfony command \'hello\'', true);
        $this->logReset();
        $this->assertEquals(0, $rc);
    }
}
