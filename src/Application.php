<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use League\Container\Container;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Level as MonLvl;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Robo\Config\Config;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as SymfoApp;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\Output;

/**
 * class Application
 */
class Application
{
    /** @var string $appName */
    private $appName;
    /** @var string $appVersion */
    private $appVersion;
    /** @var Input $input */
    private $input;
    /** @var Output $output */
    private $output;
    /** @var Container $container */
    private $container;
    /** @var array<class-string> */
    private $commandClasses;
    /** @var ConfigHelper $config */
    private $config;
    /**
     * constructor
     *
     * @param Input|null                  $input
     * @param Output|null                 $output
     * @param ConfigurationInterface|null $confSchema
     */
    public function __construct($input = null, $output = null, $confSchema = null)
    {
        $appConf = new ApplicationSchema($confSchema);
        $this->config    = new ConfigHelper($appConf);

        $this->container = new Container();

        $this->input  = $input  ?? new StringInput('');
        $this->output = $output ?? new \Symfony\Component\Console\Output\ConsoleOutput();
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
     * @return Monolog
     */
    public function logger()
    {
        /** @var Monolog $logger */
        $logger = $this->container->get('logger');

        return $logger;
    }

    /**
     * sets the application name
     *
     * @param string $name
     *
     * @return self
     */
    public function setApplicationName($name)
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
    public function setApplicationVersion($version)
    {
        $this->appVersion = $version;

        return $this;
    }
    /** sets the list of classes containing robo commands
     *
     * @param array<class-string> $roboCommandClasses
     *
     * @return void
     */
    public function setRoboCommands($roboCommandClasses)
    {
        $this->commandClasses = $roboCommandClasses;
    }
    /**
     * Finalize application
     *
     * @param integer $configOptions
     *
     * @return void
     */
    public function finalize($configOptions = 0)
    {
        // Create applicaton.
        $this->setApplicationNameAndVersion();
        $application = new SymfoApp($this->appName, $this->appVersion);


        $this->config->build($configOptions);

        // Create and configure container.
        Robo::configureContainer(
            $this->container,
            $application,
            $this->config,
            $this->input,
            $this->output
        );

        $verbosity = $this->getVerbosity($this->input);
        $this->container->addShared('verbosity', $verbosity);

        if (!$this->container->has('roboLogger')) {
            $this->container->extend('logger')->setAlias('roboLogger');
        }
        $logger = $this->buildLogger();

        // didn't find a way to replace a service => rename old and create new
        $this->container->addShared('logger', $logger);
        Robo::finalizeContainer($this->container);
    }
    /**
     * Run command
     *
     * @return integer
     */
    public function run()
    {
        // Instantiate Robo Runner.
        $runner = new RoboRunner();
        $runner->setContainer($this->container);
        /** @var Input $input */
        $input       = $this->container->get('input');
        /** @var Output $output */
        $output      = $this->container->get('output');
        /** @var \Robo\Application $application */
        $application =  $this->container->get('application');
        /** @phpstan-ignore-next-line */
        $statusCode  = $runner->run($input, $output, $application, $this->commandClasses);

        return $statusCode;
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
