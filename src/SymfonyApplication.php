<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Command as ApplicationCommand;
use DgfipSI1\Application\Config\ConfigLoader;
use DgfipSI1\Application\Config\InputOptionsInjector;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Contracts\AppAwareInterface;
use DgfipSI1\Application\Exception\RuntimeException as ExceptionRuntimeException;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use League\Container\Argument\Literal\IntegerArgument;
use League\Container\ContainerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
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
            $logCtx = ['name' => 'findCommand', 'cmd' => $command->getName()];
            $this->getLogger()->notice("command {cmd} registered", $logCtx);
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
            /** @var array<ApplicationCommand> $results */
            $results = $this->getContainer()->get($cmdName);
            if (is_array($results) && count($results) === 1) {
                return array_shift($results);
            }
        }
        throw new ExceptionRuntimeException(sprintf("Error looking for command %s in container", $cmdName));
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
        $statusCode = 0;
        $logContext['cmd_name'] = $this->isSingleCommand() ? 'list' : $this->input->getFirstArgument();
        $this->getLogger()->notice("Launching symfony command '{cmd_name}'", $logContext);
        $statusCode = $this->run($this->input, $this->output);

        return $statusCode;
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
        /** @var string $namespace */
        $namespace = $this->intConfig->get(CONF::APPLICATION_NAMESPACE);
        // Create and configure container.
        $this->configureContainer();

        /** @var ClassDiscoverer $disc */
        $disc = $this->getContainer()->get('class_discoverer');
        $disc->addDiscoverer($namespace, self::COMMAND_TAG, self::COMMAND_SUBCLASS, idAttribute:'name');

        // Discover configurator classes
        $appClass = ApplicationAwareInterface::class;
        $cmdClass = ApplicationCommand::class;
        $disc->addDiscoverer($namespace, self::COMMAND_CONFIG_TAG, [ $appClass, $cmdClass ], idAttribute:'name');
        $disc->addDiscoverer($namespace, self::GLOBAL_CONFIG_TAG, [ $appClass ], excludeDeps: [ $cmdClass ]);

        // Discover event listeners
        $disc->addDiscoverer($namespace, self::EVENT_LISTENER_TAG, [ EventSubscriberInterface::class]);

        // launch discoverer
        $disc->discoverAllClasses();

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
        $command = $this->getCommandName($this->input);
        $optionsSetter->setInputOptions($this->intConfig, "$command");
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
        $this->getContainer()->addShared('logger', ApplicationLogger::class)
            ->addArguments(['internal_configuration', 'output', 'verbosity']);
        $this->getContainer()->addShared('classLoader', $this->classLoader);
        $this->getContainer()->addShared('class_discoverer', ClassDiscoverer::class)->addArgument('classLoader');
        $this->getContainer()->addShared('input_options_setter', InputOptionsSetter::class);
        $this->getContainer()->addShared('input_options_injector', InputOptionsInjector::class);
        $this->getContainer()->addShared('configuration_loader', ConfigLoader::class)
            ->addMethodCall('configure', ['internal_configuration']);
        $this->getContainer()->addShared('eventDispatcher', \Symfony\Component\EventDispatcher\EventDispatcher::class)
            ->addMethodCall('addSubscriber', ['configuration_loader'])       // 100 priority
            ->addMethodCall('addSubscriber', ['input_options_injector']);   // 90 priority
        $this->setAutoExit(false);
        // see also applyInflectorsBeforeContainerConfiguration to have this done to objects before bootstrap...
        $this->getContainer()->inflector(ConfigAwareInterface::class)->invokeMethod('setConfig', ['config']);
        $this->getContainer()->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', ['logger']);
        $this->getContainer()->inflector(ContainerAwareInterface::class)->invokeMethod('setContainer', ['container']);
        $this->getContainer()->inflector(AppAwareInterface::class)->invokeMethod('setApplication', ['application']);

        $logger = $this->getContainer()->get('logger');              /** @var LoggerInterface $logger */
        $this->logger = $logger;
        $dispatcher = $this->getContainer()->get('eventDispatcher'); /** @var EventDispatcherInterface $dispatcher */
        $this->setDispatcher($dispatcher);
    }
}