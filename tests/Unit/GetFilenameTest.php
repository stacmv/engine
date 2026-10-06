<?php
namespace Engine\Tests\Unit;

use PHPUnit\Framework\TestCase;

class GetFilenameTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        engine_require('functions/engine_functions.php');
    }

    public function testTildeIsNotPercentEncoded(): void
    {
        $name = get_filename('IMG-20240101-WA0001~2', '.jpg');

        $this->assertStringNotContainsString('%', $name);
        $this->assertMatchesRegularExpression('/^[a-z0-9_-]+\.jpg$/', $name);
    }

    public function testOnlyWhitelistedCharactersBeforeExtension(): void
    {
        $name = get_filename('Отчёт №5 (final)! #1 [a]~b', '.png');

        $this->assertMatchesRegularExpression('/^[a-z0-9_-]+\.png$/', $name);
    }
}
