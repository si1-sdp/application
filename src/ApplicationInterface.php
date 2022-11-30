<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\Util\ConfigOverlay;
use Consolidation\Log\Logger;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\BaseSchema;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Symfony\Component\Console\Application as SymfoApp;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\Output;

/**
 * class Application
 */
interface ApplicationInterface extends ConfigAwareInterface, ContainerAwareInterface, LoggerAwareInterface
{
    /**
     * constructor
     *
     * @param ClassLoader   $classLoader
     * @param array<string> $argv
     */
    public function __construct($classLoader, $argv = []);
    /**
     * Do whatever needed after classes have been added to container
     *
     * @return void
     *
     */
    public function registerCommands();

    /**
     * run application
     *
     * @return int
     */
    public function go();
    /**
     * sets the application name
     *
     * @param string $name
     *
     * @return self
     */
    public function setName($name);
    /**
     * Sets the application version
     *
     * @param string $version
     *
     * @return self
     */
    public function setVersion($version);
    /**
     * Sets the namespace for class dicoverers
     *
     * @param string      $namespace
     * @param string|null $tag
     *
     * @return void
     */
    public function setNameSpace($namespace, $tag = null);
    /**
     * gets the namespace
     *
     * @param string|null $tag
     *
     * @return string
     */
    public function getNameSpace($tag = null);
    /**
     * Adds a mapped option to application configuration
     *
     * @param MappedOption $option
     *
     * @return void
     */
    public function addMappedOption($option);
    /**
     * Get mapped options for given command (or global options if no command specified)
     *
     * @param string|null $command
     *
     * @return array<MappedOption>
     */
    public function getMappedOptions($command = null);
    /**
     * Gets the InputDefinition related to this Application.
     */
    public function getDefinition(): InputDefinition;
}
