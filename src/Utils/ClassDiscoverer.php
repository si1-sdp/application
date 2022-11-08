<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;

/**
 * Mapped Option class
 */
class ClassDiscoverer implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    /** @var ClassLoader $classLoader  */
    protected $classLoader;
    /**
     * Undocumented function
     *
     * @param ClassLoader $classLoader
     *
     * @return array<string>
     */
    public function __construct($classLoader)
    {
        $this->classLoader = $classLoader;
    }
        /**
     * Discovers commands that are PSR4 auto-loaded.
     *
     * @param string            $namespace
     * @param class-string|null $dependency Filter classes on class->implementsInterface(dependency)
     *                                      or class->isSubClassOf(dependency)
     * @param bool              $silent     Do not warn if no class found
     *
     * @return array<string>
     *
     * @throws \ReflectionException
     */
    public function discoverPsr4Classes($namespace, $dependency = null, $silent = false): array
    {
        $logContext = ['name' => 'discoverPsr4Classes', 'namespace' => $namespace, 'dependency' => $dependency ];
        $depRef = null;
        if (null !== $dependency) {
            try {
                $depRef = new \ReflectionClass($dependency);
                /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
            } catch (\ReflectionException $e) {
                if (null === $this->logger) {
                    throw $e;
                }
                $this->logger->warning($e->getMessage(), $logContext);

                return [];
            }
        }
        $classes = $this->discoverClassesInNamespace($namespace);
        $classes = $this->filterClasses($classes, $depRef);
        if (null !== $this->logger) {
            if (empty($classes) && false === $silent) {
                $msg = "No classes subClassing or implementing {dependency} found in namespace '{namespace}'";
                $this->logger()->warning($msg, $logContext);
            } else {
                foreach ($classes as $class) {
                    $logContext['class'] = $class;
                    $this->logger->debug("2/2 - Filter : {class} matches", $logContext);
                }
                $count = count($classes);
                $this->logger->info("2/2 - $count classe(s) found in namespace '{namespace}'", $logContext);
            }
        }

        return $classes;
    }

    /** discovers classes that are in a directory ($namespace) imediatly under 'src'
     *
     * @param string $namespace
     *
     * @return array<string>
     */
    protected function discoverClassesInNamespace($namespace)
    {
        $logContext = ['name' => 'discoverPsr4Classes', 'namespace' => $namespace ];
        $classes = (new RelativeNamespaceDiscovery($this->classLoader))
            ->setRelativeNamespace($namespace)
            ->getClasses();
        if (null !== $this->logger) {
            foreach ($classes as $class) {
                $logContext['class'] = $class;
                $this->logger->debug("1/2 - search {namespace} namespace - found {class}", $logContext);
            }
            $this->logger->info("1/2 - ".count($classes)." classe(s) found.", $logContext);
        }

        return $classes;
    }
    /**
     * filters out classes not dependent of $dependency
     * also filters out abstract classes, interfaces and traits
     *
     * @param array<string>         $classes
     * @param \ReflectionClass|null $dependency
     *
     * @return array<string>
     */
    protected function filterClasses($classes, $dependency)
    {
        $logContext = ['name' => 'discoverPsr4Classes' ];
        $filteredClasses = [];
        foreach ($classes as $class) {
            try {
                /** @var class-string $class */
                $refClass = new \ReflectionClass($class);
                /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
            } catch (\ReflectionException $e) {
                if (null !== $this->logger) {
                    $this->logger()->warning("2/2 ".$e->getMessage(), $logContext);
                    continue;
                } else {
                    throw $e;
                }
            }
            if ($refClass->isAbstract() || $refClass->isInterface() || $refClass->isTrait()) {
                continue;
            }
            if (null === $dependency) {
                $filteredClasses[] = $class;
            } else {
                if (($dependency->isInterface() && $refClass->implementsInterface($dependency)) ||
                     $refClass->isSubclassOf($dependency)) {
                    $filteredClasses[] = $class;
                }
            }
        }

        return $filteredClasses;
    }
}
