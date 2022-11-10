<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use Consolidation\Config\ConfigInterface;
use Consolidation\Log\Logger;
use DgfipSI1\Application\ApplicationSchema as CONF;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Level as MonLvl;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * class Application
 *
 */
class ApplicationLogger implements LoggerInterface
{
    /** @var Monolog $logger */
    protected $logger;

    /**
     * Constructor for Application logger class
     *
     * @param ConfigInterface $config
     * @param OutputInterface $output
     * @param int             $verbosity
     *
     * return void
     */
    public function __construct($config, $output, $verbosity)
    {
        $monologLevels = [
            OutputInterface::VERBOSITY_QUIET         => MonLvl::fromName('WARNING'),
            OutputInterface::VERBOSITY_NORMAL        => MonLvl::fromName('NOTICE'),
            OutputInterface::VERBOSITY_VERBOSE       => MonLvl::fromName('INFO'),
            OutputInterface::VERBOSITY_VERY_VERBOSE  => MonLvl::fromName('DEBUG'),
            OutputInterface::VERBOSITY_DEBUG         => MonLvl::fromName('DEBUG'),
        ];
        $this->logger = new Monolog('application_logger');
        $consoleLogger = new Logger($output);
        $consoleLogger->setLogOutputStyler(new \Robo\Log\RoboLogStyle());


        $logDirectory = $config->get(CONF::LOG_DIRECTORY);
        if (is_string($logDirectory)) {
            if (!file_exists($logDirectory)) {
                set_error_handler(function ($errno, $errstr) use ($logDirectory) {
                    $errMsg = "Can't create log directory '%s' - cause : %s";
                    throw new \Exception(sprintf($errMsg, $logDirectory, $errstr));
                });
                mkdir($logDirectory);
                restore_error_handler();
            }
            $logfile = $config->get(CONF::LOG_FILENAME);
            if (!is_string($logfile)) {
                $logfile = $config->get(CONF::APPLICATION_NAME).".log";
            }
            $logContext = [ 'name' => 'new ApplicationLogger', 'file' => "$logDirectory/$logfile" ];
            $consoleLogger->notice("starting file logger. Filename = {file}", $logContext);
            /** @var \Monolog\Level $configuredLogLevel */
            $configuredLogLevel = $config->get(CONF::LOG_LEVEL);
            $sh = new StreamHandler("$logDirectory/$logfile", $configuredLogLevel);
            $sh->pushProcessor(new PsrLogMessageProcessor());
            $dateFormat = CONF::DEFAULT_DATE_FORMAT;
            if (is_string($config->get(CONF::LOG_DATE_FORMAT))) {
                $dateFormat = $config->get(CONF::LOG_DATE_FORMAT);
            }
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
            // change it to our needs.
            $outputFormat = CONF::DEFAULT_OUTPUT_FORMAT;
            if (is_string($config->get(CONF::LOG_OUTPUT_FORMAT))) {
                $outputFormat = $config->get(CONF::LOG_OUTPUT_FORMAT);
            }
            $formatter = new LineFormatter($outputFormat, $dateFormat);
            $sh->setFormatter($formatter);
            $this->logger->pushHandler($sh);
        }
        $consoleHandler = new PsrHandler($consoleLogger, $monologLevels[$verbosity]);
        $this->logger->pushHandler($consoleHandler);
    }
    /**
     * Detect verbosity specified on command line
     *
     * @param InputInterface $input
     *
     * @return integer
     */
    public static function getVerbosity($input)
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
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
    /**
     * logging wrapper
     *
     * @param \Monolog\Level       $level
     * @param string|\Stringable   $message
     * @param array<string,string> $context
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
