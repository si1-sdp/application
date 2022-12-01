<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\testLogger\LogTestCase;

/**
 *
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
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
     * @covers \DgfipSI1\Application\Config\MappedOption::__construct
     * @covers \DgfipSI1\Application\Config\MappedOption::getOption
     * @covers \DgfipSI1\Application\Config\MappedOption::getName
     * @covers \DgfipSI1\Application\Config\OptionType::mode
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
        $this->assertEquals('test', $opt->getName());
        $this->assertTrue($opt->getOption()->isArray());
        $this->assertFalse($opt->getOption()->isNegatable());
        $this->assertFalse($opt->getOption()->isValueOptional());
        $this->assertTrue($opt->getOption()->isValueRequired());

        $opt = new MappedOption('test', OptionType::Scalar);
        $this->assertEquals('', $opt->getOption()->getDescription());
        $this->assertEquals(null, $opt->getOption()->getShortcut());
        $this->assertEquals(null, $opt->getOption()->getDefault());
        $this->assertEquals('test', $opt->getOption()->getName());
        $this->assertEquals('test', $opt->getName());
        $this->assertFalse($opt->getOption()->isArray());
        $this->assertFalse($opt->getOption()->isNegatable());
        $this->assertFalse($opt->getOption()->isValueOptional());
        $this->assertTrue($opt->getOption()->isValueRequired());

        $opt = new MappedOption('testb', OptionType::Boolean, 'bool test', 'B');
        $this->assertEquals('bool test', $opt->getDescription());
        $this->assertEquals('B', $opt->getOption()->getShortcut());
        $this->assertEquals('testb', $opt->getOption()->getName());
        $this->assertEquals('testb', $opt->getName());
        $this->assertFalse($opt->getOption()->isArray());
        $this->assertTrue($opt->getOption()->isNegatable());
        $this->assertFalse($opt->getOption()->isValueOptional());
        $this->assertFalse($opt->getOption()->isValueRequired());
    }
    /**
     * test setInputOptions
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::getCommand
     * @covers \DgfipSI1\Application\Config\MappedOption::setCommand
     *
     * @return void
     */
    public function testSetGetCommand()
    {
        $opt = new MappedOption('test', OptionType::Array, 'this is a test option', 'A', []);
        $opt->setCommand('foo');
        $this->assertEquals('foo', $opt->getCommand());
    }
    /**
     * test optionFromConfig
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::createFromConfig
     *
     * @return void
     */
    public function testCreateFromConfig()
    {

        // test 1 : scalar option
        $scalarOpt = [
            MappedOption::OPT_SHORT         => 'T',
            MappedOption::OPT_DESCRIPTION   => 'this is a testing option',
            MappedOption::OPT_TYPE          => 'scalar',
        ];
        /** @var MappedOption $option */
        $option = MappedOption::createFromConfig('test-scalar-opt', $scalarOpt);
        $inputOption = $option->getOption();
        $this->assertEquals('test-scalar-opt', $inputOption->getName());
        $this->assertEquals('T', $inputOption->getShortcut());
        $this->assertEquals('this is a testing option', $inputOption->getDescription());
        $this->assertEquals(null, $inputOption->getDefault());
        $this->assertTrue($inputOption->isValueRequired());
        $this->assertFalse($inputOption->isArray());

        // test 2 : array option
        $arrayOpt = [
            MappedOption::OPT_TYPE          => 'array',
            MappedOption::OPT_DEFAULT_VALUE => [ 'foo' ],
        ];
        /** @var MappedOption $option */
        $option = MappedOption::createFromConfig('test-array-opt', $arrayOpt);
        $inputOption = $option->getOption();
        $this->assertEquals('test-array-opt', $inputOption->getName());
        $this->assertEquals(null, $inputOption->getShortcut());
        $this->assertEquals(null, $inputOption->getDescription());
        $this->assertEquals(['foo'], $inputOption->getDefault());
        $this->assertTrue($inputOption->isValueRequired());
        $this->assertTrue($inputOption->isArray());

        // test 3 : boolean option
        $boolOpt = [
            MappedOption::OPT_TYPE          => 'boolean',
        ];
        /** @var MappedOption $option */
        $option = MappedOption::createFromConfig('test-bool-opt', $boolOpt);
        $inputOption = $option->getOption();
        $this->assertEquals('test-bool-opt', $inputOption->getName());
        $this->assertEquals(null, $inputOption->getShortcut());
        $this->assertEquals(null, $option->getDescription());
        $this->assertEquals(null, $inputOption->getDefault());
        $this->assertFalse($inputOption->isValueRequired());
        $this->assertFalse($inputOption->isArray());

        // test 4 : unknown type
        $badOpt = [
            MappedOption::OPT_TYPE          => 'foo',
        ];
        $msg = '';
        try {
            $option = MappedOption::createFromConfig('test-bad-opt', $badOpt);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Unknown option type 'foo' for option 'test-bad-opt'", $msg);

        // test 5 : mismatch
        $badOpt = [
            MappedOption::OPT_TYPE          => 'array',
            MappedOption::OPT_DEFAULT_VALUE => true,
        ];
        $msg = '';
        try {
            $option = MappedOption::createFromConfig('bad-opt', $badOpt);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $error = "Error creating option bad-opt : A default value for an array option must be an array.";
        $this->assertEquals($error, $msg);

        // test 6 : no type at all
        $badOpt = [];
        $msg = '';
        try {
            $option = MappedOption::createFromConfig('test-bad-opt', $badOpt);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Missing option type for test-bad-opt", $msg);
    }
}
