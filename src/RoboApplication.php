<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Utils\ApplicationLogger;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use League\Container\Argument\Literal\IntegerArgument;
use League\Container\Exception\NotFoundException;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Robo\Tasks;

/**
 * class Application
 */
class RoboApplication extends AbstractApplication
{
    public const COMMAND_TAG      = 'roboCommand';
    protected const COMMAND_SUBCLASS = '\Robo\Tasks';

    /** sets the list of classes containing robo commands
     *
     * @inheritDoc
     */
    public function registerCommands()
    {
        $logContext = [ 'name' => 'registerCommands' ];
        try {
            /** @var array<Tasks> $cClasses */
            $cClasses = $this->getContainer()->get(self::COMMAND_TAG);
        } catch (NotFoundException $e) {
            $this->getLogger()->alert("No robo command(s) found", $logContext);

            return;
        }
        $commands = [];
        foreach ($cClasses as $commandClass) {
            /** @var class-string $commandClass */
            $reflectionClass = new \ReflectionClass($commandClass);
            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $reflectionClass->getName()) {
                    $commands[] = $method->getName();
                }
            }
        }
        if (sizeof($commands) > 0) {
            $logContext['count'] = count($commands);
            $this->getLogger()->info("{count} robo command(s) found", $logContext);
        } else {
            $this->getLogger()->alert("No robo command(s) found", $logContext);
        }
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
        // Instantiate Robo Runner.
        $runner = new RoboRunner();
        $runner->setContainer($this->getContainer());
        $this->getLogger()->info("Launching robo command", $logContext);
        /** @var array<Tasks> $commandClasses */
        $commandClasses = $this->getContainer()->get(self::COMMAND_TAG);

        return $runner->run($this->input, $this->output, $this, $commandClasses);        /** @phpstan-ignore-line */
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

        $namespace = $this->getNamespace(self::COMMAND_TAG);
        $this->addDiscoveries($namespace, self::COMMAND_TAG, self::COMMAND_SUBCLASS);
        Robo::finalizeContainer($this->getContainer());

        $this->discoverClasses();
        $this->registerCommands();
    }
    /**
     * Configure the container
     *
     * @return void
     */
    protected function configureContainer()
    {
        // Create and configure container.
        Robo::configureContainer(
            $this->getContainer(),
            $this,
            $this->config,
            $this->input,
            $this->output,
            $this->classLoader
        );
        $this->getContainer()->extend('logger')->setAlias('roboLogger');
        $verbosity = ApplicationLogger::getVerbosity($this->input);
        $this->getContainer()->inflector(ConfigAwareInterface::class)->invokeMethod('setConfig', ['config']);
        $this->getContainer()->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', ['logger']);
        $this->getContainer()->addShared('verbosity', new IntegerArgument($verbosity));
        $this->getContainer()->addShared('internal_configuration', $this->intConfig);
        $this->getContainer()->addShared('logger', $this->logger);
        $this->getContainer()->addShared('input_options_setter', InputOptionsSetter::class);
    }
}
