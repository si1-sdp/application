<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Composer\Console\Input\InputArgument;
use Consolidation\Config\ConfigInterface;
use Consolidation\Config\Util\ConfigOverlay;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Application;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Contracts\AppAwareInterface;
use DgfipSI1\Application\Contracts\AppAwareTrait;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * envent subscriber that loads all input options values into config
 */
class ConfigLoader implements
    EventSubscriberInterface,
    ConfigAwareInterface,
    LoggerAwareInterface,
    AppAwareInterface,
    ContainerAwareInterface
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use AppAwareTrait;
    use ContainerAwareTrait;

    /** @var string $configDir */
    protected $configDir;

    /** @var array<string> $namePatterns */
    protected $namePatterns;

    /** @var array<string> $pathPatterns */
    protected $pathPatterns;

    /** @var int $depth */
    protected $depth;

    /** @var bool $sortByName */
    protected $sortByName;

    /** @var string|null $appRoot */
    protected $appRoot;

    /**
     * {@inheritdoc}
    */
    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::COMMAND => [ 'loadConfiguration', 100 ]];
    }

    /**
     * Setup necessary parameters from internal configuration
     *
     * @param ConfigInterface $config
     *
     * @return void
     */
    public function configure($config)
    {
        /** @var string $configDir */
        $configDir             = $config->get(CONF::CONFIG_DIRECTORY);
        /** @var array<string> $configNamePatterns */
        $configNamePatterns    = $config->get(CONF::CONFIG_NAME_PATTERNS);
        /** @var array<string> $configPathPatterns */
        $configPathPatterns    = $config->get(CONF::CONFIG_PATH_PATTERNS);
        /** @var int $configDepth */
        $configDepth = $config->get(CONF::CONFIG_SEARCH_RECURSIVE) ? -1 : 0;
        /** @var bool $configSortByName */
        $configSortByName      = $config->get(CONF::CONFIG_SORT_BY_NAME);
        /** @var string $appRoot */
        $appRoot               = $config->get(CONF::RUNTIME_ROOT_DIRECTORY);
        $this->configDir       = $configDir;
        $this->namePatterns    = $configNamePatterns;
        $this->pathPatterns    = $configPathPatterns;
        $this->depth           = $configDepth;
        $this->sortByName      = $configSortByName;
        $this->appRoot         = $appRoot;
    }
    /**
     * Loads all configuration files
     *
     * @param ConsoleCommandEvent $event
     *
     * @return void
     */
    public function loadConfiguration(ConsoleCommandEvent $event)
    {
        $this->configureSchemas();
        if (false === $this->loadConfigFromOptions($event)) {
            $this->loadConfigFiles();
        }
        $this->addConfigFromOptions($event);
        /** @var ConfigHelper $config */
        $config = $this->getConfig();
        $config->setActiveContext(ConfigOverlay::PROCESS_CONTEXT);
    }

    /**
     * Configure schema according to ApplicationAware classes
     *
     * @return void
     */
    protected function configureSchemas()
    {
        $configurators = [];
        if ($this->getContainer()->has(AbstractApplication::GLOBAL_CONFIG_TAG)) {
            /** @var array<ApplicationAwareInterface> $globalConfigurators */
            $globalConfigurators = $this->getContainer()->get(AbstractApplication::GLOBAL_CONFIG_TAG);
            $configurators = array_merge($configurators, $globalConfigurators) ;
        }
        if ($this->getContainer()->has(AbstractApplication::COMMAND_CONFIG_TAG)) {
            /** @var array<ApplicationAwareInterface> $commandConfigurators */
            $commandConfigurators = $this->getContainer()->get(AbstractApplication::COMMAND_CONFIG_TAG);
            $configurators = array_merge($configurators, $commandConfigurators) ;
        }
        foreach ($configurators as $configurator) {
            /** @var ConfigHelper $config */
            $config = $this->getConfig();
            /** @var ApplicationAwareInterface $configurator */
            $config->addSchema($configurator);
        }
    }
    /**
     * load config file specified in --config option
     *
     * @param ConsoleCommandEvent $event
     *
     * @return bool
     */
    protected function loadConfigFromOptions($event)
    {
        /** @var ConfigHelper $config */
        $config = $this->getConfig();

        /* Load configuration specified in --config */
        if ($event->getInput()->hasOption('config') && null !== $event->getInput()->getOption('config')) {
            /** @var string $filename */
            $filename = $event->getInput()->getOption('config');
            if ($filename && file_exists($filename)) {
                $logCtx = [ 'file' => $filename];
                $this->getLogger()->debug("Loading configfile: {file}", $logCtx);
                $config->addFile($filename);
            } else {
                throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $filename));
            }

            return true;
        }

        return false;
    }
    /**
     * Load configuration files specified in .application-config.yml
     *
     * @return void
     */
    protected function loadConfigFiles()
    {
        $logCtx = ['name' => 'loadConfigFiles'];
        /** @var ConfigHelper $config */
        $config = $this->getConfig();
        $askedConfig = true;
        if (null === $this->configDir) {
            $askedConfig = false;
            $dir = $this->appRoot ?? realpath($_SERVER['PWD']);
        } else {
            $dir = $this->configDir;
        }
        /** @var string $dir */
        if (!is_dir($dir)) {
            if (true === $askedConfig) {
                throw new ConfigFileNotFoundException(sprintf("Configuration directory '%s' not found", $dir));
            }
        } else {
            $logCtx['paths'] = "[".($this->pathPatterns ? implode(', ', $this->pathPatterns) : '')."]";
            $logCtx['names'] = "[".($this->namePatterns ? implode(', ', $this->namePatterns) : '')."]";
            $logCtx['sort']  = $this->sortByName ? 'name' : 'path';
            $logCtx['depth'] = $this->depth;
            $msg = "Loading config: paths={paths} - names={names} - sort by {sort} - depth = {depth}";
            $this->getLogger()->debug($msg, $logCtx);
            $config->findConfigFiles($dir, $this->pathPatterns, $this->namePatterns, $this->sortByName, $this->depth);
        }
    }
    /**
     * add config files specified in --add-config
     *
     * @param ConsoleCommandEvent $event
     *
     * @return void
     */
    protected function addConfigFromOptions($event)
    {
        /** @var ConfigHelper $config */
        $config = $this->getConfig();
        if ($event->getInput()->hasOption('add-config') && null !== $event->getInput()->getOption('add-config')) {
            /** @var array<string> $filenames */
            $filenames = $event->getInput()->getOption('add-config');
            foreach ($filenames as $file) {
                if ($file && file_exists($file)) {
                    $logCtx = [ 'file' => $file];
                    $this->getLogger()->debug("Adding configfile: {file}", $logCtx);
                    $config->addFile($file);
                } else {
                    throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $file));
                }
            }
        }
    }
}
