<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\RoboApplication;
use DgfipSI1\ApplicationTests\TestClasses\Commands\AppTestRoboFile;
use DgfipSI1\ApplicationTests\TestClasses\Commands\EmptyRoboFile;
use DgfipSI1\ApplicationTests\TestClasses\Commands\EventTestClass;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use Mockery;
use Mockery\Mock;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
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
class RoboApplicationTest extends LogTestCase
{
    protected const COMMAND_NAMESPACE = 'TestClasses\\Commands';

    /** @var ClassLoader $loader */
    protected $loader;

    /** @var \ReflectionClass<ApplicationInterface> */
    protected $class;

    /** @var \ReflectionProperty */
    protected $lg;

    /** @var ReflectionMethod $cc */
    private $cc;
    /** @var ReflectionMethod $fin */
    private $fin;
    /** @var ReflectionProperty $ic */
    private $ic;

    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
        $this->class = new \ReflectionClass(RoboApplication::class);

        $this->cc = $this->class->getMethod('configureContainer');
        $this->cc->setAccessible(true);
        $this->fin = $this->class->getMethod('finalize');
        $this->fin->setAccessible(true);
        $this->ic = $this->class->getProperty('intConfig');
        $this->ic->setAccessible(true);

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
    * @covers \DgfipSI1\Application\RoboApplication::registerCommands
    */
    public function testRegisterRoboCommands(): void
    {
        $app = new RoboApplication($this->loader, [ './test', 'hello:test', '-q']);
        $app->setLogger($this->logger);
        $app->registerCommands();
        $this->assertAlertInLog('No robo command(s) found');

        $app->getContainer()->addShared(EmptyRoboFile::class)->addTag(RoboApplication::COMMAND_TAG);
        $app->registerCommands();
        $this->assertAlertInLog('No robo command(s) found');

        $app->getContainer()->addShared(AppTestRoboFile::class)->addTag(RoboApplication::COMMAND_TAG);
        $app->registerCommands();
        $this->assertInfoInContextLog('robo command(s) found', ['count' => 1, 'name' => 'registerCommands']);
    }
    /**
    * @covers \DgfipSI1\Application\RoboApplication::go
    */
    public function testRoboGo(): void
    {
        /** @var \Mockery\MockInterface $app */
        $app = Mockery::mock(RoboApplication::class);
        $app->shouldAllowMockingProtectedMethods();
        $app->makePartial();
        $app->shouldReceive('finalize')->once();


        /** @var RoboApplication $app */
        $app->setLogger($this->logger);
        $app->setContainer(new Container());
        $app->getContainer()->addShared(AppTestRoboFile::class)->addTag(RoboApplication::COMMAND_TAG);

        /** @var \Mockery\MockInterface $runner */
        $runner = \Mockery::mock('overload:Robo\Runner')->makePartial();
        $runner->shouldReceive('setContainer')->with($app->getContainer())->once();
        $runner->shouldReceive('run')->once();

        $app->go();

        $this->assertInfoInContextLog('Launching robo command', ['name' => 'go']);
    }
    /**
    * @covers \DgfipSI1\Application\RoboApplication::finalize
    */
    public function testFinalizeRobo(): void
    {
        /** @var \Mockery\MockInterface $app */
        $app = Mockery::mock(RoboApplication::class);
        $app->shouldAllowMockingProtectedMethods();
        $app->makePartial();
        // Name and version checked
        $app->shouldReceive('setApplicationNameAndVersion')->once();
        // Container built
        $app->shouldReceive('configureContainer')->once();
        // find needed classes
        $app->shouldReceive('getNamespace')->with(RoboApplication::COMMAND_TAG)->once()->andReturn('ns');
        $cmdTag = RoboApplication::COMMAND_TAG;
        $cmdSubClass = $this->class->getConstant('COMMAND_SUBCLASS');
        $app->shouldReceive('addDiscoveries')->with('ns', $cmdTag, $cmdSubClass)->once();
        $app->shouldReceive('discoverClasses')->once();
        // commands registered
        $app->shouldReceive('registerCommands')->once();
        /** @var RoboApplication $app */
        $app->setLogger($this->logger);
        $app->setConfig(new ConfigHelper());
        $app->setContainer(new Container());
        $app->getContainer()->addShared('application', $app);
        $dispatcher = new EventDispatcher();
        $app->getContainer()->addShared('eventDispatcher', $dispatcher);

        $this->fin->invoke($app);
        // test that Robo::finalizeContainer has been called
        $class = new ReflectionClass($app::class);
        /** @var ReflectionClass $p */
        $p = $class->getParentClass();
        /** @var ReflectionClass $pp */
        $pp = $p->getParentClass();
        /** @var ReflectionClass $ppp */
        $ppp = $pp->getParentClass();
        $disp = $ppp->getProperty('dispatcher');
        self::assertEquals($dispatcher, $disp->getValue($app));


        $this->assertAlertInLog('Advanced logger configuration applies only to Monolog');
    }
    /**
    * @covers \DgfipSI1\Application\RoboApplication::configureContainer
    */
    public function testConfigureRoboContainer(): void
    {
        $app = new RoboApplication($this->loader, [ './test', 'hello:test', '-q']);
        $this->cc->invoke($app);

        self::assertTrue($app->getContainer()->has('roboLogger'));
        self::assertTrue($app->getContainer()->has('application'));
        self::assertTrue($app->getContainer()->has('verbosity'));
        self::assertTrue($app->getContainer()->has('internal_configuration'));
        self::assertTrue($app->getContainer()->has('input_options_setter'));
        self::assertTrue($app->getContainer()->has('logger'));

        // test inflectors : add configAware / LoggerAware class
        $app->getContainer()->addShared(EventTestClass::class);
        /** @var EventTestClass $obj */
        $obj = $app->getContainer()->get(EventTestClass::class);
        self::assertNotNull($obj->getConfig());
        self::assertNotNull($obj->getLogger());
    }
}
