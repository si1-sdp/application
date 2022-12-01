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
use Symfony\Component\Console\Application;

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
        $options = [];
        $treeBuilder = new TreeBuilder('');
        $name = '';
        if ($this instanceof Command) {
            $name = $this->getName();
            $treeBuilder = new TreeBuilder("commands/$name/options");
            $options = $this->getConfiguredApplication()->getMappedOptions($name);
        } elseif ($this instanceof ConfiguredApplicationInterface) {  /** @phpstan-ignore-line */
            $treeBuilder = new TreeBuilder("options");
            $options = $this->getConfiguredApplication()->getMappedOptions();
            $name = 'global';
        }
        $root = $treeBuilder->getRootNode();           /** @var ArrayNodeDefinition $root */
        $children = $root->children();
        foreach ($options as $mappedOption) {
            $opt = $mappedOption->getOption();
            if ($opt->isArray()) {
                $node = $children->arrayNode($opt->getName());
                if (!empty($opt->getDefault())) {
                    $node->defaultValue($opt->getDefault());
                }
            } elseif ($opt->isValueRequired()) {
                $node = $children->scalarNode($opt->getName());
                if (null !== $opt->getDefault()) {
                    $node->defaultValue($opt->getDefault());
                }
            } else {
                $node = $children->booleanNode($opt->getName());
            }
            $node->info($mappedOption->getDescription());
        }

        return $treeBuilder;
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
        if (null !== $this->getContainer() && $this->getContainer()->has('application')) {
            $application = $this->getContainer()->get('application');
            if ($application instanceof ApplicationInterface) {
                /** @var  ApplicationInterface $application */

                return $application;
            }
        }

        throw new RuntimeException('No application has been set.');
    }
}
