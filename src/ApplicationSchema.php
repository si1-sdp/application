<?php
/*
 * This file is part of dgfip-si1/process-helper
 */

namespace DgfipSI1\Application;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration branch for dgfip-si1 applications
 */
class ApplicationSchema implements ConfigurationInterface
{
    public const APPLICATION_NAME        = 'dgfip_si1.application.name';
    public const APPLICATION_VERSION     = 'dgfip_si1.application.version';
    public const APPLICATION_NAMESPACE   = 'dgfip_si1.application.commands_namespace';
    public const DEFAULT_NAMESPACE       = 'Commands';

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


    public const GLOBAL_OPTIONS          = 'dgfip_si1.global_options';

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
                        ->scalarNode('commands_namespace')->defaultValue(self::DEFAULT_NAMESPACE)
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
                ->append($this->inputOptions('global_options'))
                ->arrayNode('command_options')
                    ->useAttributeAsKey('command_name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('command_name')->info('command name as defined in AsCommand attribute')->end()
                            ->append($this->inputOptions('options'))
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('runtime')
                    ->variablePrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
    /**
     * Schema part for a list of input options
     *
     * @param string $name
     *
     * @return NodeDefinition
     */
    protected function inputOptions($name)
    {
        $treeBuilder = new TreeBuilder($name);
        $node = $treeBuilder->getRootNode();
        /** @phpstan-ignore-next-line */
        $node->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('name')
                        ->info('name that users must type to pass this option (e.g. --iterations=5)')->end()
                    ->scalarNode('short_option')
                        ->validate()
                            ->ifTrue(function ($s) {
                                return !(is_string($s) && strlen($s) === 1);
                            })
                            ->thenInvalid('Short option should be a one letter string.')
                            ->end()
                        ->info('optional one letter shortcut of the option name, (e.g. `i` for `-i`)')
                        ->end()
                    ->scalarNode('description')
                        ->info('the option description displayed when showing the command help')->end()
                    ->variableNode('default')
                        ->info('the default value of the option (for those which allow to pass values)')->end()
                    ->enumNode('type')->values(['array', 'boolean', 'scalar', 'argument'])->defaultValue('scalar')
                        ->info("Type of option \n
    - array   : option accepts multiple values (e.g. --dir=/foo --dir=/bar)
    - scalar  : --iterations=5 or --name=John
    - boolean : --yell  ")->end()
                    ->booleanNode('required')
                        ->info("For arguments only - is argument required ?")->end()
                ->end()
                ->validate()
                    ->ifTrue(function ($v) {
                        return ( $v['type'] !== 'argument' && array_key_exists('required', $v) );
                    })
                    ->thenInvalid('Required option only valid for argument')
                ->end()
            ->end();

        return $node;
    }
}
