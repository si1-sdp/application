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
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use League\Container\Container;
use League\Container\ContainerAwareTrait;
use Symfony\Component\Console\Application as SymfoApp;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;

/**
 * class Application
 */
abstract class AbstractApplication extends SymfoApp implements ApplicationInterface
{
    use ConfigAwareTrait;
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    public const DEFAULT_MAP_ROOT           = 'options';
    protected const DEFAULT_APP_CONFIG_FILE = '.application-config.yml';

    protected const COMMAND_SUBCLASS   = '__abstract__';
    public const COMMAND_TAG        = '__abstract__';
    public const COMMAND_CONFIG_TAG = 'commandConfigurator';
    public const GLOBAL_CONFIG_TAG  = 'globalConfigurator';
    public const EVENT_LISTENER_TAG = 'eventListener';
    public const DEFAULT            = 'default';

    /** @var string $appName */
    protected $appName;
    /** @var string $appVersion */
    protected $appVersion;
    /** @var InputInterface $input */
    protected $input;
    /** @var Output $output */
    protected $output;
    /** @var ClassLoader $classLoader  */
    protected $classLoader;
    /** @var ConfigHelper $intConfig Application internal configuration */
    protected $intConfig;
    /** @var array<string,string> $namespaces */
    protected $namespaces;
    /** @var array<string,array<MappedOption>> $mappedOptions */
    protected $mappedOptions;
    /**
     * constructor
     *
     * @param ClassLoader   $classLoader
     * @param array<string> $argv
     */
    public function __construct($classLoader, $argv = [])
    {
        parent::__construct();

        // initialize input and output
        $this->input  = new ArgvInput($argv);
        $this->output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->output->setVerbosity(ApplicationLogger::getVerbosity($this->input));

        // setup a temporary logger for initialisation
        $this->logger = new Logger($this->output);
        $this->logger->setLogOutputStyler(new \Robo\Log\RoboLogStyle());

        // setup app internal configuration
        $this->setupApplicationConfig();

        $this->config    = new ConfigHelper(new BaseSchema());
        $this->config->setLogger($this->getLogger());
        $this->container = new Container();
        $this->classLoader = $classLoader;
        $this->namespaces = [];
        $this->mappedOptions = [];
    }
    /**
     * sets the application name
     *
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->appName = $name;

        return $this;
    }
    /**
     * Sets the application version
     *
     * @param string $version
     *
     * @return self
     */
    public function setVersion($version)
    {
        $this->appVersion = $version;

        return $this;
    }
    /**
     * Sets the namespace for class dicoverers
     *
     * @param string      $namespace
     * @param string|null $tag
     *
     * @return void
     */
    public function setNamespace($namespace, $tag = null)
    {
        $tag = $tag ?? self::DEFAULT;
        $this->namespaces[$tag] = $namespace;
    }
    /**
     * gets the namespace
     *
     * @param string|null $tag
     *
     * @return string
     */
    public function getNamespace($tag = null)
    {
        $tag = $tag ?? self::DEFAULT;
        if (array_key_exists($tag, $this->namespaces)) {
            $ret = $this->namespaces[$tag];
        } elseif (array_key_exists(self::DEFAULT, $this->namespaces)) {
            $ret = $this->namespaces[self::DEFAULT];
        } elseif (null !== $this->intConfig->get(CONF::APPLICATION_NAMESPACE)) {
            $ret = $this->intConfig->get(CONF::APPLICATION_NAMESPACE);
        } else {
            throw new RuntimeException("Can't determine namespace");
        }
        /** @var string $ret */

        return $ret;
    }
    /**
     * Adds a mapped option to application configuration
     *
     * @param MappedOption $opt
     *
     * @return void
     */
    public function addMappedOption($opt)
    {
        $logCtx = ['name' => 'addMappedOption', 'opt' => $opt->getName(), 'cmd' => $opt->getCommand() ?? 'global'];
        $this->getLogger()->debug('Adding option {opt} for {cmd}', $logCtx);
        if (null !== $opt->getCommand()) {
            $key = $opt->getCommand();
        } else {
            $key = '__global__';
        }
        if (!array_key_exists($key, $this->mappedOptions)) {
            $this->mappedOptions[$key] = [];
        }
        $this->mappedOptions[$key][] = $opt;
    }
    /**
     * Get mapped option
     *
     * @param string|null $command
     *
     * @return array<MappedOption>
     */
    public function getMappedOptions($command = null)
    {
        if (null === $command) {
            $command = '__global__';
        }
        if (array_key_exists($command, $this->mappedOptions)) {
            return $this->mappedOptions[$command];
        }

        return [];
    }

    /**
     * Verify that we have an application name and version
     *
     * @return void
     */
    protected function setApplicationNameAndVersion()
    {
        if (!$this->appName) {
            /** @var string $appName */
            $appName = $this->intConfig->get(CONF::APPLICATION_NAME);
            $this->appName = $appName;
            if (!$this->appName) {
                throw new NoNameOrVersionException("Application name missing");
            }
        }
        if (!$this->appVersion) {
            /** @var string $appVersion */
            $appVersion = $this->intConfig->get(CONF::APPLICATION_VERSION);
            $this->appVersion = $appVersion;
            if (!$this->appVersion) {
                throw new NoNameOrVersionException("Version missing");
            }
        }
        parent::setName($this->appName);
        $this->intConfig->set(CONF::APPLICATION_NAME, $this->appName);
        parent::setVersion($this->appVersion);
        $this->intConfig->set(CONF::APPLICATION_VERSION, $this->appVersion);
    }
    /**
     * find technical config file and load it
     *
     * @return void
     */
    protected function setupApplicationConfig()
    {
        $internalConfSchema = new ApplicationSchema();
        $this->intConfig    = new ConfigHelper($internalConfSchema);
        $defaultConfigFiles = [
            __DIR__.DIRECTORY_SEPARATOR.self::DEFAULT_APP_CONFIG_FILE,
            $_SERVER['PWD'].DIRECTORY_SEPARATOR.self::DEFAULT_APP_CONFIG_FILE,
            getcwd().DIRECTORY_SEPARATOR.self::DEFAULT_APP_CONFIG_FILE,
        ];
        $logContext = [ 'name' => 'new Application()' ];
        foreach ($defaultConfigFiles as $file) {
            $logContext['file'] = $file;
            if (file_exists($file)) {
                $this->intConfig->addFile(ConfigOverlay::DEFAULT_CONTEXT, $file);
                try {
                    $this->intConfig->build();
                } catch (\Exception $e) {
                    $this->intConfig = new ConfigHelper($internalConfSchema);
                    $this->getLogger()->warning("Error loading configuration $file : \n".$e->getMessage());
                    break;
                }
                $this->getLogger()->debug("Configuration file {file} loaded.", $logContext);
                $this->intConfig->setDefault(CONF::RUNTIME_INT_CONFIG, $file);
                $this->intConfig->setDefault(CONF::RUNTIME_ROOT_DIRECTORY, realpath(dirname($file)));

                break;
            }
        }
        if (!$this->intConfig->get(CONF::RUNTIME_INT_CONFIG)) {
            $this->getLogger()->debug("No default configuration loaded", $logContext);
        }
    }
    /**
     * Configure the container
     *
     * @return void
     */
    abstract protected function configureContainer();
}
