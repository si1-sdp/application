<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Console\Input\InputArgument;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\testLogger\LogTestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

/**
 *
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Config\InputOptionsSetter::safeBind
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
     * data provider for option constructors
     *
     * @return array<string,array<mixed>>
     */
    public function optionsData()
    {
                    //                                   expected    set     requi-
        return [         //  name      type       short  default     default red    exception regexp
            'array    ' => [ 'opt_a', 'array'   , null , []        , false , null , null                         ],
            'array-A  ' => [ 'opt-a', 'array'   , 'A'  , ['1', '2'], true  , null , null                         ],
            'array_Err' => [ 'opt_a', 'array'   , null , 'foo'     , true  , null , 'default value for an array' ],
            'scalar   ' => [ 'opt_s', 'scalar'  , null , null      , false , null , null                         ],
            'scalar-S ' => [ 'opt-s', 'scalar'  , 'S'  , '1'       , true  , null , null                         ],
            'boolean  ' => [ 'opt.b', 'boolean' , null , false     , false , null , null                         ],
            'boolean-t' => [ 'opt-b', 'boolean' , 'B'  , true      , true  , null , null                         ],
            'boolean_f' => [ 'opt-b', 'boolean' , 'B'  , false     , true  , null , null                         ],
            // Simple argument with no default
            'arg      ' => [ 'arg'  , 'argument', null , null      , false , null , null                         ],
            // Simple argument : required is true, so setting default should trigger an error
            'arg-err  ' => [ 'arg-a', 'argument', null , 'foo'     , true  , true , 'Cannot set a default value' ],
            // Simple argument, not required with a default value
            'arg-foo  ' => [ 'arg_a', 'argument', null , 'foo'     , true  , false , null                         ],
            // Simple argument array with no default
            'argarr   ' => [ 'arg'  , 'arg-array', null , []       , false , null , null                         ],
            // Simple argument : required is true, so setting default should trigger an error
            'argarr-err' => [ 'arg-a', 'arg-array', null , ['A', 'B'], true  , true , 'Cannot set a default value' ],
            // Simple argument, not required with a default value
            'argarr-AB' => [ 'arg_a', 'arg-array', null , ['A', 'B'], true  , false , null                         ],
            //
            // Errors
            //
            'badType  ' => [ 'error', 'error'   , null , 'foo'     , true  , false , 'Unknown option type'        ],
            'noType   ' => [ 'error', null      , null , null      , true  , false , 'Missing option type'        ],
            'bad-short' => [ 'error', 'scalar'  , 'AA' , null      , false , null , null                         ],

        ];
    }
    /**
     * test setInputOptions
     * @dataProvider optionsData
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::__construct
     * @covers \DgfipSI1\Application\Config\MappedOption::getName
     * @covers \DgfipSI1\Application\Config\MappedOption::getMode
     * @covers \DgfipSI1\Application\Config\MappedOption::getDefaultValue
     * @covers \DgfipSI1\Application\Config\MappedOption::getDescription
     * @covers \DgfipSI1\Application\Config\MappedOption::isArray
     * @covers \DgfipSI1\Application\Config\MappedOption::isBool
     * @covers \DgfipSI1\Application\Config\MappedOption::isScalar
     * @covers \DgfipSI1\Application\Config\MappedOption::isArgument
     * @covers \DgfipSI1\Application\Config\OptionType
     *
     * @param string      $name
     * @param string|null $optType
     * @param string|null $short
     * @param mixed       $expDefault
     * @param bool        $setDefault
     * @param bool|null   $required
     * @param string|null $exception
     *
     * @return void
     */
    public function testConstructor($name, $optType, $short, $expDefault, $setDefault, $required, $exception)
    {
        $option = null;
        $msg = '';
        try {
            $type = OptionType::from((string) $optType);
        } catch (\ValueError $e) {
            self::assertTrue(true);

            return;   // bad type errors not tested here as we use enum in constructor
        }
        $args = [ $name, $type];
        // to simplify, we set description when short is specified otherwise set to ''
        $desc = '';
        if (null !== $short) {
            $desc = "Description for $name";
        }
        if (null !== $required) {
            $args[] = $desc;
            $args[] = $short;
            $args[] = $setDefault ? $expDefault : null;
            $args[] = $required;
        } elseif ($setDefault) {
            $args[] = $desc;
            $args[] = $short;
            $args[] = $expDefault;
        } elseif (null !== $short) {
            $args[] = $desc;
            $args[] = $short;
        }

        //print_r($args);
        $msg = '';
        try {
            $option = new MappedOption(...$args);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if (null !== $exception) {
            self::assertMatchesRegularExpression("/$exception/", $msg);

            return;
        }
        self::assertEquals('', $msg);
        self::assertNotNull($option);
        $this->validateOption($option, $type, $desc, $name, $short, $expDefault, $required);
    }
    /**
     * test optionFromConfig
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::createFromConfig
     *
     * @dataProvider optionsData
     *
     * @param string      $name
     * @param string|null $type
     * @param string|null $short
     * @param mixed       $expDefault
     * @param bool        $setDefault
     * @param bool|null   $required
     * @param string|null $exception
     *
     * @return void
     */
    public function testCreateFromConfig($name, $type, $short, $expDefault, $setDefault, $required, $exception)
    {
        $opts = [];
        $desc = '';
        $option = null;
        if (null !== $short) {
            $desc = "Description for $name";
            $opts[MappedOption::OPT_SHORT] = $short;
            $opts[MappedOption::OPT_DESCRIPTION] = $desc;
        }
        if (null !== $type) {
            $opts[MappedOption::OPT_TYPE] = $type;
        }
        if ($setDefault) {
            $opts[MappedOption::OPT_DEFAULT_VALUE] = $expDefault;
        }
        if (null !== $required) {
            $opts[MappedOption::OPT_REQUIRED] = $required;
        }
        $msg = '';
        try {
            $option = MappedOption::createFromConfig($name, $opts);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if (null !== $exception) {
            self::assertMatchesRegularExpression("/$exception/", $msg);

            return;
        }
        self::assertEquals('', $msg);
        self::assertNotNull($option);
        /** @var string $type */
        $this->validateOption($option, OptionType::from($type), $desc, $name, $short, $expDefault, $required);
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
        $ret = $opt->setCommand('foo');
        self::assertEquals('foo', $opt->getCommand());
        self::assertEquals($opt, $ret);
    }
    /**
     * data provider for getConfName tests
     *
     * @return array<string,array<mixed>>
     */
    public function confNameData()
    {
        return [
            'plainname'  => [ 'cmdname'        , 'cmdname'         ],
            'with_sep '  => [ 'cmd-name'       , 'cmd_name'        ],
            'with_grp '  => [ 'cmd.name'       , 'cmd_name'        ],
            'composed '  => [ 'group.cmd-name' , 'group_cmd_name'  ],
        ];
    }

    /**
     *  test constructor
     * @dataProvider confNameData
     *
     * @covers \DgfipSI1\Application\Config\MappedOption::getConfName
     *
     * @param string $name
     * @param string $expected
     *
     * @return void
     */
    public function testGetConfName($name, $expected): void
    {
        self::assertEquals($expected, MappedOption::getConfName($name));
    }
    /**
     * @covers DgfipSI1\Application\Config\OptionType::mode
     * Just test that default is false. rest is tested via mappedOption::constructor
     *
     * @return void
     */
    public function testInputOptionMode()
    {
        self::assertEquals(OptionType::Argument->mode(), OptionType::Argument->mode(false));
        self::assertNotEquals(OptionType::Argument->mode(), OptionType::Argument->mode(true));
    }
    /**
     * validate option object
     *
     * @param MappedOption $opt
     * @param OptionType   $type
     * @param string       $desc
     * @param string       $name
     * @param string|null  $short
     * @param mixed        $expDefault
     * @param bool|null    $required
     *
     * @return void
     */
    protected function validateOption($opt, $type, $desc, $name, $short, $expDefault, $required)
    {
        $inputOptName = str_replace('_', '-', $name);
        $confOptName  = MappedOption::getConfName($name);
        $comments = '';
        if ($opt->isBool()) {
            $comments = '.*default : '.(is_bool($expDefault) ? ( $expDefault ? 'true' : 'false' ) : 'false');
        }
        self::assertEquals($desc, $opt->getDescription());
        if ($opt->isArgument()) {
            $required ??= false;
            self::assertEquals($desc, $opt->getArgument()->getDescription());
            self::assertEquals($required, $opt->getArgument()->isRequired());
            self::assertEquals($expDefault, $opt->getArgument()->getDefault());
            self::assertEquals($inputOptName, $opt->getArgument()->getName());
        } else {
            // print "\n$comments\n";
            // print $opt->getArgument()->getDescription()."\n";
            self::assertMatchesRegularExpression("/$desc$comments/", $opt->getOption()->getDescription());
            self::assertEquals($short, $opt->getOption()->getShortcut());
            if ($opt->isBool()) {
                self::assertEquals(null, $opt->getOption()->getDefault());
            } else {
                self::assertEquals($expDefault, $opt->getOption()->getDefault());
            }
            self::assertEquals($inputOptName, $opt->getOption()->getName());
        }
        self::assertEquals($expDefault, $opt->getDefaultValue());
        self::assertEquals($confOptName, $opt->getName());
        $isArray = $isBool = $isScalar = $isArgument = false;
        switch ($type) {
            case OptionType::Array:
                $isArray = true;
                self::assertTrue($opt->getOption()->isArray());
                self::assertFalse($opt->getOption()->isNegatable());
                self::assertFalse($opt->getOption()->isValueOptional());
                self::assertTrue($opt->getOption()->isValueRequired());
                break;
            case OptionType::Boolean:
                $isBool = true;
                self::assertFalse($opt->getOption()->isArray());
                self::assertTrue($opt->getOption()->isNegatable());
                self::assertFalse($opt->getOption()->isValueOptional());
                self::assertFalse($opt->getOption()->isValueRequired());
                break;
            case OptionType::Scalar:
                $isScalar = true;
                self::assertFalse($opt->getOption()->isArray());
                self::assertFalse($opt->getOption()->isNegatable());
                self::assertFalse($opt->getOption()->isValueOptional());
                self::assertTrue($opt->getOption()->isValueRequired());
                break;
            case OptionType::Argument:
                $isArgument = true;
                self::assertEquals($required, $opt->getArgument()->isRequired());
                self::assertFalse($opt->getArgument()->isArray());
                break;
            case OptionType::ArgArray:
                $isArray = true;
                $isArgument = true;
                self::assertEquals($required, $opt->getArgument()->isRequired());
                self::assertTrue($opt->getArgument()->isArray());
                break;
        }
        self::assertEquals($type->mode(), $opt->getMode());
        self::assertEquals($isArray, $opt->isArray());
        self::assertEquals($isBool, $opt->isBool());
        self::assertEquals($isScalar, $opt->isScalar());
        self::assertEquals($isArgument, $opt->isArgument());
    }
}
