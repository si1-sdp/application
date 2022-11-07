<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use DgfipSI1\Application\Config\BaseSchema;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Listeners\InputOptionsToConfig;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\ApplicationTypeException;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;

use DgfipSI1\ConfigHelper\ConfigHelper;

use Composer\Autoload\ClassLoader;
use Consolidation\Config\Util\ConfigOverlay;
use Consolidation\Log\Logger;
use DgfipSI1\Application\Listeners\InputOptionsToConfig as ListenersInputOptionsToConfig;
use League\Container\Definition\DefinitionInterface;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Level as MonLvl;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Psr\Log\LoggerInterface;
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * class Application
 */
class Application extends SymfoApp
{
    public const DEFAULT_MAP_ROOT           = 'options';

    protected const ROBO_APPLICATION        = 'robo';
    protected const SYMFONY_APPLICATION     = 'symfony';
    protected const DEFAULT_APP_CONFIG_FILE = '.application-config.yml';

    private const SYMFONY_SUBCLASS = '\Symfony\Component\Console\Command\Command';
    private const ROBO_SUBCLASS    = '\Robo\Tasks';
    private const SYMFONY_CMD_TAG  = 'symfonyCommand';

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
    /** @var ConfigHelper $intConfig Application internal configuration */
    private $intConfig;
    /** @var LoggerInterface $logger */
    private $logger;

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
        $this->output->setVerbosity($this->getVerbosity($this->input));

        // setup a temporary logger for initialisation
        $this->logger = new Logger($this->output);
        $this->logger->setLogOutputStyler(new \Robo\Log\RoboLogStyle());

        // setup app internal configuration
        $this->setupApplicationConfig();

