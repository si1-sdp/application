<?php
/*
 * This file is part of dgfip-si1/process-helper
 */
namespace DgfipSI1\Application\Config;

use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dumpconfig', description: 'Configuration dumper')]
/**
 * Config dumper command
 */
class DumpconfigCommand extends Command
{
     /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        print $this->getConfig()->dumpSchema();

        return 0;
    }
}
