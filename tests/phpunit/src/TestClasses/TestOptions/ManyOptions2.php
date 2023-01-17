<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\ApplicationTests\TestClasses\TestOptions;

use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\ConfiguredApplicationTrait;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType as OT;

/**
 * class ManyOptions : provides a class with many options
 */
class ManyOptions2 implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
    public const LIMIT = 4;
    public const SHUFFLE = false;  // shuffle not possible because we have 2 arguments
    /**
     * Undocumented function
     *
     * @return array<MappedOption>
     */
    public function getConfigOptions()
    {
        $opts = [];
        $opts[] = new MappedOption('arg-required', OT::Argument, required: true);              //
        $opts[] = new MappedOption('arg-array', OT::ArgArray);                              //
        $opts[] = new MappedOption('opt-bool-true', OT::Boolean, optShort: 'B', default: true); //
        $opts[] = new MappedOption('opt-bool', OT::Boolean);                               //
        $opts[] = new MappedOption('opt-array-12', OT::Array, optShort: 't', default: [1, 2]); //
        //$opts[] = new MappedOption('arg-optional'  , OT::Argument);

        return $opts;
    }
}
