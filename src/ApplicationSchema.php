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
    public const LOG_LEVEL               = 'dgfip_si1.log.level';

    public const CONFIG_DIRECTORY        = 'dgfip_si1.configuration.root_dir';
    public const CONFIG_PATH_PATTERNS    = 'dgfip_si1.configuration.path_patterns';
    public const CONFIG_NAME_PATTERNS    = 'dgfip_si1.configuration.name_patterns';
    public const CONFIG_SORT_BY_NAME     = 'dgfip_si1.configuration.sort_by_name';
    public const CONFIG_SEARCH_RECURSIVE = 'dgfip_si1.configuration.search_recursive';

    public const DEFAULT_DATE_FORMAT     = "Y:m:d-H:i:s";
    public const DEFAULT_OUTPUT_FORMAT   = "%datetime%|%level_name%|%context.name%|%message%\n";

    public const RUNTIME_INT_CONFIG      = "dgfip_si1.runtime.app_config_file";
    public const RUNTIME_ROOT_DIRECTORY  = "dgfip_si1.runtime.root_directory";

    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('dgfip_si1');
        $treeBuilder->getRootNode()
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
                            ->info("Log output format ")->end()
                        ->enumNode('level')
                            ->Values(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])
                            ->defaultValue('notice')->info("Logfile output level")->end()
                    ->end()
                ->end()
                ->arrayNode('configuration')
                    ->info('directory patterns that can contain configuration files')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('root_dir')
                            ->info("configuration search root - default is application root directory")
                        ->end()
                        ->arrayNode('path_patterns')
                            ->info('directory patterns that can contain configuration files (default = none)')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('name_patterns')
                            ->info('file patterns of configuration files (default = \'config.yml\')')
                            ->defaultValue(['config.yml'])
                            ->scalarPrototype()->end()
                        ->end()
                        ->booleanNode('sort_by_name')->defaultFalse()
                            ->info("sort files strictly by filename (instead of by full path)")->end()
                        ->booleanNode('search_recursive')->defaultFalse()
                            ->info("recurse search in sub directories")->end()
                    ->end()
                ->end()
                ->arrayNode('runtime')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
