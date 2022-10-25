<?php
namespace hello_world\Commands;

use DgfipSI1\Application\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(name: 'hello', description: 'A symfony command hello world example' )]
class HelloWorldCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('yell',   null, InputOption::VALUE_NONE, 'Should I yell while greeting?')
            ->addArgument('who', description: 'Who should we say hello to.', default: 'world')
            ->setHelp('This command allows you to say hello...');
    }
     /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $text = "hello ".$input->getArgument('who');
        if ($input->getOption('yell')) {
            $text = strtoupper($text);
        }
        $output->writeln($text);
        return 0;
    }

}
