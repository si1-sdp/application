<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\RuntimeException;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;

/**
 * Mapped Option class
 */
class ClassDiscoverer implements LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    /** @var array<string,array<DiscovererDefinition>> $discoverers */
    protected $discoverers;

    /** @var ClassLoader $classLoader  */
    protected $classLoader;

    /** @var array<string,int> $tagCount  */
    protected $tagCount;

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
        $this->discoverers = [];
        $this->tagCount    = [];
    }
    /**
     * Add discoverer
     * Note :
     * - dependencies work with logical AND : all dependencies have to be met
     * - exclusions work with logical OR : anny exclusion filters class out.

     * @param array<string>|string $namespaces
     * @param string               $tag
     * @param array<string>|string $deps
     * @param array<string>|string $excludeDeps
     * @param string|null          $idAttribute
     * @param boolean              $emptyOk
     *
     * @return void
     */
    public function addDiscoverer($namespaces, $tag, $deps, $excludeDeps = [], $idAttribute = null, $emptyOk = true)
    {
        $def = new DiscovererDefinition($namespaces, $tag, $deps, $excludeDeps, $idAttribute);
        if (false === $emptyOk) {
            $this->tagCount[$tag] = 0;
        }
        if (!empty($def->getErrMessages())) {
            foreach ($def->getErrMessages() as $msg) {
                $this->getLogger()->warning($msg, ['name' => 'addDiscoverer']);
            }
        }
        foreach ($def->getNamespaces() as $ns) {
            $this->discoverers[$ns][] = $def;
        }
    }
    /**
     * Discovers all classes in $this->discoverers array
     *
     * @return void
     */
    public function discoverAllClasses()
    {
        $logContext = ['name'  => 'discoverAllClasses'];
        $this->getLogger()->debug("Discovering all classes...", $logContext);
        foreach (array_keys($this->discoverers) as $namespace) {
            $classes = $this->discoverClassesInNamespace($namespace);
            $classNames = function (\ReflectionClass $r) {
                return $r->getShortName();
            };
            foreach ($this->discoverers[$namespace] as $def) {
                $logContext = [
                    'name'      => 'discoverAll',
                    'tag'       => $def->getTag(),
                    'deps'      => "[".implode(', ', array_map($classNames, $def->getDependencies()))."]",
                    'excludes'  => "[".implode(', ', array_map($classNames, $def->getExcludeDeps()))."]",
                    'attribute' => $def->getIdAttribute() ?? '',
                ];
                $filtered = $this->filterClasses($classes, $def->getDependencies(), $def->getExcludeDeps());
                if (array_key_exists($def->getTag(), $this->tagCount)) {
                    $this->tagCount[$def->getTag()] += count($filtered);
                }
                $this->registerClasses($filtered, $def->getTag(), $def->getIdAttribute());
                $logContext['count'] = count($filtered);
                $message = "{tag} : Search {deps} classes, excluding {excludes} : {count} classe(s) found.";
                $this->getLogger()->info($message, $logContext);
            }
        }
        foreach ($this->tagCount as $tag => $count) {
            if ($count < 1) {
                throw new RuntimeException(sprintf('No classes found for tag %s', $tag));
            }
        }
    }
    /**
     * Add classes to container, tag according to definition
     *
     * @param array<class-string> $classes
     * @param string              $tag
     * @param string|null         $idAttribute
     *
     * @return void
     */
    protected function registerClasses($classes, $tag, $idAttribute = null)
    {
        $logContext = ['name'  => 'registerClasses', 'tag' => $tag];
        /** @var class-string $class */
        foreach ($classes as $class) {
            $logContext['class'] = $class;
            try {
                $serviceId = $this->getAttributeValue($class, $idAttribute);
            } catch (\Exception $e) {
                $msg = "invalid service id for class {class}, '.$idAttribute.' attribute argument not found";
                $this->getLogger()->warning($msg, $logContext);
                continue;
            }
            $logContext['id'] = $serviceId;
            if ($this->getContainer()->has((string) $class)) {
                $this->getLogger()->debug("Class {class} already in container", $logContext);
                $serviceDefinition = $this->getContainer()->extend((string) $class);
            } else {
                $this->getLogger()->debug("Adding class {class} to container", $logContext);
                $serviceDefinition = $this->getContainer()->addShared((string) $class);
            }
            if (null !== $serviceId && !$serviceDefinition->hasTag((string) $serviceId)) {
                $this->getLogger()->debug("Add tag {id} for class {class}", $logContext);
                $serviceDefinition->addTag((string) $serviceId);
            }
            if (!$serviceDefinition->hasTag($tag)) {
                $this->getLogger()->debug("Add tag {tag} for class {class}", $logContext);
                $serviceDefinition->addTag($tag);
            }
        }
    }
    /**
     * get attribute value
     *
     * @param class-string $class
     * @param string|null  $attributeName
     *
     * @return string|null
     */
    protected function getAttributeValue($class, $attributeName)
    {
        if (null === $attributeName) {
            return null;
        }
        try {
            $attributes = (new \ReflectionClass($class))->getAttributes();
            $value = null;
            foreach ($attributes as $attribute) {
                $attributeArguments = $attribute->getArguments();
                if (array_key_exists($attributeName, $attributeArguments)) {
                    $value = $attributeArguments[$attributeName];
                    break;
                }
            }
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf('Error inspecting class "%s"', $class));
        }
        if (null === $value) {
            $msg = "Attribute '%s' not found in class '%s'";
            throw new RuntimeException(sprintf($msg, $attributeName, $class));
        }

        return $value;
    }


    /** discovers classes that are in a directory ($namespace) imediatly under 'src'
     *
     * @param string $namespace
     *
     * @return array<string>
     */
    protected function discoverClassesInNamespace($namespace)
    {
        $logContext = ['name' => 'discoverClassesInNamespace', 'namespace' => $namespace ];
        $classes = (new RelativeNamespaceDiscovery($this->classLoader))
            ->setRelativeNamespace($namespace)
            ->getClasses();
        foreach ($classes as $class) {
            $logContext['class'] = $class;
            $this->getLogger()->debug("Search {namespace} namespace - found {class}", $logContext);
        }
        $this->getLogger()->info(count($classes)." classe(s) found in namespace {namespace}.", $logContext);

        return $classes;
    }
    /**
     * filters out classes not dependent of $dependencies
     * also filters out abstract classes, interfaces and traits
     *
     * @param array<string>           $classes
     * @param array<\ReflectionClass> $dependencies
     * @param array<\ReflectionClass> $excludeDeps
     *
     * @return array<class-string>
     */
    protected function filterClasses($classes, $dependencies, $excludeDeps = [])
    {
        $filteredClasses = [];
        foreach ($classes as $className) {
            $logContext = ['name' => 'filterClasses', 'class' => $className ];
            $this->getLogger()->debug("Applying Filters to {class}", $logContext);
            try {
                /** @var class-string $className */
                $class = new \ReflectionClass($className);
                /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
            } catch (\ReflectionException $e) {
                $this->getLogger()->warning($e->getMessage(), $logContext);
                continue;
            }
            if ($class->isAbstract() || $class->isInterface() || $class->isTrait()) {
                continue;
            }
            if ($this->classMatchesFilters($class, $dependencies, $excludeDeps)) {
                $filteredClasses[] = $className;
                $this->getLogger()->debug("Keeping {class}", $logContext);
            }
        }

        return $filteredClasses;
    }
    /**
     * classMatchesFilters - returns true if class derives from all dependencies
     * and does not derive from any exclude dependency
     *
     * @param \ReflectionClass        $class
     * @param array<\ReflectionClass> $dependencies
     * @param array<\ReflectionClass> $excludeDeps
     *
     * @return bool
     */
    protected function classMatchesFilters($class, $dependencies, $excludeDeps)
    {
        // exclusions work with logical OR : anny exclusion filters class out.
        if (!empty($excludeDeps)) {
            foreach ($excludeDeps as $dep) {
                if ($this->dependsOn($class, $dep)) {
                    return false;
                }
            }
        }
        // dependencies work with logical AND : all dependencies have to be met
        foreach ($dependencies as $dep) {
            if (!$this->dependsOn($class, $dep)) {
                return false;
            }
        }

        return true;
    }
    /**
     * Undocumented function
     *
     * @param \ReflectionClass $class
     * @param \ReflectionClass $dep
     *
     * @return bool
     */
    protected function dependsOn($class, $dep)
    {
        return ($dep->isInterface() && $class->implementsInterface($dep)) || $class->isSubclassOf($dep);
    }
}
