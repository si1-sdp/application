<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\ApplicationTests\TestClasses\Commands;

use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\ConfiguredApplicationTrait;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType as OT;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * class ManyOptions : provides a class with many options
 */
class ManyOptions implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
    /**
     * Undocumented function
     *
     * @return array<MappedOption>
     */
    public function getConfigOptions()
    {
        $opts = [];
        $opts[] = new MappedOption('opt-bool-true', OT::Boolean, optShort: 'B', default: true);   //    4
        $opts[] = new MappedOption('opt-bool', OT::Boolean);                                 //  * 3 = 12
        $opts[] = new MappedOption('opt-scalar-foo', OT::Scalar, optShort: 'S', default: 'boo');  //  * 3 = 36
        $opts[] = new MappedOption('opt-array-12', OT::Array, optShort: 't', default: [1, 2]);  //  * 5 = 180
        $opts[] = new MappedOption('opt-arg-required', OT::Argument, required: true);                //  * 1 = 180
        $opts[] = new MappedOption('opt-arg-optional', OT::Argument);                                //  * 2 = 360

        return $opts;
    }
}
