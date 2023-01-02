<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\ApplicationTests\TestClasses\Commands;

use DgfipSI1\Application\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * class HelloWorldCommand : provides the hello world symfony command
 */
#[AsCommand(name: 'hello-world', description: 'A symfony command hello world example')]
class HelloWorldCommand extends Command
{
     /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        print 'Hello world !!';
        $this->getLogger()->debug('Hello world just ran !');

        return 0;
    }
}
