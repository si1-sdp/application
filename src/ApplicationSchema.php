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
    public const LOG_DIRECTORY           = 'dgfip_si1.log.directory';
    public const LOG_FILENAME            = 'dgfip_si1.log.filename';
    public const LOG_DATE_FORMAT         = 'dgfip_si1.log.date_format';
    public const LOG_OUTPUT_FORMAT       = 'dgfip_si1.log.output_format';

    public const DEFAULT_DATE_FORMAT     = "Y:m:d-H:i:s";
    public const DEFAULT_OUTPUT_FORMAT   = "%datetime%|%level_name%|%context.name%|%message%\n";

    /** @var ConfigurationInterface|null $applicationSchema */
    protected $applicationSchema;
    /**
     * constructor
     *
     * @param ConfigurationInterface $schema
     */
    public function __construct($schema = null)
    {
        $this->applicationSchema = $schema;
    }
    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        if (null === $this->applicationSchema) {
            $treeBuilder = new TreeBuilder('application');
        } else {
            $treeBuilder = $this->applicationSchema->getConfigTreeBuilder();
        }
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('dgfip_si1')
                    ->children()
                        ->arrayNode('application')
                            ->children()
                                ->scalarNode('name')->info("Application name")->end()
                                ->scalarNode('version')->info("Application version")->end()
                            ->end()
                        ->end()
                        ->arrayNode('log')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('directory')->defaultValue('.')
                                    ->info("Log directory (default = current directory)")->end()
                                ->scalarNode('filename')->info("Log filename")->end()
                                ->scalarNode('date_format')->defaultValue(self::DEFAULT_DATE_FORMAT)
                                    ->info("Log date format ")->end()
                                ->scalarNode('output_format')->defaultValue(self::DEFAULT_OUTPUT_FORMAT)
                                    ->info("Log date format ")->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('options')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
