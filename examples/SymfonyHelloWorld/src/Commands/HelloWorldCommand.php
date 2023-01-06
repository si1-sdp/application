<?php
namespace hello_world\Commands;

use Composer\Console\Input\InputArgument;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(name: 'hello', description: 'A symfony command hello world example' )]
class HelloWorldCommand extends Command
{
    public function getConfigOptions()
    {
        $opts = [];
        $opts[] = new MappedOption('yell', OptionType::Boolean, 'Should I yell while greeting ?');
        $opts[] = new MappedOption('who', OptionType::Argument, 'Who should we say hello to.', default: 'world');
        return $opts;
    }
    protected function configure(): void
    {
        $this
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
