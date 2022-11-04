<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use DgfipSI1\Application\Application;
use Symfony\Component\Console\Input\InputOption as symfonyOption;

/**
 * Mapped Option class
 */
class MappedOption
{
    /** @var string $mapping */
    protected $mapping;

    /** @var symfonyOption $inputOption */
    protected $inputOption;

    /**
     * @param symfonyOption $opt
     * @param string|null   $mapping
     *
     * @return void;
     */
    public function __construct($opt, $mapping = null)
    {
        $this->inputOption = $opt;
        if (null === $mapping) {
            $mapping = Application::DEFAULT_MAP_ROOT.".".$opt->getName();
        }
        $this->mapping     = $mapping;
    }
    /**
     * inputOption getter
     *
     * @return symfonyOption
     */
    public function getOption()
    {
        return $this->inputOption;
    }
    /**
     * mapping getter
     *
     * @return string|null
     */
    public function getMapping()
    {
        return $this->mapping;
    }
}
