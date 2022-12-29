<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\Application\Utils\DiscovererDefinition;
use DgfipSI1\ApplicationTests\TestClasses\ForDiscoverer\AbstractTestClass;
use DgfipSI1\ApplicationTests\TestClasses\ForDiscoverer\TestBaseClass;
use DgfipSI1\ApplicationTests\TestClasses\ForDiscoverer\TestInterface;
use DgfipSI1\ApplicationTests\TestClasses\ForDiscoverer\TestRoboClass;
use DgfipSI1\ApplicationTests\TestClasses\ForDiscoverer\TestTrait;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use Mockery;
use ReflectionClass;
use Robo\Tasks;

/**
 * @uses \DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses \DgfipSI1\Application\Utils\DiscovererDefinition
 *
 */
class ClassDiscovererTest extends LogTestCase
{
    protected const TEST_NAMESPACE = 'TestClasses\\ForDiscoverer';
    /** @var ClassLoader $loader */
    protected $loader;

    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
    }
    /**
     * Checks that log is empty at end of test
     *
     * @return void
     */
    public function tearDown(): void
    {
        \Mockery::close();
    }
    /**
     * creates a fully equiped discoverer
     *  @param bool $mock
     *
     * @return ClassDiscoverer;
     */
    public function createDiscoverer($mock = false)
    {
        if ($mock) {
            /** @var \Mockery\MockInterface $disc */
            $disc = Mockery::mock(ClassDiscoverer::class);
            $disc->makePartial();
            $disc->shouldAllowMockingProtectedMethods();

            $class = new ReflectionClass(ClassDiscoverer::class);
            $clProp = $class->getProperty('classLoader');
            $clProp->setAccessible(true);
            $clProp->setValue($disc, $this->loader);
        } else {
            $disc = new ClassDiscoverer($this->loader);
        }
        /** @var ClassDiscoverer $disc */
        $disc->setContainer(new Container());
        $this->logger = new TestLogger();
        $disc->setLogger($this->logger);

        return $disc;
    }
    /**
     * test constructor
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::__construct
     *
     * @return void
     */
    public function testConstructor()
    {
        $class = new ReflectionClass(ClassDiscoverer::class);
        $discProp = $class->getProperty('discoverers');
        $discProp->setAccessible(true);
        $loadProp = $class->getProperty('classLoader');
        $loadProp->setAccessible(true);

        $disc = new ClassDiscoverer($this->loader);
        self::assertEquals([], $discProp->getValue($disc));
        self::assertEquals($this->loader, $loadProp->getValue($disc));
    }
    /**
     * test discovererProperties
     *
     * @covers \DgfipSI1\Application\Utils\DiscovererDefinition
     *
     * @return void
     */
    public function testDefinition()
    {
        // test 01 - nominal case - minimal input
        $def = new DiscovererDefinition('ns', 'tag');
        self::assertEquals(['ns'], $def->getNamespaces());
        self::assertEquals('tag', $def->getTag());
        self::assertEquals([], $def->getDependencies());
        self::assertEquals([], $def->getExcludeDeps());
        self::assertEquals(null, $def->getIdAttribute());
        self::assertEquals(true, $def->getEmptyOk());
        self::assertEmpty($def->getErrMessages());

        // test 02 - nominal case - all
        $roboClass = TestRoboClass::class;
        $roboRef = new ReflectionClass($roboClass);
        $testClass = TestBaseClass::class;
        $testRef = new ReflectionClass($testClass);
        $def = new DiscovererDefinition([ 'ns' ], 'tag', $roboClass, $testClass, 'id', false);
        self::assertEquals(['ns'], $def->getNamespaces());
        self::assertEquals('tag', $def->getTag());
        self::assertEquals([$roboRef], $def->getDependencies());
        self::assertEquals([$testRef], $def->getExcludeDeps());
        self::assertEquals('id', $def->getIdAttribute());
        self::assertEquals(false, $def->getEmptyOk());
        self::assertEmpty($def->getErrMessages());

        // test 03 - bad classes for dependencies or excludes
        $def = new DiscovererDefinition([ 'ns' ], 'tag', [ 'foo', $roboClass ], [ 'bar', $testClass ], 'id', false);
        $errors = ['Class "foo" does not exist', 'Class "bar" does not exist' ];
        self::assertEquals($errors, $def->getErrMessages());
        self::assertEquals([$roboRef], $def->getDependencies());
        self::assertEquals([$testRef], $def->getExcludeDeps());
    }
    /**
     * test addDiscoverer
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::addDiscoverer
     *
     * @return void
     */
    public function testAddDiscoverer()
    {
        $class = new ReflectionClass(ClassDiscoverer::class);
        $discProp = $class->getProperty('discoverers');
        $discProp->setAccessible(true);
        $tcProp = $class->getProperty('tagCount');
        $tcProp->setAccessible(true);


        $disc = $this->createDiscoverer();
        $disc->addDiscoverer([ 'ns' ], 'tag', [ 'foo' ], [ 'bar' ], 'id', false);
        $this->assertWarningInContextLog('Class "foo" does not exist', ['name' => 'addDiscoverer']);
        $this->assertWarningInContextLog('Class "bar" does not exist', ['name' => 'addDiscoverer']);
        /** @var array<string,int> $tagCount */
        $tagCount = $tcProp->getValue($disc);
        self::assertArrayHasKey('tag', $tagCount);
        self::assertEquals(0, $tagCount['tag']);
        /** @var array<string,array<DiscovererDefinition>> $definitions */
        $definitions = $discProp->getValue($disc);
        self::assertTrue(array_key_exists('ns', $definitions));
        self::assertEquals(1, count($definitions));
        $def = $definitions['ns'][0];
        self::assertEquals(['ns'], $def->getNamespaces());
        self::assertEquals('tag', $def->getTag());
        self::assertEquals([], $def->getDependencies());
        self::assertEquals([], $def->getExcludeDeps());

        $disc->addDiscoverer([ 'ns' ], 'tag_not_required', [ 'foo' ], [ 'bar' ], 'id');

        self::assertFalse(array_key_exists('tag_not_required', $tagCount));
    }
    /**
     * test
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::discoverAllClasses
     *
     * @return void
     */
    public function testDiscoverAllClasses()
    {
        $class = new ReflectionClass(ClassDiscoverer::class);
        $tcProp = $class->getProperty('tagCount');
        $tcProp->setAccessible(true);

        $disc = $this->createDiscoverer(true);
        /** @var \Mockery\MockInterface $disc */
        $disc->shouldReceive('registerClasses')->times(3);

        /** @var ClassDiscoverer $disc */
        $this->logger->setCallers(['discoverAllClasses']);
        $disc->addDiscoverer(self::TEST_NAMESPACE, 'tag1', Tasks::class, $this::class, emptyOk: false);
        $disc->addDiscoverer('TestClasses\\Commands', 'tag1', Tasks::class, [], 'name', emptyOk: false);
        $disc->addDiscoverer(self::TEST_NAMESPACE, 'tag2', Tasks::class, [], emptyOk: false);

        $disc->discoverAllClasses();
        $this->assertDebugInContextLog('Discovering all classes...', ['name' => 'discoverAllClasses']);
        $msg = "{tag} : Search {deps} classes, excluding {excludes} : {count} classe(s) found.";
        $logCtxt = ['name' => 'discoverAllClasses', 'tag' => 'tag1', 'deps' => '[Tasks]', 'count' => 1];
        $this->assertInfoInContextLog($msg, array_merge($logCtxt, ['excludes' => '[ClassDiscovererTest]']));
        $logCtxt = ['name' => 'discoverAllClasses', 'tag' => 'tag1', 'deps' => '[Tasks]', 'attribute' => 'name'];
        $this->assertInfoInContextLog($msg, array_merge($logCtxt, ['excludes' => '[]', 'count' => 2]));
        $logCtxt = ['name' => 'discoverAllClasses', 'tag' => 'tag2', 'deps' => '[Tasks]', 'attribute' => ''];
        $this->assertInfoInContextLog($msg, array_merge($logCtxt, ['excludes' => '[]', 'count' => 1]));
        //$this->showLogs();
        $this->assertLogEmpty();
        /** @var array<string,int> $tagCount */
        $tagCount = $tcProp->getValue($disc);
        self::assertArrayHasKey('tag1', $tagCount);
        self::assertEquals(3, $tagCount['tag1']);
        self::assertArrayHasKey('tag2', $tagCount);
        self::assertEquals(1, $tagCount['tag2']);



        $disc = $this->createDiscoverer();
        $this->logger->setCallers(['discoverAllClasses']);
        $disc->addDiscoverer([ 'ns' ], 'tag', [ 'foo' ], emptyOk: false);
        $msg = '';
        try {
            $disc->discoverAllClasses();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('No classes found for tag tag', $msg);
    }
    /**
     *  test discoverClassesInNamespace
     *
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::discoverClassesInNamespace
     *
     */
    public function testDiscoverClassesInNamespace(): void
    {
        $disc = $this->createDiscoverer();

        $class = new ReflectionClass(ClassDiscoverer::class);
        $dcinMethod = $class->getMethod('discoverClassesInNamespace');
        $dcinMethod->setAccessible(true);
        /** @var array<class-string> $classes */
        $classes = $dcinMethod->invokeArgs($disc, [ self::TEST_NAMESPACE ]);
        $refClasses = [
            TestRoboClass::class,
            TestInterface::class,
            TestBaseClass::class,
            AbstractTestClass::class,
            TestTrait::class,
        ];
        self::assertEquals(count($refClasses), count($classes));
        $ctx = ['name' => 'discoverClassesInNamespace', 'namespace' => self::TEST_NAMESPACE ];
        foreach ($refClasses as $refClass) {
            $this->assertDebugInContextLog("found {class}", array_merge($ctx, ['class' => $refClass]));
            self::assertTrue(in_array($refClass, $classes, true));
        }
        $this->assertInfoInLog("5 classe(s) found in namespace");
        $this->assertLogEmpty();
    }
    /**
     *  test filterClasses
     *
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::filterClasses
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::classMatchesFilters
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::dependsOn
     *
     */
    public function testfilterClasses(): void
    {
        $disc = $this->createDiscoverer();

        $class = new ReflectionClass(ClassDiscoverer::class);
        $filterMethod = $class->getMethod('filterClasses');
        $filterMethod->setAccessible(true);
        $refClasses = [
            TestRoboClass::class,
            TestInterface::class,
            TestBaseClass::class,
            AbstractTestClass::class,
            TestTrait::class,
        ];
        $roboClass = new ReflectionClass(Tasks::class);
        $baseClass = new ReflectionClass(ConfigAwareInterface::class);

        // test1 - no filters - only abstract, traits and interface are filtered out  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, []]);
        self::assertEquals([ TestRoboClass::class, TestBaseClass::class ], $classes);
        foreach ($refClasses as $ref) {
            $this->assertDebugInContextLog('Applying Filters ', ['name' => 'filterClasses', 'class' => $ref ]);
        }
        $this->assertDebugInLog('Keeping '.TestRoboClass::class, true);
        $this->assertDebugInLog('Keeping '.TestBaseClass::class, true);
        $this->assertLogEmpty();

        // test2 - Filter In - Robo  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [$roboClass]]);
        self::assertEquals([ TestRoboClass::class ], $classes);
        foreach ($refClasses as $ref) {
            $this->assertDebugInLog('Applying Filters to '.$ref, true);
        }
        $this->assertDebugInLog('Keeping '.TestRoboClass::class, true);
        $this->assertLogEmpty();

        // test3 - Filter In two classes => exclude all  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [$roboClass, $baseClass]]);
        self::assertEquals([], $classes);
        foreach ($refClasses as $ref) {
            $this->assertDebugInLog('Applying Filters to '.$ref, true);
        }
        $this->assertLogEmpty();

        // test4 - Filter Out - Robo */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [], [$roboClass]]);
        self::assertEquals([ TestBaseClass::class ], $classes);
        foreach ($refClasses as $ref) {
            $this->assertDebugInLog('Applying Filters to '.$ref, true);
        }
        $this->assertDebugInLog('Keeping '.TestBaseClass::class, true);
        $this->assertLogEmpty();

        // test5 - Filter Out two classes => include none  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [], [$roboClass, $baseClass]]);
        self::assertEquals([ ], $classes);
        foreach ($refClasses as $ref) {
            $this->assertDebugInLog('Applying Filters to '.$ref, true);
        }
        $this->assertLogEmpty();

        // test6 - Filter with a bad class  */
        $classes = $filterMethod->invokeArgs($disc, [ ['foo', TestBaseClass::class], []]);
        $this->assertDebugInLog('Applying Filters to {class}');
        $this->assertWarningInLog('Class "foo" does not exist');
        $this->assertDebugInLog('Keeping '.TestBaseClass::class, true);
        $this->assertLogEmpty();
    }
    /**
     *  test registerClasses
     *
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::registerClasses
     *
     */
    public function testRegisterClasses(): void
    {
        $disc = $this->createDiscoverer();

        $class = new ReflectionClass(ClassDiscoverer::class);
        $registerMethod = $class->getMethod('registerClasses');
        $registerMethod->setAccessible(true);

        $classes = [
            TestRoboClass::class,
            TestBaseClass::class,
        ];
        $registerMethod->invokeArgs($disc, [ $classes, 'allClasses' ]);
        $ctx = ['name' => 'registerClasses', 'class' => TestRoboClass::class, 'tag' => 'allClasses'];
        $this->assertDebugInContextLog('Adding class {class} to container', $ctx);
        //$this->assertDebugInLog('Adding class '.TestRoboClass::class.' to container', true);
        $this->assertDebugInLog('Adding class '.TestBaseClass::class.' to container', true);
        $this->assertDebugInLog('Add tag allClasses for class '.TestRoboClass::class, true);
        $this->assertDebugInLog('Add tag allClasses for class '.TestBaseClass::class, true);
        //$this->showLogs();
        //$this->logReset();
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ $classes, 'allClasses' ]);
        $this->assertDebugInLog('Class '.TestRoboClass::class.' already in container', true);
        $this->assertDebugInLog('Class '.TestBaseClass::class.' already in container', true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ [TestRoboClass::class], 'RoboTasks' ]);
        $this->assertDebugInLog('Class '.TestRoboClass::class.' already in container', true);
        $this->assertDebugInLog('Add tag RoboTasks for class '.TestRoboClass::class, true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [$classes, 'RoboTaskNoName', 'nameNotHere' ]);
        $this->assertWarningInLog('invalid service id for class '.TestRoboClass::class, true);
        $this->assertWarningInLog('invalid service id for class '.TestBaseClass::class, true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ [TestBaseClass::class], 'BaseClass', 'name' ]);
        $this->assertDebugInLog('Class '.TestBaseClass::class.' already in container', true);
        $this->assertDebugInLog('Add tag test for class '.TestBaseClass::class, true);
        $this->assertDebugInLog('Add tag BaseClass for class '.TestBaseClass::class, true);
        $this->assertLogEmpty();

        /** @var TestBaseClass $testClass */
        $testClass = $disc->getContainer()->get(TestRoboClass::class);
        self::assertEquals(TestRoboClass::class, get_class($testClass));
        $roboDef = $disc->getContainer()->extend(TestRoboClass::class);
        self::assertTrue($roboDef->hasTag('allClasses'));
        self::assertTrue($roboDef->hasTag('RoboTasks'));
        self::assertFalse($roboDef->hasTag('RoboTaskNoName'));
        self::assertFalse($roboDef->hasTag('BaseClass'));

        /** @var TestBaseClass $testClass */
        $testClass = $disc->getContainer()->get(TestBaseClass::class);
        self::assertEquals(TestBaseClass::class, get_class($testClass));
        $baseDef = $disc->getContainer()->extend(TestBaseClass::class);
        self::assertTrue($baseDef->hasTag('allClasses'));
        self::assertFalse($baseDef->hasTag('RoboTasks'));
        self::assertFalse($baseDef->hasTag('RoboTaskNoName'));
        self::assertTrue($baseDef->hasTag('BaseClass'));
        self::assertTrue($baseDef->hasTag('test'));
    }
        /**
     *  test getAttributeValue
     *
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::getAttributeValue
     *
     */
    public function testGetAttributeValue(): void
    {
        $disc = $this->createDiscoverer();

        $class = new ReflectionClass(ClassDiscoverer::class);
        $method = $class->getMethod('getAttributeValue');
        $method->setAccessible(true);

        self::assertNull($method->invokeArgs($disc, [ TestBaseClass::class, null]));

        $name = $method->invokeArgs($disc, [ TestBaseClass::class, 'name' ]);
        self::assertEquals('test', $name);

        $msg = '';
        try {
            $name = $method->invokeArgs($disc, [ TestBaseClass::class, 'foo' ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/Attribute .foo. not found in class/', $msg);
        $msg = '';
        try {
            $name = $method->invokeArgs($disc, [ 'badClass', 'foo' ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/Error inspecting class/', $msg);
    }
}
