<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Consolidation\Config\ConfigInterface;
use Consolidation\Config\Util\ConfigOverlay;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Exception\ConfigFileNotFoundException;
use DgfipSI1\ConfigHelper\ConfigHelper;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * envent subscriber that loads all input options values into config
 */
class ConfigLoader implements EventSubscriberInterface, ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;

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
        /** @var string $dir */
        $dir                   = $config->get(CONF::CONFIG_DIRECTORY);
        /** @var array<string> $configNamePatterns */
        $configNamePatterns    = $config->get(CONF::CONFIG_NAME_PATTERNS);
        /** @var array<string> $configPathPatterns */
        $configPathPatterns    = $config->get(CONF::CONFIG_PATH_PATTERNS);
        /** @var int $configDepth */
        $configDepth = (bool) $config->get(CONF::CONFIG_SEARCH_RECURSIVE) ? -1 : 0;
        /** @var bool $configSortByName */
        $configSortByName      = $config->get(CONF::CONFIG_SORT_BY_NAME);
        $this->configDir       = $dir;
        $this->namePatterns    = $configNamePatterns;
        $this->pathPatterns    = $configPathPatterns;
        $this->depth           = $configDepth;
        $this->sortByName      = $configSortByName;
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
            /** @var array<ConfiguredApplicationInterface> $globalConfigurators */
            $globalConfigurators = $this->getContainer()->get(AbstractApplication::GLOBAL_CONFIG_TAG);
            $configurators = $globalConfigurators;
        }
        if ($this->getContainer()->has(AbstractApplication::COMMAND_CONFIG_TAG)) {
            /** @var array<ConfiguredApplicationInterface> $commandConfigurators */
            $commandConfigurators = $this->getContainer()->get(AbstractApplication::COMMAND_CONFIG_TAG);
            $configurators = array_merge($configurators, $commandConfigurators) ;
        }
        foreach ($configurators as $configurator) {
            /** @var ConfigHelper $config */
            $config = $this->getConfig();
            /** @var ConfigurationInterface $configurator */
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
            if ('' !== $filename && file_exists($filename)) {
                $logCtx = [ 'name' => 'loadConfigFromOptions', 'file' => $filename];
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
            $rootDirectory = '.';
        } else {
            $rootDirectory = $this->configDir;
        }
        $dirs = $this->getDirectories($rootDirectory, $this->getConfiguredApplication());
        if (sizeof($dirs) === 0) {
            if (true === $askedConfig) {
                throw new ConfigFileNotFoundException(sprintf("Config directory '%s' not found", $rootDirectory));
            }
            $this->getLogger()->debug("No configuration files found", $logCtx);

            return;
        }
        $logCtx['dirs']  = "[".implode(', ', $dirs)."]";
        $logCtx['paths'] = "[".((bool) $this->pathPatterns ? implode(', ', $this->pathPatterns) : '')."]";
        $logCtx['names'] = "[".((bool) $this->namePatterns ? implode(', ', $this->namePatterns) : '')."]";
        $logCtx['sort']  = $this->sortByName ? 'name' : 'path';
        $logCtx['depth'] = $this->depth;
        $msg = "Loading config: paths={paths} - names={names} - sort by {sort} - depth = {depth}";
        $this->getLogger()->debug($msg, $logCtx);
        $config->findConfigFiles($dirs, $this->pathPatterns, $this->namePatterns, $this->sortByName, $this->depth);
    }
    /**
     * Get config directory candidates
     *
     * @param string               $configDir
     * @param ApplicationInterface $app
     *
     * @return array<string>
     */
    protected function getDirectories($configDir, $app)
    {
        $directories = [];
        // absolute path, just make sure we're an array
        if (str_starts_with($configDir, '/') || strpos($configDir, '://') !== false) {
            if (is_dir($configDir)) {
                $directories = [$configDir];
            }
        // relative path ? try under every possible root directory
        } else {
            foreach ([$app->getPharRoot(), $app->getHomeDir(), $app->getCurrentDir()] as $dir) {
                $fullDir = "$dir/$configDir";
                if (is_dir($dir) && is_dir($fullDir) && !in_array($fullDir, $directories, true)) {
                    $directories[] = $fullDir;
                }
            }
        }

        return $directories;
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
                if (file_exists($file)) {
                    $logCtx = [ 'name' => 'addConfigFromOptions', 'file' => $file];
                    $this->getLogger()->debug("Adding configfile: {file}", $logCtx);
                    $config->addFile($file);
                } else {
                    throw new ConfigFileNotFoundException(sprintf("Configuration file '%s' not found", $file));
                }
            }
        }
    }
}
