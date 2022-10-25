<?php
/*
 * This file is part of dgfip-si1/process-helper
 */

namespace DgfipSI1\Application;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration branch for dgfip-si1 applications
 */
class ApplicationSchema implements ConfigurationInterface
{
    public const APPLICATION_NAME        = 'dgfip_si1.application.name';
    public const APPLICATION_VERSION     = 'dgfip_si1.application.version';
    public const APPLICATION_TYPE        = 'dgfip_si1.application.type';
    public const APPLICATION_NAMESPACE   = 'dgfip_si1.application.commands_namespace';
    public const LOG_DIRECTORY           = 'dgfip_si1.log.directory';
    public const LOG_FILENAME            = 'dgfip_si1.log.filename';
    public const LOG_DATE_FORMAT         = 'dgfip_si1.log.date_format';
    public const LOG_OUTPUT_FORMAT       = 'dgfip_si1.log.output_format';

    public const DEFAULT_DATE_FORMAT     = "Y:m:d-H:i:s";
    public const DEFAULT_OUTPUT_FORMAT   = "%datetime%|%level_name%|%context.name%|%message%\n";

    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('application');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('dgfip_si1')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('application')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('name')->info("Application name")->end()
                                ->scalarNode('version')->info("Application version")->end()
                                ->enumNode('type')->values(['symfony', 'robo'])->defaultValue('symfony')
                                    ->info('type : symfony or robo')->end()
                                ->scalarNode('commands_namespace')->defaultValue('Commands')
                                    ->info("namespace for commands")->end()
                            ->end()
                        ->end()
                        ->arrayNode('log')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('directory')->info("Log directory")->end()
                                ->scalarNode('filename')->info("Log filename")->end()
                                ->scalarNode('date_format')->defaultValue(self::DEFAULT_DATE_FORMAT)
                                    ->info("Log date format ")->end()
                                ->scalarNode('output_format')->defaultValue(self::DEFAULT_OUTPUT_FORMAT)
                                    ->info("Log date format ")->end()
                            ->end()
                        ->end()
                        ->arrayNode('runtime')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
