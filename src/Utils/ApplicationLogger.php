<?php
/*
 * This file is part of DgfipSI1\Application\Utils
 */
namespace DgfipSI1\Application\Utils;

use Consolidation\Config\ConfigInterface;
use Consolidation\Log\Logger;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Exception\RuntimeException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\ConfigHelper\ConfigHelperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Level as MonLvl;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * class Application
 *
 */
class ApplicationLogger
{
    /**
     * Initialize a logger with only the PsrHandler
     *
     * @param OutputInterface $output
     *
     * @return LoggerInterface
     */
    public static function initLogger($output)
    {
        $monologLevels = [
            OutputInterface::VERBOSITY_QUIET         => MonLvl::fromName('WARNING'),
            OutputInterface::VERBOSITY_NORMAL        => MonLvl::fromName('NOTICE'),
            OutputInterface::VERBOSITY_VERBOSE       => MonLvl::fromName('INFO'),
            OutputInterface::VERBOSITY_VERY_VERBOSE  => MonLvl::fromName('DEBUG'),
            OutputInterface::VERBOSITY_DEBUG         => MonLvl::fromName('DEBUG'),
        ];
        $vlMap = [
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
        ];
        $logger = new Monolog('application_logger');
        $consoleLogger = new Logger($output, $vlMap);
        $consoleLogger->setLogOutputStyler(new \Robo\Log\RoboLogStyle());
        $consoleHandler = new PsrHandler($consoleLogger, $monologLevels[$output->getVerbosity()]);
        $logger->pushHandler($consoleHandler);

        return $logger;
    }
    /**
     * Configures the logger after having read the configuration
     *
     * @param LoggerInterface       $logger
     * @param ConfigHelperInterface $config
     * @param string                $homeDir
     *
     * @return void
     */
    public static function configureLogger($logger, $config, $homeDir)
    {
        if (!$logger instanceof Monolog) {
            $logger->alert("Advanced logger configuration applies only to Monolog");

            return;
        }
        $logDirectory = $config->get(CONF::LOG_DIRECTORY);
        if (is_string($logDirectory)) {
            if (substr($logDirectory, 0, 1) !== '/' && strpos($logDirectory, '://') === false) {
                $logDirectory = (string) realpath($homeDir).DIRECTORY_SEPARATOR.$logDirectory;
            }
            if (!file_exists($logDirectory)) {
                set_error_handler(function ($errno, $errstr) use ($logDirectory) {
                    $errMsg = "Can't create log directory '%s' - cause : %s";
                    throw new RuntimeException(sprintf($errMsg, $logDirectory, $errstr));
                });
                mkdir($logDirectory);
                restore_error_handler();
            }
            $logfile = $config->get(CONF::LOG_FILENAME);
            if (!is_string($logfile)) {
                $logfile = $config->get(CONF::APPLICATION_NAME).".log";
            }
            $logContext = [ 'name' => 'new ApplicationLogger', 'file' => "$logDirectory/$logfile" ];
            $logger->info("starting file logger. Filename = {file}", $logContext);
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
            $logger->pushHandler($sh);
        }
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
            } elseif ($input->hasParameterOption('-v', true) || $v1 || $vv || (bool) $v) {
                $verbosity = OutputInterface::VERBOSITY_VERBOSE;
            }
        }

        return $verbosity;
    }
}
