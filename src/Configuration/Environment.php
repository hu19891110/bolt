<?php

namespace Bolt\Configuration;

use Bolt\Cache;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Environment set up and management class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Environment
{
    /** @var Cache */
    protected $cache;
    /** @var Filesystem */
    protected $filesystem;
    /** @var string */
    protected $srcRoot;
    /** @var string */
    protected $webRoot;
    /** @var string */
    protected $boltName;
    /** @var string */
    protected $boltVersion;

    /**
     * Constructor.
     *
     * @param string $srcRoot
     * @param string $webRoot
     * @param Cache  $cache
     * @param string $boltName
     * @param string $boltVersion
     */
    public function __construct($srcRoot, $webRoot, Cache $cache, $boltName, $boltVersion)
    {
        $this->filesystem = new Filesystem();
        $this->srcRoot = rtrim($srcRoot, '/');
        $this->webRoot = rtrim($webRoot, '/');
        $this->cache = $cache;
        $this->boltName = $boltName;
        $this->boltVersion = $boltVersion;
    }

    /**
     * Check Bolt's version against a cached key. If there is a change we flush
     * the cache data and if required synchronise asset directories.
     */
    public function checkVersion()
    {
        if ($this->checkCacheVersion()) {
            return;
        }
        $this->syncView();
        $this->cache->clearCache();
    }

    /**
     * Perform a synchronisation of files in specific app/view/ subdirectories.
     *
     * @return array|null
     */
    public function syncView()
    {
        $views = ['css', 'fonts', 'img', 'js'];
        $response = null;
        foreach ($views as $dir) {
            try {
                $this->syncViewDirectory($dir);
            } catch (IOException $e) {
                $response[] = $e->getMessage();
            }
        }

        return $response;
    }

    /**
     * Synchronise the files in an app/view/ subdirectory.
     *
     * @param string $dir
     */
    protected function syncViewDirectory($dir)
    {
        if ($this->srcRoot === $this->webRoot) {
            return;
        }

        $source = $this->srcRoot . '/app/view/' . $dir;
        $target = $this->webRoot . '/app/view/' . $dir;
        if ($this->filesystem->exists($target)) {
            return;
        }

        $this->filesystem->mirror($source, $target, null, ['override' => true, 'delete' => true]);
    }

    /**
     * Check if the cache version matches Bolt's current version
     *
     * @return boolean TRUE  - versions match
     *                 FALSE - versions don't match
     */
    protected function checkCacheVersion()
    {
        $file = $this->cache->getDirectory() . '/.version';

        if (!file_exists($file)) {
            return false;
        }

        $version = md5($this->boltVersion . $this->boltName);
        $cached  = file_get_contents($file);

        if ($version === $cached) {
            return true;
        }

        return false;
    }
}
