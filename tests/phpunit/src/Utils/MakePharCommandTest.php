<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Utils;

use Composer\Autoload\ClassLoader;
use DgfipSI1\Application\Command;
use DgfipSI1\Application\SymfonyApplication;
use DgfipSI1\Application\Utils\MakePharCommand;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use Mockery;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use Phar;
use RecursiveIteratorIterator;
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
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
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
        $this->assertMatchesRegularExpression('/phar.readonly/', $cmd->getHelp());
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
        $this->assertMatchesRegularExpression('/Creating phar disabled/', $msg);
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
        /** @var Mock $cmd */
        $cmd = $this->createPharMaker(true);
        $cmd->shouldReceive('pharReadonly')->andReturn(true); /** @phpstan-ignore-line */
        $msg = '';
        try {
            /** @var MakePharCommand $cmd */
            $cmd->execute(new ArgvInput([]), new NullOutput());
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Creating phar disabled by the php.ini/', $msg);
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
    public function testExecute()
    {
        /** @var Mock $cmd */
        $cmd = $this->createPharMaker(true);
        $cmd->shouldReceive('pharReadonly')->andReturn(false); /** @phpstan-ignore-line */
        $cmd->shouldReceive('composerRun');
        $cmd->shouldReceive('makePhar');
        /** @var MakePharCommand $cmd */
        $cmd->execute(new ArgvInput([]), new NullOutput());
        $this->assertNoticeInLog('{file} successfully created');
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
        $cmd = $this->createPharMaker(true);
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('composerRun');
        $method->setAccessible(true);

        $method->invokeArgs($cmd, [['about']]);
        $this->assertNoticeInLog('Running composer about');
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

        $this->assertTrue($method->invoke($cmd));
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
        $this->assertEquals(sizeof($expectedFiles), sizeof($files));
        foreach ($expectedFiles as $file) {
            $this->assertTrue(in_array($file, $files));
        }
        $this->assertLogEmpty();
    }

    /**
     * makePhar
     *
     * @covers \DgfipSI1\Application\Utils\MakePharCommand::makePhar
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testMakePhar()
    {
        $cmd = $this->createPharMaker(true, true);
        $class = new \ReflectionClass(MakePharCommand::class);
        $method = $class->getMethod('makePhar');
        $method->setAccessible(true);

        $root = vfsStream::setup();
        $entryPoint = $root->url().DIRECTORY_SEPARATOR.'testApp';
        $pharFile = 'testApp.phar';
        $fullName = $root->url().DIRECTORY_SEPARATOR.'testApp.phar';
        file_put_contents($fullName, 'xxx');
        $msg = '';
        try {
            $method->invokeArgs($cmd, [$pharFile, $entryPoint, []]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        // if we get to chmod, we have run threw all phar commands ;)
        $this->assertEquals("Phar file wasn't created", $msg);
        $this->assertNoticeInLog('removing old phar : {file}');
        $this->assertNoticeInLog('Creating phar : {file}');
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
        $this->assertNull($phar);
        $this->assertAlertInLog("Can't create phar : {file}");
    }
    /**
     * create pharmaker mock
     *
     * @param bool $mock
     * @param bool $mockPhar
     *
     * @return Command
     */
    protected function createPharMaker($mock = false, $mockPhar = false)
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $app = new SymfonyApplication($loaders[0], ['./testApp']);

        $app->setLogger($this->logger);
        $app->getContainer()->addShared('application', $app);
        if ($mock) {
            /** @var Mock $mock */
            $mock = Mockery::mock(MakePharCommand::class);
            $mock->shouldAllowMockingProtectedMethods();
            $mock->makePartial();
            $cmd = $mock;
            if ($mockPhar) {
                /** @var Mock $phar */
                $phar = \Mockery::mock(Phar::class);
                $phar->makePartial(); /* @php-ignore */
                $phar->shouldReceive('startBuffering')->once();/** @phpstan-ignore-line */
                $phar->shouldReceive('createDefaultStub')->withArgs(['testApp']);/** @phpstan-ignore-line */
                $phar->shouldReceive('buildFromIterator')->once();/** @phpstan-ignore-line */
                $phar->shouldReceive('setStub')->once();/** @phpstan-ignore-line */
                $phar->shouldReceive('stopBuffering')->once();/** @phpstan-ignore-line */
                $phar->shouldReceive('compressFiles')->once();/** @phpstan-ignore-line */
                $mock->shouldReceive('initPhar')->once()->andReturn($phar);     /** @phpstan-ignore-line */
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
