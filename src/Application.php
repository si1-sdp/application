<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\Application\Exception\ApplicationTypeException;
use Composer\Autoload\ClassLoader;
use Consolidation\Config\Util\ConfigOverlay;
use Consolidation\Log\Logger;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use League\Container\Definition\DefinitionInterface;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Level as MonLvl;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as SymfoApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;

/**
 * class Application
 */
class Application extends SymfoApp
{
    protected const ROBO_APPLICATION    = 'robo';
    protected const SYMFONY_APPLICATION = 'symfony';

    private const SYMFONY_SUBCLASS = '\Symfony\Component\Console\Command\Command';
    private const ROBO_SUBCLASS    = '\Robo\Tasks';
    private const SYMFONY_CMD_TAG  = 'symfonyCommand';

    protected const DEFAULT_APP_CONFIG_FILE = '.application-config.yml';

    /** @var string $appName */
    private $appName;
    /** @var string $appVersion */
    private $appVersion;
    /** @var string $appType */
    private $appType;
    /** @var Input $input */
    private $input;
    /** @var Output $output */
    private $output;
    /** @var ApplicationContainer $container */
    protected $container;
    /** @var ClassLoader $classLoader  */
    private $classLoader;
    /** @var array<string> */
    private $commandClasses;
    /** @var ConfigHelper $config */
    private $config;
    /** @var ConfigHelper $intConfig */
    private $intConfig;
    /** @var LoggerInterface $logger */
    private $logger;

    /**
     * constructor
     *
     * @param ClassLoader                 $classLoader
     * @param array<string>               $argv
     * @param ConfigurationInterface|null $confSchema
     */
    public function __construct($classLoader, $argv = [], $confSchema = null)
    {
        parent::__construct();

        // initialize input and output
        $this->input  = new ArgvInput($argv);
        $this->output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->output->setVerbosity($this->getVerbosity($this->input));

        // setup a temporary logger for initialisation
        $this->logger = new Logger($this->output);
        $this->logger->setLogOutputStyler(new \Robo\Log\RoboLogStyle());

        // setup app internal configuration
        $this->setupApplicationConfig();

        $this->config    = new ConfigHelper($confSchema);
        $this->container = new ApplicationContainer();
        $this->classLoader = $classLoader;
    }

