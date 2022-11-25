<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use ReflectionClass;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;

/**
 * Mapped Option class
 */
class DiscovererDefinition
{
    /** @var array<string> $namespaces */
    protected $namespaces;
    /** @var string $tag */
    protected $tag;
    /** @var array<\ReflectionClass> $dependencies */
    protected $dependencies;
    /** @var array<\ReflectionClass> $excludeDeps */
    protected $excludeDeps;
    /** @var string|null $idAttribute */
    protected $idAttribute;
    /** @var bool $emptyOk */
    protected $emptyOk;


    /** @var array<string> $errMessages */
    protected $errMessages;

    /**
     * Constructor
     *
     * @param array<string>|string $namespaces
     * @param string               $tag
     * @param array<string>|string $deps
     * @param array<string>|string $excludeDeps
     * @param string|null          $idAttribute
     * @param boolean              $emptyOk
     *
     * @return array<string>
     */
    public function __construct($namespaces, $tag, $deps = [], $excludeDeps = [], $idAttribute = null, $emptyOk = true)
    {
        if (!is_array($namespaces)) {
            $namespaces = [$namespaces];
        }
        $this->namespaces   = $namespaces;
        $this->tag          = $tag;
        $this->dependencies = [];
        if (!is_array($deps)) {
            $deps = [ $deps ];
        }
        foreach ($deps as $dependency) {
            try {
                /** @var class-string $dependency */
                $depRef = new \ReflectionClass($dependency);
                /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
            } catch (\ReflectionException $e) {
                $this->errMessages[] = $e->getMessage();
                continue;
            }
            $this->dependencies[] = $depRef;
        }
        $this->excludeDeps = [];
        if (!is_array($excludeDeps)) {
            $excludeDeps = [ $excludeDeps ];
        }
        foreach ($excludeDeps as $dependency) {
            try {
                /** @var class-string $dependency */
                $depRef = new \ReflectionClass($dependency);
                /** @phpstan-ignore-next-line -- dead catch falsely detected by phpstan */
            } catch (\ReflectionException $e) {
                $this->errMessages[] = $e->getMessage();
                continue;
            }
            $this->excludeDeps[] = $depRef;
        }
        $this->idAttribute  = $idAttribute;
        $this->emptyOk      = $emptyOk;
    }


    /**
     * Get the value of namespaces
     *
     * @return array<string>
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }
    /**
     * Get the value of tag
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }
    /**
     * Get the value of dependencies
     *
     * @return array<ReflectionClass>
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }
    /**
     * Get the value of excludeDeps
     *
     * @return array<ReflectionClass>
     */
    public function getExcludeDeps()
    {
        return $this->excludeDeps;
    }
    /**
     * Get the value of idAttribute
     *
     * @return string|null
     */
    public function getIdAttribute()
    {
        return $this->idAttribute;
    }
    /**
     * Get the value of emptyOk
     *
     * @return bool
     */
    public function getEmptyOk()
    {
        return $this->emptyOk;
    }
    /**
     * Get error messages
     *
     * @return array<string>
     */
    public function getErrMessages()
    {
        return $this->errMessages;
    }
}
