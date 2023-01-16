<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Config;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ApplicationTests\TestClasses\Commands\HelloWorldCommand;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldAutoSchema;
use DgfipSI1\ApplicationTests\TestClasses\configSchemas\HelloWorldSchema;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use League\Container\Container;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfiguredApplicationTrait
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Contracts\ConfigAwareTrait
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 * @uses DgfipSI1\Application\Utils\MakePharCommand
 * @uses DgfipSI1\Application\Command
 */
class ConfiguredApplicationTraitTest extends LogTestCase
{
    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
    }
    /**
     * test schemaFromOptions
     *
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::schemaFromOptions
     *
     * @return void
     */
    public function testSchemaFromCommandOptions()
    {
        /** @var ConfiguredApplicationInterface $cmd */
        $cmd = new HelloWorldCommand();
        $DUMP = 'schema:
    commands:
        hello_world:
            options:

                # A boolean value here
                bool:                 true

                # array there
                array:                []

                # & scalar
                scalar:               ~
';

        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);
        $cmd->setContainer(new Container());
        $cmd->getContainer()->addShared('application', $app);
        $bool = new MappedOption('bool', OptionType::Boolean, 'A boolean value here', 'B', true);
        $app->addMappedOption($bool->setCommand('hello-world'));
        $app->addMappedOption((new MappedOption('array', OptionType::Array, 'array there'))->setCommand('hello-world'));
        $app->addMappedOption((new MappedOption('scalar', OptionType::Scalar, '& scalar'))->setCommand('hello-world'));

        /** @var ConfigurationInterface $cmd */
        $config = new ConfigHelper($cmd);
        self::assertEquals($DUMP, $config->dumpSchema());
    }
    /**
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::schemaFromOptions
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::getConfigTreeBuilder
     *
     * @return void
     */
    public function testSchemaFromGlobalOptions()
    {
        /** @var ConfiguredApplicationInterface $cmd */
        $cmd = new HelloWorldAutoSchema();
        $DUMP = 'schema:
    options:

        # A boolean value here
        bool:                 false

        # An array there
        array:

            # Defaults:
            - A
            - B

        # And a scalar
        scalar:               ~

        # argument
        argument:             foo
';

        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);
        $cmd->setContainer(new Container());
        $cmd->getContainer()->addShared('application', $app);
        $app->addMappedOption(new MappedOption('bool', OptionType::Boolean, 'A boolean value here'));
        $app->addMappedOption(new MappedOption('array', OptionType::Array, 'An array there', default: ['A', 'B']));
        $app->addMappedOption(new MappedOption('scalar', OptionType::Scalar, 'And a scalar'));
        $app->addMappedOption(new MappedOption('argument', OptionType::Argument, 'argument', default: 'foo'));

        $config = new ConfigHelper($cmd);
        self::assertEquals($DUMP, $config->dumpSchema());

        self::assertInstanceOf(TreeBuilder::class, $cmd->schemaFromOptions());
    }
    /**
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::getOptionValue
     *
     * @return void
     */
    public function testGetOptionValue()
    {
        $cmd = new HelloWorldCommand();
        $cmd->setConfig(new ConfigHelper());
        $notCmd = new HelloWorldSchema();
        $notCmd->setConfig($cmd->getConfig());

        self::assertNull($cmd->getOptionValue('foo-bar'));
        self::assertNull($notCmd->getOptionValue('foo-bar'));

        $cmd->getConfig()->set('options.foo_bar', 'bar from global');
        self::assertEquals('bar from global', $cmd->getOptionValue('foo-bar'));

        $cmd->getConfig()->set('commands.hello_world.options.foo_bar', 'bar from hello');
        self::assertEquals('bar from hello', $cmd->getOptionValue('foo-bar'));

        self::assertEquals('bar from global', $notCmd->getOptionValue('foo-bar'));
    }

    /**
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::getConfigOptions
     *
     * @return void
     */
    public function testGetConfigOptions()
    {
        $trait = new HelloWorldAutoSchema();
        self::assertEquals([], $trait->getConfigOptions());
    }
    /**
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::getConfiguredApplication
     *
     * @return void
     */
    public function testGetConfiguredApplication()
    {
        $cmd = new HelloWorldCommand();
        $cmd->setContainer(new Container());

        $msg = '';
        try {
            $cmd->getConfiguredApplication();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('No application has been set.', $msg);

        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);
        $cmd->getContainer()->addShared('application', $app);
        self::assertEquals($app, $cmd->getConfiguredApplication());

        $container = new Container();
        $container->addShared('application', $this);
        $cmd->setContainer($container);
        $msg = '';
        try {
            $cmd->getConfiguredApplication();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals('No application has been set.', $msg);
    }
}
