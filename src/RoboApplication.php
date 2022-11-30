<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Config\InputOptionsSetter;
use DgfipSI1\Application\Utils\ClassDiscoverer;
use League\Container\Argument\Literal\IntegerArgument;
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
        /** @var array<Tasks> $cClasses */
        $cClasses = $this->getContainer()->get(self::COMMAND_TAG);
        foreach ($cClasses as $commandClass) {
            /** @var class-string $commandClass */
            $reflectionClass = new \ReflectionClass($commandClass);
            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $reflectionClass->getName()) {
                    $commands[] = $method->getName();
                }
            }
        }
        if (!empty($commands)) {
            $logContext['count'] = count($commands);
            $this->getLogger()->notice("{count} robo command(s) found", $logContext);
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
        $statusCode = 0;
        // Instantiate Robo Runner.
        $runner = new RoboRunner();
        $runner->setContainer($this->getContainer());
        //print(implode("\n", array_keys($this->container->getDefinitions()))."\n\n");
        $this->getLogger()->notice("Launching robo command", $logContext);
        /** @var array<Tasks> $commandClasses */
        $commandClasses = $this->getContainer()->get(self::COMMAND_TAG);
        /** @phpstan-ignore-next-line */
        $statusCode  = $runner->run($this->input, $this->output, $this, $commandClasses);

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
        $this->configureContainer();
        /** @var ClassDiscoverer $disc */
        $disc = $this->getContainer()->get('class_discoverer');
        $namespace = $this->getNameSpace(self::COMMAND_TAG);
        $disc->addDiscoverer($namespace, self::COMMAND_TAG, self::COMMAND_SUBCLASS, emptyOk: false);
        Robo::finalizeContainer($this->getContainer());
        /** Discover all needed classes  */
        $disc->discoverAllClasses();
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
        $this->getContainer()->addShared('logger', ApplicationLogger::class)
            ->addArguments(['internal_configuration', 'output', 'verbosity']);
        $this->getContainer()->addShared('input_options_setter', InputOptionsSetter::class);
        $this->getContainer()->addShared('class_discoverer', ClassDiscoverer::class)
            ->addArgument('classLoader');
    }
}
