<?php
/*
 * This file is part of dgfip-si1/process-helper
 */
namespace DgfipSI1\Application\Utils;

use Composer\Console\Application;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\Utils\DirectoryStasher;
use DgfipSI1\Application\Exception\RuntimeException;
use Phar;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make-phar', description: 'Configuration dumper')]
/**
 * Config dumper command
 */
class MakePharCommand extends Command
{
    /** @var array<string> $testFiles */
    protected $testFiles     = ['tests', 'phpcs.xml', 'phpstan.neon.dist', 'phpunit.xml'];
    /** @var array<string> $devFiles */
    protected $devFiles      = ['.git', '.gitignore', '.vscode'  ];
    /** @var array<string> $composerFiles */
    protected $composerFiles = ['bin', 'vendor'];
    /** @var array<string> $variousFiles */
    protected $variousFiles  = [ 'README.md', 'CONTRIBUTING.md' ];
    /** @var array<string> $pharExcludes */
    protected $pharExcludes  = [ 'vendor/bin', 'composer.lock', 'composer.json'];


    /**
     * sets the pharRoot directory
     *
     * @return string
     */
    public static function getPharRoot()
    {
        return Phar::running();
    }
    /**
     * @inheritDoc
     *
     */
    public function configure(): void
    {
        $excludeText  = "  * <info>Test files</>        : ".implode(', ', $this->testFiles)."\n";
        $excludeText .= "  * <info>Development files</> : ".implode(', ', $this->devFiles)."\n";
        $excludeText .= "  * <info>Composer files</>    : ".implode(', ', $this->composerFiles)."\n";
        $excludeText .= "  * <info>Various files</>     : ".implode(', ', $this->variousFiles)."\n";

        $this->setHelp(<<<EOH
The <info>%command.name%</> command  :

To use this command, php.ini 'phar.readonly' must be set to false, you can override this by running
       <info>php -d phar.readonly=false <command> make-phar</>

<comment>What will be done :
===================</comment>
  - run <info>composer install --no-dev</> to get rid of development packages.
  - get recursively all files in current directory, excluding listed files.
  - build phar file
  - run <info>composer install</> to get development packages back.

<comment>Excluding files :
=================</comment>
The following files will be automaticaly excluded :
$excludeText

In addition the following section can be included in <info>.application-config.yml</>
<comment>dgfip_si1:
    phar:
        excludes:
            - DIRECTORY
            - filename.txt
</comment>

EOH
        );
    }
     /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $entryPoint     = $this->getConfiguredApplication()->getEntryPoint();
        if ($this->pharReadonly()) {
            $msg = "Creating phar disabled by the php.ini setting 'phar.readonly'. Try this :\n";
            $msg .= "   php -d phar.readonly=0 $entryPoint make-phar\n";
            throw new RuntimeException("$msg");
        }
        if (null !== $this->getConfig()->get('commands.make_phar.options.phar_file')) {
            $pharFile = $this->getConfig()->get('commands.make_phar.options.phar_file');
        } else {
            $pharFile       = getcwd().DIRECTORY_SEPARATOR.basename($entryPoint.'.phar');
        }
        $logCtx = ['name' => 'make-phar', 'file' => $pharFile];
        /** @var string $pharFile */
        if (file_exists($pharFile)) {
            unlink($pharFile);
            $this->getLogger()->notice("removing old phar : {file}", $logCtx);
        }
        $dirStasher = new DirectoryStasher();
        $dirStasher->setLogger($this->getLogger());
        $callBacks = [];
        $stashDir = '/tmp/stash';
        $composerOpts = [ 'install', '--working-dir', $stashDir, '--no-dev' ];
        $callBacks[] = [ 'callable' => [ $this       , 'composerRun'     ], 'args' => [ $composerOpts ] ];
        $callBacks[] = [ 'callable' => [ $dirStasher , 'resolveSymlinks' ], 'args' => [ ] ];
        $dirStasher->stash('.', $stashDir, $this->getStashExcludedFiles(), $callBacks);

        $this->makePhar($pharFile, $stashDir, $entryPoint, $this->pharExcludes);

        $dirStasher->cleanup();
        $this->getLogger()->notice("Phar file created : {file}", $logCtx);


        return 0;
    }
    /**
     * executes composer command with given args
     * @param array<string> $argv
     *
     * @return int
     */
    public function composerRun($argv)
    {
        $this->getLogger()->notice("Running composer ".implode(' ', $argv), ['name' => 'make-phar']);
        $composerAppli  = new Application();
        $composerAppli->setAutoExit(false);

        array_unshift($argv, 'composer');
        array_push($argv, '-q');
        $input = new ArgvInput($argv);

        return $composerAppli->run($input);
    }
    /**
     * make phar
     *
     * @param string        $pharFile
     * @param string        $directory
     * @param string        $entryPoint
     * @param array<string> $excludes
     *
     * @return void
     */
    protected function makePhar($pharFile, $directory, $entryPoint, $excludes)
    {
        $logCtx = ['name' => 'make-phar', 'file' => $pharFile];
        $this->getLogger()->notice("Creating phar : {file}", $logCtx);
        $fullName = $directory.DIRECTORY_SEPARATOR.$pharFile;
        $phar = $this->initPhar($fullName);
        if (null !== $phar) {
            // start buffering. Mandatory to modify stub to add shebang
            $phar->startBuffering();
            // Create the default stub from main.php entrypoint
            $defaultStub = Phar::createDefaultStub(basename($entryPoint));
            $phar->buildFromIterator($this->getIterator($excludes, $directory), $directory);
            // Customize the stub to add the shebang
            $stub = "#!/usr/bin/env php\n".$defaultStub;
            // Add the stub
            $phar->setStub($stub);
            $phar->stopBuffering();
            // plus - compressing it into gzip
            $phar->compressFiles(Phar::GZ);
            // Make the file executable
            if (!file_exists($fullName)) {
                throw new RuntimeException("Phar file wasn't created");
            }
            chmod($fullName, 0770);
        }
    }
    /**
     * get list of files to exclude from stash directory
     *
     * @return array<string>
     */
    protected function getStashExcludedFiles()
    {
        $configExcludes = $this->getConfiguredApplication()->getPharExcludes();
        $builtinExcludes = array_merge($this->testFiles, $this->devFiles, $this->composerFiles, $this->variousFiles);
        $excludes = array_merge($configExcludes, $builtinExcludes);

        return $excludes;
    }
    /**
     * get phar.readonly parameter in php.ini
     *
     * @return bool
     */
    protected function pharReadonly()
    {
        return (bool) ini_get('phar.readonly');
    }
    /**
     * get file iterator
     *
     * @param array<string> $excludes
     * @param string        $directory
     *
     * @return RecursiveIteratorIterator
     */
    protected function getIterator($excludes, $directory)
    {
        $filter = function ($file, $key, $iterator) use ($excludes, $directory) {
            foreach ($excludes as $excludefilename) {
                if (strcmp($directory.DIRECTORY_SEPARATOR.$excludefilename, $file) === 0) {
                    return false;
                }
            }

            return true;
        };
        $innerIterator = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);

        return new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator($innerIterator, $filter));
    }
    /**
     * initPhar : initialize phar object
     *
     * @param string $pharFile
     *
     * @return Phar|null
     */
    protected function initPhar($pharFile)
    {
        $logCtx = ['name' => 'make-phar', 'file' => $pharFile];
        try {
            $phar = new Phar($pharFile);
        } catch (\Exception $e) {
            $this->getLogger()->alert("Can't create phar : {file}", $logCtx);
            $phar = null;
        }

        return $phar;
    }
}
