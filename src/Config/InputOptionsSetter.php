<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Composer\Console\Input\InputArgument;
use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\ConfigHelper\ConfigHelperInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * loads all input options values into config
 */
class InputOptionsSetter implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
    /**
     * Setup necessary parameters from internal configuration
     *
     * @param ConfigInterface $config
     *
     * @return void
     */
    public function setInputOptions($config)
    {
        /** @var array<string,mixed> globalOptions*/
        $globalOptions  = $config->get('dgfip_si1.global_options');

        /** @var InputInterface $input */
        $input = $this->getContainer()->get('input');
        /** @var SymfonyApplication $app */
        $app = $this->getConfiguredApplication();

        $this->registerGlobalOptions($globalOptions);
        $this->registerCommandOptions($config);
        $this->setupTechnicalOptions($input);
        $this->setupGlobalOptions();
        $this->setupCommandOptions($input, (string) $app->getCmdName());
    }
    /**
     * @param InputInterface  $input
     * @param InputDefinition $definition
     *
     * @return void
     */
    public static function safeBind($input, $definition)
    {
        try {
            $input->bind($definition);
        } catch (RuntimeException $e) {
            // Errors must be ignored, full binding/validation happens later
        }
    }
    /**
     * Setup application global options --config, --add-config and --define(-D)
     *
     * @param InputInterface $input
     *
     * @return void
     */
    protected function setupTechnicalOptions($input)
    {
        $definition = $this->getConfiguredApplication()->getDefinition();
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
        self::safeBind($input, $definition);
    }
    /**
     * Setup application global options from config and ApplicationAware classes
     *
     * @return void
     */
    protected function setupGlobalOptions()
    {
        $logCtx = [ 'name' => 'setupGlobalOptions', 'context' => 'Global' ];
        $definition = $this->getConfiguredApplication()->getDefinition();
        foreach ($this->getConfiguredApplication()->getMappedOptions() as $opt) {
            $this->addOption($definition, $opt, $logCtx);
        }
    }
    /**
     * Setup options for current command
     *
     * @param InputInterface $input
     * @param string         $cmdName
     *
     * @return void
     */
    protected function setupCommandOptions($input, $cmdName)
    {
        $logCtx = [ 'name' => 'setupCommandOptions' , 'command' => $cmdName, 'context' => $cmdName ];
        $this->getLogger()->info("Setting up command options for {command}", $logCtx);
        $app = $this->getConfiguredApplication();
        if (!$app instanceof SymfonyApplication) {
            $this->getLogger()->info("Only symfony command supported - skipping", $logCtx);

            return;
        }
        if ('help' === $cmdName) {
            $definition = new InputDefinition([
                new InputArgument('command', InputArgument::REQUIRED, 'The command name'),
                new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name'),
            ]);
            self::safeBind($input, $definition);
            /** @var string $cmdName */
            $cmdName = $input->getArgument('command_name');
        }
        if (empty($cmdName) || !$this->getContainer()->has($cmdName)) {
            return;
        }
        $this->getLogger()->info("Adding input options for command '{command}'", $logCtx);
        $command = $app->getCommand($cmdName);
        $definition = $command->getDefinition();
        foreach ($app->getMappedOptions($cmdName) as $option) {
            $this->addOption($definition, $option, $logCtx);
        }
    }
    /**
     * Registers command options :
     * Scan application configuration and getConfigOptions() for command options
     * Adds every mappedOption returned to application MappedOptions list
     *
     * @param ConfigInterface $config
     *
     * @return void
     */
    protected function registerCommandOptions($config)
    {
        /** @var array<ApplicationCommand> $commands */
        $commands = $this->getContainer()->get(SymfonyApplication::COMMAND_TAG);
        foreach ($commands as $command) {
            $cmdName = str_replace(':', '_', (string) $command->getName());
            $key = 'dgfip_si1.command_options.'.$cmdName.'.options';
            $commandOptions = $config->get($key);
            if (is_array($commandOptions)) {
                foreach ($commandOptions as $key => $options) {
                    $opt = MappedOption::createFromConfig($key, $options);
                    $opt->setCommand((string) $command->getName());
                    $this->getConfiguredApplication()->addMappedOption($opt);
                }
            }
            /** @var \DgfipSI1\Application\Command $command */
            if ($command instanceof ConfiguredApplicationInterface) {
                foreach ($command->getConfigOptions() as $opt) {
                    $opt->setCommand((string) $command->getName());
                    $this->getConfiguredApplication()->addMappedOption($opt);
                }
            }
        }
    }
    /**
     * Registers global options :
     * Scan application configuration and getConfigOptions() for global options
     * Adds every mappedOption returned to application MappedOptions list
     *
     * @param array<string,mixed> $globalOptions
     *
     * @return void
     */
    protected function registerGlobalOptions($globalOptions)
    {
        if (null !== $globalOptions) {
            /** @var array<string,mixed> $options */
            foreach ($globalOptions as $key => $options) {
                $opt = MappedOption::createFromConfig($key, $options);
                $this->getConfiguredApplication()->addMappedOption($opt);
            }
        }
        if ($this->getContainer()->has(AbstractApplication::GLOBAL_CONFIG_TAG)) {
            /** @var array<ConfiguredApplicationInterface> $confServices */
            $confServices = $this->getContainer()->get(AbstractApplication::GLOBAL_CONFIG_TAG);
            foreach ($confServices as $configurator) {
                foreach ($configurator->getConfigOptions() as $opt) {
                    $this->getConfiguredApplication()->addMappedOption($opt);
                }
            }
        }
    }

    /**
     * @param InputDefinition      $definition
     * @param MappedOption         $mappedOption
     * @param array<string,string> $logCtx
     *
     * @return void
     */
    private function addOption($definition, $mappedOption, $logCtx)
    {
        $logCtx['option'] = $mappedOption->getName();
        if ($mappedOption->isArgument()) {
            $input = $mappedOption->getArgument();
            if ($definition->hasArgument($input->getName())) {
                $this->getLogger()->warning("{context} argument {option} already exists", $logCtx);
            } else {
                $this->getLogger()->debug("{context} argument {option} added", $logCtx);
                $definition->addArgument($input);
            }
        } else {
            $input = $mappedOption->getOption();
            if ($definition->hasOption($input->getName())) {
                $this->getLogger()->warning("{context} option {option} already exists", $logCtx);
            } else {
                $this->getLogger()->debug("{context} option {option} added", $logCtx);
                $definition->addOption($input);
            }
        }
    }
}
