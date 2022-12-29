<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\Config\ConfigLoader;
use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\InputOptionsInjector;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Exception\RuntimeException as ExceptionRuntimeException;
use DgfipSI1\Application\Utils\ApplicationLogger;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\Application\Utils\DumpconfigCommand;
use DgfipSI1\Application\Utils\MakePharCommand;
use League\Container\Argument\Literal\IntegerArgument;
use League\Container\ContainerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * class Application
 */
class SymfonyApplication extends AbstractApplication
{

    public const COMMAND_TAG      = 'symfonyCommand';
    protected const COMMAND_SUBCLASS = '\Symfony\Component\Console\Command\Command';

    /** sets the list of classes containing symfony commands
     * @inheritDoc
     */
    public function registerCommands()
    {
        /** @var array<ApplicationCommand> $cClasses */
        $cClasses = $this->getContainer()->get(self::COMMAND_TAG);
        foreach ($cClasses as $command) {
            /** @var Command $command */
            $this->add($command);
            $logCtx = ['name' => 'registerCommands', 'cmd' => $command->getName()];
            $this->getLogger()->info("command {cmd} registered", $logCtx);
        }
        $this->getContainer()->addShared(DumpconfigCommand::class)->addTag(self::COMMAND_TAG)->addTag('dump-config');
        /** @var Command $cmd */
        $cmd = $this->getContainer()->get(DumpconfigCommand::class);
        $this->add($cmd);
        // have make-phar command available only if we're not in a phar
        if ('' === $this->pharRoot) {
            $this->getContainer()->addShared(MakePharCommand::class)->addTag(self::COMMAND_TAG)->addTag('make-phar');
            /** @var Command $cmd */
            $cmd = $this->getContainer()->get(MakePharCommand::class);
            $this->add($cmd);
        }
    }
    /**
     * Return command object from container
     *
     * @param string $cmdName
     *
     * @return ApplicationCommand
     */
    public function getCommand($cmdName)
    {
        if ($this->getContainer()->has($cmdName)) {
            $results = $this->getContainer()->get($cmdName);
            if (is_array($results) && count($results) === 1) {
                return array_shift($results);
            }
        }
        throw new ExceptionRuntimeException(sprintf("Error looking for command %s in container", $cmdName));
    }
    /**
     * return command name
     *
     * @return string|null
     */
    public function getCmdName(): ?string
    {
        return parent::getCommandName($this->input);
    }

    /**
     * run application
     *
     * @inheritDoc
     */
    public function go()
    {
        $this->finalize();
        $logContext = ['name' => 'go'];
        $logContext['cmd_name'] = $this->isSingleCommand() ? 'list' : $this->input->getFirstArgument();
        $this->getLogger()->info("Launching symfony command '{cmd_name}'", $logContext);

        return $this->run($this->input, $this->output);
    }

    /**
     * finalize application before run
     *
     * @return void
     */
    protected function finalize()
    {
        // set application's name and version
        $this->setApplicationNameAndVersion();
        // configure container.
        $this->configureContainer();
        // configure application logger
        ApplicationLogger::configureLogger($this->getLogger(), $this->intConfig, $this->homeDir);

        // Discover commands
        /** @var string $namespace */
        $namespace = $this->getNamespace();
        $this->addDiscoveries($namespace, self::COMMAND_TAG, self::COMMAND_SUBCLASS, [], 'name');
        // Discover configurator classes
        $appClass = ConfiguredApplicationInterface::class;
        $cmdClass = ApplicationCommand::class;
        $this->addDiscoveries($namespace, self::COMMAND_CONFIG_TAG, $cmdClass, [], 'name');
        $this->addDiscoveries($namespace, self::GLOBAL_CONFIG_TAG, $appClass, $cmdClass);
        // Discover event listeners
        $this->addDiscoveries($namespace, self::EVENT_LISTENER_TAG, [ EventSubscriberInterface::class]);
        // launch discoverer
        $this->discoverClasses();

        // register discovered command classes
        $this->registerCommands();

        // register event subsribers
        if ($this->getContainer()->has(self::EVENT_LISTENER_TAG)) {
            /** @var EventDispatcher $eventDispatcher */
            $eventDispatcher = $this->getContainer()->get("eventDispatcher");
            /** @var array<EventSubscriberInterface> $listeners */
            $listeners = $this->getContainer()->get(self::EVENT_LISTENER_TAG);
            foreach ($listeners as $service) {
                $eventDispatcher->addSubscriber($service);
            }
        }
        // set input options according to internal config and configAwareInterface
        /** @var InputOptionsSetter $optionsSetter */
        $optionsSetter = $this->getContainer()->get('input_options_setter');
        $optionsSetter->setInputOptions($this->intConfig);
    }
    /**
     * Configure the container for symfony applications
     *
     * @return void
     */
    protected function configureContainer()
    {
        $verbosity = ApplicationLogger::getVerbosity($this->input);
        // Self-referential container reference for the inflector
        $this->getContainer()->add('container', $this->container);
        $this->getContainer()->addShared('application', $this);
        $this->getContainer()->addShared('config', $this->config);
        $this->getContainer()->addShared('input', $this->input);
        $this->getContainer()->addShared('output', $this->output);
        $this->getContainer()->addShared('verbosity', new IntegerArgument($verbosity));
        $this->getContainer()->addShared('internal_configuration', $this->intConfig);
        $this->getContainer()->addShared('logger', $this->logger);
        $this->getContainer()->addShared('classLoader', $this->classLoader);
        $this->getContainer()->addShared('input_options_setter', InputOptionsSetter::class);
        $this->getContainer()->addShared('input_options_injector', InputOptionsInjector::class);
        $this->getContainer()->addShared('configuration_loader', ConfigLoader::class)
            ->addMethodCall('configure', ['internal_configuration']);
        $this->getContainer()->addShared('eventDispatcher', \Symfony\Component\EventDispatcher\EventDispatcher::class)
            ->addMethodCall('addSubscriber', ['configuration_loader'])       // 100 priority
            ->addMethodCall('addSubscriber', ['input_options_injector']);   // 90 priority
        $this->getContainer()->inflector(ConfigAwareInterface::class)->invokeMethod('setConfig', ['config']);
        $this->getContainer()->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', ['logger']);
        $this->getContainer()->inflector(ContainerAwareInterface::class)->invokeMethod('setContainer', ['container']);

        $logger = $this->getContainer()->get('logger');              /** @var LoggerInterface $logger */
        $this->logger = $logger;
        $dispatcher = $this->getContainer()->get('eventDispatcher'); /** @var EventDispatcherInterface $dispatcher */
        $this->setDispatcher($dispatcher);
    }
}
