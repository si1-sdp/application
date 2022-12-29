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
    public function setUp(): void
    {
    }
    /**
     * test setInputOptions
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::__construct
     * @covers \DgfipSI1\Application\Config\MappedOption::getName
     * @covers \DgfipSI1\Application\Config\MappedOption::getDefaultValue
     * @covers \DgfipSI1\Application\Config\MappedOption::getDescription
     * @covers \DgfipSI1\Application\Config\MappedOption::isArray
     * @covers \DgfipSI1\Application\Config\MappedOption::isBool
     * @covers \DgfipSI1\Application\Config\MappedOption::isScalar
     * @covers \DgfipSI1\Application\Config\MappedOption::isArgument
     * @covers \DgfipSI1\Application\Config\OptionType::mode
     *
     * @return void
     */
    public function testConstructor()
    {
        $opt = new MappedOption('test', OptionType::Array, 'this is a test option', 'A');
        self::assertEquals('this is a test option', $opt->getOption()->getDescription());
        self::assertEquals('this is a test option', $opt->getDescription());
        self::assertEquals('A', $opt->getOption()->getShortcut());
        self::assertEquals([], $opt->getOption()->getDefault());
        self::assertEquals([], $opt->getDefaultValue());
        self::assertEquals('test', $opt->getOption()->getName());
        self::assertEquals('test', $opt->getName());
        self::assertTrue($opt->getOption()->isArray());
        self::assertFalse($opt->getOption()->isNegatable());
        self::assertFalse($opt->getOption()->isValueOptional());
        self::assertTrue($opt->getOption()->isValueRequired());
        self::assertTrue($opt->isArray());

        $opt = new MappedOption('test', OptionType::Scalar);
        self::assertEquals('', $opt->getOption()->getDescription());
        self::assertEquals('', $opt->getDescription());
        self::assertEquals(null, $opt->getOption()->getShortcut());
        self::assertEquals(null, $opt->getOption()->getDefault());
        self::assertEquals(null, $opt->getDefaultValue());
        self::assertEquals('test', $opt->getOption()->getName());
        self::assertEquals('test', $opt->getName());
        self::assertFalse($opt->getOption()->isArray());
        self::assertFalse($opt->getOption()->isNegatable());
        self::assertFalse($opt->getOption()->isValueOptional());
        self::assertTrue($opt->getOption()->isValueRequired());
        self::assertTrue($opt->isScalar());

        $opt = new MappedOption('testb', OptionType::Boolean, 'bool test', 'B');
        self::assertEquals('bool test', $opt->getDescription());
        self::assertEquals('B', $opt->getOption()->getShortcut());
        self::assertEquals('testb', $opt->getOption()->getName());
        self::assertEquals('testb', $opt->getName());
        self::assertEquals(null, $opt->getDefaultValue());
        self::assertFalse($opt->getOption()->isArray());
        self::assertTrue($opt->getOption()->isNegatable());
        self::assertFalse($opt->getOption()->isValueOptional());
        self::assertFalse($opt->getOption()->isValueRequired());
        self::assertTrue($opt->isBool());

        $opt = new MappedOption('testb', OptionType::Boolean, 'bool test', 'B', true);
        self::assertEquals('bool test', $opt->getDescription());
        self::assertEquals('B', $opt->getOption()->getShortcut());
        self::assertEquals('testb', $opt->getOption()->getName());
        self::assertEquals('testb', $opt->getName());
        self::assertEquals(true, $opt->getDefaultValue());

        $opt = new MappedOption('test-arg', OptionType::Argument, 'arg test', required: true);
        self::assertEquals('arg test', $opt->getDescription());
        self::assertEquals('test-arg', $opt->getArgument()->getName());
        self::assertEquals('test-arg', $opt->getName());
        self::assertTrue($opt->isArgument());
    }
    /**
     * test getArgument/getOption
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::getOption
     * @covers \DgfipSI1\Application\Config\MappedOption::getArgument
     *
     * @return void
     */
    public function testGetInputElement()
    {
        $opt = new MappedOption('test', OptionType::Array, 'this is a test option', 'A');
        self::assertEquals('this is a test option', $opt->getOption()->getDescription());
        $msg = '';
        try {
            $opt->getArgument();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('Cannot getArgument() on InputOption', $msg);
        $opt = new MappedOption('test', OptionType::Argument, 'this is a test argument');
        self::assertEquals('this is a test argument', $opt->getArgument()->getDescription());
        $msg = '';
        try {
            $opt->getOption();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('Cannot getOption() on InputArgument', $msg);
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
        self::assertEquals('foo', $opt->getCommand());
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
        self::assertEquals('test-scalar-opt', $inputOption->getName());
        self::assertEquals('T', $inputOption->getShortcut());
        self::assertEquals('this is a testing option', $inputOption->getDescription());
        self::assertEquals(null, $inputOption->getDefault());
        self::assertTrue($inputOption->isValueRequired());
        self::assertFalse($inputOption->isArray());

        // test 2 : array option
        $arrayOpt = [
            MappedOption::OPT_TYPE          => 'array',
            MappedOption::OPT_DEFAULT_VALUE => [ 'foo' ],
        ];
        /** @var MappedOption $option */
        $option = MappedOption::createFromConfig('test-array-opt', $arrayOpt);
        $inputOption = $option->getOption();
        self::assertEquals('test-array-opt', $inputOption->getName());
        self::assertEquals(null, $inputOption->getShortcut());
        self::assertEquals(null, $inputOption->getDescription());
        self::assertEquals(['foo'], $inputOption->getDefault());
        self::assertTrue($inputOption->isValueRequired());
        self::assertTrue($inputOption->isArray());

        // test 3 : boolean option
        $boolOpt = [
            MappedOption::OPT_TYPE          => 'boolean',
        ];
        /** @var MappedOption $option */
        $option = MappedOption::createFromConfig('test-bool-opt', $boolOpt);
        $inputOption = $option->getOption();
        self::assertEquals('test-bool-opt', $inputOption->getName());
        self::assertEquals(null, $inputOption->getShortcut());
        self::assertEquals(null, $option->getDescription());
        self::assertEquals(null, $inputOption->getDefault());
        self::assertFalse($inputOption->isValueRequired());
        self::assertFalse($inputOption->isArray());

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
        self::assertEquals("Unknown option type 'foo' for option 'test-bad-opt'", $msg);

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
        self::assertEquals($error, $msg);

        // test 6 : no type at all
        $badOpt = [];
        $msg = '';
        try {
            $option = MappedOption::createFromConfig('test-bad-opt', $badOpt);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals("Missing option type for test-bad-opt", $msg);
    }
}
