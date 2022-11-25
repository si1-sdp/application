<?php
/*
 * This file is part of dgfip-si1/application
 */
namespace DgfipSI1\ApplicationTests\TestClasses\ForDiscoverer;

use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 *    RoboFile for Application test
 */
#[AsCommand(name: 'test', description: 'A test')]
class TestBaseClass implements ConfigAwareInterface
{
    use ConfigAwareTrait;
    /**
     * Hello test
     *
     * @return void
     */
    public function helloTest()
    {
        echo "Hello !";
    }
}
