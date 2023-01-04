<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use DgfipSI1\Application\Command as appCommand;

/**
 * envent subscriber that loads all input options values into config
 */
class InputOptionsInjector implements EventSubscriberInterface, ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::COMMAND => [ 'handleCommandEvent', 90 ]];
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
        $this->manageGlobalOptions($event->getInput());
        $command = $event->getCommand();
        if (null !== $command) {
            $this->manageCommandOptions($event->getInput(), $command);
        }
        $this->manageDefineOption($event);
    }
    /**
     * FIXME : put somewhere else
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function toString($value)
    {
        $ret = null;
        if (null === $value) {
            $ret = 'null';
        } elseif (is_bool($value)) {
            $ret = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            if ([] === $value) {
                $ret = '[]';
            } elseif (array_keys($value) === range(0, count($value) - 1)) {
                // sequential array
                $ret = '['.implode(', ', array_map(function ($v) {
                    return print_r($v, true);
                }, $value)).']';
            }
        }
        $ret = $ret ?? print_r($value, true);

        return $ret;
    }
    /**
     * manageCommandOptions
     *
     * @param InputInterface $input
     * @param Command        $command
     *
     * @return void
     */
    protected function manageCommandOptions($input, $command)
    {
        // don't manage builtin commands
        $commandName = (string) $command->getName();
        $logCtx = ['name' => 'manageCommand '.$commandName.' options'];
        if (!$this->getContainer()->has($commandName)) {
            $this->getLogger()->debug("Skipping $commandName (not in container)", $logCtx);

            return;
        }
        $mappedOptions = $this->getConfiguredApplication()->getMappedOptions($commandName);
        $this->getLogger()->debug("Synchronizing config and inputOptions", $logCtx);
        foreach ($mappedOptions as $option) {
            $this->syncInputWithConfig($input, $option, $commandName);
        }
    }
    /**
     * manageGlobalOptions
     *
     * @param InputInterface $input
     *
     * @return void
     */
    protected function manageGlobalOptions($input)
    {
        $logCtx = ['name' => 'manageGlobalOptions'];
        try {
            $this->getConfiguredApplication();
        } catch (\Exception $e) {
            $this->getLogger()->debug("Skipping : no application", $logCtx);

            return;
        }
        $mappedOptions = $this->getConfiguredApplication()->getMappedOptions();
        $this->getLogger()->debug("Synchronizing config and inputOptions", $logCtx);
        foreach ($mappedOptions as $option) {
            $this->syncInputWithConfig($input, $option);
        }
    }
    /**
     * synchronizeInputOptionWithConfig
     *
     * @param InputInterface $input
     * @param MappedOption   $mappedOpt
     * @param string|null    $command
     *
     * @return void
     */
    protected function syncInputWithConfig($input, $mappedOpt, $command = null)
    {
        if (null === $command) {
            $prefix = 'options';
            $caller = 'manageGlobalOptions';
        } else {
            $prefix = "commands.".AppCommand::getConfName($command).".options";
            $caller = "manage $command options";
        }
        //
        // GET OPTION VALUE FROM COMMAND LINE
        //
        $inputValue = $mappedOpt->getDefaultFreeInputValue($input);
        if ($mappedOpt->isArray() && [] === $inputValue) {
            $inputValue = null;
        }
        //
        // GET OPTION VALUE FROM CONFIGURATION
        //
        $key = $prefix.'.'.$mappedOpt->getName();
        /** @var array<mixed>|bool|float|int|string|null $confValue */
        $confValue = $this->getConfig()->get($key);

        $confStr = self::toString($confValue);
        $logCtx = ['name' => $caller, 'key' => $key, 'input' => self::toString($inputValue), 'conf' => $confStr];
        $this->getLogger()->debug("{key} => INPUT : {input} - CONFIG : {conf}", $logCtx);
        if (null === $inputValue) {
            if (null === $confValue) {
                // both null : set from defaults:
                $this->setValueFromDefault($input, $mappedOpt, $key, $logCtx);
            } else {
                // input empty but value in conf
                $this->setValueFromConfig($input, $mappedOpt, $confValue, $logCtx);
            }
        } else {
            // Option specified on command line : override configuration whith it
            $this->getLogger()->debug("    => CONFIG->SET('{key}',  {input})", $logCtx);
            $this->getConfig()->set("$key", $inputValue);
        }
    }
    /**
     * synchronizeInputOptionWithConfig
     *
     * @param InputInterface       $input
     * @param MappedOption         $mappedOpt
     * @param string               $key
     * @param array<string,string> $logCtx
     *
     * @return void
     */
    protected function setValueFromDefault($input, $mappedOpt, $key, $logCtx)
    {
        $value = $mappedOpt->getDefaultValue();
        if (null !== $value) {
            $logCtx['value'] = self::toString($value);
            $this->getLogger()->debug("    => CONFIG->SET('{key}',  {value})", $logCtx);
            $this->config->set($key, $value);
            // default value for arguments are automaticaly set - no need to do it here
            if (!$mappedOpt->isArgument()) {
                $input->setOption($mappedOpt->getOption()->getName(), $value);
            }
            $this->getLogger()->debug("    => INPUT->SET('{key}',  {value})", $logCtx);
        }
    }
    /**
     * synchronizeInputOptionWithConfig
     *
     * @param InputInterface                          $input
     * @param MappedOption                            $mappedOpt
     * @param array<mixed>|bool|float|int|string|null $confValue
     * @param array<string,string>                    $logCtx
     *
     * @return void
     */
    protected function setValueFromConfig($input, $mappedOpt, $confValue, $logCtx)
    {
        if ($mappedOpt->isBool()) {
            $input->setOption($mappedOpt->getOption()->getName(), (bool) $confValue);
        } elseif (!$mappedOpt->isArgument()) {
            $mappedOpt->getOption()->setDefault($confValue);
        } else {
            $mappedOpt->getArgument()->setDefault($confValue);
        }
        $this->getLogger()->debug("    => INPUT->SET('{key}',  {conf})", $logCtx);
    }
    /**
     * Examine the commandline --define / -D options, and apply the provided
     * values to the active configuration.
     *
     * @param \Symfony\Component\Console\Event\ConsoleCommandEvent $event
     *
     * @return void
     */
    protected function manageDefineOption(ConsoleCommandEvent $event)
    {
        $input = $event->getInput();

        // Set any `-Dconfig.key=value` options from the commandline.
        if ($input->hasOption('define')) {
            /** @var array<string> $configDefinitions */
            $configDefinitions = $input->getOption('define');
            foreach ($configDefinitions as $value) {
                list($key, $value) = $this->splitConfigKeyValue($value);
                $this->config->set("$key", $value);
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
