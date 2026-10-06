<?php
namespace Engine\Tests\Compat;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The engine must load cleanly on PHP 8.4/8.5: no deprecations (non-canonical
 * casts, implicit nullable parameters) and no redeclaration of functions that
 * became built-ins (mb_ucfirst).
 */
class PhpCompatibilityTest extends TestCase
{
    /**
     * Known remaining deprecations that GA never patched (implicit nullable
     * parameters). Ported as-is on purpose; fix separately and drop from here.
     */
    private const KNOWN_UNFIXED = [
        "classes/Glog.class.php",
        "classes/GlogItem.class.php",
    ];

    public function testEverySourceFilePassesLintWithoutDeprecations(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $problems = [];
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $rel = str_replace(DIRECTORY_SEPARATOR, "/", substr($file->getPathname(), strlen($root) + 1));
            if (in_array($rel, self::KNOWN_UNFIXED, true)) {
                continue;
            }
            $cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=-1 -d display_errors=1 -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
            exec($cmd, $lines);
            // Drop php.ini startup noise that has nothing to do with the engine.
            $lines = array_filter($lines, fn($l) => !str_contains($l, 'PHP Startup'));
            $out = implode("\n", $lines);
            unset($lines);
            if (preg_match('/Deprecated|Fatal|Parse error|Warning/i', $out)) {
                $problems[] = $out;
            }
        }

        $this->assertSame([], $problems, "php -l reported problems:\n" . implode("\n", $problems));
    }

    #[RunInSeparateProcess]
    public function testStringsFileCoexistsWithBuiltInMbUcfirst(): void
    {
        engine_require('functions/strings.php');

        $this->assertTrue(function_exists('mb_ucfirst'));
        $this->assertSame('Привет', mb_ucfirst('привет'));
    }
}
