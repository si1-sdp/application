<?php
/*
 * This file is part of dgfip-si1/application
 */
namespace DgfipSI1\ApplicationTests\TestClasses\Commands;

use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\ConfiguredApplicationTrait;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Hello world main schema
 */
class HelloWorldSchema implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('people')
                    ->useAttributeAsKey('id')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('id')->end()
                            ->scalarNode('title')->end()
                            ->scalarNode('name')->end()
                            ->scalarNode('first_name')->end()
                        ->end()
                    ->end()
                ->end()
                ->append($this->schemaFromOptions()->getRootNode())
            ->end();

        return $treeBuilder;
    }
}
