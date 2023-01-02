<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\Config\ConfigLoader;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Utils\DumpconfigCommand;
use DgfipSI1\ApplicationTests\TestClasses\Commands\EventTestClass;
use DgfipSI1\ApplicationTests\TestClasses\Commands\HelloWorldCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use League\Container\Argument\Literal\StringArgument;
use Mockery;
use Mockery\Mock;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
class SymfonyApplicationTest extends LogTestCase
{
    protected const COMMAND_NAMESPACE = 'TestClasses\\Commands';

    /** @var ClassLoader $loader */
    protected $loader;

    /** @var \ReflectionClass<ApplicationInterface> */
    protected $class;

    /** @var \ReflectionMethod */
    protected $cc;
    /** @var \ReflectionMethod */
    protected $fin;
    /** @var \ReflectionProperty */
    protected $ic;
    /** @var \ReflectionProperty */
    protected $input;
    /** @var \ReflectionProperty */
    protected $phar;

    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
        $this->class = new \ReflectionClass(SymfonyApplication::class);
        $this->cc = $this->class->getMethod('configureContainer');
        $this->cc->setAccessible(true);
        $this->fin = $this->class->getMethod('finalize');
        $this->fin->setAccessible(true);
        $this->ic = $this->class->getProperty('intConfig');
        $this->ic->setAccessible(true);
        $this->input = $this->class->getProperty('input');
        $this->input->setAccessible(true);
        $this->phar = $this->class->getProperty('pharRoot');
        $this->phar->setAccessible(true);
        $this->logger = new TestLogger();
    }
    /**
     * Checks that log is empty at end of test
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->assertLogEmpty();
        \Mockery::close();
    }
    /**
     *  test getters
     *
     * @covers \DgfipSI1\Application\SymfonyApplication::getCommand
     * @covers \DgfipSI1\Application\SymfonyApplication::getCmdName
     *
     *
     */
    public function testGetters(): void
    {
        $app = new SymfonyApplication($this->loader, ['./test', 'hello']);

        $cmd = new DumpconfigCommand();
        $app->add($cmd);
        $def = $app->getContainer()->addShared(DumpconfigCommand::class);
        $def->addTag((string) $cmd->getName())->addTag(SymfonyApplication::COMMAND_TAG);

        $cmd = new HelloWorldCommand();
        $app->add($cmd);
        $def = $app->getContainer()->addShared(HelloWorldCommand::class);
        $def->addTag((string) $cmd->getName())->addTag(SymfonyApplication::COMMAND_TAG);


        self::assertEquals('hello', $app->getCmdName());
        $found = $app->getCommand((string) $cmd->getName());
        self::assertTrue($found::class === HelloWorldCommand::class);
        $msg = '';
        try {
            $found = $app->getCommand('foo');
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('Error looking for command foo in container', $msg);
        $msg = '';
        try {
            $found = $app->getCommand(SymfonyApplication::COMMAND_TAG);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('Error looking for command '.SymfonyApplication::COMMAND_TAG.' in container', $msg);
    }
    /**
     * data provider for symfony app
     *
     * @return array<string,array<mixed>>
     */
    public function symfonyRegisterCommandsData()
    {
        return [            // pharRoot
            'withPhar' => [ '',           ],
            'without ' => [ 'phar:///tmp' ],
        ];
    }
    /**
    * @covers \DgfipSI1\Application\SymfonyApplication::registerCommands
     *
     * @dataProvider symfonyRegisterCommandsData
       *
       * @param string $pharRoot
     *
     * @return void
    */
    public function testRegisterSymfonyCommands($pharRoot): void
    {
        $app = new SymfonyApplication($this->loader, [ './test', 'hello:test', '-q']);
        $app->setLogger($this->logger);
        $app->getContainer()->addShared(HelloWorldCommand::class)->addTag(SymfonyApplication::COMMAND_TAG);
        $this->phar->setValue($app, $pharRoot);
        $app->registerCommands();

        self::assertTrue($app->has('hello-world'));
        self::assertTrue($app->has('dump-config'));
        if ('' === $pharRoot) {
            self::assertTrue($app->has('make-phar'));
        } else {
            self::assertFalse($app->has('make-phar'));
        }
        $ctx = ['name' => 'registerCommands', 'cmd' => 'hello-world'];
        $this->assertInfoInContextLog('command {cmd} registered', $ctx);
    }
    /**
     * data provider for symfony go
     *
     * @return array<string,array<mixed>>
     */
    public function symfonyGoData()
    {
        return [
            'list_command' => [ true  , 'list'  ],
            'any_command ' => [ false , 'hello' ],
        ];
    }
    /**
     * @covers \DgfipSI1\Application\SymfonyApplication::go
     *
     * @dataProvider symfonyGoData
     *
     * @param bool   $isSingle
     * @param string $cmdName
     */
    public function testSymfonyGo($isSingle, $cmdName): void
    {
        /** @var \Mockery\MockInterface $app */
        $app = Mockery::mock(SymfonyApplication::class);
        $app->shouldAllowMockingProtectedMethods();
        $app->makePartial();
        $app->shouldReceive('finalize')->once();
        $app->shouldReceive('isSingleCommand')->once()->andReturn($isSingle);
        $app->shouldReceive('run')->once();

        /** @var SymfonyApplication $app */
        $app->setLogger($this->logger);
        $input = new ArgvInput(['./tests', 'hello']);
        $this->input->setValue($app, $input);
        $app->go();

        $this->assertInfoInContextLog('Launching symfony command', ['name' => 'go', 'cmd_name' => $cmdName]);
    }
    /**
     * @covers \DgfipSI1\Application\SymfonyApplication::finalize
     */
    public function testFinalizeSymfony(): void
    {
        /** @var \Mockery\MockInterface $app */
        $app = Mockery::mock(SymfonyApplication::class);
        $app->shouldAllowMockingProtectedMethods();
        $app->makePartial();

        /** @var SymfonyApplication $app */
        $app->setLogger($this->logger);
        $app->setConfig(new ConfigHelper());
        $app->setContainer(new Container());
        $app->getContainer()->addShared('application', $app);

        // add optionSetter to container
        /** @var \Mockery\MockInterface $optionSetter */
        $optionSetter = Mockery::mock(InputOptionsSetter::class);
        $optionSetter->makePartial();
        $optionSetter->shouldReceive('setInputOptions')->once();
        $app->getContainer()->addShared('input_options_setter', $optionSetter);

        // add event dispatcher
        $listenerTag = SymfonyApplication::EVENT_LISTENER_TAG;
        $app->getContainer()->addShared('EL1', new StringArgument('EL1'))->addTag($listenerTag);
        $app->getContainer()->addShared('EL2', new StringArgument('EL2'))->addTag($listenerTag);
        /** @var \Mockery\MockInterface $dispatcher */
        $dispatcher = Mockery::mock('dispatch');
        $dispatcher->makePartial();
        $dispatcher->shouldReceive('addSubscriber')->with('EL1')->once();
        $dispatcher->shouldReceive('addSubscriber')->with('EL2')->once();
        $app->getContainer()->addShared('eventDispatcher', $dispatcher);


        // Name and version checked
        /** @var \Mockery\MockInterface $app */
        $app->shouldReceive('setApplicationNameAndVersion')->once();
        // Container built
        $app->shouldReceive('configureContainer')->once();

        // find needed classes
        $app->shouldReceive('getNamespace')->with()->once()->andReturn('ns');
        $cmdTag = SymfonyApplication::COMMAND_TAG;
        $cmdSubClass = $this->class->getConstant('COMMAND_SUBCLASS');
        $app->shouldReceive('addDiscoveries')->withSomeOfArgs('ns', $cmdTag, $cmdSubClass, [], 'name')->once();
        $cmdConfTag = SymfonyApplication::COMMAND_CONFIG_TAG;
        $cmdClass = 'DgfipSI1\Application\Command';
        $app->shouldReceive('addDiscoveries')->with('ns', $cmdConfTag, $cmdClass, [], 'name')->once();
        $globConfTag = SymfonyApplication::GLOBAL_CONFIG_TAG;
        $appClass = 'DgfipSI1\Application\Config\ConfiguredApplicationInterface';
        $app->shouldReceive('addDiscoveries')->with('ns', $globConfTag, $appClass, $cmdClass)->once();
        $listenerClass = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
        $app->shouldReceive('addDiscoveries')->with('ns', $listenerTag, [$listenerClass])->once();
        $app->shouldReceive('discoverClasses')->once();

        // commands registered
        $app->shouldReceive('registerCommands')->once();
        /** @var SymfonyApplication $app */
        $this->fin->invoke($app);

        $this->assertAlertInLog('Advanced logger configuration applies only to Monolog');
    }
    /**
    * @covers \DgfipSI1\Application\SymfonyApplication::configureContainer
    */
    public function testConfigureSymfonyContainer(): void
    {
        $app = new SymfonyApplication($this->loader, [ './test', 'hello:test', '-q']);
        $this->cc->invoke($app);

        self::assertTrue($app->getContainer()->has('application'));
        self::assertTrue($app->getContainer()->has('config'));
        self::assertTrue($app->getContainer()->has('input'));
        self::assertTrue($app->getContainer()->has('output'));
        self::assertTrue($app->getContainer()->has('verbosity'));
        self::assertTrue($app->getContainer()->has('internal_configuration'));
        self::assertTrue($app->getContainer()->has('logger'));
        self::assertTrue($app->getContainer()->has('classLoader'));
        self::assertTrue($app->getContainer()->has('input_options_setter'));
        self::assertTrue($app->getContainer()->has('input_options_injector'));
        self::assertTrue($app->getContainer()->has('configuration_loader'));
        self::assertTrue($app->getContainer()->has('eventDispatcher'));

        // test inflectors : add configAware / LoggerAware class
        $app->getContainer()->addShared(EventTestClass::class);
        /** @var EventTestClass $cmd */
        $cmd = $app->getContainer()->get(EventTestClass::class);
        self::assertNotNull($cmd->getConfig());
        self::assertNotNull($cmd->getLogger());
        /** @var ConfigLoader $cmd */
        $cmd = $app->getContainer()->get('configuration_loader');
        self::assertNotNull($cmd->getContainer());



        // test that dispatcher has been added to application
        $class = new ReflectionClass($app::class);
        /** @var ReflectionClass $p */
        $p = $class->getParentClass();
        /** @var ReflectionClass $pp */
        $pp = $p->getParentClass();
        $disp = $pp->getProperty('dispatcher');
        self::assertEquals($app->getContainer()->get('eventDispatcher'), $disp->getValue($app));
    }
}
