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
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
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
 *
 *
 */
class MappedOptionTest extends LogTestCase
{
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
    }
    /**
     * test setInputOptions
     *
     * @covers \DgfipSI1\Application\Config\MappedOption
     * @covers \DgfipSI1\Application\Config\OptionType
     *
     * @return void
     */
    public function testMappedOption()
    {
        $opt = new MappedOption('test', OptionType::Array, 'this is a test option', 'A', []);
        $this->assertEquals('this is a test option', $opt->getOption()->getDescription());
        $this->assertEquals('A', $opt->getOption()->getShortcut());
        $this->assertEquals([], $opt->getOption()->getDefault());
        $this->assertEquals('test', $opt->getOption()->getName());
        $this->assertTrue($opt->getOption()->isArray());
        $this->assertFalse($opt->getOption()->isNegatable());
        $this->assertFalse($opt->getOption()->isValueOptional());
        $this->assertTrue($opt->getOption()->isValueRequired());

        $opt = new MappedOption('test', OptionType::Scalar);
        $this->assertEquals('', $opt->getOption()->getDescription());
        $this->assertEquals(null, $opt->getOption()->getShortcut());
        $this->assertEquals(null, $opt->getOption()->getDefault());
        $this->assertEquals('test', $opt->getOption()->getName());
        $this->assertFalse($opt->getOption()->isArray());
        $this->assertFalse($opt->getOption()->isNegatable());
        $this->assertFalse($opt->getOption()->isValueOptional());
        $this->assertTrue($opt->getOption()->isValueRequired());

        $opt = new MappedOption('testb', OptionType::Boolean, 'bool test', 'B');
        $this->assertEquals('bool test', $opt->getOption()->getDescription());
        $this->assertEquals('B', $opt->getOption()->getShortcut());
        $this->assertEquals('testb', $opt->getOption()->getName());
        $this->assertFalse($opt->getOption()->isArray());
        $this->assertTrue($opt->getOption()->isNegatable());
        $this->assertFalse($opt->getOption()->isValueOptional());
        $this->assertFalse($opt->getOption()->isValueRequired());
    }
}
