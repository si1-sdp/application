<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\ApplicationInterface;
use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\Application\Utils\MakePharCommand;
use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use Mockery;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Phar;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 *
 * @uses DgfipSI1\Application\AbstractApplication
 * @uses DgfipSI1\Application\ApplicationSchema
 * @uses DgfipSI1\Application\Config\BaseSchema
 * @uses DgfipSI1\Application\Config\ConfiguredApplicationTrait
 * @uses DgfipSI1\Application\Utils\ApplicationLogger
 * @uses DgfipSI1\Application\Utils\ClassDiscoverer
 */
class MakePharCommandTest extends LogTestCase
{
    /** @var vfsStreamDirectory */
    private $root;

    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $this->root = vfsStream::setup();
        $this->logger = new TestLogger();
    }
    /**
     *
     * @inheritDoc
     */
    public function tearDown(): void
    {
        \Mockery::close();
    }
    /**
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::getPharRoot
     *
     *
     * @return void
     */
    public function testGetPharRoot()
    {
        self::assertEquals('', MakePharCommand::getPharRoot());
    }
    /**
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::configure
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testConfigure()
    {
        /** @var MakePharCommand $cmd */
        $cmd = $this->createPharMaker();
        $cmd->configure();
        $flatHelp = str_replace("\n", "@", $cmd->getHelp());

        $regexp = "excluded :.*Test files[^@]*tests, phpcs.xml.*@.*";
        $regexp .= "Development files[^@]*.git, .gitignore.*@.*";
        $regexp .= "Composer files[^@]*bin, vendor.*@.*";
        $regexp .= "Various files[^@]*README.md, CONTRIBUTING.md.*@@@.*In addition the following";

        self::assertMatchesRegularExpression("/$regexp/", $flatHelp);
    }
    /**
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::execute
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testPharMakerException()
    {
        /** @var MakePharCommand $cmd */
        $cmd = $this->createPharMaker();
        $msg = '';
        try {
            $cmd->execute(new ArgvInput(), new NullOutput());
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/Creating phar disabled/', $msg);
    }
    /**
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::execute
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testExecuteException()
    {
        /** @var \Mockery\MockInterface $cmd */
        $cmd = $this->createPharMaker(true);
        $cmd->shouldReceive('pharReadonly')->andReturn(true);
        $msg = '';
        try {
            /** @var MakePharCommand $cmd */
            $cmd->execute(new ArgvInput([]), new NullOutput());
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/Creating phar disabled by the php.ini/', $msg);
    }
    /**
     * DataProvider for execute
     *
     * @return array<string,array<mixed>>
     */
    public function executeData()
    {
                           //pass phar_file
        return [                //    arg ?
            'With_phararg_create_phar' => [ true   ,  true    ],
            'With_phararg_dont_create' => [ true   ,  false   ],
            'With_default_file       ' => [ false  ,  false   ] ,
        ];
    }
    /**
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::execute
     *
     * @dataProvider executeData
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @param bool $pharArg
     * @param bool $createPhar
     *
     * @return void
     */
    public function testExecute($pharArg, $createPhar)
    {
        /** @var \Mockery\MockInterface $cmd */
        $cmd = $this->createPharMaker(true);
        $cmd->shouldReceive('pharReadonly')->once()->andReturn(false);

        /** @var MakePharCommand $cmd */
        if ($pharArg) {
            $phar = $this->root->url()."/testApp.phar";
            touch($phar);
            $cmd->setConfig(new ConfigHelper());
            $cmd->getConfig()->set('commands.make_phar.options.phar_file', $phar);
        } else {
            $phar = getcwd().'/testApp.phar';
        }
        /** @var \Mockery\MockInterface $cmd */
        $cmd->shouldReceive('makePhar')->withArgs(
            function ($args) use ($createPhar, $phar) {
                if ($createPhar) {
                    touch($phar);
                }

                return true;
            }
        )->once();

        /** @var \Mockery\MockInterface $m */
        $m = Mockery::mock('overload:DgfipSI1\Application\Utils\DirectoryStasher')->makePartial();
        $m->shouldReceive('stash')->withArgs(
            function ($src, $dst, $ex, $callBacks) {
                $methods = array_map(static fn ($v) => [$v[0][1], $v[1]], $callBacks);
                $composer = $symlinks = false;
                foreach ($methods as $m) {
                    if ('composerRun' === $m[0] && implode(' ', $m[1]) === "install --working-dir ".$dst." --no-dev") {
                        $composer = true;
                    }
                    if ('resolveSymlinks' === $m[0]) {
                        $symlinks = true;
                    }
                }
                if ($composer && $symlinks) {
                    return true;
                }

                return false;
            }
        )->once();
        $m->shouldReceive('cleanup')->once();
        /** @var MakePharCommand $cmd */
        $ret = $cmd->execute(new ArgvInput([]), new NullOutput());
        if ($pharArg) {
            $this->assertNoticeInLog('removing old phar');
            if ($createPhar) {
                self::assertFileExists($phar);
            } else {
                self::assertFileDoesNotExist($phar);
            }
        } else {
            $this->assertNoticeNotInLog('removing old phar');
        }
        $this->assertNoticeInContextLog('Phar file created', ['name' => 'make-phar', 'file' => $phar]);
        $this->assertLogEmpty();
        self::assertEquals(0, $ret);
    }
    /**
     * composerRun
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::composerRun
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testComposerRun()
    {
        $m = Mockery::mock('overload:Composer\Console\Application')->makePartial();
        $m->shouldReceive('setAutoExit')->with(false)->once();
        $m->shouldReceive('run')->withArgs(
            function ($argv) {
                if ($argv instanceof ArgvInput) {
                    $cmd = $argv->getFirstArgument();
                    $verbose = $argv->getParameterOption('about');
                    if ('about' === $cmd && '-q' === $verbose) {
                        return true;
                    }
                }

                return false;
            }
        )->once();
        /** @var MakePharCommand $cmd */
        $cmd = $this->createPharMaker();
        $ret = $cmd->composerRun(['about']);


        $this->assertNoticeInContextLog('Running composer about', ['name' => 'make-phar']);
        self::assertEquals(0, $ret);
    }
    /**
     * pharExcluded
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::getStashExcludedFiles
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testGetStashExcludedFiles()
    {
        $cmd = $this->createPharMaker(true);
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('getStashExcludedFiles');
        $method->setAccessible(true);
        $tfprop = $class->getProperty('testFiles');
        $tfprop->setAccessible(true);
        $dfprop = $class->getProperty('devFiles');
        $dfprop->setAccessible(true);
        $cfprop = $class->getProperty('composerFiles');
        $cfprop->setAccessible(true);
        $vfprop = $class->getProperty('variousFiles');
        $vfprop->setAccessible(true);

        /** @var ApplicationInterface $application */
        $application = $cmd->getContainer()->get('application');
        $icprop = (new \ReflectionClass(SymfonyApplication::class))->getProperty('intConfig');
        $icprop->setAccessible(true);
        /** @var ConfigHelper $conf */
        $conf = $icprop->getValue($application);
        $conf->set(ApplicationSchema::PHAR_EXCLUDES, ['foo']);

        /** @var array<string> $files */
        $files = $method->invoke($cmd);
        /** @var ReflectionProperty $prop */
        foreach ([$tfprop, $dfprop, $cfprop, $vfprop] as $prop) {
            /** @var array<string> $propFiles */
            $propFiles = $prop->getValue($cmd);
            foreach ($propFiles as $file) {
                self::assertTrue(in_array($file, $files, true));
            }
        }
        self::assertTrue(in_array('foo', $files, true));
    }
    /**
     * pharReadonly
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::pharReadonly
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testPharReadonly()
    {
        $cmd = $this->createPharMaker();
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('pharReadonly');
        $method->setAccessible(true);

        self::assertTrue($method->invoke($cmd));
    }
    /**
     * makePhar
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::getIterator
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testGetIterator()
    {
        $cmd = $this->createPharMaker();
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('getIterator');
        $method->setAccessible(true);

        /** @var array<string> $expectedFiles */
        $expectedFiles = scandir(__DIR__);
        array_shift($expectedFiles); // get rid of '.'
        array_shift($expectedFiles); // get rid of '..'
        // get two first files in $excludes
        $excludes = [];
        $excludes[] = array_shift($expectedFiles);
        $excludes[] = array_shift($expectedFiles);
        /** @var RecursiveIteratorIterator $iterator */
        $iterator = $method->invokeArgs($cmd, [$excludes, realpath(__DIR__)]);
        $files = [];
        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $files[] = $item->getFilename();
        }
        self::assertEquals(sizeof($expectedFiles), sizeof($files));
        foreach ($expectedFiles as $file) {
            self::assertTrue(in_array($file, $files, true));
        }
        $this->assertLogEmpty();
    }

    /**
     *
     *
     * @return array<string,array<mixed>>
     *
     */
    public function makePharData()
    {
        return [             //  create
            'error_creating' => [ false ],
            'phar_create_ok' => [ true  ],
        ];
    }
    /**
     * makePhar
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::makePhar
     *
     * @dataProvider makePharData
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @param bool $createPhar
     *
     * @return void
     */
    public function testMakePhar($createPhar)
    {

        $cmd = $this->createPharMaker(true, true, $createPhar);
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('makePhar');
        $method->setAccessible(true);

        $entryPoint = $this->root->url().DIRECTORY_SEPARATOR.'testApp';
        $pharFile = 'testApp.phar';
        $fullName = $this->root->url().DIRECTORY_SEPARATOR.'testApp.phar';
        $msg = '';
        try {
            $method->invokeArgs($cmd, [$pharFile, $this->root->url(), $entryPoint, []]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if (false === $createPhar) {
            self::assertEquals("Phar file wasn't created", $msg);
        } else {
            $perms = substr(sprintf('%o', fileperms($fullName)), -4);
            self::assertEquals('0770', $perms);
            self::assertEquals("", $msg);
        }
        $this->assertNoticeInContextLog('Creating phar : {file}', ['name' => 'make-phar', 'file' => $pharFile]);
        $this->assertLogEmpty();
    }
    /**
     * initPhar
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::initPhar
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testInitPhar()
    {
        $cmd = $this->createPharMaker();
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('initPhar');
        $method->setAccessible(true);
        $phar = $method->invokeArgs($cmd, ['pharFile']);
        self::assertNull($phar);
        $this->assertAlertInContextLog("Can't create phar : {file}", ['name' => 'make-phar', 'file' => 'pharFile']);
    }
    /**
     * create pharmaker mock
     *
     * @param bool $mock
     * @param bool $mockPhar
     * @param bool $mockPharCreate
     *
     * @return Command
     */
    protected function createPharMaker($mock = false, $mockPhar = false, $mockPharCreate = false)
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], ['./testApp']);

        $app->setLogger($this->logger);
        $app->getContainer()->addShared('application', $app);
        if ($mock) {
            /** @var \Mockery\MockInterface $mock */
            $mock = Mockery::mock(MakePharCommand::class);
            $mock->shouldAllowMockingProtectedMethods();
            $mock->makePartial();
            $cmd = $mock;
            if ($mockPhar) {
                /** @var \Mockery\MockInterface $phar */
                $phar = \Mockery::mock(Phar::class);
                $phar->makePartial();
                $phar->shouldReceive('startBuffering')->once();
                $phar->shouldReceive('buildFromIterator')->once();
                $phar->shouldReceive('setStub')->withArgs(
                    function ($stub) {
                        $start = str_starts_with($stub, "#!/usr/bin/env php");
                        $more = sizeof(explode("\n", $stub)) > 2;

                        return $start && $more;
                    }
                )->once();
                $phar->shouldReceive('stopBuffering')->once();
                if ($mockPharCreate) {
                    $phar->shouldReceive('compressFiles')->withArgs(
                        function ($args) {
                            touch($this->root->url().DIRECTORY_SEPARATOR.'testApp.phar');

                            return true;
                        }
                    )->once();
                } else {
                    $phar->shouldReceive('compressFiles')->once();
                }
                $mock->shouldReceive('initPhar')->once()->andReturn($phar);
            }
        } else {
            $cmd = new MakePharCommand();
        }
        /** @var MakePharCommand $cmd */
        $cmd->setConfig($app->getConfig());
        $cmd->setContainer($app->getContainer());
        $cmd->setLogger($app->getLogger());

        return $cmd;
    }
}
