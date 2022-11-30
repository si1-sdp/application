<?php
/*
 * This file is part of dgfip-si1/process-helper
 */
namespace DgfipSI1\ApplicationTests\TestClasses\configSchemas;

use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\ConfiguredApplicationTrait;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * global application schema
 */
class HelloWorldAutoSchema implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
}
