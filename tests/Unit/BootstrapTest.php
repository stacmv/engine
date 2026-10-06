<?php
namespace Engine\Tests\Unit;

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testDataDirIsDefinedForGlogUtil(): void
    {
        $this->assertTrue(defined('DATA_DIR'));
    }

    public function testEngineSrcResolvesToExistingDirectory(): void
    {
        $this->assertDirectoryExists(engine_src('functions'));
    }
}
