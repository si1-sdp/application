<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\InputOptionsInjector;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\Commands\HelloWorldCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use League\Container\Container;
use Mockery;
use Mockery\Mock;
use ReflectionClass;
use stdClass;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\SymfonyApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfigLoader
 * @uses DgfipSI1\Application\Config\InputOptionsSetter
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses DgfipSI1\Application\Config\InputOptionsInjector
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\MakePharCommand
 * @uses DgfipSI1\Application\Command
 */
class InputOptionsInjectorTest extends LogTestCase
{
    /** @var ReflectionClass $class */
    protected $class;
    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $this->class = new ReflectionClass(InputOptionsInjector::class);
    }
    /**
     *
     * @inheritDoc
     */
    public function tearDown(): void
    {
        \Mockery::close();
    }
    /**
     * test subscribedEvents
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::getSubscribedEvents
     *
     * @return void
     */
    public function testGetSubscribedEvents()
    {
        $events = InputOptionsInjector::getSubscribedEvents();
        self::assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        foreach ($events as $handler) {
            /** @var string $method */
            $method = $handler[0];
            self::assertTrue(method_exists(InputOptionsInjector::class, $method));
            self::assertEquals(0, $handler[1] % 10);         // priority should be a multiple of 10
        }
    }
    /**
     * test handleCommandEvent without command
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::handleCommandEvent
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHandleCommandEventNoCommand()
    {
        $argv     = ['./test', 'test'];
        $input    = new ArgvInput($argv);
        $injector = $this->createInjector($argv, true);

        $event    = $this->createEvent($argv, createCommand: false);
        $injector->shouldReceive('manageGlobalOptions')->once()->with($event->getInput()); /** @phpstan-ignore-line */
        $injector->shouldNotReceive('manageCommandOptions');                               /** @phpstan-ignore-line */
        $injector->shouldReceive('manageDefineOption')->once()->with($event);              /** @phpstan-ignore-line */
        $injector->handleCommandEvent($event);
        $this->assertNoMoreProdMessages();

        $method = (new ReflectionClass(InputOptionsInjector::class))->getMethod('handleCommandEvent');
        self::assertTrue($method->isPublic());
    }

    /**
     * test handleCommandEvent
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::handleCommandEvent
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHandleCommandEvent()
    {
        $argv     = ['./test', 'test'];
        $input    = new ArgvInput($argv);
        $injector = $this->createInjector($argv, true);

        $event    = $this->createEvent($argv);
        /** @var \Mockery\MockInterface $injector */
        $injector->shouldReceive('buildDefaultlessInput')->once();
        $injector->shouldReceive('manageGlobalOptions')->once()->with($event->getInput());
        $injector->shouldReceive('manageCommandOptions')->once()->with($event->getInput(), $event->getCommand());
        $injector->shouldReceive('manageDefineOption')->once()->with($event);

        /** @var InputOptionsInjector $injector */
        $injector->handleCommandEvent($event);
        $this->assertNoMoreProdMessages();
    }
    /**
     * @return array<string,mixed>
     */
    public function dataToString(): array
    {
        $obj1 = new stdClass();
        $obj1->name = 'obj1';

        return [
            'null           ' => [ null              , 'null'                           ],
            'false          ' => [ false             , 'false'                          ],
            'true           ' => [ true              , 'true'                           ],
            'empty_array    ' => [ []                , '[]'                             ],
            'seq_array      ' => [ ['one', 'two' ]   , '[one, two]'                     ],
            'seq_obj_array  ' => [ [$obj1        ]   , "[".print_r($obj1, true)."]"     ],
            'assoc_array    ' => [ ['one' => 'two' ] , print_r(['one' => 'two' ], true) ],
        ];
    }

    /**
     * test syncInputWithConfig
     *
     * @param mixed  $data
     * @param string $expect
     *
     * @return void
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::toString
     *
     * @dataProvider dataToString
     */
    public function testToString($data, $expect)
    {
        self::assertEquals($expect, InputOptionsInjector::toString($data));
    }
    /**
     * test manageCommandOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::manageCommandOptions
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testManageCommandOptions()
    {
        $argv     = ['./test', 'test'];
        $input    = new ArgvInput($argv);
        /** @var \Mockery\MockInterface $injector */
        $injector = $this->createInjector($argv, true);
        $opt = (new MappedOption('test-opt', OptionType::Boolean))->setCommand('hello-world');
        $injector->shouldReceive('syncInputWithConfig')->once()->with($input, $opt, 'hello-world');


        $command = new HelloWorldCommand();

        /** @var InputOptionsSetter $injector */
        $injector->getContainer()->addShared('hello-world', $command);
        $command->getDefinition()->addOption($opt->getOption());
        $injector->getConfiguredApplication()->addMappedOption($opt);

        $injector->manageCommandOptions($input, $command);                          /** @phpstan-ignore-line */
        $ctx = ['name' => 'manageCommand hello-world options'];
        $this->assertDebugInContextLog('Synchronizing config and inputOptions', $ctx);
    }
    /**
     * test manageCommandOptions with no command
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::manageCommandOptions
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testManageCommandOptionsNoCommand()
    {
        $argv     = ['./test', 'test'];
        $input    = new ArgvInput($argv);
        $injector = $this->createInjector($argv, true);

        $command = new HelloWorldCommand();

        $injector->shouldNotReceive('syncInputWithConfig');                       /** @phpstan-ignore-line */
        $injector->manageCommandOptions($input, $command);                        /** @phpstan-ignore-line */
        $this->assertDebugInLog('Skipping hello-world (not in container)');
        $this->assertLogEmpty();
    }
    /**
     * test manageGlobalOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::manageGlobalOptions
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testManageGlobalOptions()
    {
        $argv     = ['./test', 'test'];
        $input    = new ArgvInput($argv);
        $injector = $this->createInjector($argv, true);

        // $method = $this->class->getMethod('manageGlobalOptions');
        // $method->setAccessible(true);

        $opt = new MappedOption('test-opt', OptionType::Boolean);
        $injector->getConfiguredApplication()->getDefinition()->addOption($opt->getOption());
        $injector->getConfiguredApplication()->addMappedOption($opt);
        /** @phpstan-ignore-next-line */
        $injector->shouldReceive('syncInputWithConfig')->once()->with($input, $opt);
        /** @phpstan-ignore-next-line */
        $injector->manageGlobalOptions($input);
        $this->assertDebugInContextLog('Synchronizing config and inputOptions', ['name' => 'manageGlobalOptions']);
    }
    /**
     * test manageGlobalOptions - with no application
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::manageGlobalOptions
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testManageGlobalOptionsNoApp()
    {
        $argv     = ['./test', 'test'];
        $input    = new ArgvInput($argv);
        $injector = $this->createInjector($argv, true, false);

        $injector->shouldNotReceive('syncInputWithConfig');                /** @phpstan-ignore-line */
        $injector->manageGlobalOptions($input);                            /** @phpstan-ignore-line */
        $this->assertDebugInLog('Skipping : no application');
    }

    /**
     * @return array<string,mixed>
     */
    public function dataSyncInput(): array
    {
                    //                                empty
        $variables = [   // name     type,        short    value    value1    value2
            'boolean' => [ 'bool'  , 'boolean'  , 'B'    , null   , true   , false      ],
            'Array-A' => [ 'arry'  , 'array'    , 'A'    , []     , [1, 2]  , [3, 4]      ],
            'ScalarS' => [ 'scal'  , 'scalar'   , 'S'    , ''     , 'foo'  , 'bar'      ],
            'Argumnt' => [ 'argt'  , 'argument' , true   , ''     , 'foo'  , 'bar'      ],
        ];
        $data = [];
        foreach ($variables as $iName => $item) {
            $name  = $item[0];
            $type  = $item[1];
            if ('argument' === $type) {
                $short = $item[2];
                $required = null;
            } else {
                $short = null;
                $required = $item[2];
            }
            $emptyValue = $item[3];
            $v1 = $item[4];
            $v2 = $item[5];
            foreach ([true, false] as $useGlobal) {
                $indexName = $iName.($useGlobal ? '-Glo' : '-Cmd');
                $values = [ $v1, $v2, $emptyValue];
                if (null !== $emptyValue) {
                    $values[] = null;
                }
                foreach ($values as $default) {
                    if ([] === $default) {   // skip [] default, which is same as null
                        continue;
                    }
                    $ID = "$indexName:D".InputOptionsInjector::toString($default);
                    foreach ($values as $config) {
                        $IC = "$ID:C".InputOptionsInjector::toString($config);
                        foreach ($values as $option) {
                            if ([] === $option) {   // skip [] in input, which is same as null
                                continue;
                            }
                            $IO = str_replace([' '], '', "$IC:O".InputOptionsInjector::toString($option));
                            $data[$IO] = [$name, $type, $short, $useGlobal, $default, $config, $option];
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::buildDefaultlessInput
     *
     * @return void
     */
    public function testbuildDefaultlessInput()
    {
        // create injector
        $injector = $this->createInjector();
        $method = $this->class->getMethod('buildDefaultlessInput');
        $method->setAccessible(true);
        $result = $this->class->getProperty('defaultlessInput');
        $result->setAccessible(true);
        /** @var ApplicationInterface $app */
        $app = $injector->getContainer()->get('application');

        /** @var MappedOption $arg */
        $arg = new MappedOption('test-arg', OptionType::Argument, default: 'arg-default');
        $app->addMappedOption($arg);
        $app->getDefinition()->addArgument($arg->getArgument());

        /** @var MappedOption $option */
        $option = new MappedOption('test-opt', OptionType::Scalar, default: 'opt-default');
        $app->addMappedOption($option);
        $app->getDefinition()->addOption($option->getOption());


        //$input = new ArgvInput(['./test', 'command', 'test-arg-value', '--test-opt', 'test-value']);
        $input = new ArgvInput(['./test', 'command', '--help', '--test-opt', 'test' ]);
        InputOptionsSetter::safeBind($input, $app->getDefinition());

        self::assertNull($result->getValue($injector));
        $method->invokeArgs($injector, [$input, 'hello']);
        /** @var ArgvInput $inputResult */
        $inputResult = $result->getValue($injector);
        self::assertEquals('test', $inputResult->getOption('test-opt'));
        self::assertEquals(null, $inputResult->getArgument('test-arg'));
    }
    /**
     * test syncInputWithConfig
     *
     * @param string      $name
     * @param string      $type
     * @param string|null $short
     * @param bool        $useGlobal
     * @param mixed       $default
     * @param mixed       $confValue
     * @param mixed       $optValue
     *
     * @return void
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::syncInputWithConfig
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::setValueFromDefault
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::setValueFromConfig
     *
     * @dataProvider dataSyncInput
     */
    public function testSyncInputWithConfig($name, $type, $short, $useGlobal, $default, $confValue, $optValue)
    {
        // create injector
        $injector = $this->createInjector();
        $method = $this->class->getMethod('syncInputWithConfig');
        $method->setAccessible(true);
        $bi = $this->class->getMethod('buildDefaultlessInput');
        $bi->setAccessible(true);
        $definition = $injector->getConfiguredApplication()->getDefinition();
        //
        // CREATE CORRESPONDING MAPPED OPTION
        //
        $options = [
            MappedOption::OPT_SHORT          => $short,
            MappedOption::OPT_DESCRIPTION    => "Description for $type option '$name'",
            MappedOption::OPT_TYPE           => $type,
            MappedOption::OPT_DEFAULT_VALUE  => $default,
        ];
        /** @var MappedOption $option */
        $option = MappedOption::createFromConfig($name, $options);
        if ('argument' === $type) {
            $definition->addArgument($option->getArgument());
        } else {
            $definition->addOption($option->getOption());
        }
        /** @var ApplicationInterface $app */
        $app = $injector->getContainer()->get('application');
        $app->addMappedOption($option);
        //
        // SET CONFIG VALUE
        //
        if ($useGlobal) {
            $prefix = 'options';
        } else {
            $prefix = 'commands.hello.options';
        }
        /** @var ConfigHelper $config */
        $config = $injector->getConfig();
        $key = $prefix.'.'.str_replace(['.', '-' ], '_', $name);
        $config->set($key, $confValue);
        //
        // CREATE COMMAND LINE ARGUMENTS FOR OPTION
        //
        $argv = $this->getCommandArgvFromValue($type, $name, $short, $optValue);
        $input = new ArgvInput($argv);
        $input->bind($definition);
        $bi->invokeArgs($injector, [$input, 'hello']);
        //
        // CALL TESTED METHOD
        //
        if ($useGlobal) {
            $method->invokeArgs($injector, [$input, $option]);
            $caller = 'manageGlobalOptions';
        } else {
            $method->invokeArgs($injector, [$input, $option, 'hello']);
            $caller = "manage hello options";
        }
        //
        // DETERMINE EXPECTED VALUE
        //
        $expect = null;
        if (null !== $optValue) {
            $expect = $optValue;
            $this->assertDebugInContextLog('CONFIG->SET', ['input' => InputOptionsInjector::toString($optValue)]);
        } elseif (null !== $confValue) {
            $expect = $confValue;
            $this->assertDebugInContextLog('INPUT->SET', ['conf' => InputOptionsInjector::toString($confValue)]);
        } elseif (null !== $default) {
            $expect = $default;
            $this->assertDebugInContextLog('CONFIG->SET', ['value' => InputOptionsInjector::toString($expect)]);
            $this->assertDebugInContextLog('INPUT->SET', ['value' => InputOptionsInjector::toString($expect)]);
        }
        if (null === $expect && 'array' === $type) {
            $expect = [];
        }
        $ctx = [
            'name' => $caller,
            'key' => $key,
            'input' => InputOptionsInjector::toString($optValue),
            'conf' => InputOptionsInjector::toString($confValue),
        ];
        // print "\n";
        // print "(T)WANT DEFAULT : ".InputOptionsInjector::toString($default)."\n";
        // print "(T)WANT CONFIG  : ".InputOptionsInjector::toString($confValue)."\n";
        // print "(T)WANT INPUT   : ".InputOptionsInjector::toString($optValue)."\n";
        // print "(T)==> ARGV     : ".implode(" ", $argv)."\n";
        // print_r($ctx);
        // $this->showLogs();

        self::assertEquals($expect, $config->get($key));
        if ('argument' === $type) {
            self::assertEquals($expect, $input->getArgument($name));
        } else {
            self::assertEquals($expect, $input->getOption($name));
        }
        $this->assertDebugInContextLog('INPUT', $ctx);
    }
    /**
     * test manageDefineOptions
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::manageDefineOption
     *
     * @return void
     */
    public function testManageDefineOption()
    {
        $injector = $this->createInjector();
        $method = $this->class->getMethod('manageDefineOption');
        $method->setAccessible(true);
        /** @var ConfigHelper $config */
        $config = $injector->getConfig();

        // test with multi arguments
        $event = $this->createEvent(['./test', '-D', 'options.test_opt=bar', '-D', 'options.test2=value2'], true);
        $method->invokeArgs($injector, [$event]);
        self::assertEquals('bar', $config->get('options.test_opt'));
        self::assertEquals('value2', $config->get('options.test2'));

        // test with one argument
        $event = $this->createEvent(['./test', '-D', 'options.test_opt=bar'], true);
        $config->set('options', []);
        $method->invokeArgs($injector, [$event]);
        self::assertEquals('bar', $config->get('options.test_opt'));
        self::assertEquals(null, $config->get('options.test2'));

        // test with no argument
        $event = $this->createEvent(['./test', '-D', 'options.test_opt=bar'], false);
        $config->set('options', []);
        $method->invokeArgs($injector, [$event]);
        self::assertEquals(null, $config->get('options.test_opt'));
    }
    /**
     * test splitConfigKeyValue
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::splitConfigKeyValue
     *
     * @return void
     */
    public function testSplitConfigKeyValue()
    {
        $injector = $this->createInjector();
        $method = $this->class->getMethod('splitConfigKeyValue');
        $method->setAccessible(true);

        $res = $method->invokeArgs($injector, ['foo=bar']);
        self::assertEquals(['foo', 'bar', true], $res);
        $res = $method->invokeArgs($injector, ['foo']);
        self::assertEquals(['foo', true], $res);
        $res = $method->invokeArgs($injector, ['foo=bar=3']);
        self::assertEquals(['foo', 'bar=3', true], $res);
    }
    /**
     * builds argv needed to set given option
     *
     * @param string      $type
     * @param string      $name
     * @param string|null $short
     * @param mixed       $optValue
     *
     * @return array<string>
     */
    protected function getCommandArgvFromValue($type, $name, $short, $optValue)
    {
        $argv = [ "./test", "command" ];
        if (null !== $optValue) {
            // determine option name to uses ie : -short, --long or --no-long
            if (null === $short || ('boolean' === $type && false === $optValue)) {
                if ('boolean' === $type && false === $optValue) {
                    $optName = "--no-$name";
                } else {
                    $optName = "--$name";
                }
            } else {
                $optName = "-$short";
            }
            switch ($type) {
                case 'boolean':
                    $argv[] = $optName;
                    break;
                case 'scalar':
                    /** @var string $optValue */
                    array_push($argv, "$optName", "$optValue");
                    break;
                case 'argument':
                    /** @var string $optValue */
                    $argv[] = "$optValue";
                    break;
                case 'array':
                    /** @var array<string> $optValue */
                    foreach ($optValue as $value) {
                        array_push($argv, "$optName", "$value");
                    }
                    break;
            }
        }

        return $argv;
    }
    /**
     * creates a fully equiped loader
     *
     * @param array<string> $argv
     * @param bool          $createMock
     * @param bool          $createApp
     *
     * @return InputOptionsInjector;
     */
    protected function createInjector($argv = [], $createMock = false, $createApp = true)
    {
        $argv = [ './testapp' ] + $argv;
        $this->logger = new TestLogger();
        if (true === $createMock) {
            /** @var Mock $mock */
            $mock = Mockery::mock(InputOptionsInjector::class);
            $mock->shouldAllowMockingProtectedMethods();
            $mock->makePartial();
            $injector = $mock;
        } else {
            $injector = new InputOptionsInjector();
        }
        /** @var InputOptionsInjector $injector */
        $injector->setLogger($this->logger);
        $config = new ConfigHelper();
        $injector->setConfig($config);
        $injector->setContainer(new Container());
        if (true === $createApp) {
            $app = new SymfonyApplication(new ClassLoader(), $argv);
            $injector->getContainer()->addShared('application', $app);
        }

        return $injector;
    }
    /**
     * creates an Event
     *
     * @param array<string> $argv
     * @param bool          $addDefineOpt
     * @param bool          $createCommand
     *
     * @return ConsoleCommandEvent;
     */
    protected function createEvent($argv, $addDefineOpt = false, $createCommand = true)
    {
        $input = new ArgvInput($argv);
        if ($addDefineOpt) {
            $definition = new InputDefinition();
            $definition->addOption(new InputOption(
                '--define',
                '-D',
                InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED,
                'define config key value (example: -Doptions.user=foo)'
            ));
            try {
                $input->bind($definition);
            } catch (\Exception $e) {
                // Errors must be ignored, full binding/validation happens later
            }
        }
        if ($createCommand) {
            $cmd   = new ApplicationCommand('hello-world');
            $event = new ConsoleCommandEvent($cmd, $input, new NullOutput());
        } else {
            $event = new ConsoleCommandEvent(null, $input, new NullOutput());
        }

        return $event;
    }
}
