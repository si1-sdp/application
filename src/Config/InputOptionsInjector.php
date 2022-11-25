<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\Contracts\AppAwareInterface;
use DgfipSI1\Application\Contracts\AppAwareTrait;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function PHPUnit\Framework\isNan;

/**
 * envent subscriber that loads all input options values into config
 */
class InputOptionsInjector implements
    EventSubscriberInterface,
    ConfigAwareInterface,
    LoggerAwareInterface,
    AppAwareInterface
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use AppAwareTrait;


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
        $logCtx = ['name' => 'manageCommand '.$commandName.'Options'];
        if (!$this->application->getContainer()->has($commandName)) {
            $this->getLogger()->debug("Skipping $commandName (not in container)", $logCtx);

            return;
        }
        $commandName = str_replace(':', '.', (string) $command->getName());
        $prefix = "commands.$commandName.options";
        $definition = $command->getDefinition();
        //InputOptionsSetter::safeBind($input, $definition);
        $inputOptions = $definition->getOptions();
        $this->getLogger()->debug("Synchronizing config and inputOptions", $logCtx);
        $globalOptions = [];
        if (null !== $this->application) {
            $globalOptions = array_keys($this->application->getDefinition()->getOptions());
        }
        foreach ($inputOptions as $option => $inputOption) {
            if (!in_array($option, $globalOptions)) {
                $this->syncInputWithConfig($input, $inputOption, $prefix, 'manageCommand '.$commandName.' options');
            }
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
        if (null === $this->application) {
            $this->getLogger()->debug("Skipping : no application", $logCtx);

            return;
        }
        $prefix = 'options';
        $definition = $this->application->getDefinition();
        $inputOptions = $definition->getOptions();
        $this->getLogger()->debug("Synchronizing config and inputOptions", $logCtx);
        foreach ($inputOptions as $inputOption) {
            $this->syncInputWithConfig($input, $inputOption, $prefix, 'manageGlobalOptions');
        }
    }
    /**
     * synchronizeInputOptionWithConfig
     *
     * @param InputInterface $input
     * @param InputOption    $inputOption
     * @param string         $prefix
     * @param string         $caller
     *
     * @return void
     */
    protected function syncInputWithConfig($input, $inputOption, $prefix, $caller)
    {
        $option = $inputOption->getName();
        $inputValue = $input->getOption($option);
        if ($inputOption->isNegatable() && 1 === $inputValue) {
            $inputValue = true;
        }
        if ($inputOption->isArray() && [] === $inputValue) {
            $inputValue = null;
        }
        $key = $prefix.'.'.str_replace(['.', '-' ], '_', $option);
        /** @var array<mixed>|bool|float|int|string|null $confValue */
        $confValue = $this->getConfig()->get($key);
        // if input value is null, override it whith configured value
        $confStr = self::toString($confValue);
        $inputStr = self::toString($inputValue);
        $logCtx = ['name' => $caller, 'key' => $key, 'conf' => $confStr, 'input' => $inputStr];
        $this->getLogger()->debug("{key} => INPUT : {input} - CONFIG : {conf}", $logCtx);
        // Option not specified on command line : see what we have in configuration
        if (null === $inputValue) {
            if (null !== $confValue) {
                if (is_bool($confValue)) {
                    $input->setOption($option, $confValue);
                } elseif ($inputOption->acceptValue()) {
                    $inputOption->setDefault($confValue);
                    $this->getLogger()->debug("    => INPUT->SET('{key}',  {conf})", $logCtx);
                }
            } else {
                // option is empty in conf and on command line => take default from input option
                if ($inputOption->acceptValue() && null !== $inputOption->getDefault()) {
                    $inputValue = $inputOption->getDefault();
                    $logCtx['input'] = self::toString($inputValue);
                    $this->getLogger()->debug("    => CONFIG->SET('{key}',  {input})", $logCtx);
                    $this->config->set($key, $inputValue);
                }
            }
        } else {
        // Option specified on command line : override configuration whith it
            $this->getLogger()->debug("    => CONFIG->SET('{key}',  {input})", $logCtx);
            $this->getConfig()->set("$key", $inputValue);
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
    protected function manageDefineOption(ConsoleCommandEvent $event)
    {
        $input = $event->getInput();

        // Set any `-Dconfig.key=value` options from the commandline.
        if ($input->hasOption('define')) {
            /** @var array<string> $configDefinitions */
            $configDefinitions = $input->getOption('define');
            //print_r($configDefinitions);
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
