<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ExtraTests;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\ConfigLoader;
use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\InputOptionsInjector;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\Commands\HelloWorldCommand;
use DgfipSI1\ApplicationTests\TestClasses\TestOptions\ManyOptions1;
use DgfipSI1\ApplicationTests\TestClasses\TestOptions\ManyOptions2;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use ReflectionClass;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Command
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfigLoader
 * @uses DgfipSI1\Application\Config\ConfiguredApplicationTrait
 * @uses DgfipSI1\Application\Config\InputOptionsInjector
 * @uses DgfipSI1\Application\Config\InputOptionsSetter
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\SymfonyApplication
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses DgfipSI1\Application\Utils\DiscovererDefinition
 * @uses DgfipSI1\Application\Utils\MakePharCommand
 *
 * @coversNothing
 */
class CommandLineOptionsTest extends LogTestCase
{
    /** @var ReflectionClass $class */
    protected $class;
    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
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
     * @return array<string,mixed>
     */
    public function dataCommands()
    {
        $testClasses = [ ManyOptions1::class, ManyOptions2::class];
        $alltests = [];
        foreach ($testClasses as $class) {
            $alltests = array_merge($alltests, $this->generateTests($class, $testClasses));
        }

        return $alltests;
    }
    /**
     * test manageCommandOptions
     *
     * covers \DgfipSI1\Application\Config\InputOptionsInjector::manageCommandOptions
     *
     * @dataProvider dataCommands
     *
     * @param array<string>|null   $args
     * @param array<string,string> $expect
     * @param array<string>        $exclude classes to exclude from discovery
     *
     * @return void
     */
    public function testManageCommandOptions($args, $expect, $exclude)
    {
        if (null === $args) {
            $argString = './tests many-options';
        } else {
            $argString = './tests many-options '.implode(' ', $args);
        }
// print "\nARGS : $argString\n";
// print_r($expect);

        /** @var array<string> $argv */
        $argv = preg_split('/ +/', $argString);
        // BUILD NEW APPLICATION
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], $argv);
        $this->logger = new TestLogger();
        $app->setLogger($this->logger);
        $cc = (new ReflectionClass($app::class))->getMethod('configureContainer');
        $cc->setAccessible(true);
        $cc->invoke($app);

        // DISCOVER AND REGISTER COMMAND CLASSES
        $appClass = ConfiguredApplicationInterface::class;
        array_push($exclude, Command::class);
        $app->addDiscoveries('TestClasses\\Commands', SymfonyApplication::COMMAND_TAG, Command::class, [], 'name');
        $app->addDiscoveries('TestClasses\\TestOptions', SymfonyApplication::GLOBAL_CONFIG_TAG, $appClass, $exclude);
        $this->logger->reset();
        $app->discoverClasses();
        //$this->showInterpolatedLogs();
        $app->registerCommands();
        /** @var Command $command */
        $command = $app->getContainer()->get(HelloWorldCommand::class);

        // SETUP AND BIND INPUT
        /** @var ArgvInput $input */
        $input = $app->getContainer()->get('input');
        $event = new ConsoleCommandEvent($command, $input, new NullOutput());

        // REGISTER OPTIONS AND SCHEMAS
        /** @var InputOptionsSetter $optionsSetter */
        $optionsSetter = $app->getContainer()->get('input_options_setter');
        $optionsSetter->setInputOptions(new ConfigHelper());
        /** @var ConfigLoader $configLoader */
        $configLoader = $app->getContainer()->get('configuration_loader');
        $configLoader->loadConfiguration($event);
        /** @var InputOptionsInjector $injector */
        $injector = $app->getContainer()->get('input_options_injector');
        $input->bind($app->getDefinition());
        $this->logger->reset();
        $injector->handleCommandEvent($event);
        foreach ($expect as $name => $value) {
            self::assertEquals($value, $app->getOptionValue($name));
        }
    }
    /**
     * Generate all possible tests for a given global option class
     *
     * @param string        $class
     * @param array<string> $allClasses
     *
     * @return array<string,array<mixed>>
     */
    protected function generateTests($class, $allClasses)
    {
        $limit = $class::LIMIT;
        /** @var ConfiguredApplicationInterface $cai */
        $cai = new $class();
        $options = $cai->getConfigOptions();
        $prefix = (new ReflectionClass($cai::class))->getShortName().'-';
        $excludes = array_diff($allClasses, [$class]);
        $allTests = [];
        foreach ($options as $opt) {
            $unitTests = [];
            $default = $opt->getDefaultValue();
            if ($opt->isArgument()) {
                $name = $opt->getArgument()->getName();
                $required = $opt->getArgument()->isRequired();
                if ($opt->isArray()) {
                    if (!$required) {
                        $unitTests[] = [ 'args' => '', 'expect' => $default ??= null];
                    }
                    $unitTests[] = [ 'args' => 'Arg1 Arg2', 'expect' => ['Arg1', 'Arg2' ]];
                    $unitTests[] = [ 'args' => 'ArgA', 'expect' => ['ArgA'] ];
                } else {
                    if (!$required) {
                        $unitTests[] = [ 'args' => '', 'expect' => $default ??= null];
                    }
                    $unitTests[] = [ 'args' => 'argS', 'expect' => 'argS'];
                }
            } else {
                $name = $opt->getOption()->getName();
                $short = $opt->getOption()->getShortcut();
                if ($opt->isBool()) {
                    $unitTests[] = [ 'args' => '', 'expect' => $default ??= false];
                    $unitTests[] = [ 'args' => "--$name", 'expect' => true];
                    if (null !== $short) {
                        $unitTests[] = [ 'args' => "-$short", 'expect' => true];
                    }
                    $unitTests[] = [ 'args' => "--no-$name", 'expect' => false];
                } elseif ($opt->isScalar()) {
                    $unitTests[] = [ 'args' => '', 'expect' => $default ];
                    $unitTests[] = [ 'args' => "--$name scalarValue", 'expect' => 'scalarValue'];
                    if (null !== $short) {
                        $unitTests[] = [ 'args' => "-$short valueWithShort", 'expect' => 'valueWithShort'];
                    }
                } elseif ($opt->isArray()) {
                    $unitTests[] = [ 'args' => '', 'expect' => $default ];
                    $unitTests[] = [ 'args' => "--$name singleValue", 'expect' => [ 'singleValue' ]];
                    $unitTests[] = [ 'args' => "--$name Value1 --$name Value2", 'expect' => [ 'Value1', 'Value2' ]];
                    if (null !== $short) {
                        $unitTests[] = [ 'args' => "-$short A", 'expect' => ["A"]];
                        $unitTests[] = [ 'args' => "-$short A -$short B", 'expect' => ["A", "B"]];
                    }
                }
            }
            if (0 === sizeof($unitTests)) {
                continue;
            }
            if (0 === sizeof($allTests)) {
                foreach ($unitTests as $i => $ut) {
                    $testName = sprintf('%s%04d', $prefix, $i);
                    if ($ut['args'] === '') {
                        $allTests[$testName] = [ null, [ $name => $ut['expect']] ];
                    } else {
                        $allTests[$testName] = [ [ $ut['args']], [ $name => $ut['expect']] ];
                    }
                }
            } else {
                $stepTests = [];
                $index = 0;
                foreach ($allTests as $test) {
                    $args = $test[0];
                    $expectations = $test[1];
                    foreach ($unitTests as $ut) {
                        if ($ut['args'] === '') {
                            $allArgs = $args;
                        } elseif (null === $args) {
                            $allArgs = [$ut['args']];
                        } else {
                            $allArgs = array_merge($args, [$ut['args']]);
                        }
                        if (is_array($allArgs) && sizeof($allArgs) > $limit) {
                            continue;  // skip test with more than $limit
                        }
                        $allExpects = array_merge($expectations, [$name => $ut['expect']]);
                        $newTest = [ $allArgs, $allExpects, $excludes ];
                        $testName = sprintf('%s%04d', $prefix, ++$index);
                        $stepTests[$testName] = $newTest;
                    }
                }
                $allTests = $stepTests;
            }
            if ($class::SHUFFLE) {
                $index = 0;
                $stepTests = [];
                foreach ($allTests as $test) {
                    $args = $test[0];
                    $expectations = $test[1];
                    $permutations = [];
                    $this->permute($permutations, $args);
                    foreach ($permutations as $newargs) {
                        $newTest = [ $newargs, $expectations, $excludes ];
                        $testName = sprintf('%s%04d', $prefix, ++$index);
                        $stepTests[$testName] = $newTest;
                    }
                }
                $allTests = $stepTests;
            }
        }

        return $allTests;
    }

    /**
     * compute all permutations of $items
     *
     * @param array<array<string>> $result
     * @param array<string>|null   $items
     * @param array<string>        $perms
     *
     * @return void
     */
    private function permute(&$result, $items, $perms = [])
    {
        if (null === $items || 0 === count($items)) {
            $result[] = $perms;
        } else {
            for ($i = count($items) - 1; $i >= 0; --$i) {
                 $newitems = $items;
                 $newperms = $perms;

                 list($foo) = array_splice($newitems, $i, 1);
                 array_unshift($newperms, $foo);
                 $this->permute($result, $newitems, $newperms);
            }
        }
    }
}
