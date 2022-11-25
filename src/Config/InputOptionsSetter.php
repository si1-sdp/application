<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Composer\Console\Input\InputArgument;
use Consolidation\Config\ConfigInterface;
use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Application;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Contracts\AppAwareInterface;
use DgfipSI1\Application\Contracts\AppAwareTrait;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\SymfonyApplication;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * loads all input options values into config
 */
class InputOptionsSetter implements
    ConfigAwareInterface,
    LoggerAwareInterface,
    AppAwareInterface,
    ContainerAwareInterface
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use AppAwareTrait;
    use ContainerAwareTrait;

    /**
     * Setup necessary parameters from internal configuration
     *
     * @param ConfigInterface $config
     * @param string          $commandName
     *
     * @return void
     */
    public function setInputOptions($config, $commandName)
    {
        /** @var array<string,mixed> globalOptions*/
        $globalOptions  = $config->get('dgfip_si1.global_options');
        /** @var array<string,mixed> commandOptions*/
        $commandOptions = $config->get('dgfip_si1.command_options');

        /** @var InputInterface $input */
        $input = $this->getContainer()->get('input');

        $this->setupTechnicalOptions($input);
        $this->setupGlobalOptions($globalOptions);
        $this->setupCommandOptions($input, $commandName, $commandOptions);
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
        $definition = $this->application->getDefinition();
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
     * @param array<string,mixed> $globalOptions
     *
     * @return void
     */
    protected function setupGlobalOptions($globalOptions)
    {
        $logCtx = [ 'name' => 'setupGlobalOptions', 'context' => 'Global' ];
        $definition = $this->application->getDefinition();
        if (null !== $globalOptions) {
            /** @var array<string,mixed> $options */
            foreach ($globalOptions as $key => $options) {
                $opt = $this->optionFromConfig($key, $options);
                $this->addOption($definition, $opt, $logCtx);
            }
        }
        if ($this->getContainer()->has(AbstractApplication::GLOBAL_CONFIG_TAG)) {
            /** @var array<ApplicationAwareInterface> $confServices */
            $confServices = $this->getContainer()->get(AbstractApplication::GLOBAL_CONFIG_TAG);
            foreach ($confServices as $configurator) {
                foreach ($configurator->getConfigOptions() as $option) {
                    $this->addOption($definition, $option->getOption(), $logCtx);
                }
            }
        }
    }
    /**
     * Setup options for current command
     *
     * @param InputInterface      $input
     * @param string              $cmdName
     * @param array<string,mixed> $commandOptions
     *
     * @return void
     */
    protected function setupCommandOptions($input, $cmdName, $commandOptions)
    {
        $logCtx = [ 'name' => 'setupCommandOptions' , 'command' => $cmdName ];
        $app = $this->getApplication();
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
        $this->getLogger()->notice("Adding input options for command '{command}'", $logCtx);
        /** @var Command $command */
        $command = $app->getCommand($cmdName);
        //$command = $this->getContainer()->get($cmdName);
        $logCtx['context'] = "Command '$cmdName'";
        $definition = $command->getDefinition();
        if (null !== $commandOptions) {
            if (array_key_exists($cmdName, $commandOptions)) {
                /** @var array<string,array<string,array<string,mixed>>> $cmdTree */
                $cmdTree = $commandOptions[$cmdName];
                if (array_key_exists('options', $cmdTree)) {
                    foreach ($cmdTree['options'] as $key => $options) {
                        $opt = $this->optionFromConfig($key, $options);
                        $this->addOption($definition, $opt, $logCtx);
                    }
                }
            }
        }
        /** @var \DgfipSI1\Application\Command $command */
        if ($command instanceof ApplicationAwareInterface) {
            foreach ($command->getConfigOptions() as $option) {
                $this->addOption($definition, $option->getOption(), $logCtx);
            }
        }
    }
    /**
     *
     *
     * @param string              $name
     * @param array<string,mixed> $options
     *
     * @return InputOption
     */
    protected function optionFromConfig($name, $options)
    {
        /** @var string|null $shortOpt */
        $shortOpt   = array_key_exists(CONF::OPT_SHORT, $options) ? $options[CONF::OPT_SHORT] : null;
        /** @var string $optDesc */
        $optDesc    = array_key_exists(CONF::OPT_DESCRIPTION, $options) ? $options[CONF::OPT_DESCRIPTION] : '';
        /** @var array<mixed>|bool|float|int|string|null $optDefault */
        $optDefault = array_key_exists(CONF::OPT_DEFAULT_VALUE, $options) ? $options[CONF::OPT_DEFAULT_VALUE] : null;
        if (!array_key_exists(CONF::OPT_TYPE, $options)) {
            throw new \Exception(sprintf('Missing option type for %s', $name));
        }
        /** @var string $optType */
        $optType = $options[CONF::OPT_TYPE];
        try {
            $type = OptionType::from($optType);
            $inputOption = (new MappedOption($name, $type, $optDesc, $shortOpt, $optDefault))->getOption();
        } catch (\ValueError $e) {
            throw new \Exception(sprintf("Unknown option type '%s' for option '%s'", $optType, $name));
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Error creating option %s : %s', $name, $e->getMessage()));
        }

        return $inputOption;
    }
    /**
     * @param InputDefinition      $definition
     * @param InputOption          $option
     * @param array<string,string> $logCtx
     *
     * @return void
     */
    private function addOption($definition, $option, $logCtx)
    {
        $logCtx['option'] = $option->getName();
        if ($definition->hasOption($option->getName())) {
            $this->getLogger()->warning("{context} option {option} already exists", $logCtx);
        } else {
            $this->getLogger()->debug("{context} option {option} added", $logCtx);
            $definition->addOption($option);
        }
    }
}
