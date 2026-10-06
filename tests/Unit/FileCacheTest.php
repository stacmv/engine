<?php
namespace Engine\Tests\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * file_cache.php derives FILE_CACHE_DIR from PUBLIC_TMP_DIR at load time and
 * keeps a static index, so every test runs in its own process with its own dir.
 */
class FileCacheTest extends TestCase
{
    private const KEY = 'thumb_posts__files__5__u__10x10.jpg';

    private function boot(): string
    {
        $tmp = sys_get_temp_dir() . '/engine-fc-' . uniqid() . '/';
        mkdir($tmp . 'file_cache', 0777, true);
        define('PUBLIC_TMP_DIR', $tmp);
        engine_require('functions/file_cache.php');
        return FILE_CACHE_DIR;
    }

    private function filesFor(string $dir): array
    {
        $f = array_map('basename', glob($dir . '*') ?: []);
        sort($f);
        return $f;
    }

    #[RunInSeparateProcess]
    public function testSetTwiceReusesStillValidFile(): void
    {
        $dir = $this->boot();

        file_cache_set(self::KEY, 'first', 3600);
        // Different TTL => a different timestamped file name, unless reused.
        file_cache_set(self::KEY, 'second', 7200);

        $this->assertCount(1, $this->filesFor($dir), 'only one cache file per key: ' . implode(', ', $this->filesFor($dir)));
        $this->assertSame('second', file_cache_get(self::KEY));
    }

    #[RunInSeparateProcess]
    public function testSetRemovesOlderVersionsOfTheKey(): void
    {
        $tmp = sys_get_temp_dir() . '/engine-fc-' . uniqid() . '/';
        mkdir($tmp . 'file_cache', 0777, true);
        // An expired version left over from an earlier run, and an unrelated key.
        $stale = $tmp . 'file_cache/thumb_posts__files__5__u__10x10.20200101000000.jpg';
        $other = $tmp . 'file_cache/thumb_other.20991231235959.jpg';
        file_put_contents($stale, 'stale');
        file_put_contents($other, 'other');
        define('PUBLIC_TMP_DIR', $tmp);
        engine_require('functions/file_cache.php');

        file_cache_set(self::KEY, 'fresh', 3600);

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($other);
        $this->assertSame('fresh', file_cache_get(self::KEY));
    }
}
