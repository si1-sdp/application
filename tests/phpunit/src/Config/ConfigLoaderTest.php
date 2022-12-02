<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Application;
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
    public function setup(): void
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
        $this->root = $this->class->getProperty('appRoot');
        $this->root->setAccessible(true);
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
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
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
            CONF::RUNTIME_ROOT_DIRECTORY   => 'root_dir',
        ];
        $loader = $this->createConfigLoader(config: $testConfig);
        $this->assertEquals('conf_dir', $this->cd->getValue($loader));
        $this->assertEquals(['name1', 'name2'], $this->np->getValue($loader));
        $this->assertEquals(['path1', 'path2'], $this->pp->getValue($loader));
        $this->assertEquals(-1, $this->de->getValue($loader));
        $this->assertEquals('sort', $this->sn->getValue($loader));
        $this->assertEquals('root_dir', $this->root->getValue($loader));
    }
    /**
     * test loadConfiguration method
     *
     * @covers \DgfipSI1\Application\Config\ConfigLoader::loadConfiguration
     */
    public function testLoadConfiguration(): void
    {
        $testConfig = [
            CONF::CONFIG_DIRECTORY         => null,
            CONF::CONFIG_NAME_PATTERNS     => ['config.yml'],
            CONF::CONFIG_PATH_PATTERNS     => ['test'],
            CONF::CONFIG_SEARCH_RECURSIVE  => true,
            CONF::CONFIG_SORT_BY_NAME      => true,
            CONF::RUNTIME_ROOT_DIRECTORY   => __DIR__,
        ];
        $loader = $this->createConfigLoader(config: $testConfig);
        $argv = [ './test'];
        $cmd = new Command('hello');
        $event = new ConsoleCommandEvent($cmd, new ArgvInput($argv), new NullOutput());
        $loader->loadConfiguration($event);
        $msg = 'Loading config: paths=[test] - names=[config.yml] - sort by name - depth = -1';
        $this->assertDebugInLog($msg, true);
        $this->assertLogEmpty();

        $loader->getContainer()->addShared(HelloWorldSchema::class)->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);
        $loader->getContainer()->addShared(HelloWorldCommand::class)->addTag(SymfonyApplication::COMMAND_CONFIG_TAG);
        $loader->loadConfiguration($event);

        /** @var ConfigHelper $config */
        $config = $loader->getConfig();
        $dump = 'commands:
  hello:
    options:
      yell: false
      formal: true
