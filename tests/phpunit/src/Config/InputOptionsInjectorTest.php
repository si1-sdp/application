<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\InputOptionsInjector;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\Commands\HelloWorldCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use Mockery;
use Mockery\Mock;
use ReflectionClass;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\SymfonyApplication
 * @uses DgfipSI1\Application\ApplicationLogger
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfigLoader
 * @uses DgfipSI1\Application\Config\InputOptionsSetter
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\Contracts\LoggerAwareTrait
 * @uses DgfipSI1\Application\Contracts\AppAwareTrait
 * @uses DgfipSI1\Application\Config\InputOptionsInjector
 *
 *
 */
class InputOptionsInjectorTest extends LogTestCase
{
    /** @var ReflectionClass $class */
    protected $class;
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
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
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
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

        $injector->shouldReceive('manageGlobalOptions')->once()->with($event->getInput()); /** @phpstan-ignore-line */
        /** @phpstan-ignore-next-line */
        $injector->shouldReceive('manageCommandOptions')->once()->with($event->getInput(), $event->getCommand());
        $injector->shouldReceive('manageDefineOption')->once()->with($event);              /** @phpstan-ignore-line */
        $injector->handleCommandEvent($event);
        $this->assertNoMoreProdMessages();
    }
    /**
     * @return array<string,mixed>
     */
    public function dataToString(): array
    {
        $data = [
            'null       ' => [ null              , 'null'                     ],
            'false      ' => [ false             , 'false'                    ],
            'true       ' => [ true              , 'true'                     ],
            'empty_array' => [ []                , '[]'                       ],
            'seq_array  ' => [ ['one', 'two' ]   , '[one, two]'               ],
            'other      ' => [ ['one' => 'two' ] , print_r(['one' => 'two' ], true) ],
        ];

        return $data;
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
        $this->assertEquals($expect, InputOptionsInjector::toString($data));
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
        $injector = $this->createInjector($argv, true);

        $command = new HelloWorldCommand();
        $injector->getApplication()->getContainer()->addShared('hello', $command);
        $opt = new InputOption('foo');
        $command->getDefinition()->addOption($opt);
        $prefix = 'commands.hello.options';
        $caller = 'manageCommand hello options';
        /** @phpstan-ignore-next-line */
        $injector->shouldReceive('syncInputWithConfig')->once()->with($input, $opt, $prefix, $caller);

        $injector->manageCommandOptions($input, $command);                          /** @phpstan-ignore-line */
        $this->assertDebugInLog('Synchronizing config and inputOptions');
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
        $this->assertDebugInLog('Skipping hello (not in container)');
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

        $options = $injector->getApplication()->getDefinition()->getOptions();
        $caller = 'manageGlobalOptions';
        foreach ($options as $opt) {
            /** @phpstan-ignore-next-line */
            $injector->shouldReceive('syncInputWithConfig')->once()->with($input, $opt, 'options', $caller);
        }
        $injector->manageGlobalOptions($input);                            /** @phpstan-ignore-line */
        $this->assertDebugInLog('Synchronizing config and inputOptions');
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
        $variables = [
            'true   ' => [ 'type' => 'boolean', 'short' => 'B', 'value' => true          ],
            'false  ' => [ 'type' => 'boolean', 'short' => 'B', 'value' => false         ],
            'integer' => [ 'type' => 'scalar' , 'short' => 's', 'value' => '99'          ],
            'string ' => [ 'type' => 'scalar' , 'short' => 's', 'value' => 'foobar'      ],
            'array12' => [ 'type' => 'array'  , 'short' => 'A', 'value' => ['one', 'two']],
            'array1 ' => [ 'type' => 'array'  , 'short' => 'A', 'value' => ['one']       ],
        ];
        $data = [];
        foreach ($variables as $name => $item) {
            foreach ([$item['value'], null] as $default) {
                foreach ([$item['value'], null] as $conf) {
                    foreach ([$item['value'], null] as $opt) {
                        $test = "$name:".(null !== $default ? 'D' : '-').':'.
                            (null !== $conf ? 'C' : '-').':'.(null !== $opt ? 'O' : '-');
                        $data[$test] = [ $item['type'], $name, $item['short'], $default, $conf, $opt ];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * test syncInputWithConfig
     *
     * @param string $type
     * @param string $name
     * @param string $short
     * @param mixed  $default
     * @param mixed  $confValue
     * @param mixed  $optValue
     *
     * @return void
     *
     * @covers \DgfipSI1\Application\Config\InputOptionsInjector::syncInputWithConfig
     *
     * @dataProvider dataSyncInput
     */
    public function testSyncInputWithConfig($type, $name, $short, $default, $confValue, $optValue)
    {
        $prefix = 'options';
        // create injector
        $injector = $this->createInjector();
        $method = $this->class->getMethod('syncInputWithConfig');
        $method->setAccessible(true);

        // create an input option
        $ios = new InputOptionsSetter();
        $iosClass = new ReflectionClass(InputOptionsSetter::class);
        $iosOpt = $iosClass->getMethod('optionFromConfig');
        $iosOpt->setAccessible(true);
        $options = [
            CONF::OPT_SHORT          => $short,
            CONF::OPT_DESCRIPTION    => "Description for $type option '$name'",
            CONF::OPT_TYPE           => $type,
        ];
        if ('boolean' !== $type) {
            $options[CONF::OPT_DEFAULT_VALUE] = $default;
        }
        /** @var InputOption $inputOption */
        $inputOption = $iosOpt->invokeArgs($ios, [ $name, $options]);
        $injector->getApplication()->getDefinition()->addOption($inputOption);

        /** @var ConfigHelper $config */
        $config = $injector->getConfig();
        $key = $prefix.'.'.str_replace(['.', '-' ], '_', $name);
        $config->set($key, $confValue);
        $tests = [];
        if (null !== $optValue) {
            foreach (['short', 'long'] as $optType) {
                // create argv
                $args = [];
                if ('short' === $optType) {
                    if ('boolean' === $type && false === $optValue) {
                        continue;
                    }
                    $optName = "-$short";
                } else {
                    if ('boolean' === $type && false === $optValue) {
                        $optName = "--no-$name";
                    } else {
                        $optName = "--$name";
                    }
                }
                if (null !== $optValue) {
                    switch ($type) {
                        case 'boolean':
                            $args = [ $optName ];
                            break;
                        case 'scalar':
                            /** @var string $optValue */
                            $args = [ "$optName", "$optValue" ];
                            break;
                        case 'array':
                            /** @var array<string> $optValue */
                            foreach ($optValue as $value) {
                                $args[] = "$optName";
                                $args[] = "$value";
                            }
                            break;
                    }
                }
                $args = array_merge([ "./test", "command" ], $args);
                $tests[] = $args;
            }
        } else {
            $tests[] = [ "./test", "command" ];
        }
        foreach ($tests as $args) {
            $input = new ArgvInput($args);
            $input->bind($injector->getApplication()->getDefinition());
            // populate config

            $method->invokeArgs($injector, [$input, $inputOption, $prefix, 'test']);
            $expect = null;
            if (null !== $optValue) {
                $expect = $optValue;
            } elseif (null !== $confValue) {
                $expect = $confValue;
            } elseif (null !== $default && 'boolean' !== $type) {
                $expect = $default;
            }
            if (null === $expect && 'array' === $type) {
                $expect = [];
            }
            $this->assertEquals($expect, $config->get($key));
            $this->assertEquals($expect, $input->getOption($name));
        }
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
        $this->assertEquals('bar', $config->get('options.test_opt'));
        $this->assertEquals('value2', $config->get('options.test2'));

        // test with one argument
        $event = $this->createEvent(['./test', '-D', 'options.test_opt=bar'], true);
        $config->set('options', []);
        $method->invokeArgs($injector, [$event]);
        $this->assertEquals('bar', $config->get('options.test_opt'));
        $this->assertEquals(null, $config->get('options.test2'));

        // test with no argument
        $event = $this->createEvent(['./test', '-D', 'options.test_opt=bar'], false);
        $config->set('options', []);
        $method->invokeArgs($injector, [$event]);
        $this->assertEquals(null, $config->get('options.test_opt'));
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
        $this->assertEquals(['foo', 'bar', true], $res);
        $res = $method->invokeArgs($injector, ['foo']);
        $this->assertEquals(['foo', true], $res);
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
        if (true === $createApp) {
            $app = new SymfonyApplication(new ClassLoader(), $argv);
            $injector->setApplication($app);
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
            $cmd   = new ApplicationCommand('hello');
            $event = new ConsoleCommandEvent($cmd, $input, new NullOutput());
        } else {
            $event = new ConsoleCommandEvent(null, $input, new NullOutput());
        }

        return $event;
    }
}