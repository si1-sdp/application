<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\Application\Application;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use ReflectionClass;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 *
 */
class ClassDiscovererTest extends LogTestCase
{
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
     *  test discoverPsr4Classes
     *
     * @covers \DgfipSI1\Application\Utils\ClassDiscoverer
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
    */
    public function testDiscoverPsr4Classes(): void
    {
        $disc = new ClassDiscoverer($this->loader);
        $class = new ReflectionClass(Application::class);
        /** @var class-string $roboClass */
        $roboClass = $class->getConstant('ROBO_SUBCLASS');
        $roboClassName = "DgfipSI1\ApplicationTests\Utils\TestClasses\TestRoboClass";
        $baseClassName = "DgfipSI1\ApplicationTests\Utils\TestClasses\TestBaseClass";

        // test with no log and bad subclass => should throw an exception
        $msg = '';
        try {
            $returnedValue = $disc->discoverPsr4Classes('Utils\\TestClasses', 'fooBar'); /** @phpstan-ignore-line */
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('Class "fooBar" does not exist', $msg);
        // test with no log and discover bad class => should throw an exception
        $msg = '';
        try {
            $returnedValue = $disc->discoverPsr4Classes('Utils\\TestBadClasses', $roboClass);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('Class "DgfipSI1\ApplicationTests\Utils\TestBadClasses\BadCommand" does not exist', $msg);

        // test discover one class with no log
        $returnedValue = $disc->discoverPsr4Classes('Utils\\TestClasses', $roboClass);
        $this->assertEquals([$roboClassName], $returnedValue);

        // now add a logger
        $this->logger = new TestLogger(['discoverPsr4Classes', 'discoverClassesInNamespace', 'filterClasses']);
        $disc->setLogger($this->logger);

        /** test nominal case : discover one class  */
        $returnedValue = $disc->discoverPsr4Classes('Utils\\TestClasses', $roboClass);
        $this->assertEquals([$roboClassName], $returnedValue);
        $this->assertDebugInLog("1/2 - search {namespace} namespace - found");
        $this->assertInfoInLog("1/2 - 5 classe(s) found.");
        $this->assertDebugInLog("2/2 - Filter : {class} matches");
        $this->assertInfoInLog("2/2 - 1 classe(s) found in namespace");
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        /** test nominal case without filter : discover two classes  */
        $returnedValue = $disc->discoverPsr4Classes('Utils\\TestClasses');
        $this->assertEquals(2, count($returnedValue));
        $this->assertTrue(in_array($roboClassName, $returnedValue));
        $this->assertTrue(in_array($baseClassName, $returnedValue));
        $this->assertDebugInLog("1/2 - search {namespace} namespace - found");
        $this->assertInfoInLog("1/2 - 5 classe(s) found.");
        $this->assertDebugInLog("2/2 - Filter : {class} matches");
        $this->assertInfoInLog("2/2 - 2 classe(s) found in namespace");
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        /** test class in error */
        $returnedValue = $disc->discoverPsr4Classes('Utils\\TestOtherBadClasses', $roboClass);
        $this->assertEquals([], $returnedValue);

        $this->assertDebugInLog("1/2 - search {namespace} namespace - found");
        $this->assertInfoInLog("1/2 - 1 classe(s) found.");
        $classString = "DgfipSI1\ApplicationTests\Utils\TestOtherBadClasses\BadCommand";
        $this->assertWarningInLog("2/2 Class \"$classString\" does not exist");
        $this->assertWarningInLog("No classes subClassing or implementing {dependency} found in namespace");


        /** test dependency in error */
        $returnedValue = $disc->discoverPsr4Classes('TestBadClasses', 'symfonyBadCommands');/** @phpstan-ignore-line */
        $this->assertEquals([], $returnedValue);
        $this->assertWarningInLog('Class "symfonyBadCommands" does not exist');
        $this->assertDebugLogEmpty();
        $this->assertNoMoreProdMessages();

        //$this->showDebugLogs();
    }
}
