<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\Application\Utils\ApplicationLogger;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use Mockery;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Console\Application as symfoApp;
use Symfony\Component\Console\Output\ConsoleOutput;

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
class AbstractApplicationTest extends LogTestCase
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

    /** @var \ReflectionClass<symfoApp> */
    protected $pp;

    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $this->root = vfsStream::setup();
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
        $this->class = new \ReflectionClass(SymfonyApplication::class);
        $this->lg = $this->class->getProperty('logger');
        $this->lg->setAccessible(true);
        $this->logger = new TestLogger();
        /** @var ReflectionClass $p */
        $p = $this->class->getParentClass();    // AbstractApplication
        /** @var ReflectionClass $pp */
        $pp = $p->getParentClass();      // synfony\component\Console\Application
        $this->pp = $pp;
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
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @covers \DgfipSI1\Application\AbstractApplication::__construct
     */
    public function testConstructor(): void
    {
        /** @var \Mockery\MockInterface $m */
        $m = Mockery::mock('overload:DgfipSI1\Application\Utils\MakePharCommand')->makePartial();
        $m->shouldReceive('getPharRoot')->once()->andReturn('mockedPharRoot');

        $inputProp = $this->class->getProperty('input');
        $inputProp->setAccessible(true);
        $outputProp = $this->class->getProperty('output');
        $outputProp->setAccessible(true);
        $containerProp = $this->class->getProperty('container');
        $containerProp->setAccessible(true);
        $configProp = $this->class->getProperty('config');
        $configProp->setAccessible(true);

        $app = new SymfonyApplication($this->loader, ['/tmp/tests', '-q']);

        // test parent constructor has been called => defaultCommand = 'list'
        $dc = $this->pp->getProperty('defaultCommand');
        $dc->setAccessible(true);
        self::assertEquals('list', $dc->getValue($app));

        self::assertEquals('/tmp/tests', $app->getEntryPoint());
        self::assertEquals('/tmp', $app->getHomeDir());
        self::assertEquals(getcwd(), $app->getCurrentDir());

        self::assertEquals('mockedPharRoot', $app->getPharRoot());

        /** build to force getConfigTreeBuilder launching */
        $config = $app->getConfig();
        /** @var ConfigHelper $config */
        $config->build();
        $input = $inputProp->getValue($app);
        self::assertInstanceOf('\Symfony\Component\Console\Input\ArgvInput', $input);
        /** @var ConsoleOutput $output */
        $output = $outputProp->getValue($app);
        $v = ApplicationLogger::getVerbosity($input);
        self::assertEquals($v, $output->getVerbosity());
        self::assertInstanceOf('\Symfony\Component\Console\Output\ConsoleOutput', $output);

        $container = $containerProp->getValue($app);
        self::assertInstanceOf(Container::class, $container);
        /** @var ConfigHelper $config */
        $config = $configProp->getValue($app);
        self::assertInstanceOf('DgfipSI1\ConfigHelper\ConfigHelper', $config);
        // check that config has a logger
        $lgProp = (new ReflectionClass($config::class))->getProperty('logger');
        $lgProp->setAccessible(true);
        self::assertInstanceOf(LoggerInterface::class, $lgProp->getValue($config));

        // check that discoverer has been correctly initalized
        /** @var ClassDiscoverer $disc */
        $disc = $app->getContainer()->get('class_discoverer');
        self::assertEquals($disc->getContainer(), $app->getContainer());
        self::assertInstanceOf(LoggerInterface::class, $disc->getLogger());

        $ae = $this->pp->getProperty('autoExit');
        $ae->setAccessible(true);
        self::assertEquals(false, $ae->getValue($app));


        // test entrypoint and home dir with argv  empty
        $app = new SymfonyApplication($this->loader);
        self::assertEquals('', $app->getEntryPoint());
        self::assertEquals(getcwd(), $app->getHomeDir());
    }
    /**
     * data provider
     *
     * @return array<string,array<mixed>>
     */
    public function setupConfigData()
    {
        return [
            'no_config   ' => [  null ,    false ],
            'phar_conf_ko' => [ 'phar',    false ],
            'phar_conf_ok' => [ 'phar',    true  ],
            'home_conf_ko' => [ 'home',    false ],
            'home_conf_ok' => [ 'home',    true  ],
            'curr_dir_ko ' => [ 'current', false ],
            'curr_dir_ok ' => [ 'current', true  ],
        ];
    }

    /**
     * @covers DgfipSI1\Application\AbstractApplication::setupApplicationConfig
     *
     * @dataProvider setupConfigData
     *
     * @param string|null $configDir
     * @param bool        $good
     *
     * @return void
     */
    public function testSetupApplicationConfig($configDir, $good): void
    {
        /* make setupApplicationConfig available */
        $ac = $this->class->getMethod('setupApplicationConfig');
        $ac->setAccessible(true);
        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        $hd = $this->class->getProperty('homeDir');
        $hd->setAccessible(true);
        $cd = $this->class->getProperty('currentDir');
        $cd->setAccessible(true);
        $pr = $this->class->getProperty('pharRoot');
        $pr->setAccessible(true);

        // /** @var \Mockery\MockInterface $m */
        // $m = Mockery::mock('overload:DgfipSI1\Application\SymfonyApplication')->makePartial();
        // $m->shouldReceive('')->once()->andReturn('mockedPharRoot');

        /* test with no config */
        $app = new SymfonyApplication($this->loader);
        $this->logger->setCallers(['setupApplicationConfig']);
        $app->setLogger($this->logger);

        $hd->setValue($app, $this->root->url().DIRECTORY_SEPARATOR."home");
        $cd->setValue($app, $this->root->url().DIRECTORY_SEPARATOR."current");
        $pr->setValue($app, $this->root->url().DIRECTORY_SEPARATOR."phar");
        if (null !== $configDir) {
            // generate a config file inside
            mkdir($this->root->url().DIRECTORY_SEPARATOR.$configDir);
            $dir = $this->root->url().DIRECTORY_SEPARATOR.$configDir;
            $confFile = $dir.DIRECTORY_SEPARATOR.".application-config.yml";
            if ($good) {
                $contents = "dgfip_si1:\n  application:\n    name: test_app\n    version: '0.99.0'\n";
            } else {
                $contents = "dgfip_si1:\n  application:\n    name: test_app\n  global_options:\n";
                $contents .= "    test_opt:\n      short_option: FOO\n";
            }
            file_put_contents($confFile, $contents);
            // check that first config is taken even if phar config exists
            if ('phar' !== $configDir) {
                mkdir($this->root->url().DIRECTORY_SEPARATOR.'phar');
                $farFile = $this->root->url().DIRECTORY_SEPARATOR.'phar'.DIRECTORY_SEPARATOR.".application-config.yml";
                file_put_contents($farFile, $contents);
            }
        }
        $ac->invoke($app);

        if (null === $configDir) {
            $this->assertDebugInLog("No default configuration loaded");
            $this->assertNoMoreProdMessages();
            $this->assertDebugLogEmpty();
        } else {
            if ($good) {
                $this->assertDebugInContextLog("{file} loaded.", ['name' => 'new Application()', 'file' => $confFile]);
                /** @var ConfigHelper $config */
                $config = $ic->getValue($app);
                self::assertEquals('test_app', $config->get(CONF::APPLICATION_NAME));
                self::assertEquals('0.99.0', $config->get(CONF::APPLICATION_VERSION));
            } else {
                //$this->showLogs();
                $this->assertWarningInLog("Error loading configuration $confFile : \n====");
                $this->assertDebugInLog("No default configuration loaded");
            }
        }
    }
    /**
     * @return array<string,mixed>
     */
    public function dataBadConfig(): array
    {
        $baseContents  = "dgfip_si1:\n  application:\n    name: test_app\n  global_options:\n";
        $badShort = $baseContents."    test_opt:\n      short_option: FOO\n";
        $reqOpt   = $baseContents."    test_arg:\n      required: true\n";

        return [
            'long_short_opt' => [ $badShort , 'Short option should be a one letter string'],
            'required_opt  ' => [ $reqOpt   , 'Required option only valid for argument'],
        ];
    }

    /**
     * @covers DgfipSI1\Application\SymfonyApplication::setupApplicationConfig
     *
     * @param string $contents
     * @param string $error
     *
     * @dataProvider dataBadConfig
     */
    public function tetSetupApplicationBadConfig($contents, $error): void
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
        self::assertNull($config->get('dgfip_si1.runtime.config_file'));
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
        self::assertEquals(ApplicationSchema::DEFAULT_NAMESPACE, $app->getNamespace());

        // 02 - tagged namespace present but default not set => fallback to config
        $app->setNamespace('tagged_namespace', 'tag');
        self::assertEquals(ApplicationSchema::DEFAULT_NAMESPACE, $app->getNamespace());

        /* 02 - set a default namespace :
         *   - default getters return default namespace
         *   - tagged getter on non existen tag falls back to default
         *   - tagged getter on existen tag returns tegged namespace
         */
        $app->setNamespace('default_namespace');
        self::assertEquals('default_namespace', $app->getNamespace());
        self::assertEquals('default_namespace', $app->getNamespace('not-here'));
        self::assertEquals('tagged_namespace', $app->getNamespace('tag'));

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
        self::assertEquals('Can\'t determine namespace', $msg);
    }

    /**
     *  test getters
     *
     * @covers \DgfipSI1\Application\AbstractApplication::getEntryPoint
     * @covers \DgfipSI1\Application\AbstractApplication::getHomeDir
     * @covers \DgfipSI1\Application\AbstractApplication::getCurrentDir
     * @covers \DgfipSI1\Application\AbstractApplication::getPharRoot
     * @covers \DgfipSI1\Application\AbstractApplication::getPharExcludes     *
     */
    public function testGetters(): void
    {
        $app = new SymfonyApplication($this->loader, ['./test', 'hello']);

        $ic = $this->class->getProperty('intConfig');
        $ic->setAccessible(true);
        /** @var ConfigHelper $conf */
        $conf = $ic->getValue($app);
        $conf->set('dgfip_si1.phar.excludes', ['tmp']);
        self::assertEquals('./test', $app->getEntryPoint());
        self::assertEquals(getcwd(), $app->getHomeDir());
        self::assertEquals(getcwd(), $app->getCurrentDir());
        self::assertEquals(false, $app->getPharRoot());
        self::assertEquals(['tmp'], $app->getPharExcludes());
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
        $ret = $an->invokeArgs($app, ['set-app']);
        self::assertEquals($app, $ret);
        $ret = $av->invokeArgs($app, ['set-ver']);
        self::assertEquals($app, $ret);
        $setav->invoke($app);
        self::assertEquals('set-app', $config->get(CONF::APPLICATION_NAME));
        self::assertEquals('set-ver', $config->get(CONF::APPLICATION_VERSION));
        self::assertEquals('set-app', $app->getName());
        self::assertEquals('set-ver', $app->getVersion());

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
        self::assertEquals("Application name missing", $msg);

        // test with name but no version
        $config->set(CONF::APPLICATION_NAME, 'conf-app');
        $msg = '';
        try {
            $setav->invoke($app);
            /** @phpstan-ignore-next-line  false deadcatch on Reflection */
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals("Version missing", $msg);

        // test with name and version
        $config->set(CONF::APPLICATION_VERSION, 'conf-ver');
        $setav->invoke($app);
        self::assertEquals('conf-app', $app->getName());
        self::assertEquals('conf-ver', $app->getVersion());
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
        $app->setLogger($this->logger);
        self::assertEquals([], $app->getMappedOptions());
        self::assertEquals([], $app->getMappedOptions('test_cmd'));
        $app->addMappedOption((new MappedOption('test_scalar', OptionType::Scalar))->setCommand('test_cmd'));
        $ctx = ['name' => 'addMappedOption' ];
        $this->assertDebugInContextLog('Adding option {opt}', $ctx + [ 'opt' => 'test_scalar', 'cmd' => 'test_cmd']);
        $app->addMappedOption((new MappedOption('test_bool', OptionType::Boolean))->setCommand('test_cmd'));
        $this->assertDebugInContextLog('Adding option {opt}', $ctx + [ 'opt' => 'test_bool', 'cmd' => 'test_cmd']);
        $app->addMappedOption(new MappedOption('test_global', OptionType::Scalar));
        $this->assertDebugInContextLog('Adding option {opt}', $ctx + [ 'opt' => 'test_global', 'cmd' => 'global']);
        $options = $app->getMappedOptions('test_cmd');
        self::assertArrayHasKey('test_scalar', $options);
        self::assertInstanceOf(MappedOption::class, $options['test_scalar']);
        self::assertArrayHasKey('test_bool', $options);
        self::assertInstanceOf(MappedOption::class, $options['test_bool']);
        self::assertEquals(2, count($options));
        $options = $app->getMappedOptions();
        self::assertArrayHasKey('test_global', $options);
        self::assertInstanceOf(MappedOption::class, $options['test_global']);
        self::assertEquals(1, count($options));
    }
    /**
     * @covers \DgfipSI1\Application\AbstractApplication::addDiscoveries
     * @covers \DgfipSI1\Application\AbstractApplication::discoverClasses
     *
     */
    public function testDiscoverClasses(): void
    {
        $app = new SymfonyApplication($this->loader);
        $app->getContainer()->extend('class_discoverer')->setAlias('old_disc');
        /** @var \Mockery\MockInterface $discoverer */
        $discoverer = Mockery::mock(ClassDiscoverer::class);
        $discoverer->shouldAllowMockingProtectedMethods();
        $discoverer->makePartial();
        $discoverer->shouldReceive('addDiscoverer')->once()->with('ns', 'tag', [], [], null, true);
        $discoverer->shouldReceive('discoverAllClasses')->once();
        $app->getContainer()->addShared('class_discoverer', $discoverer);

        $app->addDiscoveries('ns', 'tag', []);
        $app->discoverClasses();
    }
}