        $this->config    = new ConfigHelper(new BaseSchema());
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
        return $this->logger;
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
        $this->intConfig->set(CONF::APPLICATION_NAMESPACE, $relativeNamespace);
    }
    /** sets the list of classes containing symfony commands
     *
     * @param string       $relativeNamespace
     * @param class-string $subClass
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
        $this->intConfig->set(CONF::APPLICATION_NAMESPACE, $relativeNamespace);
    }
    /**
     * run application
     *
     * @return int
     */
    public function go()
    {
        $this->finalize();
        $logContext = ['name' => 'go'];
        $statusCode = 0;
        if (self::ROBO_APPLICATION === $this->appType) {
            // Instantiate Robo Runner.
            $runner = new RoboRunner();
            $runner->setContainer($this->container);
            //print(implode("\n", array_keys($this->container->getDefinitions()))."\n\n");
            $this->logger->notice("Launching robo command", $logContext);
            /** @phpstan-ignore-next-line */
            $statusCode  = $runner->run($this->input, $this->output, $this, $this->commandClasses);
        } elseif (self::SYMFONY_APPLICATION === $this->appType) {
            $logContext['cmd_name'] = $this->isSingleCommand() ? 'list' : $this->input->getFirstArgument();
            $this->logger->notice("Launching symfony command '{cmd_name}'", $logContext);
            $statusCode = $this->run($this->input, $this->output);
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
        // load configuration files - handles --config and intConfig->get(dgfip_si1.configuration.files)
        $this->configSetup();

        // set application's name and version
        $this->setApplicationNameAndVersion();

        // discover commands
        $this->discoverCommands();

        $logger = $this->buildLogger();

        // Create and configure container.
        if (self::ROBO_APPLICATION === $this->appType) {
            Robo::configureContainer($this->container, $this, $this->config, $this->input, $this->output);
            // if (!$this->container->has('roboLogger')) {
            //     $this->container->extend('logger')->setAlias('roboLogger');
            // }
            // $this->container->addShared('logger', $logger);
            Robo::finalizeContainer($this->container);
        } else {
            $this->configureSymfonyContainer($logger);
        }

        $this->setupOptions();
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
        parent::setVersion($this->appVersion);
    }
    /**
     * Undocumented function
     *
     * @return void
     */
    protected function discoverCommands()
    {

        $configuredType       = $this->intConfig->getRaw(CONF::APPLICATION_TYPE);
        /** @var string $configuredNamespace */
        $configuredNamespace  = $this->intConfig->get(CONF::APPLICATION_NAMESPACE);

        if (null !== $this->appType) {
            /* findCommands method has been called - just check it's consistent with configuration */
            if (is_string($configuredType) && $configuredType !== $this->appType) {
                $err = "Type mismatched - find%sCommands lauched but configured type is '%s'";
                throw new ApplicationTypeException(sprintf($err, ucfirst($this->appType), $configuredType));
            }
        } else {
            // no type - means we haven't called find[Robo|Symfony]Commands(namespace)
            // see what we can do from configuration - get the configured default value
            $type = $configuredType ?? $this->intConfig->get(CONF::APPLICATION_TYPE);
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
     * @param string       $nameSpace
     * @param class-string $subClass

     * @return array<string>
     *
     * @throws \ReflectionException
     */
    protected function discoverRoboCommands($nameSpace, $subClass): array
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
     * @param string       $nameSpace
     * @param class-string $subClass
     * @param string|null  $tag
     * @param string       $attributeNameForId

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
     * @param string            $namespace
     * @param class-string|null $dependency Filter classes on class->implementsInterface(dependency)
     *                                      or class->isSubClassOf(dependency)
     * @param bool              $silent     Do not warn if no class found
     *
     * @return array<string>
     *
     * @throws \ReflectionException
     */
    protected function discoverPsr4Classes($namespace, $dependency, $silent = false): array
    {
        $logContext = ['name' => 'discoverPsr4Classes', 'namespace' => $namespace, 'dependency' => $dependency ];
        // discovers classes that are in a directory ($namespace) imediatly under 'src'
        $classes = (new RelativeNamespaceDiscovery($this->classLoader))
            ->setRelativeNamespace($namespace)
            ->getClasses();
        foreach ($classes as $class) {
            $logContext['class'] = $class;
            $this->logger()->debug("1/2 - search {namespace} namespace - found {class}", $logContext);
        }
        $this->logger()->info("1/2 - ".count($classes)." classe(s) found.", $logContext);
        $filteredClasses = [];
        foreach ($classes as $class) {
            try {
                /** @var class-string $class */
                $refClass = new \ReflectionClass($class);
                /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
            } catch (\ReflectionException $e) {
                $this->logger()->warning("2/2 ".$e->getMessage(), $logContext);
                continue;
            }
            if ($refClass->isAbstract() || $refClass->isInterface() || $refClass->isTrait()) {
                continue;
            }
            if (null === $dependency) {
                $filteredClasses[] = $class;
            } else {
                try {
                    $depRef = new \ReflectionClass($dependency);
                    /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
                } catch (\ReflectionException $e) {
                    $this->logger()->warning("2/2 ".$e->getMessage(), $logContext);
                    continue;
                }
                if (($depRef->isInterface() && $refClass->implementsInterface($depRef)) ||
                     $refClass->isSubclassOf($depRef)) {
                    $filteredClasses[] = $class;
                }
            }
        }
        if (empty($filteredClasses) && false === $silent) {
            $msg = "No classes subClassing or implementing {dependency} found in namespace '{namespace}'";
            $this->logger()->warning($msg, $logContext);
        } else {
            foreach ($filteredClasses as $class) {
                $logContext['class'] = $class;
                $this->logger()->debug("2/2 - Filter : {class} matches", $logContext);
            }
            $count = count($filteredClasses);
            $this->logger()->info("2/2 - $count classe(s) found in namespace '{namespace}'", $logContext);
        }

        return $filteredClasses;
    }
    /**
     * Configure the container for symfony applications
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    protected function configureSymfonyContainer($logger)
    {

            // Self-referential container reference for the inflector
            $this->container->add('container', $this->container);

            $this->config->set('options.decorated', $this->output->isDecorated());
            $this->config->set('options.interactive', $this->input->isInteractive());

            $this->container->addShared('application', $this);
            $this->container->addShared('config', $this->config);
            $this->container->addShared('input', $this->input);
            $this->container->addShared('output', $this->output);
            //self::addShared($container, 'outputAdapter', \Robo\Common\OutputAdapter::class);
            $this->container->addShared('classLoader', $this->classLoader);
            $this->container->addShared('logger', $this->logger);
            $this->container->addShared('inputOptionsToConfig', InputOptionsToConfig::class)
                ->addMethodCall('setApplication', ['application']);
            $this->container->addShared('hookManager', \Consolidation\AnnotatedCommand\Hooks\HookManager::class)
                ->addMethodCall('addCommandEvent', ['inputOptionsToConfig']);
            $this->container->addShared('eventDispatcher', \Symfony\Component\EventDispatcher\EventDispatcher::class)
                ->addMethodCall('addSubscriber', ['hookManager']);
            $this->container->addShared('logger', $logger);
            // Make sure the application is appropriately initialized.
            $this->setAutoExit(false);
            // see also applyInflectorsBeforeContainerConfiguration to have this done to objects before bootstrap...
            $this->container->inflector(ConfigAwareInterface::class)->invokeMethod('setConfig', ['config']);
            $this->container->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', ['logger']);
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $this->container->get('eventDispatcher');
            $this->setDispatcher($dispatcher);
    }

    /**
     * Apply the inflectors to an object before the container has been configured
     *
     * @param object $object
     *
     * @return void
     */
    protected function applyInflectorsBeforeContainerConfiguration($object): void {
      if ($object instanceof LoggerAwareInterface) {
        $object->setLogger($this->logger);
      }
      if ($object instanceof ConfigAwareInterface) {
        $object->setConfig($this->config);
      }
    }



    /**
     * Sets up the configuration
     *
     * @return void
     */
    protected function configSetup()
    {
        $this->setupConfigInputOptions();   /* add --config to options                           */
        $this->setupConfigSchema();         /* load classes implementing configAwareInterface    */
        $this->loadConfigFiles();           /* load configuration files                          */
        try {
            $this->config->build();
        } catch (\exception $e) {
            $this->renderThrowable(new RuntimeException($e->getMessage()), $this->output);
            exit(2);
        }
    }
    /**
     * load all configuration files
     *
     * @return void
     */
    protected function loadConfigFiles()
    {
        $logCtx = [ 'name' => 'loadConfigFiles'];
        /* Load configuration specified in --config */
        if (null !== $this->input->getOption('config')) {
            /** @var string $filename */
            $filename = $this->input->getOption('config');
            if ($filename && file_exists($filename)) {
                $this->config->addFile($filename);
            } else {
                throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $filename));
            }
        } else {
        /* Load default configuration */
            /** @var string $directory */
            $directory = $this->intConfig->get(CONF::CONFIG_DIR) ?? '.';
            $directory = ''.realpath($directory);
            if (!is_dir($directory)) {
                throw new ConfigFileNotFoundException(sprintf("Configuration directory '%s' not found", $directory));
            }
            /** see if we asked for this file or if it is a default configuration */
            $askedFiles = !empty($this->intConfig->getRaw(CONF::CONFIG_FILES));
            /** @var array<string> $configuredFiles */
            $configuredFiles = $this->intConfig->get(CONF::CONFIG_FILES);
            foreach ($configuredFiles as $file) {
                $logCtx['file'] = $file;
                $fullPath = $directory.DIRECTORY_SEPARATOR.$file;
                if (file_exists($fullPath)) {
                    $this->config->addFile($fullPath);
                } else {
                    if ($askedFiles) {
                        throw new ConfigFileNotFoundException(sprintf("Config file '%s' not found", $file));
                    }
                    $this->logger()->info("Default config file {file} not found", $logCtx);
                }
            }
        }
    }
    /**
     * - add all config schemas from commands and global to have a full schema
     * - add global options
     *
     * @return void
     */
    protected function setupConfigSchema()
    {
        /** @var string $namespace */
        $namespace   = $this->intConfig->get(CONF::APPLICATION_NAMESPACE);
        $classes     = $this->discoverPsr4Classes($namespace, ApplicationAwareInterface::class, silent: true);
        foreach ($classes as $class) {
          /** @var ApplicationAwareInterface $configurator */
          $configurator = new $class();
          $this->applyInflectorsBeforeContainerConfiguration($configurator);
          $this->config->addSchema($configurator);
        }
    }
    /**
     * setup Options - add options defined in discovered ApplicationAwareInterface classes
     *
     * @return void
     */
    protected function setupOptions()
    {
        /** @var string $namespace */
        $namespace   = $this->intConfig->get(CONF::APPLICATION_NAMESPACE);
        $classes     = $this->discoverPsr4Classes($namespace, ApplicationAwareInterface::class, silent: true);
        foreach ($classes as $class) {
            $serviceName = "configurator.".$class;
            $this->container()->addShared($serviceName, $class);
            /** @var ApplicationAwareInterface $configurator */
            $configurator = $this->container()->get($serviceName);
            if (is_subclass_of($class, Command::class)) {
                $name = '';
                $attributes = (new \ReflectionClass($class))->getAttributes();
                foreach ($attributes as $attribute) {
                    $attributeArguments = $attribute->getArguments();
                    if (array_key_exists('name', $attributeArguments)) {
                        $name = $attributeArguments['name'];
                        break;
                    }
                }
                if (is_string($name) && $name === $this->getCommandName($this->input)) {
                    /** @var Command $command */
                    //print "COMMAND $name\n";
                    $command = $this->container->get($name);
                    $definition = $command->getDefinition();
                } else {
                    continue;
                }
            } else {
                //print "APPLICATION\n";
                $definition = $this->getDefinition();
            }
            foreach ($configurator->getConfigOptions() as $option) {
                //print "  Adding ".$option->getOption()->getName()."\n";
                $definition->addOption($option->getOption());
                // if (null !== $option->getMapping()) {
                //     $name = $option->getOption()->getName();
                //     $this->optMap[$name] = $option->getMapping();
                // }
            }
        }
        $this->config->setActiveContext(ConfigOverlay::PROCESS_CONTEXT);
    }
    /**
     * add --config and --add-config input options #FIXME : test with robo
     *
     * @return void
     */
    protected function setupConfigInputOptions()
    {
        $definition = $this->getDefinition();
        $definition->addOption(new InputOption(
            '--config',
            null,
            InputOption::VALUE_REQUIRED,
            'Specify configuration file (replace default configuration file).'
        ));
        $definition->addOption(new InputOption(
            '--add-config',
            null,
            InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED,
            'Specify additional configuration files (in increasing priority order).'
        ));
        $definition->addOption(new InputOption(
            '--define',
            '-D',
            InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED,
            'define config key value (example: -Doptions.user=foo)'
        ));
        try {
            $this->input->bind($definition);
        } catch (RuntimeException $e) {
            // Errors must be ignored, full binding/validation happens later when the command is known.
        }

        return;
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
        $ld = $this->intConfig->get(CONF::LOG_DIRECTORY);
        if (is_string($ld)) {
            if (!file_exists($ld)) {
                set_error_handler(function ($errno, $errstr) use ($ld) {
                    throw new \Exception(sprintf("Can't create log directory '%s' - cause : %s", $ld, $errstr));
                });
                mkdir($ld);
                restore_error_handler();
            }
            $logfile = $this->intConfig->get(CONF::LOG_FILENAME);
            if (!is_string($logfile)) {
                $logfile = "$this->appName.log";
            }
            $logContext['file'] = "$ld/$logfile";
            $this->logger()->notice("starting file logger. Filename = {file}", $logContext);
            $sh = new StreamHandler("$ld/$logfile", MonLvl::fromName('DEBUG'));
            $sh->pushProcessor(new PsrLogMessageProcessor());
            $dateFormat = CONF::DEFAULT_DATE_FORMAT;
            if (is_string($this->intConfig->get(CONF::LOG_DATE_FORMAT))) {
                $dateFormat = $this->intConfig->get(CONF::LOG_DATE_FORMAT);
            }
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
            // change it to our needs.
            $outputFormat = CONF::DEFAULT_OUTPUT_FORMAT;
            if (is_string($this->intConfig->get(CONF::LOG_OUTPUT_FORMAT))) {
                $outputFormat = $this->intConfig->get(CONF::LOG_OUTPUT_FORMAT);
            }
            $formatter = new LineFormatter($outputFormat, $dateFormat);
            $sh->setFormatter($formatter);
            $logger->pushHandler($sh);
        }
        $consoleHandler = new PsrHandler($this->logger, $monologLevels[$this->getVerbosity($this->input)]);
        $logger->pushHandler($consoleHandler);
        $this->logger = $logger;

        return $logger;
    }
}
