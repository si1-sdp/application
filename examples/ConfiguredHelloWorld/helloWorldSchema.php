<?php
namespace hello_world\Commands;

use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Input\InputOption;

class helloWorldSchema implements ApplicationAwareInterface
{
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
                ->arrayNode('options')
                    ->children()
                        ->scalarNode('user')->end()
                    ->end()
                ->end()
            ->end();
        return $treeBuilder;
    }
    public function getConfigOptions()
    {
        $opts = [];
        // $opt = new InputOption('--configAware', null, InputOption::VALUE_REQUIRED);
        // $opts[] = new MappedOption($opt, 'user');
       
        return $opts;
    }
}

