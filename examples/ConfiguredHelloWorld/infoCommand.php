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


#[AsCommand(name: 'info', description: 'Gets info about people' )]
class InfoCommand extends Command implements ApplicationAwareInterface
{
    /** 
     * @inheritDoc 
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('commands/info/options');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('list')->defaultFalse()->end()
            ->end();
        return $treeBuilder;    
    }
    /**
     * @inheritDoc
     * @deprecated 
     */   
    public function getConfigOptions()
    {
        $opts = [];
        $opt = new InputOption('list',   null, InputOption::VALUE_NONE, 'list all people');
        $opts[] = new MappedOption($opt);
        return $opts;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->config->get('commands.info.options.list') === true) {
            foreach (array_keys($this->config->get('people')) as $id) {
                $full = "[$id] => ".$this->config->get("people.$id.title").' ';
                $full.= $this->config->get("people.$id.first_name") .' '. $this->config->get("people.$id.name")."\n";
                print $full;
            }
        } else {
            $id = $this->config->get('options.user');
            print "Information for id $id : \n";
            print "       Title : ".$this->config->get("people.$id.title")."\n";
            print "  First name : ".$this->config->get("people.$id.first_name") ."\n";
            print "   Last name : ".$this->config->get("people.$id.name")."\n";
        }
        return 0;
    }

}
