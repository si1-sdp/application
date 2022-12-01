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
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use League\Container\Container;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\ApplicationLogger
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfiguredApplicationTrait
 * @uses DgfipSI1\Application\Config\MappedOption
 * @uses DgfipSI1\Application\Config\OptionType
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 */
class ConfiguredApplicationTraitTest extends LogTestCase
{
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
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
        hello:
            options:

                # A boolean value here
                bool:                 ~

                # An array there
                array:                []

                # And a scalar
                scalar:               ~
';

        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);
        $cmd->setContainer(new Container());
        $cmd->getContainer()->addShared('application', $app);
        $app->addMappedOption((
        new MappedOption('bool', OptionType::Boolean, 'A boolean value here'))->setCommand('hello'));
        $app->addMappedOption((new MappedOption('array', OptionType::Array, 'An array there'))->setCommand('hello'));
        $app->addMappedOption((new MappedOption('scalar', OptionType::Scalar, 'And a scalar'))->setCommand('hello'));

        /** @var ConfigurationInterface $cmd */
        $config = new ConfigHelper($cmd);
        $this->assertEquals($DUMP, $config->dumpSchema());
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
        bool:                 ~

        # An array there
        array:                []

        # And a scalar
        scalar:               ~
';

        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);
        $cmd->setContainer(new Container());
        $cmd->getContainer()->addShared('application', $app);
        $app->addMappedOption(new MappedOption('bool', OptionType::Boolean, 'A boolean value here'));
        $app->addMappedOption(new MappedOption('array', OptionType::Array, 'An array there'));
        $app->addMappedOption(new MappedOption('scalar', OptionType::Scalar, 'And a scalar'));

        $config = new ConfigHelper($cmd);
        $this->assertEquals($DUMP, $config->dumpSchema());

        $this->assertInstanceOf(TreeBuilder::class, $cmd->schemaFromOptions());
    }
    /**
     * @covers DgfipSI1\Application\Config\ConfiguredApplicationTrait::getConfigOptions
     *
     * @return void
     */
    public function testGetConfigOptions()
    {
        $trait = new HelloWorldAutoSchema();
        $this->assertEquals([], $trait->getConfigOptions());
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
        $this->assertEquals('No application has been set.', $msg);

        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], []);
        $cmd->getContainer()->addShared('application', $app);
        $this->assertEquals($app, $cmd->getConfiguredApplication());
    }
}
