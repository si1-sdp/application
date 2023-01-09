<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\Application\Utils;

use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use DgfipSI1\Application\Exception\RuntimeException;
use FilesystemIterator;
use Grasmash\Expander\Stringifier;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Directory stasher class
 */
class DirectoryStasher implements ConfigAwareInterface, LoggerAwareInterface, ContainerAwareInterface
{
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    public const DEFAULT_TIMEOUT = 60;
    /** @var Filesystem $filesystem */
    protected $filesystem;

    /** @var string $baseDir */
    protected $baseDir;
    /** @var string|null $srcDir */
    protected $srcDir;
    /** @var string|null $destDir */
    protected $destDir;
    /**
     * constructor function
     *
     * @param string|null $baseDir
     */
    public function __construct($baseDir = null)
    {
        $this->filesystem = new Filesystem();
        if (null === $baseDir) {
            $this->baseDir =  (string) getcwd();
        } else {
            $this->baseDir = $baseDir;
        }
    }

    /**
     * Undocumented function
     *
     * @param string                                         $source
     * @param string                                         $destination
     * @param array<string>                                  $excluded
     * @param array<int,array<string,callable|array<mixed>>> $callbacks
     *
     * @return void
     */
    public function stash($source, $destination, $excluded = null, $callbacks = null)
    {
        $excluded  ??= [];
        $callbacks ??= [];
        $source      = $this->makeAbsolute($source, $this->baseDir);
        $destination = $this->makeAbsolute($destination, $this->baseDir);
        $this->srcDir = $source;
        $this->destDir = $destination;
        $this->ensurePrerequisitesAreMet($source, $destination);
        $this->cleanupExtraFilesInDestination($destination, $source, $excluded);
        $this->copyToDestination($source, $destination, $excluded);
        foreach ($callbacks as $index => $cb) {
            $name = '';
            if (is_callable($cb['callable'], callable_name: $name)) {
                /** @var array<mixed> $args */
                $args = $cb['args'];
                $ret = call_user_func_array($cb['callable'], $args);
                if (is_string($ret) || is_numeric($ret)) {
                    $ret = (string) $ret;
                } else {
                    $ret = '<not_printable>';
                }
                $this->getLogger()->debug("Return from $name : $ret", ['name' => 'stash']);
            } else {
                $this->getLogger()->alert("Not a callable at index $index", ['name' => 'stash']);
            }
        }
    }
    /**
     * Remove stashed directory
     *
     * @return void
     */
    public function cleanup()
    {
        if (null !== $this->destDir) {
            $this->filesystem->remove($this->destDir);
            $this->destDir = null;
        }
    }
    /**
     * Resolve intenal symlinks - FIXME : should be recursive (or symlinks sorted ?)
     * @param string|null $directory
     *
     * @return void
     */
    public function resolveSymlinks($directory = null)
    {
        $directory ??=  $this->destDir;
        if (null === $directory) {
            throw new RuntimeException('No directory given, and destination directory not set');
        }
        foreach ($this->find($directory) as $file) {
            if (is_link($file)) {
                $linked = $this->filesystem->readlink($file, true);
                if (null === $linked) {
                    $msg = "resolveSymlinks : the symlink '%s' to '%s' can't be resolved";
                    throw new RuntimeException(sprintf($msg, $file, $this->filesystem->readlink($file)));
                }
                if (Path::isBasePath($directory, $linked)) {
                    $this->filesystem->remove($file);
                    if (is_dir($linked)) {
                        $this->filesystem->mirror($linked, $file);
                    } else {
                        $this->filesystem->copy($linked, $file);
                    }
                } else {
                    $msg = "resolveSymlinks : Can't resolve '%s' : '%s' points outside stashed directory";
                    throw new RuntimeException(sprintf($msg, $file, $this->filesystem->readlink($file)));
                }
            }
        }
    }
    /**
     * Undocumented function
     *
     * @param string $source
     * @param string $destination
     *
     * @return void
     */
    protected function ensurePrerequisitesAreMet($source, $destination)
    {
        if ($source === $destination) {
            $msg = 'The source and destination directories cannot be the same at "%s"';
            throw new RuntimeException(sprintf($msg, $source));
        }
        if (!$this->filesystem->exists($source)) {
            throw new RuntimeException(sprintf('The source directory does not exist at "%s"', $source));
        }
        $this->filesystem->mkdir($destination);
    }
    /**
     * cleanup destination
     *
     * @param string        $destination
     * @param string        $source
     * @param array<string> $excluded
     *
     * @return void
     */
    protected function cleanupExtraFilesInDestination($destination, $source, $excluded)
    {
        if ($this->directoryEmpty($destination)) {
            return;
        }

        $destinationFiles = $this->find($destination, $excluded);
        foreach ($destinationFiles as $destinationFile) {
            $relativePath = Path::makeRelative($destinationFile, $destination);
            $sourceFile = $source.DIRECTORY_SEPARATOR.$relativePath;
            if ($this->filesystem->exists($sourceFile)) {
                 continue;
            }
            $this->filesystem->remove($destinationFile);
        }
    }
    /**
     * copy files
     *
     * @param string        $source
     * @param string        $destination
     * @param array<string> $excluded
     *
     * @return void
     */
    protected function copyToDestination($source, $destination, $excluded)
    {
        $sourceFiles = $this->find($source, $excluded);
        foreach ($sourceFiles as $sourceFile) {
            $relativePathname = Path::makeRelative($sourceFile, $source);
            $destinationFile = $destination.DIRECTORY_SEPARATOR.$relativePathname;
            $this->filesystem->copy($sourceFile, $destinationFile, true);
        }
    }

    /**
     * find files
     *
     * @param string        $directory
     * @param array<string> $excluded
     *
     * @return array<string>
     */
    protected function find($directory, $excluded = null)
    {
        $excluded ??= [];
        $directoryIterator = $this->getRecursiveDirectoryIterator($directory);

        $excluded = array_map(function ($path) use ($directory): string {
            if (Path::isAbsolute($path)) {
                return $path;
            }

            return $directory.DIRECTORY_SEPARATOR.$path;
        }, $excluded);

        $filterIterator = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static fn (string $foundPathname): bool => !in_array($foundPathname, $excluded, true)
        );
        /** @var \Traversable<string> $iterator */
        $iterator = new RecursiveIteratorIterator($filterIterator);
        $files = iterator_to_array($iterator);
        sort($files);

        return $files;
    }
    /**
     * get directory iterator
     *
     * @param string $directory
     *
     * @return RecursiveDirectoryIterator
     */
    private function getRecursiveDirectoryIterator(string $directory): RecursiveDirectoryIterator
    {
        try {
            $options = FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS;

            return new RecursiveDirectoryIterator($directory, $options);
        } catch (\UnexpectedValueException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
    /**
     * makeAbsolute path.
     * we need vfs for testing purposes and Path::makeabsolute
     * does not detect vfs urls as absolute.
     *
     * @param string $source
     * @param string $baseDir
     *
     * @return string
     */
    private function makeAbsolute($source, $baseDir)
    {
        if (str_starts_with($source, 'vfs://')) {
            return $source;
        }

        return Path::makeAbsolute($source, $baseDir);
    }
    /**
     *
     * @param string $directory
     *
     * @return bool
     */
    private function directoryEmpty($directory)
    {
        return scandir($directory) === ['.', '..'];
    }
}
