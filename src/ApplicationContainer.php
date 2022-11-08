<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use League\Container\Container;

/**
 * class ApplicationContainer
 * a League\Container\Container container whith function to get lists of definnitions and services
 */
class ApplicationContainer extends Container implements ApplicationContainerInterface
{
    /**
     * @inheritDoc
     */
    public function getDefinitions(string $regex = null, string $tag = null, string $baseInstance = null)
    {
        $ret = [];
        foreach ($this->definitions->getIterator() as $key => $definition) {
            /** @var \League\Container\Definition\Definition $definition */
            $alias = $definition->getAlias();
            if ((null !== $regex) && !preg_match($regex, $alias)) {
                continue;
            }
            if ((null !== $tag) && !$definition->hasTag($tag)) {
                continue;
            }
            if ((null !== $baseInstance) && !($definition instanceof $baseInstance)) {
                continue;
            }
            $ret[$alias] = $definition;
        }

        return $ret;
    }

    /**
     * @inheritDoc
     */
    public function getServices(string $regex = null, string $tag = null, string $baseInstance = null)
    {
        $ret = [];
        foreach ($this->getDefinitions($regex, $tag, $baseInstance) as $alias => $definition) {
            $obj =  $this->resolve($alias);
            if (is_object($obj)) {
                $ret[$alias] = $obj;
            }
        }

        return $ret;
    }
}
