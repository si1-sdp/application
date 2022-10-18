<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\Application\Exception\ApplicationTypeException;
use Composer\Autoload\ClassLoader;
use Consolidation\Log\Logger;
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
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;

/**
 * class Application
 */
class Application extends SymfoApp
{
    protected const ROBO_APPLICATION    = 1;
    protected const SYMFONY_APPLICATION = 2;

    private const SYMFONY_SUBCLASS = '\Symfony\Component\Console\Command\Command';
    private const ROBO_SUBCLASS    = '\Robo\Tasks';

    /** @var string $appName */
    private $appName;
    /** @var string $appVersion */
    private $appVersion;
    /** @var int $appType */
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
        $appConf = new ApplicationSchema($confSchema);
        $this->config    = new ConfigHelper($appConf);

        $this->container = new ApplicationContainer();

        $this->classLoader = $classLoader;
        $this->input  = new ArgvInput($argv);
        $this->output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->output->setVerbosity($this->getVerbosity($this->input));
        $this->logger = new Logger($this->output);
        $this->logger->setLogOutputStyler(new \Robo\Log\RoboLogStyle());
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
            /** @var Monolog $logger */
            $logger = $this->container->get('logger');
        } else {
            return $this->logger;
        }

        return $logger;
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
        $this->configureAndRegisterServices($relativeNamespace, $subClass, 'symfonyCommand', "name");
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
        // Check if appType has been set, probably during finalize when discovering commands
        if ($this->appType !== self::SYMFONY_APPLICATION && $this->appType !== self::ROBO_APPLICATION) {
            throw new ApplicationTypeException('No type. Run find[Robo|Symfony]Commands(namespace)');
        }
        $statusCode = 0;
        switch ($this->appType) {
            case self::ROBO_APPLICATION:
                // Instantiate Robo Runner.
                $runner = new RoboRunner();
                $runner->setContainer($this->container);
                /** @phpstan-ignore-next-line */
                $statusCode  = $runner->run($this->input, $this->output, $this, $this->commandClasses);
                break;
            case self::SYMFONY_APPLICATION:
                $name = $this->isSingleCommand() ? 'list' : $this->input->getFirstArgument();
                $statusCode = parent::run($this->input, $this->output);
                break;
        }

        return $statusCode;
    }

    /**
     * finalize application before run
     *
     * @param integer $configOptions
     *
     * @return void
     */
    protected function finalize($configOptions = 0)
    {
        $this->setApplicationNameAndVersion();
        /** @var \Symfony\Component\Console\Command\Command $command */
        foreach ($this->container->getServices(tag: 'symfonyCommand') as $id => $command) {
            $this->add($command);
        }

        $this->config->build($configOptions);
        // Create and configure container.
        Robo::configureContainer(
            $this->container,
            $this,
            $this->config,
            $this->input,
            $this->output
        );
        //print_r(array_keys($this->container->getDefinitions()));
        //$this->container->add('container', $this->container);

        $verbosity = $this->getVerbosity($this->input);
        Robo::addShared($this->container, 'verbosity', $verbosity);

        if (!$this->container->has('roboLogger')) {
            $this->container->extend('logger')->setAlias('roboLogger');
        }
        $logger = $this->buildLogger();

        // didn't find a way to replace a service => rename old and create new
        Robo::addShared($this->container, 'logger', $logger);
        Robo::finalizeContainer($this->container);
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
            $appName = $this->config()->get(ApplicationSchema::APPLICATION_NAME);
            $this->appName = $appName;
            if (!$this->appName) {
                throw new NoNameOrVersionException("Application name missing");
            }
        }
        if (!$this->appVersion) {
            /** @var string $appVersion */
            $appVersion = $this->config()->get(ApplicationSchema::APPLICATION_VERSION);
            $this->appVersion = $appVersion;
            if (!$this->appVersion) {
                throw new NoNameOrVersionException("Version missing");
            }
        }
        parent::setName($this->appName);
        parent::setVersion($this->appVersion);
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
        $commandClasses = $this->discoverPsr4Classes($nameSpace, $subClass);
        $commands = [];
        foreach ($commandClasses as $commandClass) {
            /** @var class-string $commandClass */
            $reflectionClass = new \ReflectionClass($commandClass);
            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $reflectionClass->getName()) {
                    $commands[] = $method->getName();
                }
            }
        }
        if (count($commands) > 0) {
            $logContext['count'] = count($commands);
            $this->logger()->notice("{count} command(s) found", $logContext);
        }

        return $commandClasses;
    }


    /**
     * discovers classes in a namespace and register each instance to the container
     *
     * @param string      $nameSpace
     * @param string      $subClass
     * @param string|null $tag
     * @param string|null $attributeNameForId

     * @return array<string>
     *
     * @throws \ReflectionException
     */
    protected function configureAndRegisterServices(
        string $nameSpace,
        string $subClass,
        string $tag = null,
        string $attributeNameForId = null
    ): array {
        $logContext = [ 'name' => 'configureAndRegisterServices' ];
        $discoveredClasses = $this->discoverPsr4Classes($nameSpace, $subClass);
        $returned = [];
        foreach ($discoveredClasses as $discoveredClass) {
            $concrete = new $discoveredClass();
            try {
                $serviceDefinition = $this->addSharedService(
                    $concrete,
                    $attributeNameForId,
                    $subClass,
                    $tag
                );
                $returned[] = $serviceDefinition->getAlias();
            } catch (\LogicException $e) {
                $logContext['class'] = $discoveredClass;
                $logContext['errorMessage'] = $e->getMessage();
                $this->logger()->warning(
                    "Service could not be added for {commandClass}: {errorMessage}",
                    $logContext
                );
            }
        }
        if (count($returned) > 0) {
            $logContext['count'] = count($returned);
            $this->logger()->notice("{count} service(s) found", $logContext);
        }

        return $returned;
    }

    /**
     * Add a shared service to the container.
     * The id of the service is automatically discovered using PHP attributes.
     * A tag can be automatically added.
     *
     * @param string|object $concrete
     * @param string|null   $attributeNameForId
     * @param string|null   $subClassRestriction
     * @param string|null   $tag
     *
     * @return DefinitionInterface
     *
     * @throws \LogicException
     */
    protected function addSharedService(
        $concrete,
        string $attributeNameForId = null,
        string $subClassRestriction = null,
        string $tag = null
    ): DefinitionInterface {
        if (!is_object($concrete)) {
            throw new LogicException("invalid Service provided");
        }
        if ((null === $subClassRestriction) || !is_subclass_of($concrete, $subClassRestriction)) {
            throw new LogicException(sprintf("invalid Service provided: not subclass of %s", $subClassRestriction));
        }
        if (null === $attributeNameForId) {
            $attributeNameForId = "name";
        }
        if (!$attributeNameForId) {
            throw new LogicException("invalid attribute name provided for service id");
        }
        $serviceId = null;
        try {
            $attributes = (new \ReflectionClass($concrete::class))->getAttributes();
            foreach ($attributes as $attribute) {
                $attributeArguments = $attribute->getArguments();
                if (array_key_exists($attributeNameForId, $attributeArguments)) {
                    $serviceId = $attributeArguments[$attributeNameForId];
                    break;
                }
            }
            if (null === $serviceId) {
                throw new LogicException(
                    sprintf(
                        "invalid service id for class %s, %s attribute argument not found",
                        $concrete::class,
                        $attributeNameForId
                    )
                );
            }
        } catch (\ReflectionException) {
            throw new LogicException("invalid service id, could not decode attributes");
        }
        $serviceDefinition = $this->container->addShared($serviceId, $concrete);
        if ((null !== $tag) && ("" !== $tag)) {
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
            $this->logger()->warning("No classes subClassing {subClass} found in namespace {namespace}", $logContext);
        } else {
            foreach ($filteredClasses as $class) {
                $logContext['class'] = $class;
                $this->logger()->debug("2/2 - Filter : {class} matches", $logContext);
            }
            $logContext['count'] = count($filteredClasses);
            $this->logger()->notice("{count} classe(s) found in namespace {namespace}", $logContext);
        }

        return $filteredClasses;
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
