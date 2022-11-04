<?php
namespace hello_world\Commands;

use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use DgfipSI1\Application\Config\MappedOption;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(name: 'hello', description: 'A symfony command hello world example' )]
class HelloWorldCommand extends Command implements ApplicationAwareInterface
{
    public const CONFIG_YELL   = 'hello.greeting.yell';
    public const CONFIG_FORMAL = 'hello.greeting.formal';
    /** 
     * @inheritDoc 
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('commands/hello/options');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('yell')->defaultFalse()->end()
                ->scalarNode('formal')->defaultFalse()->end()
            ->end();
        return $treeBuilder;
    }
    /**
     * @inheritDoc
     */
    public function getConfigOptions()
    {
        $opts = [];
        $opt = new InputOption('yell',   null, InputOption::VALUE_NEGATABLE, 'Should I yell while greeting?');
        $opts[] = new MappedOption($opt, self::CONFIG_YELL);
        $opt = new InputOption('formal', null, InputOption::VALUE_NONE, 'Should I be more formal?');
        $opts[] = new MappedOption($opt, self::CONFIG_FORMAL);
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
        $user = $this->conf()->get("options.user");
        $fullUser = $this->conf()->get("people.$user.first_name");
        $formal = $this->conf()->get("commands.hello.options.formal");
        if ($formal) {
            $fullUser = $this->conf()->get("people.$user.title")." ".$this->conf()->get("people.$user.name");
        }
        $text = "hello $fullUser";
        if ($this->conf()->get("commands.hello.options.yell")) {
            $text = strtoupper($text);
        }
        $output->writeln($text);
        return 0;
    }

}
