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
class ManyOptions1 implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
    public const LIMIT = 3;
    public const SHUFFLE = true;
    /**
     * Undocumented function
     *
     * @return array<MappedOption>
     */
    public function getConfigOptions()
    {
        $opts = [];
        $opts[] = new MappedOption('arg-opt', OT::Argument, required: false);             //
        $opts[] = new MappedOption('opt-bool-true', OT::Boolean, optShort: 'B', default: true); //
        $opts[] = new MappedOption('opt-bool', OT::Boolean);                               //
        $opts[] = new MappedOption('opt-scalar-foo', OT::Scalar, optShort: 'S', default: 'boo'); //

        return $opts;
    }
}
