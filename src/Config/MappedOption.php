<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use DgfipSI1\Application\AbstractApplication;
use DgfipSI1\Application\Application;
use Symfony\Component\Console\Input\InputOption as symfonyOption;
use Symfony\Component\Console\Input\InputOption;

/**
 * Mapped Option class
 */
class MappedOption
{
    /** @var InputOption $inputOption */
    protected $inputOption;

    /**
     * @param string                                  $name
     * @param OptionType                              $type
     * @param string                                  $description
     * @param string|null                             $optShort
     * @param array<mixed>|bool|float|int|string|null $default
     *
     * @return void;
     */
    public function __construct($name, $type, $description = '', $optShort = null, $default = null)
    {
        $name = str_replace('_', '-', $name);
        $mode = $type->mode();
        $this->inputOption = new InputOption($name, $optShort, $mode, $description, $default);
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
}
