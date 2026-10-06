<?php
namespace Engine\Tests\Unit;

use Engine\Tests\Support\Fakes;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ImagesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/Fakes.php';
        require_once __DIR__ . '/../support/fakes_db.php';
        engine_require('functions/images.php');
    }

    public function testB64UuidWithUrlSafeBase64DecodesToPath(): void
    {
        // ">>>" and "???" produce '+' and '/' in standard base64.
        $path = '/data/x>>>???/photo.jpg';
        $std = base64_encode($path);
        $this->assertMatchesRegularExpression('#[+/]#', $std, 'precondition: standard base64 has + or /');
        $urlSafe = strtr(rtrim($std, '='), '+/', '-_');

        $this->assertSame([$path], get_images('posts', 'files', 1, 'B64' . $urlSafe));
    }

    public function testB64UuidWithStandardBase64StillDecodes(): void
    {
        $path = '/data/plain.jpg';

        $this->assertSame([$path], get_images('posts', 'files', 1, 'B64' . base64_encode($path)));
    }

    #[RunInSeparateProcess]
    public function testFindsVideoFilesAlongsideImages(): void
    {
        $root = sys_get_temp_dir() . '/engine-images-' . uniqid() . '/';
        define('IMAGES_DIR', $root);
        $dir = $root . 'posts/files/5/';
        $uuidDir = $dir . 'abc-uuid/';
        mkdir($uuidDir, 0777, true);
        foreach (['a.jpg', 'b.mp4', 'c.mov', 'ignored.txt'] as $f) {
            touch($dir . $f);
        }
        foreach (['d.png', 'e.mp4', 'ignored.php'] as $f) {
            touch($uuidDir . $f);
        }

        $found = array_map('basename', get_images('posts', 'files', 5));
        sort($found);
        $this->assertSame(['a.jpg', 'b.mp4', 'c.mov', 'd.png', 'e.mp4'], $found);

        $foundUuid = array_map('basename', get_images('posts', 'files', 5, 'abc-uuid'));
        sort($foundUuid);
        // direct files of the uid dir plus the uuid sub-directory
        $this->assertContains('e.mp4', $foundUuid);
        $this->assertContains('b.mp4', $foundUuid);
        $this->assertNotContains('ignored.php', $foundUuid);
    }
}
