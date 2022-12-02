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
    public function setup(): void
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
    }
    /**
     * creates a fully equiped discoverer
     *
     * @return ClassDiscoverer;
     */
    public function createDiscoverer()
    {
        $disc = new ClassDiscoverer($this->loader);
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
        $this->assertEquals([], $discProp->getValue($disc));
        $this->assertEquals($this->loader, $loadProp->getValue($disc));
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
        $this->assertEquals(['ns'], $def->getNamespaces());
        $this->assertEquals('tag', $def->getTag());
        $this->assertEquals([], $def->getDependencies());
        $this->assertEquals([], $def->getExcludeDeps());
        $this->assertEquals(null, $def->getIdAttribute());
        $this->assertEquals(true, $def->getEmptyOk());
        $this->assertEmpty($def->getErrMessages());

        // test 02 - nominal case - all
        $roboClass = TestRoboClass::class;
        $roboRef = new ReflectionClass($roboClass);
        $testClass = TestBaseClass::class;
        $testRef = new ReflectionClass($testClass);
        $def = new DiscovererDefinition([ 'ns' ], 'tag', $roboClass, $testClass, 'id', false);
        $this->assertEquals(['ns'], $def->getNamespaces());
        $this->assertEquals('tag', $def->getTag());
        $this->assertEquals([$roboRef], $def->getDependencies());
        $this->assertEquals([$testRef], $def->getExcludeDeps());
        $this->assertEquals('id', $def->getIdAttribute());
        $this->assertEquals(false, $def->getEmptyOk());
        $this->assertEmpty($def->getErrMessages());

        // test 03 - bad classes for dependencies or excludes
        $def = new DiscovererDefinition([ 'ns' ], 'tag', [ 'foo' ], [ 'bar' ], 'id', false);
        $errors = ['Class "foo" does not exist', 'Class "bar" does not exist' ];
        $this->assertEquals($errors, $def->getErrMessages());
        $this->assertEquals([], $def->getDependencies());
        $this->assertEquals([], $def->getExcludeDeps());
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

        $disc = $this->createDiscoverer();
        $disc->addDiscoverer([ 'ns' ], 'tag', [ 'foo' ], [ 'bar' ], 'id', false);
        $this->assertWarningInLog('Class "foo" does not exist');
        $this->assertWarningInLog('Class "bar" does not exist');

        /** @var array<string,array<DiscovererDefinition>> $definitions */
        $definitions = $discProp->getValue($disc);
        $this->assertTrue(array_key_exists('ns', $definitions));
        $this->assertEquals(1, count($definitions));
        $def = $definitions['ns'][0];
        $this->assertEquals(['ns'], $def->getNamespaces());
        $this->assertEquals('tag', $def->getTag());
        $this->assertEquals([], $def->getDependencies());
        $this->assertEquals([], $def->getExcludeDeps());
    }
    /**
     * test
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer::discoverAllClasses
     *
     * @return void
     */
    public function testDiscoverAllClasses()
    {
        $disc = $this->createDiscoverer();
        $this->logger->setCallers(['discoverAllClasses']);
        $disc->addDiscoverer(self::TEST_NAMESPACE, 'tag', Tasks::class);
        $disc->discoverAllClasses();
        $this->assertDebugInLog('Discovering all classes...');
        $this->assertInfoInLog('tag : Search [Tasks] classes, excluding [] : 1 classe(s) found.', true);
        $this->assertLogEmpty();

        $disc = $this->createDiscoverer();
        $this->logger->setCallers(['discoverAllClasses']);
        $disc->addDiscoverer([ 'ns' ], 'tag', [ 'foo' ], emptyOk: false);
        $msg = '';
        try {
            $disc->discoverAllClasses();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('No classes found for tag tag', $msg);
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
        $this->assertEquals(count($refClasses), count($classes));
        foreach ($refClasses as $refClass) {
            $this->assertDebugInLog("found $refClass", interpolate: true);
            $this->assertTrue(in_array($refClass, $classes));
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
        $this->assertEquals([ TestRoboClass::class, TestBaseClass::class ], $classes);
        foreach ($refClasses as $class) {
            $this->assertDebugInLog('Applying Filters to '.$class, true);
        }
        $this->assertDebugInLog('Keeping '.TestRoboClass::class, true);
        $this->assertDebugInLog('Keeping '.TestBaseClass::class, true);
        $this->assertLogEmpty();

        // test2 - Filter In - Robo  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [$roboClass]]);
        $this->assertEquals([ TestRoboClass::class ], $classes);
        foreach ($refClasses as $class) {
            $this->assertDebugInLog('Applying Filters to '.$class, true);
        }
        $this->assertDebugInLog('Keeping '.TestRoboClass::class, true);
        $this->assertLogEmpty();

        // test3 - Filter In two classes => exclude all  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [$roboClass, $baseClass]]);
        $this->assertEquals([], $classes);
        foreach ($refClasses as $class) {
            $this->assertDebugInLog('Applying Filters to '.$class, true);
        }
        $this->assertLogEmpty();

        // test4 - Filter Out - Robo */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [], [$roboClass]]);
        $this->assertEquals([ TestBaseClass::class ], $classes);
        foreach ($refClasses as $class) {
            $this->assertDebugInLog('Applying Filters to '.$class, true);
        }
        $this->assertDebugInLog('Keeping '.TestBaseClass::class, true);
        $this->assertLogEmpty();

        // test5 - Filter Out two classes => include none  */
        $classes = $filterMethod->invokeArgs($disc, [ $refClasses, [], [$roboClass, $baseClass]]);
        $this->assertEquals([ ], $classes);
        foreach ($refClasses as $class) {
            $this->assertDebugInLog('Applying Filters to '.$class, true);
        }
        $this->assertLogEmpty();

        // test6 - Filter with a bad class  */
        $classes = $filterMethod->invokeArgs($disc, [ ['foo'], [], [$roboClass, $baseClass]]);
        $this->assertDebugInLog('Applying Filters to {class}');
        $this->assertWarningInLog('Class "foo" does not exist');
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
        $this->assertDebugInLog('Adding class '.TestRoboClass::class.' to container', true);
        $this->assertDebugInLog('Adding class '.TestBaseClass::class.' to container', true);
        $this->assertDebugInLog('Add tag allClasses for class '.TestRoboClass::class, true);
        $this->assertDebugInLog('Add tag allClasses for class '.TestBaseClass::class, true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ $classes, 'allClasses' ]);
        $this->assertDebugInLog('Class '.TestRoboClass::class.' already in container', true);
        $this->assertDebugInLog('Class '.TestBaseClass::class.' already in container', true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ [TestRoboClass::class], 'RoboTasks' ]);
        $this->assertDebugInLog('Class '.TestRoboClass::class.' already in container', true);
        $this->assertDebugInLog('Add tag RoboTasks for class '.TestRoboClass::class, true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ [TestRoboClass::class], 'RoboTaskNoName', 'name' ]);
        $this->assertWarningInLog('invalid service id for class '.TestRoboClass::class, true);
        $this->assertLogEmpty();
        $registerMethod->invokeArgs($disc, [ [TestBaseClass::class], 'BaseClass', 'name' ]);
        $this->assertDebugInLog('Class '.TestBaseClass::class.' already in container', true);
        $this->assertDebugInLog('Add tag test for class '.TestBaseClass::class, true);
        $this->assertDebugInLog('Add tag BaseClass for class '.TestBaseClass::class, true);
        $this->assertLogEmpty();

        /** @var TestBaseClass $testClass */
        $testClass = $disc->getContainer()->get(TestRoboClass::class);
        $this->assertEquals(TestRoboClass::class, get_class($testClass));
        $roboDef = $disc->getContainer()->extend(TestRoboClass::class);
        $this->assertTrue($roboDef->hasTag('allClasses'));
        $this->assertTrue($roboDef->hasTag('RoboTasks'));
        $this->assertFalse($roboDef->hasTag('RoboTaskNoName'));
        $this->assertFalse($roboDef->hasTag('BaseClass'));

        /** @var TestBaseClass $testClass */
        $testClass = $disc->getContainer()->get(TestBaseClass::class);
        $this->assertEquals(TestBaseClass::class, get_class($testClass));
        $baseDef = $disc->getContainer()->extend(TestBaseClass::class);
        $this->assertTrue($baseDef->hasTag('allClasses'));
        $this->assertFalse($baseDef->hasTag('RoboTasks'));
        $this->assertFalse($baseDef->hasTag('RoboTaskNoName'));
        $this->assertTrue($baseDef->hasTag('BaseClass'));
        $this->assertTrue($baseDef->hasTag('test'));
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

        $this->assertNull($method->invokeArgs($disc, [ TestBaseClass::class, null]));

        $name = $method->invokeArgs($disc, [ TestBaseClass::class, 'name' ]);
        $this->assertEquals('test', $name);

        $msg = '';
        try {
            $name = $method->invokeArgs($disc, [ TestBaseClass::class, 'foo' ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Attribute .foo. not found in class/', $msg);
        $msg = '';
        try {
            $name = $method->invokeArgs($disc, [ 'badClass', 'foo' ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Error inspecting class/', $msg);
    }
}
