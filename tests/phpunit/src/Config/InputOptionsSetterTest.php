<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\Application\RoboApplication;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldCommand;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldSchema;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\SymfonyApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfigLoader
 * @uses DgfipSI1\Application\Config\InputOptionsSetter
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses DgfipSI1\Application\Utils\MakePharCommand
 * @uses DgfipSI1\Application\Command
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
    public function setUp(): void
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
        $app = $setter->getConfiguredApplication();
        $appDef = $app->getDefinition();
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $cmdDef = $command->getDefinition();

        $input = new ArgvInput([]);
        $setter->getContainer()->addShared('input', $input);

        self::assertFalse($appDef->hasOption('config'));      // tech opt
        self::assertFalse($appDef->hasOption('configAware')); // glob opt from getConfigOptions
        self::assertFalse($appDef->hasOption('test-b'));      // glob opt from config
        self::assertFalse($cmdDef->hasOption('test-a'));      // cmd opt from getConfigOptions
        self::assertFalse($cmdDef->hasOption('test-b'));      // cmd opt from config

        $config = new ConfigHelper();
        $config->addArray('global', [ 'dgfip_si1' => [ 'global_options'  => $this->globalOptions  ]]);
        $config->addArray('command', [ 'dgfip_si1' => [ 'command_options' => $this->commandOptions ]]);

        $setter->setInputOptions($config);
        self::assertTrue($appDef->hasOption('config'));      // tech opt
        self::assertTrue($appDef->hasOption('configAware')); // glob opt from getConfigOptions
        self::assertTrue($appDef->hasOption('test-b'));      // glob opt from config
        self::assertTrue($cmdDef->hasOption('test-a'));      // cmd opt from getConfigOptions
        self::assertTrue($cmdDef->hasOption('test-b'));      // cmd opt from config
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
        $app = $setter->getConfiguredApplication();
        $def = $app->getDefinition();

        $input = new ArgvInput([ './test']);
        self::assertFalse($def->hasOption('config'));
        self::assertFalse($def->hasOption('add-config'));
        self::assertFalse($def->hasOption('define'));
        $method->invokeArgs($setter, [ $input]);
        self::assertTrue($def->hasOption('config'));
        self::assertTrue($def->hasOption('add-config'));
        self::assertTrue($def->hasOption('define'));

        self::assertEquals([], $input->getOption('define'));
    }
    /**
     * test setupGlobalOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::setupGlobalOptions
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::registerGlobalOptions
     *
     * @return void
     */
    public function testSetupGlobalOptions()
    {
        $setter = $this->createSetter();
        $regCmd = $this->class->getMethod('registerCommandOptions');
        $regCmd->setAccessible(true);
        $regGlob = $this->class->getMethod('registerGlobalOptions');
        $regGlob->setAccessible(true);

        $method = $this->class->getMethod('setupGlobalOptions');
        $method->setAccessible(true);

        // nominal test - 2 options from config, 1 option from Command::getConfigOptions
        $globalOptions = [
            'test-b' => [ 'type' => 'boolean' ],
            'test-s' => [ 'type' => 'scalar'  ],
        ];
        $regGlob->invokeArgs($setter, [$globalOptions]);
        $method->invokeArgs($setter, []);
        /** @var SymfonyApplication $app */
        $app = $setter->getConfiguredApplication();
        $def = $app->getDefinition();
        self::assertTrue($def->hasOption('test-b'));
        self::assertTrue($def->hasOption('test-s'));
        self::assertTrue($def->hasOption('configAware'));  // from ApplicationAware
        $this->assertDebugInContextLog('added', [ 'name' => 'setupGlobalOptions', 'context' => 'Global' ]);
    }

    /**
     * test setupCommandOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::setupCommandOptions
     * @covers \DgfipSI1\Application\Config\InputOptionsSetter::registerCommandOptions
     *
     * @return void
     */
    public function testSetupCommandOptions()
    {
        $setter = $this->createSetter();
        $regCmd = $this->class->getMethod('registerCommandOptions');
        $regCmd->setAccessible(true);
        $regGlob = $this->class->getMethod('registerGlobalOptions');
        $regGlob->setAccessible(true);
        $method = $this->class->getMethod('setupCommandOptions');
        $method->setAccessible(true);

        // nominal test - 2 options from config, 1 option from Command::getConfigOptions
        $input = new ArgvInput([ './test']);
        $commandOptions = new ConfigHelper();
        $commandOptions->set('dgfip_si1.command_options.hello.options.test_b.type', 'boolean');
        $commandOptions->set('dgfip_si1.command_options.hello.options.test_s.type', 'scalar');
        $regCmd->invokeArgs($setter, [$commandOptions]);
        $method->invokeArgs($setter, [ $input, 'hello']);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        self::assertEquals(3, count($def->getOptions()));
        self::assertTrue($def->hasOption('test-b'));
        self::assertTrue($def->hasOption('test-s'));
        self::assertTrue($def->hasOption('test-a'));  // from ApplicationAware
        $this->assertInfoInLog('Setting up command options for hello', true);
        $ctx =  [ 'name' => 'setupCommandOptions', 'context' => 'hello', 'command' => 'hello' ];
        $this->assertDebugInContextLog('added', $ctx);
        $this->assertLogEmpty();

        // test with help command
        $setter = $this->createSetter();
        $input = new ArgvInput([ './test', 'help', 'hello' ]);
        $regCmd->invokeArgs($setter, [$commandOptions]);
        $method->invokeArgs($setter, [ $input, 'help']);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        self::assertEquals(3, count($def->getOptions()));
        self::assertTrue($def->hasOption('test-b'));
        self::assertTrue($def->hasOption('test-s'));
        self::assertTrue($def->hasOption('test-a'));  // from ApplicationAware
        $this->assertInfoInLog('Setting up command options for help', true);
        $ctx = [ 'name' => 'setupCommandOptions', 'context' => 'help', 'command' => 'hello' ];
        $this->assertDebugInContextLog('added', $ctx);
        $this->assertLogEmpty();

        // test with unknown command
        $setter = $this->createSetter();
        $input = new ArgvInput([ './test', 'hello' ]);
        $regCmd->invokeArgs($setter, [$commandOptions]);
        $method->invokeArgs($setter, [ $input, 'foo']);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        self::assertEquals(0, count($def->getOptions()));

        // test with bind exception
        $setter = $this->createSetter();
        $input = new ArgvInput([ ]);
        $commandOptions = new ConfigHelper();
        $commandOptions->set('dgfip_si1.command_options.hello.options.test_b.type', 'boolean');
        $regCmd->invokeArgs($setter, [$commandOptions]);
        $method->invokeArgs($setter, [ $input, 'hello']);
        /** @var ApplicationCommand $command */
        $command = $setter->getContainer()->get(HelloWorldCommand::class);
        $def = $command->getDefinition();
        self::assertEquals(2, count($def->getOptions()));

        // see that we skip completely if not symfonyCommand
        $setter->getContainer()->extend('application')->setAlias('oldApp');
        $setter->getContainer()->addShared('application', new RoboApplication(new ClassLoader()));
        $method->invokeArgs($setter, [ $input, 'hello', $commandOptions]);
        $this->assertInfoInLog('Only symfony command supported');
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
        // $option = new InputOption('test-opt');
        $option = new MappedOption('test-opt', OptionType::Boolean);
        $ctx = ['context' => 'testing'];
        $method->invokeArgs($setter, [$def, $option, $ctx]);
        $this->assertDebugInLog('testing option test_opt added', interpolate:true);
        $this->assertLogEmpty();
        $method->invokeArgs($setter, [$def, $option, $ctx]);
        $this->assertWarningInLog('testing option test_opt already exists', interpolate:true);
        $this->assertLogEmpty();
        // $option = new InputArgument('test-arg');
        $option = new MappedOption('test-arg', OptionType::Argument);
        $ctx = ['context' => 'testing'];
        $method->invokeArgs($setter, [$def, $option, $ctx]);
        $this->assertDebugInLog('testing argument test_arg added', interpolate:true);
        $this->assertLogEmpty();
        $method->invokeArgs($setter, [$def, $option, $ctx]);
        $this->assertWarningInLog('testing argument test_arg already exists', interpolate:true);
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
        $app = new SymfonyApplication($loaders[0], ['./test', 'hello']);

        $setter->setContainer($app->getContainer());
        $setter->getContainer()->addShared('application', $app);
        $input = new ArgvInput(['./test', 'hello']);
        $setter->getContainer()->addShared('input', $input);

        $def = $setter->getContainer()->addShared(HelloWorldCommand::class);
        $def->addTag('hello')->addTag(SymfonyApplication::COMMAND_TAG);

        $def = $setter->getContainer()->addShared(HelloWorldSchema::class);
        $def->addTag(AbstractApplication::GLOBAL_CONFIG_TAG);


        return $setter;
    }
}