';
        $this->assertEquals($dump, $config->dumpConfig());
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
        $this->assertEquals($dumpedSchema, $config->dumpSchema());

        // test2 : only global schema
        $loader->getContainer()->addShared(HelloWorldSchema::class)->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $method->invoke($loader);
        $dumpedSchema = "schema:\n".HelloWorldSchema::DUMPED_SHEMA;
        $this->assertEquals($dumpedSchema, $config->dumpSchema());

        // test3 : only command schema
        $loader->setContainer(new Container());
        $loader->getContainer()->addShared(HelloWorldCommand::class)->addTag(AbstractApplication::COMMAND_CONFIG_TAG);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $method->invoke($loader);
        $dumpedSchema = "schema:\n".HelloWorldCommand::DUMPED_SHEMA;
        $this->assertEquals($dumpedSchema, $config->dumpSchema());

        // test4 : two schemas
        $loader->getContainer()->addShared(HelloWorldSchema::class)->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);
        $config = new ConfigHelper();
        $loader->setConfig($config);
        $method->invoke($loader);
        $dumpedSchema = "schema:\n".HelloWorldSchema::DUMPED_SHEMA.HelloWorldCommand::DUMPED_SHEMA;
        $this->assertEquals($dumpedSchema, $config->dumpSchema());
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
        $this->assertFalse($ret);

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
        $this->assertEquals("Configuration file 'foo' not found", $msg);

        // test3 - config file exists
        $cfgDir = $this->appRoot.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'config';
        $cfgFile = realpath($cfgDir.DIRECTORY_SEPARATOR.'config.yml');
        $argv = ['./tests', '--config', "$cfgFile"];
        $event = $this->createEvent($argv);
        $ret = $method->invokeArgs($loader, [$event]);
        $this->assertEquals(file_get_contents((string) $cfgFile), $config->dumpConfig());
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

        // test1 - no config, only returns false
        $argv = ['./tests'];
        $method->invokeArgs($loader, [$this->createEvent($argv)]);
        $this->assertEquals('[]', $config->dumpConfig());

        // test2 - config file not found
        $argv = ['./tests', '--add-config', 'foo'];
        $event = $this->createEvent($argv);
        $msg = '';
        try {
            $method->invokeArgs($loader, [$event]);
            /** @phpstan-ignore-next-line dead catch false positive */
        } catch (ConfigFileNotFoundException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Configuration file 'foo' not found", $msg);

        // test3 - config file exists
        $cfgDir = $this->appRoot.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'config';
        $cfgFile = realpath($cfgDir.DIRECTORY_SEPARATOR.'config.yml');
        $argv = ['./tests', '--add-config', "$cfgFile"];
        $event = $this->createEvent($argv);
        $method->invokeArgs($loader, [$event]);
        $this->assertEquals(file_get_contents((string) $cfgFile), $config->dumpConfig());
    }
    /**
     * loadConfigFiles
     * @covers \DgfipSI1\Application\Config\ConfigLoader::loadConfigFiles
     */
    public function testLoadConfigFiles(): void
    {
        $method = $this->class->getMethod('loadConfigFiles');
        $method->setAccessible(true);

        $logMsg = 'Loading config: paths=[%s] - names=[%s] - sort by %s - depth = %d';

        $loader = $this->createConfigLoader();
        /** @var ConfigHelper $config */
        $config = $loader->getConfig();

        // test1 - no config
        $method->invoke($loader);
        $this->assertEquals('[]', $config->dumpConfig());
        $this->assertDebugInLog(sprintf($logMsg, '', 'config.yml', 'name', 0), true);
        $this->assertLogEmpty();

        // test2 - non existent configured config directory
        $this->cd->setValue($loader, 'foo');
        $msg = '';
        try {
            $method->invoke($loader);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Configuration directory 'foo' not found", $msg);
        $this->assertLogEmpty();

        // test3 - non existent root directory
        $this->cd->setValue($loader, null);
        $this->root->setValue($loader, 'foo');
        $method->invoke($loader);
        $this->assertEquals('[]', $config->dumpConfig());
        $this->assertLogEmpty();

        // test4 - search in data dir
        $this->root->setValue($loader, $this->appRoot);
        $this->pp->setValue($loader, ['data']);
        $this->np->setValue($loader, ['*.yml']);
        $this->de->setValue($loader, -1);
        $this->sn->setValue($loader, false);
        $method->invoke($loader);
        $this->assertDebugInLog(sprintf($logMsg, 'data', '*.yml', 'path', -1), true);
        $this->assertLogEmpty();
        $cfgDir = $this->appRoot.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'config';
        $cfgFile = realpath($cfgDir.DIRECTORY_SEPARATOR.'config.yml');
        $this->assertEquals(file_get_contents((string) $cfgFile), $config->dumpConfig());
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
        $this->assertTrue($input->hasOption('good'));
        $this->assertTrue($input->hasOption('required'));
        $this->assertFalse($input->getOption('good'));

        $definition = new InputDefinition([
            new InputOption('good', mode: InputOption::VALUE_NONE),
            new InputOption('required', mode: InputOption::VALUE_REQUIRED),
        ]);
        $input = new ArgvInput([ './test', '--required', 'foo', '--good' ]);
        InputOptionsSetter::safeBind($input, $definition);
        $this->assertTrue($input->hasOption('good'));
        $this->assertTrue($input->hasOption('required'));
        $this->assertTrue($input->getOption('good'));
        $this->assertEquals('foo', $input->getOption('required'));
    }
    /**
     * creates a fully equiped loader
     *
     * @param array<string,string>     $argv
     * @param array<string,mixed>|null $config
     *
     * @return ConfigLoader;
     */
    protected function createConfigLoader($argv = [], $config = null)
    {
        $defaultConfig = [
            CONF::CONFIG_DIRECTORY         => null,
            CONF::CONFIG_NAME_PATTERNS     => [ 'config.yml' ],
            CONF::CONFIG_PATH_PATTERNS     => [ ],
            CONF::CONFIG_SEARCH_RECURSIVE  => false,
            CONF::CONFIG_SORT_BY_NAME      => true,
            CONF::RUNTIME_ROOT_DIRECTORY   => $this->appRoot,
        ];
        $argv = [ './testapp' ] + $argv;
        $confArray = $config ?? $defaultConfig;
        $loader = new ConfigLoader();
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
     *
     * @return ConsoleCommandEvent;
     */
    protected function createEvent($argv)
    {
        $input = new ArgvInput($argv);

        $definition = new InputDefinition();
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
        try {
            $input->bind($definition);
        } catch (\Exception $e) {
            // Errors must be ignored, full binding/validation happens later
        }
        $cmd = new Command('hello');
        $event = new ConsoleCommandEvent($cmd, $input, new NullOutput());

        return $event;
    }
}
