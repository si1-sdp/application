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
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use DgfipSI1\Application\RoboApplication;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldCommand;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldSchema;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use League\Container\Definition\Definition;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\SymfonyApplication
 * @uses DgfipSI1\Application\ApplicationLogger
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfigLoader
 * @uses DgfipSI1\Application\Config\InputOptionsSetter
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses DgfipSI1\Application\Contracts\AppAwareTrait
 *
 *
 */
class InputOptionsSetterTest extends LogTestCase
{
    /** @var ReflectionClass $class */
    protected $class;
    /** @var array<mixed> $commandOptions */
    protected $commandOptions;
    /** @var array<mixed> $globalOptions */
    protected $globalOptions;

    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
        $this->class = new ReflectionClass(InputOptionsSetter::class);
        $this->commandOptions['hello'] = ['options' => [
            'test-b' => [ 'type' => 'boolean' ],
            'test-s' => [ 'type' => 'scalar' ],
        ], ];
        $this->globalOptions = [
            'test-b' => [ 'type' => 'boolean' ],
            'test-s' => [ 'type' => 'scalar'  ],
        ];
    }
    /**
     * test setInputOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::setInputOptions
     *
     * @return void
     */
    public function testSetInputOptions()
    {
        $setter = $this->createSetter();
        /** @var SymfonyApplication $app */
        $app = $setter->getApplication();
        $appDef = $app->getDefinition();
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $cmdDef = $command->getDefinition();

        $input = new ArgvInput([]);
        $setter->getContainer()->addShared('input', $input);

        $this->assertFalse($appDef->hasOption('config'));      // tech opt
        $this->assertFalse($appDef->hasOption('configAware')); // glob opt from getConfigOptions
        $this->assertFalse($appDef->hasOption('test-b'));      // glob opt from config
        $this->assertFalse($cmdDef->hasOption('test-a'));      // cmd opt from getConfigOptions
        $this->assertFalse($cmdDef->hasOption('test-b'));      // cmd opt from config

        $config = new ConfigHelper();
        $config->addArray('global', [ 'dgfip_si1' => [ 'global_options'  => $this->globalOptions  ]]);
        $config->addArray('command', [ 'dgfip_si1' => [ 'command_options' => $this->commandOptions ]]);

        $setter->setInputOptions($config, 'hello');
        $this->assertTrue($appDef->hasOption('config'));      // tech opt
        $this->assertTrue($appDef->hasOption('configAware')); // glob opt from getConfigOptions
        $this->assertTrue($appDef->hasOption('test-b'));      // glob opt from config
        $this->assertTrue($cmdDef->hasOption('test-a'));      // cmd opt from getConfigOptions
        $this->assertTrue($cmdDef->hasOption('test-b'));      // cmd opt from config
    }
    /**
     * test setupTechnicalOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::setupTechnicalOptions
     *
     * @return void
     */
    public function testSetupTechnicalOptions()
    {
        $setter = $this->createSetter();
        $method = $this->class->getMethod('setupTechnicalOptions');
        $method->setAccessible(true);
        /** @var SymfonyApplication $app */
        $app = $setter->getApplication();
        $def = $app->getDefinition();

        $input = new ArgvInput([ './test']);
        $this->assertFalse($def->hasOption('config'));
        $this->assertFalse($def->hasOption('add-config'));
        $this->assertFalse($def->hasOption('define'));
        $method->invokeArgs($setter, [ $input]);
        $this->assertTrue($def->hasOption('config'));
        $this->assertTrue($def->hasOption('add-config'));
        $this->assertTrue($def->hasOption('define'));
    }
    /**
     * test setupGlobalOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::setupGlobalOptions
     *
     * @return void
     */
    public function testSetupGlobalOptions()
    {
        $setter = $this->createSetter();
        $method = $this->class->getMethod('setupGlobalOptions');
        $method->setAccessible(true);

        // nominal test - 2 options from config, 1 option from Command::getConfigOptions
        $globalOptions = [
            'test-b' => [ 'type' => 'boolean' ],
            'test-s' => [ 'type' => 'scalar'  ],
        ];
        $method->invokeArgs($setter, [ $globalOptions]);
        /** @var SymfonyApplication $app */
        $app = $setter->getApplication();
        $def = $app->getDefinition();

        $this->assertTrue($def->hasOption('test-b'));
        $this->assertTrue($def->hasOption('test-s'));
        $this->assertTrue($def->hasOption('configAware'));  // from ApplicationAware
    }

    /**
     * test setupCommandOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::setupCommandOptions
     *
     * @return void
     */
    public function testSetupCommandOptions()
    {
        $setter = $this->createSetter();
        $method = $this->class->getMethod('setupCommandOptions');
        $method->setAccessible(true);

        // nominal test - 2 options from config, 1 option from Command::getConfigOptions
        $input = new ArgvInput([ './test']);
        $commandOptions['hello'] = ['options' => [
            'test-b' => [ 'type' => 'boolean' ],
            'test-s' => [ 'type' => 'scalar' ],
        ], ];
        $method->invokeArgs($setter, [ $input, 'hello', $commandOptions]);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        $this->assertEquals(3, count($def->getOptions()));
        $this->assertTrue($def->hasOption('test-b'));
        $this->assertTrue($def->hasOption('test-s'));
        $this->assertTrue($def->hasOption('test-a'));  // from ApplicationAware

        // test with help command
        $setter = $this->createSetter();
        $input = new ArgvInput([ './test', 'help', 'hello' ]);
        $commandOptions['hello'] = ['options' => [
            'test-b' => [ 'type' => 'boolean' ],
            'test-s' => [ 'type' => 'scalar' ],
        ], ];
        $method->invokeArgs($setter, [ $input, 'help', $commandOptions]);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        $this->assertEquals(3, count($def->getOptions()));
        $this->assertTrue($def->hasOption('test-b'));
        $this->assertTrue($def->hasOption('test-s'));
        $this->assertTrue($def->hasOption('test-a'));  // from ApplicationAware

        // test with unknown command
        $setter = $this->createSetter();
        $input = new ArgvInput([ './test', 'hello' ]);
        $method->invokeArgs($setter, [ $input, 'foo', $this->commandOptions]);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        $this->assertEquals(0, count($def->getOptions()));

        // test with bind exception
        $setter = $this->createSetter();
        $input = new ArgvInput([ ]);
        $commandOptions['hello'] = ['options' => [
            'test-b' => [ 'type' => 'boolean' ],
        ], ];
        $method->invokeArgs($setter, [ $input, 'hello', $commandOptions]);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        $this->assertEquals(2, count($def->getOptions()));

        // see that we skip completely if not symfonyCommand
        $setter->setApplication(new RoboApplication(new ClassLoader()));
        $method->invokeArgs($setter, [ $input, 'hello', $commandOptions]);
        $this->assertInfoInLog('Only symfony command supported');
    }
    /**
     * test optionFromConfig
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::optionFromConfig
     *
     * @return void
     */
    public function testOptionFromConfig()
    {
        $setter = $this->createSetter();
        $method = $this->class->getMethod('optionFromConfig');
        $method->setAccessible(true);

        // test 1 : scalar option
        $scalarOpt = [
            CONF::OPT_SHORT         => 'T',
            CONF::OPT_DESCRIPTION   => 'this is a testing option',
            CONF::OPT_TYPE          => 'scalar',
        ];
        /** @var InputOption $option */
        $option = $method->invokeArgs($setter, ['test-scalar-opt', $scalarOpt]);
        $this->assertEquals('test-scalar-opt', $option->getName());
        $this->assertEquals('T', $option->getShortcut());
        $this->assertEquals('this is a testing option', $option->getDescription());
        $this->assertEquals(null, $option->getDefault());
        $this->assertTrue($option->isValueRequired());
        $this->assertFalse($option->isArray());

        // test 2 : array option
        $arrayOpt = [
            CONF::OPT_TYPE          => 'array',
            CONF::OPT_DEFAULT_VALUE => [ 'foo' ],
        ];
        /** @var InputOption $option */
        $option = $method->invokeArgs($setter, ['test-array-opt', $arrayOpt]);
        $this->assertEquals('test-array-opt', $option->getName());
        $this->assertEquals(null, $option->getShortcut());
        $this->assertEquals(null, $option->getDescription());
        $this->assertEquals(['foo'], $option->getDefault());
        $this->assertTrue($option->isValueRequired());
        $this->assertTrue($option->isArray());

        // test 3 : boolean option
        $arrayOpt = [
            CONF::OPT_TYPE          => 'boolean',
        ];
        /** @var InputOption $option */
        $option = $method->invokeArgs($setter, ['test-bool-opt', $arrayOpt]);
        $this->assertEquals('test-bool-opt', $option->getName());
        $this->assertEquals(null, $option->getShortcut());
        $this->assertEquals(null, $option->getDescription());
        $this->assertEquals(null, $option->getDefault());
        $this->assertFalse($option->isValueRequired());
        $this->assertFalse($option->isArray());

        // test 4 : unknown type
        $badOpt = [
            CONF::OPT_TYPE          => 'foo',
        ];
        $msg = '';
        try {
            $option = $method->invokeArgs($setter, ['test-bad-opt', $badOpt]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Unknown option type 'foo' for option 'test-bad-opt'", $msg);

        // test 5 : mismatch
        $badOpt = [
            CONF::OPT_TYPE          => 'array',
            CONF::OPT_DEFAULT_VALUE => true,
        ];
        $msg = '';
        try {
            $option = $method->invokeArgs($setter, ['bad-opt', $badOpt]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $error = "Error creating option bad-opt : A default value for an array option must be an array.";
        $this->assertEquals($error, $msg);

        // test 6 : no type at all
        $badOpt = [];
        $msg = '';
        try {
            $option = $method->invokeArgs($setter, ['test-bad-opt', $badOpt]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Missing option type for test-bad-opt", $msg);
    }

    /**
     * test addOption
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::addOption
     *
     * @return void
     */
    public function testAddOption()
    {
        $setter = $this->createSetter();
        $method = $this->class->getMethod('addOption');
        $method->setAccessible(true);
        $def = new InputDefinition();
        $option = new InputOption('test-opt');
        $ctx = ['context' => 'testing'];
        $method->invokeArgs($setter, [$def, $option, $ctx]);
        $this->assertDebugInLog('testing option test-opt added', interpolate:true);
        $this->assertLogEmpty();
        $method->invokeArgs($setter, [$def, $option, $ctx]);
        $this->assertWarningInLog('testing option test-opt already exists', interpolate:true);
        $this->assertLogEmpty();
    }
    /**
     * creates a fully equiped inputSetter
     *
     * @return InputOptionsSetter;
     */
    protected function createSetter()
    {
        $setter = new InputOptionsSetter();
        $this->logger = new TestLogger();
        $setter->setLogger($this->logger);
        $config = new ConfigHelper();
        $setter->setConfig($config);
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);

        $setter->setApplication($app);
        $setter->setContainer($app->getContainer());

        $def = $setter->getContainer()->addShared(HelloWorldCommand::class);
        $def->addTag('hello')->addTag(AbstractApplication::COMMAND_TAG);

        $def = $setter->getContainer()->addShared(HelloWorldSchema::class);
        $def->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);


        return $setter;
    }
}
