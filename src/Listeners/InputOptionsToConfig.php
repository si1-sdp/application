<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Listeners;

use DgfipSI1\Application\Application;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * enven subscriber that loads all input options values into config
 */
class InputOptionsToConfig implements EventSubscriberInterface, ConfigAwareInterface, LoggerAwareInterface
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;

    /** @var Application $application|null  */
    protected $application;

    /**
     * Add a reference to the Symfony Console application object.
     *
     * @param Application $application
     *
     * @return $this
     */
    public function setApplication($application)
    {
        $this->application = $application;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::COMMAND => 'handleCommandEvent'];
    }

    /**
     * Run all of our individual operations when a command event is received.
     *
     * @param ConsoleCommandEvent $event
     *
     * @return void
     */
    public function handleCommandEvent(ConsoleCommandEvent $event)
    {
        // print "YELL : ".($event->getInput()->getOption('yell') ? 'true' : 'false')."   DEFAULT : ".$event->getCommand()->getDefinition()->getOption('yell')->getDefault()."\n";
        // print_r($event->getInput());
        $this->injectConfigurationForGlobalOptions($event->getInput());
        /** @var Command $command */
        $command = $event->getCommand();
        $this->injectConfigurationForCommand($command, $event->getInput());
        $this->setGlobalOptions($event->getInput());
        $this->setCommandOptions($command, $event->getInput());

        $this->setConfigurationValues($event);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return void
     */
    protected function injectConfigurationForGlobalOptions($input)
    {
        if (null === $this->application) {
            return;
        }
        $logCtx = ['name' => 'injectConfigIntoOptions'];
        $definition = $this->application->getDefinition();
        $options = $definition->getOptions();
        $this->logger->debug("Injecting global options from configuration", $logCtx);
        $this->injectConfigIntoOptions('options', $options, $input);
    }

    /**
     * @param \Symfony\Component\Console\Command\Command      $command
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return void
     */
    protected function injectConfigurationForCommand($command, $input)
    {
        $commandName = $command->getName();
        $logCtx = ['name' => 'injectConfigIntoOptions', 'command' => $commandName];
        $commandName = str_replace(':', '.', "$commandName");
        $prefix = "commands.$commandName.options";
        $options = $command->getDefinition()->getOptions();
        $this->logger->debug("Injecting {command} command options from configuration", $logCtx);
        $this->injectConfigIntoOptions($prefix, $options, $input);
    }

    /**
     * @param string                                          $prefix
     * @param array<InputOption>                              $options
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return void
     */
    protected function injectConfigIntoOptions($prefix, $options, $input)
    {
        foreach ($options as $option => $inputOption) {
            $key = str_replace('.', '-', $option);
            /** @var array<mixed>|bool|float|int|string|null $value */
            $value = $this->config->get("$prefix.$key");
            $logCtx = ['name' => 'injectConfigIntoOptions', 'key' => $key, 'value' => $value];
            if (null !== $value) {
                if (is_bool($value) && (true === $value)) {
                    $input->setOption($key, $value);
                    $this->logger->debug("    {key} = true", $logCtx);
                } elseif ($inputOption->acceptValue()) {
                    $inputOption->setDefault($value);
                    $this->logger->debug("    default value for {key} = {value}", $logCtx);
                }
            }
        }
    }
    /**
     * Examine the global options from the event Input, and set config. values as appropriate.
     *
     * @param InputInterface $input
     *
     * @return void
     */
    protected function setGlobalOptions(InputInterface $input)
    {
        /** @var array<string,mixed> $globalOptions */
        $globalOptions = $this->config->get("options") ?? [];
        if (null !== $this->application) {
            foreach ($this->application->getDefinition()->getOptions() as $key => $option) {
                $globalOptions["$key"] = $option->acceptValue() ? $option->getDefault() : null;
            }
        }
        $logCtx = ['name' => 'injectOptionsToConfig'];
        $this->logger->debug("Injecting global options into config", $logCtx);
        $this->injectOptionsToConfig($globalOptions, 'options', $input);
    }
    /**
     * Examine the command options from the event Input, and set config. values as appropriate.
     *
     * @param \Symfony\Component\Console\Command\Command $command
     * @param InputInterface                             $input
     *
     * @return void
     */
    protected function setCommandOptions($command, $input)
    {
        $prefix = 'commands.'.$command->getName().'.options';
        /** @var array<string,mixed> $commandOptions */
        $commandOptions = $this->config->get($prefix) ?? [];
        $logCtx = ['name' => 'injectOptionsToConfig', 'command' => $command->getName()];
        $this->logger->debug("Injecting {command} command options into config", $logCtx);
        $this->injectOptionsToConfig($commandOptions, $prefix, $input);
    }
    /**
     * Undocumented function
     *
     * @param array<string,mixed> $options
     * @param string              $prefix
     * @param InputInterface      $input
     *
     * @return void
     */
    protected function injectOptionsToConfig($options, $prefix, $input)
    {
        foreach ($options as $option => $default) {
            $value = $input->hasOption($option) ? $input->getOption($option) : null;
            // Unfortunately, the `?:` operator does not differentate between `0` and `null`
            if (!isset($value)) {
                $value = $default;
            }
            $logCtx = ['name' => 'injectOptionsToConfig', 'option' => 'options.'.$option, 'value' => $value];
            $this->logger->debug("    {option} => {value}", $logCtx);
            $this->config->set("$prefix.$option", $value);
        }
    }
    /**
     * Examine the commandline --define / -D options, and apply the provided
     * values to the active configuration.
     *
     * @param \Symfony\Component\Console\Event\ConsoleCommandEvent $event
     *
     * @return void
     */
    protected function setConfigurationValues(ConsoleCommandEvent $event)
    {
        $input = $event->getInput();

        // Also set any `-Dconfig.key=value` options from the commandline.
        if ($input->hasOption('define')) {
            /** @var array<string> $configDefinitions */
            $configDefinitions = $input->getOption('define');
            foreach ($configDefinitions as $value) {
                list($key, $value) = $this->splitConfigKeyValue($value);
                $this->conf()->set("$key", $value);
            }
        }
    }
    /**
     * Split up the key=value config setting into its component parts. If
     * the input string contains no '=' character, then the value will be 'true'.
     *
     * @param string $value
     *
     * @return array<string|bool>
     */
    protected function splitConfigKeyValue($value)
    {
        $parts = explode('=', $value, 2);
        $parts[] = true;

        return $parts;
    }
}
