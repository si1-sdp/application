<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\ApplicationTests\symfonyTestCommands;

use Composer\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * class HelloWorldCommand : provides the hello world symfony command
 */
#[AsCommand(name: 'hello', description: 'A symfony command hello world example')]
class HelloWorldCommand extends Command
{
     /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        print 'Hello world !!';

        return 0;
    }
}
