<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\RuntimeException;
use League\Container\ContainerAwareTrait;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * ConfiguredApplicationTrait
 *
 * @return TreeBuilder
 */
trait ConfiguredApplicationTrait
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use ContainerAwareTrait;
    /**
     * get TreeBuilderschema from options
     *
     * @return TreeBuilder
     */
    public function schemaFromOptions()
    {
        $name = '';
        $treeBuilder = new TreeBuilder('');
        $options = [];
        if ($this instanceof Command) {
            $name = Command::getConfName((string) $this->getName());
            $treeBuilder = new TreeBuilder("commands/$name/options");
            $options = $this->getConfiguredApplication()->getMappedOptions($this->getName());
        } else {
            $treeBuilder = new TreeBuilder("options");
            $options = $this->getConfiguredApplication()->getMappedOptions();
        }
        $root = $treeBuilder->getRootNode();           /** @var ArrayNodeDefinition $root */
        $children = $root->children();
        foreach ($options as $mappedOption) {
            $node = null;
            if ($mappedOption->isArray()) {
                $node = $children->arrayNode($mappedOption->getName());
            } elseif ($mappedOption->isScalar()) {
                $node = $children->scalarNode($mappedOption->getName());
            } elseif ($mappedOption->isBool()) {
                $node = $children->booleanNode($mappedOption->getName());
            } elseif ($mappedOption->isArgument()) {
                $node = $children->scalarNode($mappedOption->getName());
            }
            if (null !== $node) {
                $node->info($mappedOption->getDescription());
                if (null !== $mappedOption->getDefaultValue() && !$mappedOption->isArray()) {
                    $node->defaultValue($mappedOption->getDefaultValue());
                }
            }
        }

        return $treeBuilder;
    }
    /**
     * Gets the value of an option or argument
     *
     * @param string $arg
     *
     * @return mixed
     */
    public function getOptionValue($arg)
    {
        $name = MappedOption::getConfName($arg);
        if ($this instanceof Command) {
            $cmdName = Command::getConfName((string) $this->getName());
            $key = "commands.$cmdName.options.$name";
            if (null !== $this->getConfig()->get($key)) {
                return $this->getConfig()->get($key);
            }
        }

        return $this->getConfig()->get("options.$name");
    }
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        return $this->schemaFromOptions();
    }
    /**
     * @inheritDoc
     *
     */
    public function getConfigOptions()
    {
        return [];
    }
    /**
     * @inheritDoc
     *
     */
    public function getConfiguredApplication(): ApplicationInterface
    {
        if ($this->getContainer()->has('application')) {
            $application = $this->getContainer()->get('application');
            if ($application instanceof ApplicationInterface) {
                /** @var  ApplicationInterface $application */

                return $application;
            }
        }

        throw new RuntimeException('No application has been set.');
    }
}
