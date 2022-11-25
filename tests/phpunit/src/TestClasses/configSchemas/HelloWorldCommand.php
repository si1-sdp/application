<?php
/*
 * This file is part of dgfip-si1/process-helper
 */
namespace DgfipSI1\ApplicationTests\TestClasses\configSchemas;

use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\ApplicationAwareInterface;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'hello', description: 'A symfony command hello world example')]
/**
 * Hello world command test class
 */
class HelloWorldCommand extends Command implements ApplicationAwareInterface
{
    public const CONFIG_YELL   = 'hello.greeting.yell';
    public const CONFIG_FORMAL = 'hello.greeting.formal';
    public const DUMPED_SHEMA =
    '    commands:
        hello:
            options:
                yell:                 false
                formal:               true
';
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('commands/hello/options');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('yell')->defaultFalse()->end()
                ->scalarNode('formal')->defaultTrue()->end()
            ->end();

        return $treeBuilder;
    }
    /**
     * @inheritDoc
     */
    public function getConfigOptions()
    {
        $opts = [  new MappedOption('test-a', OptionType::Array)   ];

        return $opts;
    }
     /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string $user */
        $user = $this->config->get("options.user");
        $text = "hello $user";
        if ($this->config->get("commands.hello.options.yell")) {
            $text = strtoupper($text);
        }
        $output->writeln($text);

        return 0;
    }
}
