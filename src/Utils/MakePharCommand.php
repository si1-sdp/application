<?php
/*
 * This file is part of dgfip-si1/process-helper
 */
namespace DgfipSI1\Application\Utils;

use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\Config\MappedOption;
use DgfipSI1\Application\Config\OptionType;
use Phar;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make-phar', description: 'Configuration dumper')]
/**
 * Config dumper command
 */
class MakePharCommand extends Command
{
     /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $entryPoint = basename($this->getConfiguredApplication()->getEntryPoint());
        $directory = (string) realpath(dirname($this->getConfiguredApplication()->getEntryPoint()));
        $pharFile = $entryPoint.'.phar';
        $fullName =  $directory.DIRECTORY_SEPARATOR.$pharFile;
        try {
            // clean up
            if (file_exists($fullName)) {
                unlink($fullName);
            }
            // create phar
            $phar = new Phar($pharFile);
            // start buffering. Mandatory to modify stub to add shebang
            $phar->startBuffering();

            // Create the default stub from main.php entrypoint
            $defaultStub = $phar->createDefaultStub($entryPoint);

            // Add the rest of the apps files
            $phar->buildFromDirectory($directory);
            // Customize the stub to add the shebang
            $stub = "#!/usr/bin/env php\n".$defaultStub;
            // Add the stub
            $phar->setStub($stub);
            $phar->stopBuffering();
            // plus - compressing it into gzip
            $phar->compressFiles(Phar::GZ);
            // Make the file executable
            chmod($fullName, 0770);
        } catch (\Exception $e) {
            $this->getLogger()->alert($e->getMessage());

            return 1;
        }
        $this->getLogger()->notice("$pharFile successfully created");

        return 0;
    }
}
