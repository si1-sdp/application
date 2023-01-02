<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests\Utils;

use DgfipSI1\Application\Utils\ClassDiscoverer;
use DgfipSI1\Application\Utils\DirectoryStasher;
use DgfipSI1\testLogger\LogTestCase;
use DgfipSI1\testLogger\TestLogger;
use FilesystemIterator;
use Mockery;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PhpParser\Node\Scalar\MagicConst\Dir;
use RecursiveDirectoryIterator;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @uses \DgfipSI1\Application\Utils\DirectoryStasher
 *
 */
class DirectoryStasherTest extends LogTestCase
{
    /** @var vfsStreamDirectory */
    private $root;

    /** @var ReflectionClass $class */
    private $class;

    /** @var Filesystem $fs */
    private $fs;
    /**
     * @inheritDoc
     *
     */
    public function setUp(): void
    {
        $this->root = vfsStream::setup();
        $this->class = new ReflectionClass(DirectoryStasher::class);
        $this->fs = new Filesystem();
        $this->logger = new TestLogger();
    }
    /**
     * Checks that log is empty at end of test
     *
     * @return void
     */
    public function tearDown(): void
    {
        \Mockery::close();
    }

    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::__construct
     *
     * @return void
     */
    public function testConstructor()
    {
        $stash = $this->createStasher();
        $fs = $this->class->getProperty('filesystem');
        $fs->setAccessible(true);
        self::assertInstanceOf(Filesystem::class, $fs->getValue($stash));

        $bd = $this->class->getProperty('baseDir');
        $bd->setAccessible(true);
        self::assertEquals(getcwd(), $bd->getValue($stash));

        $stash = $this->createStasher('/');
        self::assertEquals('/', $bd->getValue($stash));
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::stash
     *
     * @return void
     */
    public function testStash()
    {
        $callBacks = [
            [[ $this->logger, 'alert'], ["This is an alert"]],
            [ static fn (): string => 'foo' , []],
            [ static fn (): int => 1 , []],
            [ 'not_callable' , []],
        ];
        $this->populateVfsTree();
        $src = $this->root->url().DIRECTORY_SEPARATOR.'src';
        $dest = $this->root->url().DIRECTORY_SEPARATOR.'dest';

        /** @var \Mockery\MockInterface $stash */
        $stash = $this->createStasher(mock: true);
        $stash->shouldReceive('ensurePrerequisitesAreMet')->once();
        $stash->shouldReceive('cleanupExtraFilesInDestination')
            ->with($dest, $src, ['foo', 'bar'])
            ->once();
        $stash->shouldReceive('copyToDestination')->once();

        $cwd = (string) getcwd();
        /** @var DirectoryStasher $stash */
        $stash->stash($src, $dest, ['foo', 'bar'], $callBacks); /** @phpstan-ignore-line */

        // From callbacks[0]
        $this->assertAlertInLog("This is an alert");
        $this->assertDebugInContextLog("Return from ".TestLogger::class."::alert", ['name' => 'stash']);
        // From callbacks[1]
        $this->assertDebugInLog("Return from Closure::__invoke : foo");
        // From callbacks[2]
        $this->assertDebugInLog("Return from Closure::__invoke : 1");
        // From callbacks[3]
        $this->assertAlertInContextLog("Not a callable at index 3", ['name' => 'stash']);
        //$this->assertDebugInContextLog("Return from anonymous : /tmp", );
        self::assertEquals((string) getcwd(), $cwd);
        $this->assertLogEmpty();
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::cleanup
     *
     * @return void
     */
    public function testCleanup()
    {
        $this->populateVfsTree();
        $src = $this->root->url().DIRECTORY_SEPARATOR.'src';
        $dest = $this->root->url().DIRECTORY_SEPARATOR.'dest';

        $stash = $this->createStasher();
        $dd = (new ReflectionClass($stash::class))->getProperty('destDir');
        $dd->setValue($stash, $src);

        self::assertTrue($this->fs->exists($src));
        $stash->cleanup();
        self::assertFalse($this->fs->exists($src));
    }
    /**
     * Resolve symlinks dataProvider
     *
     * @return array<string,array<mixed>>
     */
    public function resolveSymlinksData()
    {
        return [
            'no_directory'    => [ null             , "/No directory given/"              ],
            'unresolved_link' => ['unresolved_links', "/the symlink .* can't be resolved/"],
            'outside_link   ' => ['outside_links'   , "/points outside stashed directory/"],
            'inside_link    ' => ['inside_links'    , null ],
        ];
    }


    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::resolveSymlinks
     *
     * @dataProvider resolveSymlinksData
     *
     * @param string|null $directory
     * @param string|null $exception
     *
     * @return void
     */
    public function testResolveSymlinks($directory, $exception)
    {
        $symlinksPlayground = __DIR__.'/../../../data/testSymlinks';
        self::assertDirectoryExists($symlinksPlayground);
        $stash = $this->createStasher();
        $dir = null;
        if (null !== $directory) {
            $dir = $symlinksPlayground.'/'.$directory;
            self::assertDirectoryExists($dir);
            $dd = (new ReflectionClass($stash::class))->getProperty('destDir');
            $dd->setValue($stash, $dir);
            if (null === $exception) {
                // prepare directory for nominal case
                $this->fs->remove("$dir/tests");
                mkdir("$dir/tests");
                $dir = "$dir/tests";
                mkdir("$dir/d1");
                mkdir("$dir/d2");
                mkdir("$dir/d3");
                file_put_contents("$dir/d2/f2", 'f2');
                file_put_contents("$dir/d3/f3", 'f3');
                symlink("../d2", "$dir/d1/d2link");
                symlink("../d3/f3", "$dir/d1/f3link");
            }
        }
        $msg = '';
        try {
            $stash->resolveSymlinks();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        if (null !== $exception) {
            self::assertMatchesRegularExpression($exception, $msg);

            return;
        }
        self::assertEquals('', $msg);
        self::assertNotNull($dir);
        self::assertDirectoryExists("$dir/d1/d2link");
        self::assertFileExists("$dir/d1/d2link/f2");
        self::assertEquals('f2', file_get_contents("$dir/d1/d2link/f2"));
        self::assertFileExists("$dir/d1/f3link");
        self::assertEquals('f3', file_get_contents("$dir/d1/f3link"));
        $this->fs->remove("$dir");
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::ensurePrerequisitesAreMet
     *
     * @return void
     */
    public function testEnsurePrerequisitesAreMet()
    {
        $epam = $this->class->getMethod('ensurePrerequisitesAreMet');
        $epam->setAccessible(true);
        $stash = $this->createStasher();

        $this->populateVfsTree();
        $src = $this->root->url().DIRECTORY_SEPARATOR.'src';
        $dest = $this->root->url().DIRECTORY_SEPARATOR.'dest';
        $msg = '';
        try {
            $epam->invokeArgs($stash, [$dest, $dest]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/The source and destination directories cannot be the same/', $msg);
        $msg = '';
        try {
            $epam->invokeArgs($stash, [ $src.DIRECTORY_SEPARATOR.'foo', $dest]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/The source directory does not exist/', $msg);
        rmdir($dest);
        self::assertFalse($this->fs->exists($dest));
        $epam->invokeArgs($stash, [$src, $dest]);
        self::assertTrue($this->fs->exists($dest));
    }

    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::cleanupExtraFilesInDestination
     *
     *
     * @return void
     */
    public function testCleanupExtraFilesInDestination()
    {
        $cefid = $this->class->getMethod('cleanupExtraFilesInDestination');
        $cefid->setAccessible(true);
        $ctd = $this->class->getMethod('copyToDestination');
        $ctd->setAccessible(true);



        $stash = $this->createStasher();
        $this->populateVfsTree();
        $src = $this->root->url().DIRECTORY_SEPARATOR.'src';
        $dest = $this->root->url().DIRECTORY_SEPARATOR.'dest';

        $cefid->invokeArgs($stash, [ $dest, $src, []]); // should do nothing as destination is empty

        $ctd->invokeArgs($stash, [$src, $dest, []]);
        $sourceFile = $src.DIRECTORY_SEPARATOR.'D2'.DIRECTORY_SEPARATOR.'f2.txt';
        $destFile = $dest.DIRECTORY_SEPARATOR.'D2'.DIRECTORY_SEPARATOR.'f2.txt';

        $this->fs->remove($sourceFile);
        self::assertTrue($this->fs->exists($destFile));
        $cefid->invokeArgs($stash, [ $dest, $src, []]);
        self::assertFalse($this->fs->exists($destFile));
    }
    /**
     * Undocumented function
     *
     * @return array<string,array<mixed>>
     */
    public function copyData()
    {
                            //                keep files
        return [                 // excludes        match_end
            'no_excluded'       => [ null          , '.txt'    ],
            'dummy_exclude'     => [ ['foo']       , '.txt'    ],
            'real_exclude'      => [ ['D1']        , '2.txt'   ],
            'exclude_all_v2'    => [ ['D1', 'D2']  , 'none'    ],
        ];
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::copyToDestination
     *
     * @dataProvider copyData
     *
     * @param array<string>|null $excluded
     * @param string             $endMatch
     *
     * @return void
     */
    public function testCopyToDestination($excluded, $endMatch)
    {
        $ctd = $this->class->getMethod('copyToDestination');
        $ctd->setAccessible(true);
        $stash = $this->createStasher();
        $files = $this->populateVfsTree();
        $src = $this->root->url().DIRECTORY_SEPARATOR.'src';
        $dest = $this->root->url().DIRECTORY_SEPARATOR.'dest';
        if (null === $excluded) {
            $found = $ctd->invokeArgs($stash, [$src, $dest, []]);
        } else {
            $found = $ctd->invokeArgs($stash, [$src, $dest, $excluded]);
        }
        $plainFiles = array_values(array_filter($files, static fn ($f): bool => str_ends_with($f, '.txt')));
        $notExcluded = array_map(
            static fn ($f): string => str_replace('src', 'dest', $f),
            array_filter($files, static fn ($f): bool => str_ends_with($f, $endMatch))
        );
        foreach (array_map(static fn ($f): string => str_replace('src', 'dest', $f), $plainFiles) as $f) {
            if (in_array($f, $notExcluded, true)) {
                self::assertFileExists($f);
            } else {
                self::assertFileDoesNotExist($f);
            }
        }
    }
    /**
     * Undocumented function
     *
     * @return array<string,array<mixed>>
     */
    public function findData()
    {
                            //                          keep files
        return [                 // excludes                 match_end
            'no_excluded'       => [ null                  , '.txt'    ],
            'dummy_exclude'     => [ ['foo']               , '.txt'    ],
            'real_exclude'      => [ ['src/D1']            , '2.txt'   ],
            'exclude_all_v1'    => [ ['src']               , 'none'    ],
            'exclude_all_v2'    => [ ['src/D1', 'src/D2']  , 'none'    ],
            'absolute_exclude'  => [ ['/tmp']              , '.txt'   ],
        ];
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::find
     *
     * @dataProvider findData
     *
     * @param array<string>|null $excluded
     * @param string             $endMatch
     *
     * @return void
     */
    public function testFind($excluded, $endMatch)
    {
        $find = $this->class->getMethod('find');
        $find->setAccessible(true);
        $stash = $this->createStasher();
        $files = $this->populateVfsTree();
        if (null === $excluded) {
            $found = $find->invokeArgs($stash, [$this->root->url()]);
        } else {
            $found = $find->invokeArgs($stash, [$this->root->url(), $excluded]);
        }
        $plainFiles = array_values(array_filter($files, static fn ($f): bool => str_ends_with($f, $endMatch)));
        self::assertEquals($plainFiles, $found);
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::getRecursiveDirectoryIterator
     *
     * @return void
     */
    public function testGetRecursiveDirectoryIterator()
    {
        $grdi = $this->class->getMethod('getRecursiveDirectoryIterator');
        $grdi->setAccessible(true);
        $stash = $this->createStasher();
        /** @var RecursiveDirectoryIterator $di */
        $di = $grdi->invokeArgs($stash, [$this->root->url()]);
        self::assertEquals($this->root->url().DIRECTORY_SEPARATOR, $di->current());
        self::assertEquals([], iterator_to_array($di));
        $msg = '';
        try {
            $di = $grdi->invokeArgs($stash, ['@']);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertMatchesRegularExpression('/No such file or directory/', $msg);
    }
   /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::makeAbsolute
     *
     * @return void
     */
    public function testMakeAbsolute()
    {
        $ma = $this->class->getMethod('makeAbsolute');
        $ma->setAccessible(true);
        $stash = $this->createStasher();

        self::assertEquals($this->root->url(), $ma->invokeArgs($stash, [$this->root->url(), (string) getcwd()]));
        self::assertEquals('/tmp', $ma->invokeArgs($stash, ['/tmp', (string) getcwd()]));
        self::assertEquals((string) getcwd(), $ma->invokeArgs($stash, ['.', (string) getcwd()]));
    }
    /**
     * @covers \DgfipSI1\Application\Utils\DirectoryStasher::directoryEmpty
     *
     * @return void
     */
    public function testDirectoryEmpty()
    {
        $de = $this->class->getMethod('directoryEmpty');
        $de->setAccessible(true);
        $stash = $this->createStasher();

        self::assertEquals(true, $de->invokeArgs($stash, [$this->root->url()]));
        $this->fs->touch($this->root->url().DIRECTORY_SEPARATOR.'test.txt');
        self::assertEquals(false, $de->invokeArgs($stash, [$this->root->url()]));
    }
    /**
     * populate a source tree - returns list of files created
     *
     * @return array<string>
     */
    public function populateVfsTree()
    {
        $files = [];
        $srcRoot = $this->root->url().DIRECTORY_SEPARATOR.'src';
        $files['src'] = $srcRoot;                            // rooturl/
        $this->fs->mkdir($srcRoot);                          //   +-- src/
        $files['d1'] = $srcRoot.DIRECTORY_SEPARATOR.'D1';    //         +-- D1/
        $d1 = end($files);                                   //              +-- f1.txt
        $this->fs->mkdir($d1);                               //         +---D2/
        $files['f1'] = $d1.DIRECTORY_SEPARATOR.'f1.txt';     //              +-- f2.txt
        $this->fs->touch(end($files));
        $d2 = $srcRoot.DIRECTORY_SEPARATOR.'D2';
        $files['d2'] = $d2;
        $this->fs->mkdir($d2);
        $files['f2'] = $d2.DIRECTORY_SEPARATOR.'f2.txt';
        $this->fs->touch(end($files));
        $this->root->addChild(new vfsStreamDirectory('dest'));

        return $files;
    }
    /**
     * creates a fully equiped directoryStasher
     *  @param string $baseDir
     *  @param bool   $mock
     *
     * @return DirectoryStasher;
     */
    public function createStasher($baseDir = null, $mock = false)
    {
        if ($mock) {
            /** @var \Mockery\MockInterface $stash */
            $stash = Mockery::mock(DirectoryStasher::class);
            $stash->makePartial();
            $stash->shouldAllowMockingProtectedMethods();
            $fs = $this->class->getProperty('filesystem');
            $fs->setAccessible(true);
            $fs->setValue($stash, new Filesystem());
            $bd = $this->class->getProperty('baseDir');
            $bd->setAccessible(true);
            $bd->setValue($stash, (string) getcwd());
        } else {
            if (null === $baseDir) {
                $stash = new DirectoryStasher();
            } else {
                $stash = new DirectoryStasher($baseDir);
            }
        }
        /** @var DirectoryStasher $stash */
        // $stash->setContainer(new Container());
        $stash->setLogger($this->logger);


        return $stash;
    }
}
