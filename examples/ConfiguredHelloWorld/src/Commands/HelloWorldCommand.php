<?php
namespace hello_world\Commands;

use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use DgfipSI1\Application\Config\ApplicationAwareTrait;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(name: 'hello', description: 'A symfony command hello world example' )]
class HelloWorldCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function getConfigOptions()
    {
        $opts = [];
        $opts[] = new MappedOption('formal', OptionType::Boolean, 'Should I be more formal ?');
        return $opts;
    }
    // protected function configure(): void
    // {
    //     /** options are automatically added by getConfigOptions function */
    //     $this
    //         ->addArgument('who', description: 'Who should we say hello to.', default: 'world')
    //         ->setHelp('This command allows you to say hello...');
    // }
     /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->config->get("options.user");
        $fullUser = $this->config->get("people.$user.first_name");
        $formal = $this->config->get("commands.hello.options.formal");
        if ($formal) {
            $fullUser = $this->config->get("people.$user.title")." ".$this->config->get("people.$user.name");
        }
        $text = "hello $fullUser";
        if ($this->config->get("commands.hello.options.yell")) {
            $text = strtoupper($text);
        }
        $output->writeln($text);
        return 0;
    }

}
