<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationContainerTests;

use DgfipSI1\Application\ApplicationContainer;
use PHPUnit\Framework\TestCase;

/**
 *  tests of DgfipSI1\Application\ApplicationContainer
 */
class ApplicationContainerTest extends TestCase
{
    /**
     * test getDefinitions
     *
     * @covers \DgfipSI1\Application\ApplicationContainer::getDefinitions
     * @covers \DgfipSI1\Application\ApplicationContainer::getServices
     */
    public function testGetDefinitions(): void
    {
        $container = new ApplicationContainer();
        $def01 = $container->addShared('container', $container)->addTag('cont01')->addTag('container');
        $def02 = $container->addShared('container02', $container)->addTag('cont02')->addTag('container');
        $def03 = $container->addShared('phpunit', $this)->addTag('test');

        $definitions = $container->getDefinitions();
        $this->assertEquals(3, count($definitions));
        $this->assertArrayHasKey('container', $definitions);
        $this->assertEquals($def01, $definitions['container']);
        $this->assertArrayHasKey('container02', $definitions);
        $this->assertEquals($def02, $definitions['container02']);
        $this->assertArrayHasKey('phpunit', $definitions);
        $this->assertEquals($def03, $definitions['phpunit']);

        /* Test with filters          */
        $definitions = $container->getDefinitions(regex: '/^cont/');
        $this->assertEquals(2, count($definitions));
        $definitions = $container->getDefinitions(regex: '/^container[0-9]/');
        $this->assertEquals(1, count($definitions));
        $definitions = $container->getDefinitions(tag: 'container');
        $this->assertEquals(2, count($definitions));
        $definitions = $container->getDefinitions(tag: 'cont01');
        $this->assertEquals(1, count($definitions));

        /* Test services */
        $services = $container->getServices(tag: 'cont01');
        $this->assertEquals(1, count($services));
        $this->assertEquals($container, $services['container']);
        $services = $container->getServices(regex: '/php/');
        $this->assertEquals(1, count($services));
        $this->assertEquals($this, $services['phpunit']);
    }
}
