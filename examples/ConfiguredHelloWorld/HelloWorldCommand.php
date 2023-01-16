<?php
namespace hello_world\Commands;

use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\ConfigHelper\ConfigHelper;
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
        return $opts;
    }
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
