<?php
/*
 * This file is part of dgfip-si1/process-helper
 */

namespace DgfipSI1\Application\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration branch for dgfip-si1 applications
 */
class BaseSchema implements ConfigurationInterface
{
    public const OPTIONS                 = 'options';
    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('options')
                    ->ignoreExtraKeys()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
