<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\Util\ConfigOverlay;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Config\ConfigLoader;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldCommand;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldSchema;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use Mockery;
use Mockery\Mock;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Path;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\SymfonyApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfigLoader
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses DgfipSI1\Application\Utils\MakePharCommand
 * @uses DgfipSI1\Application\Config\InputOptionsSetter::safeBind
 */
class ConfigLoaderTest extends LogTestCase
{
    /** @var string appRoot */
    protected $appRoot;
    /** @var ClassLoader $classLoader      */
    protected $classLoader;
    /** @var ReflectionClass $class        */
    protected $class;
    /** @var ReflectionProperty $cd        */
    protected $cd;
    /** @var ReflectionProperty $np        */
    protected $np;
    /** @var ReflectionProperty $pp        */
    protected $pp;
    /** @var ReflectionProperty $de        */
    protected $de;
    /** @var ReflectionProperty $sn        */
    protected $sn;
    /** @var ReflectionProperty $root      */
    protected $root;
    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $path = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..';
        $this->appRoot = (string) realpath($path);
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->class = new ReflectionClass(ConfigLoader::class);
        $this->classLoader = $loaders[0];
        $this->cd = $this->class->getProperty('configDir');
        $this->cd->setAccessible(true);
        $this->np = $this->class->getProperty('namePatterns');
        $this->np->setAccessible(true);
        $this->pp = $this->class->getProperty('pathPatterns');
        $this->pp->setAccessible(true);
        $this->de = $this->class->getProperty('depth');
        $this->de->setAccessible(true);
        $this->sn = $this->class->getProperty('sortByName');
        $this->sn->setAccessible(true);
    }
    /**
     *
     * @inheritDoc
     */
    public function tearDown(): void
    {
        \Mockery::close();
    }
    /**
     * test subscribedEvents
     *
     * @covers \DgfipSI1\Application\Config\ConfigLoader::getSubscribedEvents
     *
     * @return void
     */
    public function testGetSubscribedEvents()
    {
        $events = ConfigLoader::getSubscribedEvents();
        self::assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        foreach ($events as $handler) {
            /** @var string $method */
            $method = $handler[0];
            self::assertTrue(method_exists(ConfigLoader::class, $method));
            self::assertEquals(0, $handler[1] % 10);         // priority should be a multiple of 10
        }
    }
    /**
     * Test ClassLoader configuration
     *
     * @covers \DgfipSI1\Application\Config\ConfigLoader::configure
     *
     * @return void
     */
    public function testConfigure()
    {
        $testConfig = [
            CONF::CONFIG_DIRECTORY         => 'conf_dir',
            CONF::CONFIG_NAME_PATTERNS     => ['name1', 'name2'],
            CONF::CONFIG_PATH_PATTERNS     => ['path1', 'path2'],
            CONF::CONFIG_SEARCH_RECURSIVE  => true,
            CONF::CONFIG_SORT_BY_NAME      => 'sort',
        ];
        $loader = $this->createConfigLoader(config: $testConfig);
        self::assertEquals('conf_dir', $this->cd->getValue($loader));
        self::assertEquals(['name1', 'name2'], $this->np->getValue($loader));
        self::assertEquals(['path1', 'path2'], $this->pp->getValue($loader));
        self::assertEquals(-1, $this->de->getValue($loader));
        self::assertEquals('sort', $this->sn->getValue($loader));
    }
    /**
     * test loadConfiguration method
     *
     * @covers \DgfipSI1\Application\Config\ConfigLoader::loadConfiguration
     */
    public function testLoadConfiguration(): void
    {
        $testConfig = [
            CONF::CONFIG_DIRECTORY         => 'tests',
            CONF::CONFIG_NAME_PATTERNS     => ['config.yml'],
            CONF::CONFIG_PATH_PATTERNS     => ['data'],
            CONF::CONFIG_SEARCH_RECURSIVE  => true,
            CONF::CONFIG_SORT_BY_NAME      => true,
        ];
        $loader = $this->createConfigLoader(config: $testConfig, mock: true);
        $argv = [ './test'];
        $cmd = new Command('hello');
        $event = new ConsoleCommandEvent($cmd, new ArgvInput($argv), new NullOutput());

        /** @var \Mockery\MockInterface $loader */
        $loader->shouldReceive('configureSchemas')->once();
        $loader->shouldReceive('loadConfigFromOptions')->once()->with($event)->andReturn(false);
        $loader->shouldReceive('loadConfigFiles')->once();
        $loader->shouldReceive('addConfigFromOptions')->once()->with($event);

        /** @var ConfigLoader $loader */
        $loader->loadConfiguration($event);
        /** @var ConfigHelper $config */
        $config = $loader->getConfig();
        $config->set('foo', 'bar');
        self::assertEquals('bar', $config->getContext(ConfigOverlay::PROCESS_CONTEXT)->get('foo'));


        $method = (new ReflectionClass(ConfigLoader::class))->getMethod('loadConfiguration');
        self::assertTrue($method->isPublic());
    }
    /**
     * configureSchemas
     * @covers \DgfipSI1\Application\Config\ConfigLoader::configureSchemas
     */
    public function testConfigureSchemas(): void
    {
        $loader = $this->createConfigLoader();
        $method = $this->class->getMethod('configureSchemas');
        $method->setAccessible(true);
        /** @var ConfigHelper $config */
        $config = $loader->getConfig();

        // test1 : with no schema
        $method->invoke($loader);
        $dumpedSchema = "schema:               []\n";
        self::assertEquals($dumpedSchema, $config->dumpSchema());

        // test2 : only global schema
        $loader->getContainer()->addShared(HelloWorldSchema::class)->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $method->invoke($loader);
        $dumpedSchema = "schema:\n".HelloWorldSchema::DUMPED_SHEMA;
        self::assertEquals($dumpedSchema, $config->dumpSchema());

        // test3 : only command schema
        $loader->setContainer(new Container());
        $loader->getContainer()->addShared(HelloWorldCommand::class)->addTag(AbstractApplication::COMMAND_CONFIG_TAG);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $method->invoke($loader);
        $dumpedSchema = "schema:\n".HelloWorldCommand::DUMPED_SHEMA;
        self::assertEquals($dumpedSchema, $config->dumpSchema());

        // test4 : two schemas
        $loader->getContainer()->addShared(HelloWorldSchema::class)->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $method->invoke($loader);
        $dumpedSchema = "schema:\n".HelloWorldSchema::DUMPED_SHEMA.HelloWorldCommand::DUMPED_SHEMA;
        self::assertEquals($dumpedSchema, $config->dumpSchema());
    }
    /**
     * loadConfigFromOptions
     * @covers \DgfipSI1\Application\Config\ConfigLoader::loadConfigFromOptions
     */
    public function testLoadConfigFromOptions(): void
    {
        $loader = $this->createConfigLoader();
        $method = $this->class->getMethod('loadConfigFromOptions');
        $method->setAccessible(true);
        /** @var ConfigHelper $config */
        $config = $loader->getConfig();

        // test1 - no config, only returns false
        $argv = ['./tests'];
        $ret = $method->invokeArgs($loader, [$this->createEvent($argv)]);
        self::assertFalse($ret);
        $this->assertLogEmpty();

        // test2 - config file not found
        $argv = ['./tests', '--config', 'foo'];
        $event = $this->createEvent($argv);
        $msg = '';
        try {
            $ret = $method->invokeArgs($loader, [$event]);
            /** @phpstan-ignore-next-line dead catch false positive */
        } catch (ConfigFileNotFoundException $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals("Configuration file 'foo' not found", $msg);
        $this->assertLogEmpty();

        // test3 - config file exists
        $cfgDir = $this->appRoot.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'config';
        $cfgFile = realpath($cfgDir.DIRECTORY_SEPARATOR.'config.yml');
        $argv = ['./tests', '--config', "$cfgFile"];
        $event = $this->createEvent($argv);
        $ret = $method->invokeArgs($loader, [$event]);
        self::assertEquals(file_get_contents((string) $cfgFile), $config->dumpConfig());
        self::assertTrue($ret);
        $this->assertDebugInContextLog('Loading configfile', [ 'name' => 'loadConfigFromOptions', 'file' => $cfgFile]);
    }
    /**
     * addConfigFromOptions
     * @covers \DgfipSI1\Application\Config\ConfigLoader::addConfigFromOptions
     */
    public function testAddConfigFromOptions(): void
    {
        $loader = $this->createConfigLoader();
        $method = $this->class->getMethod('addConfigFromOptions');
        $method->setAccessible(true);
        /** @var ConfigHelper $config */
        $config = $loader->getConfig();

        // test1 - no config, no options
        $argv = ['./tests'];
        $method->invokeArgs($loader, [$this->createEvent($argv, false)]);
        self::assertEquals('[]', $config->dumpConfig());

        // test2 - no config, only returns false
        $argv = ['./tests'];
        $method->invokeArgs($loader, [$this->createEvent($argv)]);
        self::assertEquals('[]', $config->dumpConfig());

        // test3 - config file not found
        $argv = ['./tests', '--add-config', 'foo'];
        $event = $this->createEvent($argv);
        $msg = '';
        try {
            $method->invokeArgs($loader, [$event]);
            /** @phpstan-ignore-next-line dead catch false positive */
        } catch (ConfigFileNotFoundException $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals("Configuration file 'foo' not found", $msg);

        // test4 - config file exists
        $cfgDir = $this->appRoot.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'config';
        $cfgFile = realpath($cfgDir.DIRECTORY_SEPARATOR.'config.yml');
        $argv = ['./tests', '--add-config', "$cfgFile"];
        $event = $this->createEvent($argv);
        $method->invokeArgs($loader, [$event]);
        self::assertEquals(file_get_contents((string) $cfgFile), $config->dumpConfig());
        $this->assertDebugInContextLog('Adding configfile', [ 'name' => 'addConfigFromOptions', 'file' => $cfgFile]);
    }
    /**
     * Data Provider for testLoadConfigFiles
     *
     * @return array<string,array<mixed>>
     */
    public function loadConfigFilesData()
    {
        $abs = realpath(__DIR__);
        $phar = 'phar://'.getcwd().'/tests/data/config/testcfg.phar';
        $conf = "test: 'test value'\n";

        return [              // cd    expect  paths    names    sort     depth    pharRoot home     cur      err
            'notConfigured' => [ null  , "[]"  , null   , null   , null   , null   , null   , null   , null   , null ],
            'noDirs__error' => [ '/foo', null  , null   , null   , null   , null   , null   , null   , null   , 'NF' ],
            'noDirs__noErr' => [ null  , "[]"  , null   , null   , null   , null   , '/foo' , '/foo' , '/foo' , 'NC' ],
            'absolute__dir' => [ $abs  , "[]"  , null   , null   , 'path' , null   , null   , null   , null   , null ],
            'in_._phar_set' => [ '.'   , $conf , null   , '*.yml', null   , 0      , $phar  , null   , null   , null ],
            'phar__as_root' => [ $phar , $conf , null   , '*.yml', null   , -1     , null   , null   , null   , null ],
            'test_data_dir' => [ 'tests', $conf, 'data' , '*.yml', 'path' , -1     , null   , null   , null   , null ],
        ];
    }
    /**
     * loadConfigFiles
     * @dataProvider loadConfigFilesData
     *
     * @covers \DgfipSI1\Application\Config\ConfigLoader::loadConfigFiles
     * @covers \DgfipSI1\Application\Config\ConfigLoader::getDirectories
     *
     * @param string|null               $confDir
     * @param string|null               $expect
     * @param array<string>|string|null $paths
     * @param array<string>|string|null $names
     * @param string|null               $sort
     * @param int|null                  $depth
     * @param string|null               $pharRoot
     * @param string|null               $home
     * @param string|null               $cur
     * @param string|null               $err
     *
     * @return void
     */
    public function testLoadConfigFiles($confDir, $expect, $paths, $names, $sort, $depth, $pharRoot, $home, $cur, $err)
    {
        $method = $this->class->getMethod('loadConfigFiles');
        $method->setAccessible(true);
        $loader = $this->createConfigLoader();
        $app = $loader->getConfiguredApplication();
        $appcl = new ReflectionClass($app::class);

        $exceptions = [
            'NF' => "/Config directory .* not found/",
        ];
        $errs = [
            'NC' => "No configuration files found",
        ];

        /** @var ConfigHelper $config */
        $config = $loader->getConfig();
        $dirs = [];
        if (null !== $confDir) {
            $this->cd->setValue($loader, $confDir);
        } else {
            $confDir = '.';
        }
        if (null !== $names) {
            if (is_string($names)) {
                $names = [$names];
            }
            $this->np->setValue($loader, $names);
        } else {
            $names = ['config.yml'];
        }
        if (null !== $paths) {
            if (is_string($paths)) {
                $paths = [$paths];
            }
            $this->pp->setValue($loader, $paths);
        } else {
            $paths = [];
        }
        if (null !== $sort) {
            $this->sn->setValue($loader, ('name' === $sort ? true : false));
        } else {
            $sort = 'name';
        }
        if (null !== $depth) {
            $this->de->setValue($loader, $depth);
        } else {
            $depth = 0;
        }
        if (null !== $pharRoot) {
            $phar = $appcl->getProperty('pharRoot');
            $phar->setAccessible(true);
            $phar->setValue($app, $pharRoot);
            $dirs[] = "$pharRoot/$confDir";
        }
        if (null !== $home) {
            $homep = $appcl->getProperty('homeDir');
            $homep->setAccessible(true);
            $homep->setValue($app, $home);
            $dirs[] = "$home/$confDir";
        }
        if (null !== $cur) {
            $curp = $appcl->getProperty('currentDir');
            $curp->setAccessible(true);
            $curp->setValue($app, $cur);
            $dirs[] = "$cur/$confDir";
        }
        $dirs[] = getcwd()."/$confDir";
        if (Path::isAbsolute($confDir)) {
            $dirs = [$confDir];
        }
        $msg = '';
        try {
            $method->invoke($loader);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if (null !== $err) {
            if (array_key_exists($err, $exceptions)) {
                self::assertMatchesRegularExpression($exceptions[$err], $msg);
            } else {
                $this->assertDebugInLog($errs[$err]);
            }

            return;
        }
        // print "\n======================================================\n";
        // print $msg;
        // print "\n======================================================\n";
        // print $config->dumpConfig();
        // print "\n======================================================\n";
        // $this->showLogs();

        self::assertEquals('', $msg);
        self::assertEquals($expect, $config->dumpConfig());
        $ctx = [
            'name'   => 'loadConfigFiles',
            'paths'  => '['.implode(' ', $paths).']',
            'names'  => '['.implode(' ', $names).']',
            'sort'   => $sort,
            'depth'  => $depth,
            'dirs'   => '['.implode(", ", $dirs).']',
        ];

        $this->assertDebugInContextLog('Loading config', $ctx);
        $this->assertLogEmpty();
    }
    /**
     * loadConfigFiles
     * @covers DgfipSI1\Application\Config\InputOptionsSetter::safeBind
     */
    public function testSafeBind(): void
    {
        $definition = new InputDefinition([
            new InputOption('good', mode: InputOption::VALUE_NONE),
            new InputOption('required', mode: InputOption::VALUE_REQUIRED),
        ]);
        $input = new ArgvInput([ './test', '--required', '--good' ]);
        InputOptionsSetter::safeBind($input, $definition);
        self::assertTrue($input->hasOption('good'));
        self::assertTrue($input->hasOption('required'));
        self::assertFalse($input->getOption('good'));

        $definition = new InputDefinition([
            new InputOption('good', mode: InputOption::VALUE_NONE),
            new InputOption('required', mode: InputOption::VALUE_REQUIRED),
        ]);
        $input = new ArgvInput([ './test', '--required', 'foo', '--good' ]);
        InputOptionsSetter::safeBind($input, $definition);
        self::assertTrue($input->hasOption('good'));     /** @phpstan-ignore-line */
        self::assertTrue($input->hasOption('required')); /** @phpstan-ignore-line */
        self::assertTrue($input->getOption('good'));
        self::assertEquals('foo', $input->getOption('required'));
    }
    /**
     * creates a fully equiped loader
     *
     * @param array<string,string>     $argv
     * @param array<string,mixed>|null $config
     * @param bool                     $mock
     *
     * @return ConfigLoader;
     */
    protected function createConfigLoader($argv = [], $config = null, $mock = false)
    {
        $defaultConfig = [
            CONF::CONFIG_DIRECTORY         => null,
            CONF::CONFIG_NAME_PATTERNS     => [ 'config.yml' ],
            CONF::CONFIG_PATH_PATTERNS     => [ ],
            CONF::CONFIG_SEARCH_RECURSIVE  => false,
            CONF::CONFIG_SORT_BY_NAME      => true,
        ];
        $argv = [ './testapp' ] + $argv;
        $confArray = $config ?? $defaultConfig;
        if ($mock) {
            /** @var Mock $mock */
            $mock = Mockery::mock(ConfigLoader::class);
            $mock->shouldAllowMockingProtectedMethods();
            $mock->makePartial();
            $loader = $mock;
        } else {
            $loader = new ConfigLoader();
        }
        /** @var ConfigLoader $loader */
        $loader->setContainer(new Container());
        $this->logger = new TestLogger();
        $loader->setLogger($this->logger);
        $intConfig = new ConfigHelper();
        $intConfig->addArray(ConfigHelper::DEFAULT_CONTEXT, $confArray);
        $loader->configure($intConfig);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $app = new SymfonyApplication($this->classLoader, $argv);
        $loader->getContainer()->addShared('application', $app);

        return $loader;
    }
    /**
     * creates an Event
     *
     * @param array<string> $argv
     * @param bool          $createOptions
     *
     * @return ConsoleCommandEvent;
     */
    protected function createEvent($argv, $createOptions = true)
    {
        $input = new ArgvInput($argv);

        $definition = new InputDefinition();
        if ($createOptions) {
            $definition->addOption(new InputOption(
                '--config',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify configuration file (replace default configuration file).'
            ));
            $definition->addOption(new InputOption(
                '--add-config',
                null,
                InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED,
                'Specify additional configuration files (in increasing priority order).'
            ));
            InputOptionsSetter::safeBind($input, $definition);
        }
        $cmd = new Command('hello');
        $event = new ConsoleCommandEvent($cmd, $input, new NullOutput());

        return $event;
    }
}
