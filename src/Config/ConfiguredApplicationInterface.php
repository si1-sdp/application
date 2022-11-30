<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use League\Container\ContainerAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Application;

/**
 * ConfiguredApplicationInterface
 */
interface ConfiguredApplicationInterface extends
    ConfigurationInterface,
    ConfigAwareInterface,
    LoggerAwareInterface,
    ContainerAwareInterface
{
    /**
     * @return array<MappedOption>
     */
    public function getConfigOptions();
    /**
     * Gets the configured application object.
     *
     * @return ApplicationInterface $application
     */
    public function getConfiguredApplication();
    /**
     * get TreeBuilderschema from options
     *
     * @return TreeBuilder
     */
    public function schemaFromOptions();
}
