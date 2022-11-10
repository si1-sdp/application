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
use DgfipSI1\Application\Utils\ClassDiscoverer;
use League\Container\Argument\Literal\IntegerArgument;
use League\Container\ContainerAwareInterface;
use League\Container\Definition\DefinitionInterface;
use Psr\Log\LoggerInterface;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Symfony\Component\Console\Application as SymfoApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
    protected $config;
    /** @var ConfigHelper $intConfig Application internal configuration */
    protected $intConfig;
    /** @var LoggerInterface $logger */
    protected $logger;

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
        $this->config->setLogger($this->logger);
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

        // Create and configure container.
        if (self::ROBO_APPLICATION === $this->appType) {
            Robo::configureContainer($this->container, $this, $this->config, $this->input, $this->output);
            if (!$this->container->has('roboLogger')) {
                 $this->container->extend('logger')->setAlias('roboLogger');
            }
            $verbosity = ApplicationLogger::getVerbosity($this->input);
            $this->container->addShared('verbosity', new IntegerArgument($verbosity));
            $this->container->addShared('internal_configuration', $this->intConfig);
            $this->container->addShared('logger', ApplicationLogger::class)
                ->addArguments(['internal_configuration', 'output', 'verbosity']);
            Robo::finalizeContainer($this->container);
        } else {
            $this->configureSymfonyContainer();
        }
        // discover commands
        $this->discoverCommands();

        $this->setupOptions();
        $this->setupEventListeners();
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
                $this->intConfig->setDefault(CONF::RUNTIME_INT_CONFIG, $file);
                $this->intConfig->setDefault(CONF::RUNTIME_ROOT_DIRECTORY, realpath(dirname($file)));

                break;
            }
        }
        if (!$this->intConfig->get(CONF::RUNTIME_INT_CONFIG)) {
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
        $this->intConfig->set(CONF::APPLICATION_NAME, $this->appName);
        parent::setVersion($this->appVersion);
        $this->intConfig->set(CONF::APPLICATION_VERSION, $this->appVersion);
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
        $disc = new ClassDiscoverer($this->classLoader);
        $disc->setLogger($this->logger);
        $cClasses = $disc->discoverPsr4Classes($nameSpace, $subClass);
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
        $disc = new ClassDiscoverer($this->classLoader);
        $disc->setLogger($this->logger);
        $discoveredClasses = $disc->discoverPsr4Classes($nameSpace, $subClass);
        $returned = [];
        foreach ($discoveredClasses as $discoveredClass) {
            // $concrete = new $discoveredClass();
            try {
                $serviceDefinition = $this->addSharedService($discoveredClass, $attributeNameForId, $tag);
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
     * @param object|string $objectOrClass
     * @param string        $attributeNameForId
     * @param string|null   $tag
     *
     * @return DefinitionInterface
     *
     * @throws \LogicException
     */
    protected function addSharedService($objectOrClass, $attributeNameForId = 'name', $tag = null)
    {
        $logContext = [ 'name' => 'addSharedService' ];
        /* if (!is_object($concrete)) {
            throw new LogicException("invalid Service provided");
        } */
        $serviceId = null;
        $reflectedClass = is_object($objectOrClass)? $objectOrClass::class : $objectOrClass;
        /** @var class-string $reflectedClass */
        $attributes = (new \ReflectionClass($reflectedClass))->getAttributes();
        foreach ($attributes as $attribute) {
            $attributeArguments = $attribute->getArguments();
            if (array_key_exists($attributeNameForId, $attributeArguments)) {
                $serviceId = $attributeArguments[$attributeNameForId];
                break;
            }
        }
        if (null === $serviceId) {
            $msg = "invalid service id for class %s, %s attribute argument not found";
            throw new LogicException(sprintf($msg, $reflectedClass, $attributeNameForId));
        }
        $serviceDefinition = $this->container->addShared($serviceId, $objectOrClass);
        $this->logger()->debug("Adding service $serviceId to container", $logContext);
        if ((null !== $tag) && ("" !== $tag)) {
            $this->logger()->debug("Add tag $tag to service $serviceId", $logContext);
            $serviceDefinition->addTag($tag);
        }

        return $serviceDefinition;
    }

    /**
     * Configure the container for symfony applications
     *
     * @return void
     */
    protected function configureSymfonyContainer()
    {
            // Self-referential container reference for the inflector
            $this->container->add('container', $this->container);

            $this->config->set('options.decorated', $this->output->isDecorated());
            $this->config->set('options.interactive', $this->input->isInteractive());

            $this->container->addShared('application', $this);
            $this->container->addShared('config', $this->config);
            $this->container->addShared('input', $this->input);
            $this->container->addShared('output', $this->output);
            $verbosity = ApplicationLogger::getVerbosity($this->input);
            $this->container->addShared('verbosity', new IntegerArgument($verbosity));
            $this->container->addShared('internal_configuration', $this->intConfig);
            $this->container->addShared('logger', ApplicationLogger::class)
                ->addArguments(['internal_configuration', 'output', 'verbosity']);
            //self::addShared($container, 'outputAdapter', \Robo\Common\OutputAdapter::class);
            $logger = $this->container->get('logger');    /** @var LoggerInterface $logger */
            $this->logger = $logger;

            $this->container->addShared('classLoader', $this->classLoader);
            //$this->container->addShared('logger', $this->logger);
            $this->container->addShared('inputOptionsToConfig', InputOptionsToConfig::class)
                ->addMethodCall('setApplication', ['application']);
            $this->container->addShared('hookManager', \Consolidation\AnnotatedCommand\Hooks\HookManager::class)
                ->addMethodCall('addCommandEvent', ['inputOptionsToConfig']);
            $this->container->addShared('eventDispatcher', \Symfony\Component\EventDispatcher\EventDispatcher::class)
                ->addMethodCall('addSubscriber', ['hookManager']);
            //$this->container->addShared('logger', $logger);
            // Make sure the application is appropriately initialized.
            $this->setAutoExit(false);
            // see also applyInflectorsBeforeContainerConfiguration to have this done to objects before bootstrap...
            $this->container->inflector(ConfigAwareInterface::class)->invokeMethod('setConfig', ['config']);
            $this->container->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', ['logger']);
            $this->container->inflector(ContainerAwareInterface::class)->invokeMethod('setContainer', ['container']);
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
    protected function applyInflectorsBeforeContainerConfiguration($object): void
    {
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
                $logCtx = [ 'file' => $filename];
                $this->logger->debug("Loading configfile: {file}", $logCtx);
                $this->config->addFile($filename);
            } else {
                throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $filename));
            }
        } else {
            /* Load default configuration */
            $dir = $this->intConfig->get(CONF::CONFIG_DIRECTORY) ?? $this->intConfig->get(CONF::RUNTIME_ROOT_DIRECTORY);
            if (null === $dir) {
                $dir = realpath($_SERVER['PWD']);
            }
            /** @var string $dir */
            if (!is_dir($dir)) {
                throw new ConfigFileNotFoundException(sprintf("Configuration directory '%s' not found", $dir));
            }
            /** @var array<string> $pathPatterns */
            $pathPatterns = $this->intConfig->get(CONF::CONFIG_PATH_PATTERNS);
            /** @var array<string> $namePatterns */
            $namePatterns = $this->intConfig->get(CONF::CONFIG_NAME_PATTERNS);
            $recurse =  $this->intConfig->get(CONF::CONFIG_SEARCH_RECURSIVE) ? -1 : 0;
            /** @var bool $sortByName */
            $sortByName = $this->intConfig->get(CONF::CONFIG_SORT_BY_NAME);
            $logCtx['paths'] = "[".($pathPatterns ? implode(', ', $pathPatterns) : '')."]";
            $logCtx['names'] = "[".($namePatterns ? implode(', ', $namePatterns) : '')."]";
            $logCtx['sort']  = $sortByName ? 'name' : 'path';
            $logCtx['depth'] = $recurse;
            $msg = "Loading config: paths={paths} - names={names} - sort by {sort} - depth = {depth}";
            $this->logger->debug($msg, $logCtx);
            $this->config->findConfigFiles($dir, $pathPatterns, $namePatterns, $sortByName, $recurse);
        }
        if (null !== $this->input->getOption('add-config')) {
            /** @var array<string> $filenames */
            $filenames = $this->input->getOption('add-config');
            foreach ($filenames as $file) {
                if ($file && file_exists($file)) {
                    $logCtx = [ 'file' => $file];
                    $this->logger->debug("Adding configfile: {file}", $logCtx);
                    $this->config->addFile($file);
                } else {
                    throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $file));
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
        $disc = new ClassDiscoverer($this->classLoader);
        $disc->setLogger($this->logger);
        $classes     = $disc->discoverPsr4Classes($namespace, ApplicationAwareInterface::class, silent: true);
        foreach ($classes as $class) {
          /** @var ApplicationAwareInterface $configurator */
            $configurator = new $class();
            $this->applyInflectorsBeforeContainerConfiguration($configurator);
            $this->config->addSchema($configurator);
        }
    }

    /**
     * - add all config schemas from commands and global to have a full schema
     * - add global options
     *
     * @return void
     */
    protected function setupEventListeners()
    {
        /** @var string $namespace */
        $namespace   = $this->intConfig->get(CONF::APPLICATION_NAMESPACE);
        $disc = new ClassDiscoverer($this->classLoader);
        $disc->setLogger($this->logger);
        $classes     = $disc->discoverPsr4Classes($namespace, EventSubscriberInterface::class, silent: true);
        $container = $this->container();
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get("eventDispatcher");
        foreach ($classes as $class) {
            // try to find the service if already registered to the container
            $serviceName = null;
            $instance = null;
            $existingServices = $container->getServices(baseInstance: $class);
            if (count($existingServices) > 0) {
                $serviceName = array_keys($existingServices)[0];
                $instance = array_values($existingServices)[0];
                $this->logger()->debug(
                    sprintf("found service %s already registered as being an EventSubscriber", $serviceName)
                );
            } else {
                $serviceName = "EventSubscriber.".$class;
                $container->add($serviceName, $class);
                $instance = $container->get($serviceName);
            }
            /** @var EventSubscriberInterface $instance */
            // $eventSubscriber = new $class();
            $eventDispatcher->addSubscriber($instance);
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
        $disc = new ClassDiscoverer($this->classLoader);
        $disc->setLogger($this->logger);
        $classes     = $disc->discoverPsr4Classes($namespace, ApplicationAwareInterface::class, silent: true);
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
}
