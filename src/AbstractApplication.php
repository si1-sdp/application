<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\Util\ConfigOverlay;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\BaseSchema;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\Application\Utils\ApplicationLogger;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\Application\Utils\MakePharCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use League\Container\Container;
use League\Container\ContainerAwareTrait;
use Phar;
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

    /** @var string $entryPoint */
    protected $entryPoint;
    /** @var string $pharRoot */
    protected $pharRoot;
    /** @var string $homeDir */
    protected $homeDir;
    /** @var string $currentDir */
    protected $currentDir;
    /** @var string $appName */
    protected $appName;
    /** @var string $appVersion */
    protected $appVersion;
    /** @var InputInterface $input */
    protected $input;
    /** @var Output $output */
    protected $output;
    /** @var ConfigHelper $intConfig Application internal configuration */
    protected $intConfig;
    /** @var ClassLoader $classLoader */
    protected $classLoader;
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
        // initialize directories
        if (sizeof($argv) > 0) {
            $this->entryPoint = $argv[0];
            $this->homeDir    = (string) realpath(dirname($argv[0]));
        } else {
            $this->entryPoint = '';
            $this->homeDir    = (string) getcwd();
        }
        $this->currentDir = (string) getcwd();
        $this->pharRoot = MakePharCommand::getPharRoot();

        // initialize input and output
        $this->input  = new ArgvInput($argv);
        $this->output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->output->setVerbosity(ApplicationLogger::getVerbosity($this->input));

        // setup a logger
        $this->logger = ApplicationLogger::initLogger($this->output);
        // setup app internal configuration
        $this->setupApplicationConfig();

        $this->config    = new ConfigHelper(new BaseSchema());
        $this->config->setLogger($this->getLogger());

        $this->classLoader = $classLoader;

        $this->container = new Container();
        $disc = new ClassDiscoverer($classLoader);
        $disc->setContainer($this->container);
        $disc->setLogger($this->getLogger());
        $this->container->addShared('class_discoverer', $disc);

        $this->namespaces = [];
        $this->mappedOptions = [];
        $this->setAutoExit(false);
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
     * gets the entrypoint
     *
     * @return string
     */
    public function getEntryPoint()
    {
        return $this->entryPoint;
    }
    /**
     * gets the home directory (directory where entrypoint is located)
     *
     * @return string
     */
    public function getHomeDir()
    {
        return $this->homeDir;
    }
    /**
     * gets the current directory
     *
     * @return string
     */
    public function getCurrentDir()
    {
        return $this->currentDir;
    }
    /**
     * gets the current directory
     *
     * @return string
     */
    public function getPharRoot()
    {
        return $this->pharRoot;
    }
    /**
     * gets phar exclusions
     *
     * @return array<string>
     */
    public function getPharExcludes()
    {
        /** @var array<string> $pharExludes */
        $pharExludes = $this->intConfig->get(CONF::PHAR_EXCLUDES) ?? [];

        return $pharExludes;
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
        $this->getLogger()->debug('Adding '.($opt->isArgument() ? 'argument' : 'option').' {opt} for {cmd}', $logCtx);
        if (null !== $opt->getCommand()) {
            $key = $opt->getCommand();
        } else {
            $key = '__global__';
        }
        if (!array_key_exists($key, $this->mappedOptions)) {
            $this->mappedOptions[$key] = [];
        }
        $this->mappedOptions[$key][$opt->getName()] = $opt;
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
     * Discoverer classes
     * Note :
     * - dependencies work with logical AND : all dependencies have to be met
     * - exclusions work with logical OR : anny exclusion filters class out.

     * @param array<string>|string $namespaces
     * @param string               $tag
     * @param array<string>|string $deps
     * @param array<string>|string $excludeDeps
     * @param string|null          $idAttribute
     * @param boolean              $emptyOk
     *
     * @return void
     */
    public function addDiscoveries($namespaces, $tag, $deps, $excludeDeps = [], $idAttribute = null, $emptyOk = true)
    {
        /** @var ClassDiscoverer $disc */
        $disc = $this->getContainer()->get('class_discoverer');
        $disc->addDiscoverer($namespaces, $tag, $deps, $excludeDeps, $idAttribute, $emptyOk);
    }
    /**
     * discovers classes previously added for discovery
     *
     * @return void
     */
    public function discoverClasses()
    {
        /** @var ClassDiscoverer $disc */
        $disc = $this->getContainer()->get('class_discoverer');
        $disc->discoverAllClasses();
    }
    /**
     * Verify that we have an application name and version
     *
     * @return void
     */
    protected function setApplicationNameAndVersion()
    {
        if (null === $this->appName) {
            /** @var string $appName */
            $appName = $this->intConfig->get(CONF::APPLICATION_NAME);
            $this->appName = $appName;
            if (null === $this->appName) {
                throw new NoNameOrVersionException("Application name missing");
            }
        }
        if (null === $this->appVersion) {
            /** @var string $appVersion */
            $appVersion = $this->intConfig->get(CONF::APPLICATION_VERSION);
            $this->appVersion = $appVersion;
            if (null === $this->appVersion) {
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
            $this->getCurrentDir().DIRECTORY_SEPARATOR.self::DEFAULT_APP_CONFIG_FILE,
            $this->getHomeDir().DIRECTORY_SEPARATOR.self::DEFAULT_APP_CONFIG_FILE,
            $this->getPharRoot().DIRECTORY_SEPARATOR.self::DEFAULT_APP_CONFIG_FILE,
        ];
        $logContext = [ 'name' => 'new Application()' ];
        $loaded = false;
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
                $loaded = true;
                break;
            }
        }
        if (false === $loaded) {
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