    /**
     * Get the config helper object
     *
     * @return ConfigHelper
     */
    public function config()
    {
        return $this->config;
    }
    /**
     * Get the logger object
     *
     * @return LoggerInterface
     */
    public function logger()
    {
        if ($this->container->has('logger')) {
            /** @var LoggerInterface $lg */
            $lg = $this->container->get('logger');
        } else {
            $lg = $this->logger;
        }

        return $lg;
    }
    /**
     * Get the container object
     *
     * @return ApplicationContainer
     */
    public function container()
    {
        return $this->container;
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
    /** sets the list of classes containing robo commands
     *
     * @param string $relativeNamespace
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws ApplicationTypeException
     */
    public function findRoboCommands($relativeNamespace)
    {
        if ($this->appType && $this->appType !== self::ROBO_APPLICATION) {
            throw new ApplicationTypeException("Can't initialize robo command - type mismatch");
        }
        $this->appType = self::ROBO_APPLICATION;
        $this->commandClasses = $this->discoverRoboCommands($relativeNamespace, self::ROBO_SUBCLASS);
    }
    /** sets the list of classes containing symfony commands
     *
     * @param string $relativeNamespace
     * @param string $subClass
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws ApplicationTypeException
     */
    public function findSymfonyCommands(string $relativeNamespace, string $subClass = self::SYMFONY_SUBCLASS)
    {
        if ($this->appType && $this->appType !== self::SYMFONY_APPLICATION) {
            throw new ApplicationTypeException("Can't initialize symfony command - type mismatch");
        }
        $this->appType = self::SYMFONY_APPLICATION;
        $this->configureAndRegisterServices($relativeNamespace, $subClass, self::SYMFONY_CMD_TAG, "name");
    }
    /**
     * Finalize application
     *
     * @param int $configOptions
     *
     * @return int
     */
    public function go($configOptions = 0)
    {
        $this->finalize();
        $logContext = ['name' => 'go'];
        $statusCode = 0;
        if (self::ROBO_APPLICATION === $this->appType) {
            // Instantiate Robo Runner.
            $runner = new RoboRunner();
            $runner->setContainer($this->container);
            /** @phpstan-ignore-next-line */
            $statusCode  = $runner->run($this->input, $this->output, $this, $this->commandClasses);
            $this->logger->notice("Launching robo command", $logContext);
        } elseif (self::SYMFONY_APPLICATION === $this->appType) {
            $logContext['cmd_name'] = $this->isSingleCommand() ? 'list' : $this->input->getFirstArgument();
            $this->logger->notice("Launching symfony command '{cmd_name}'", $logContext);
            $statusCode = parent::run($this->input, $this->output);
        }

        return $statusCode;
    }

    /**
     * finalize application before run
     *
     * @return void
     */
    protected function finalize()
    {
        // Setup --config option and handle it to load configuration
        $this->configSetup();

        // set application's name and version
        $this->setApplicationNameAndVersion();

        // discover commands
        $this->discoverCommands();

        // Create and configure container.
        $cl = $this->classLoader;
        Robo::configureContainer($this->container, $this, $this->config, $this->input, $this->output, $cl);

        // add verbosity to container
        $verbosity = $this->getVerbosity($this->input);
        $this->container->addShared('verbosity', $verbosity);
        // configure and setup logger

        if (!$this->container->has('roboLogger')) {
            $this->container->extend('logger')->setAlias('roboLogger');
        }
        $lg = $this->buildLogger();
        // didn't find a way to replace a service => rename old and create new
        // FIXME add replace() to ApplicationContainer
        $this->container->addShared('logger', $lg);
        Robo::finalizeContainer($this->container);
    }
    /**
     * setup configuration if file exists
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
                    $this->intConfig->addArray(ConfigOverlay::DEFAULT_CONTEXT, []);
                    $this->logger->warning("Error loading configuration $file : \n".$e->getMessage());
                    continue;
                }
                $this->logger->debug("Configuration file {file} loaded.", $logContext);
                $this->intConfig->setDefault("dgfip_si1.runtime.config_file", $file);
                break;
            }
        }
        if (!$this->intConfig->get("dgfip_si1.runtime.config_file")) {
            $this->logger->debug("No default configuration loaded", $logContext);
        }
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
            $appName = $this->intConfig->get(ApplicationSchema::APPLICATION_NAME);
            $this->appName = $appName;
            if (!$this->appName) {
                throw new NoNameOrVersionException("Application name missing");
            }
        }
        if (!$this->appVersion) {
            /** @var string $appVersion */
            $appVersion = $this->intConfig->get(ApplicationSchema::APPLICATION_VERSION);
            $this->appVersion = $appVersion;
            if (!$this->appVersion) {
                throw new NoNameOrVersionException("Version missing");
            }
        }
        parent::setName($this->appName);
        parent::setVersion($this->appVersion);
    }
    /**
     * Undocumented function
     *
     * @return void
     */
    protected function discoverCommands()
    {

        $configuredType       = $this->intConfig->getRaw(ApplicationSchema::APPLICATION_TYPE);
        /** @var string $configuredNamespace */
        $configuredNamespace  = $this->intConfig->get(ApplicationSchema::APPLICATION_NAMESPACE);

        if (null !== $this->appType) {
            /* findCommands method has been called - just check it's consistent with configuration */
            if (is_string($configuredType) && $configuredType !== $this->appType) {
                $err = "Type mismatched - find%sCommands lauched but configured type is '%s'";
                throw new ApplicationTypeException(sprintf($err, ucfirst($this->appType), $configuredType));
            }
        } else {
            // no type - means we haven't called find[Robo|Symfony]Commands(namespace)
            // see what we can do from configuration - get the configured default value
            $type = $configuredType ?? $this->intConfig->get(ApplicationSchema::APPLICATION_TYPE);
            if (self::ROBO_APPLICATION === $type) {
                $this->findRoboCommands($configuredNamespace);
            } else {
                $this->findSymfonyCommands($configuredNamespace);
            }
        }

        foreach ($this->container->getServices(tag: self::SYMFONY_CMD_TAG) as $command) {
            /** @var Command $command */
            $this->add($command);
        }
    }
    /**
     * Discovers robo commands
     *
     * @param string $nameSpace
     * @param string $subClass

     * @return array<string>
     *
     * @throws \ReflectionException
     */
    protected function discoverRoboCommands(string $nameSpace, string $subClass): array
    {
        $logContext = [ 'name' => 'discoverRoboCommands' ];
        $cClasses = $this->discoverPsr4Classes($nameSpace, $subClass);
        $commands = [];
        foreach ($cClasses as $commandClass) {
            /** @var class-string $commandClass */
            $reflectionClass = new \ReflectionClass($commandClass);
            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $reflectionClass->getName()) {
                    $commands[] = $method->getName();
                }
            }
        }
        if (!empty($commands)) {
            $logContext['count'] = count($commands);
            $this->logger()->notice("{count} robo command(s) found", $logContext);
        }

        return $cClasses;
    }


    /**
     * discovers classes in a namespace and register each instance to the container
     *
     * @param string      $nameSpace
     * @param string      $subClass
     * @param string|null $tag
     * @param string      $attributeNameForId

     * @return array<string>
     *
     * @throws \ReflectionException
     */
    protected function configureAndRegisterServices($nameSpace, $subClass, $tag = null, $attributeNameForId = 'name')
    {
        $logContext = [ 'name' => 'configureAndRegisterServices'  ];
        $discoveredClasses = $this->discoverPsr4Classes($nameSpace, $subClass);
        $returned = [];
        foreach ($discoveredClasses as $discoveredClass) {
            $concrete = new $discoveredClass();
            try {
                $serviceDefinition = $this->addSharedService($concrete, $attributeNameForId, $tag);
                $returned[] = $serviceDefinition->getAlias();
            } catch (\LogicException $e) {
                $logContext['class'] = $discoveredClass;
                $logContext['errorMessage'] = $e->getMessage();
                $this->logger()->warning("Service could not be added for {commandClass}: {errorMessage}", $logContext);
            }
        }
        if (count($returned) > 0) {
            $logContext['count'] = count($returned);
            $this->logger()->notice("{count} service(s) found", $logContext);
        } else {
            $this->logger()->warning("No service found", $logContext);
        }

        return $returned;
    }

    /**
     * Add a shared service to the container.
     * The id of the service is automatically discovered using PHP attributes.
     * A tag can be automatically added.
     *
     * @param string|object $concrete
     * @param string        $attributeNameForId
     * @param string|null   $tag
     *
     * @return DefinitionInterface
     *
     * @throws \LogicException
     */
    protected function addSharedService($concrete, $attributeNameForId = 'name', $tag = null)
    {
        $logContext = [ 'name' => 'addSharedService' ];
        if (!is_object($concrete)) {
            throw new LogicException("invalid Service provided");
        }
        $serviceId = null;
        $attributes = (new \ReflectionClass($concrete::class))->getAttributes();
        foreach ($attributes as $attribute) {
            $attributeArguments = $attribute->getArguments();
            if (array_key_exists($attributeNameForId, $attributeArguments)) {
                $serviceId = $attributeArguments[$attributeNameForId];
                break;
            }
        }
        if (null === $serviceId) {
            $msg = "invalid service id for class %s, %s attribute argument not found";
            throw new LogicException(sprintf($msg, $concrete::class, $attributeNameForId));
        }
        $serviceDefinition = $this->container->addShared($serviceId, $concrete);
        $this->logger()->debug("Adding service $serviceId to container", $logContext);
        if ((null !== $tag) && ("" !== $tag)) {
            $this->logger()->debug("Add tag $tag to service $serviceId", $logContext);
            $serviceDefinition->addTag($tag);
        }

        return $serviceDefinition;
    }

    /**
     * Discovers commands that are PSR4 auto-loaded.
     *
     * @param string      $namespace
     * @param string|null $subClassName
     *
     * @return array<string>
     *
     * @throws \ReflectionException
     */
    protected function discoverPsr4Classes($namespace, $subClassName): array
    {
        $logContext = ['name' => 'discoverPsr4Classes', 'namespace' => $namespace, 'subClass' => $subClassName ];
        // discovers classes that are in a directory ($namespace) imediatly under 'src'
        $classes = (new RelativeNamespaceDiscovery($this->classLoader))
            ->setRelativeNamespace($namespace)
            ->getClasses();
        foreach ($classes as $class) {
            $logContext['class'] = $class;
            $this->logger()->debug("1/2 - search {namespace} namespace - found {class}", $logContext);
        }
        $this->logger()->notice("1/2 - ".count($classes)." classe(s) found.", $logContext);

        $filteredClasses = array_filter($classes, function ($class) use ($subClassName, $logContext): bool {
            /** @var class-string $class */
            /** @var class-string $subClassName */
            try {
                $reflectionClass = new \ReflectionClass($class);
                $subClass = new \ReflectionClass($subClassName);
                /** @phpstan-ignore-next-line */
            } catch (\ReflectionException $e) {
                $this->logger()->warning("2/2 ".$e->getMessage(), $logContext);

                return false;
            }

            return $reflectionClass->isSubclassOf($subClass)
                && !$reflectionClass->isAbstract()
                && !$reflectionClass->isInterface()
                && !$reflectionClass->isTrait();
        });
        if (count($filteredClasses) === 0) {
            $this->logger()->warning("No classes subClassing {subClass} found in namespace '{namespace}'", $logContext);
        } else {
            foreach ($filteredClasses as $class) {
                $logContext['class'] = $class;
                $this->logger()->debug("2/2 - Filter : {class} matches", $logContext);
            }
            $count = count($filteredClasses);
            $this->logger()->notice("2/2 - $count classe(s) found in namespace '{namespace}'", $logContext);
        }

        return $filteredClasses;
    }
    /**
     * Sets up the configuration
     *
     * @return void
     */
    protected function configSetup()
    {
        $definition = $this->getDefinition();
        $definition->addOption(new InputOption('--config', null, InputOption::VALUE_REQUIRED, 'Configuration file.'));
        try {
            $this->input->bind($definition);
        } catch (RuntimeException $e) {
            // Errors must be ignored, full binding/validation happens later when the command is known.
        }
        if (null !== $this->input->getOption('config')) {
            /** @var string $filename */
            $filename = $this->input->getOption('config');
            if ($filename && file_exists($filename)) {
                $this->config->addFile($filename);
            } else {
                throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $filename));
            }
        }
        $this->config->build();
    }
    /**
     * Detect verbosity specified on command line
     *
     * @param Input $input
     *
     * @return integer
     */
    protected function getVerbosity($input)
    {
        $verbosity = OutputInterface::VERBOSITY_NORMAL;
        if (true === $input->hasParameterOption(['--quiet', '-q'], true)) {
            $verbosity = OutputInterface::VERBOSITY_QUIET;
        } else {
            $v = $input->getParameterOption('--verbose', false, true);
            $v3 = $input->hasParameterOption('--verbose=3', true);
            $v2 = $input->hasParameterOption('--verbose=2', true);
            $v1 = $input->hasParameterOption('--verbose=1', true);
            $vv = $input->hasParameterOption('--verbose', true);
            if ($input->hasParameterOption('-vvv', true) || $v3 || 3 === $v) {
                $verbosity = OutputInterface::VERBOSITY_DEBUG;
            } elseif ($input->hasParameterOption('-vv', true) || $v2 || 2 === $v) {
                $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE;
            } elseif ($input->hasParameterOption('-v', true) || $v1 || $vv || $v) {
                $verbosity = OutputInterface::VERBOSITY_VERBOSE;
            }
        }

        return $verbosity;
    }
    /**
     * Builds a logger
     *
     * @return Monolog
     */
    protected function buildLogger()
    {
        $logContext = [ 'name' => "buildLogger" ];
        // Create logger
        $logger = new Monolog('monolog');
        $monologLevels = [
            OutputInterface::VERBOSITY_QUIET         => MonLvl::fromName('WARNING'),
            OutputInterface::VERBOSITY_NORMAL        => MonLvl::fromName('NOTICE'),
            OutputInterface::VERBOSITY_VERBOSE       => MonLvl::fromName('INFO'),
            OutputInterface::VERBOSITY_VERY_VERBOSE  => MonLvl::fromName('DEBUG'),
            OutputInterface::VERBOSITY_DEBUG         => MonLvl::fromName('DEBUG'),
        ];
        // see if we have a log file and initialise monolog accordingly
        $ld = $this->config->get(ApplicationSchema::LOG_DIRECTORY);
        if (is_string($ld)) {
            if (!file_exists($ld)) {
                set_error_handler(function ($errno, $errstr) use ($ld) {
                    throw new \Exception(sprintf("Can't create log directory '%s' - cause : %s", $ld, $errstr));
                });
                mkdir($ld);
                restore_error_handler();
            }
            $logfile = $this->config->get(ApplicationSchema::LOG_FILENAME);
            if (!is_string($logfile)) {
                $logfile = "$this->appName.log";
            }
            $logContext['file'] = "$ld/$logfile";
            $this->logger()->notice("starting file logger. Filename = {file}", $logContext);
            $sh = new StreamHandler("$ld/$logfile", MonLvl::fromName('DEBUG'));
            $sh->pushProcessor(new PsrLogMessageProcessor());
            $dateFormat = ApplicationSchema::DEFAULT_DATE_FORMAT;
            if (is_string($this->config->get(ApplicationSchema::LOG_DATE_FORMAT))) {
                $dateFormat = $this->config->get(ApplicationSchema::LOG_DATE_FORMAT);
            }
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
            // change it to our needs.
            $outputFormat = ApplicationSchema::DEFAULT_OUTPUT_FORMAT;
            if (is_string($this->config->get(ApplicationSchema::LOG_OUTPUT_FORMAT))) {
                $outputFormat = $this->config->get(ApplicationSchema::LOG_OUTPUT_FORMAT);
            }
            $formatter = new LineFormatter($outputFormat, $dateFormat);
            $sh->setFormatter($formatter);
            $logger->pushHandler($sh);
        }
        /** @var \Psr\Log\LoggerInterface $containerLogger */
        $containerLogger = $this->container->get('roboLogger');
        $consoleHandler = new PsrHandler($containerLogger, $monologLevels[$this->container->get('verbosity')]);
        $logger->pushHandler($consoleHandler);

        return $logger;
    }
}
