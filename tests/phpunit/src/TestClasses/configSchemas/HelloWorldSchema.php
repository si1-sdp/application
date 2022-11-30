<?php
/*
 * This file is part of dgfip-si1/process-helper
 */
namespace DgfipSI1\ApplicationTests\TestClasses\configSchemas;

use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\ConfiguredApplicationTrait;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * global application schema
 */
class HelloWorldSchema implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
    public const DUMPED_SHEMA =
    '    user:                 ~
    id:                   ~
';

    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('user')->end()
                ->scalarNode('id')->end()
            ->end();

        return $treeBuilder;
    }
    /**
     * @inheritDoc
     */
    public function getConfigOptions()
    {
        $opts = [];
        $opts[] = new MappedOption('configAware', OptionType::Scalar);

        return $opts;
    }
}
