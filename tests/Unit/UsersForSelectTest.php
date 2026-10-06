<?php
namespace Engine\Tests\Unit;

use Engine\Tests\Support\Fakes;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class UsersForSelectTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testNonHumanUsersAreHiddenFromSelectors(): void
    {
        require_once __DIR__ . '/../support/Fakes.php';
        require_once __DIR__ . '/../support/fakes_db.php';
        require_once __DIR__ . '/../support/fakes_cache.php';
        engine_require('functions/engine_users.php');
        Fakes::reset();
        Fakes::$dbGetResult = [
            ['id' => 1, 'name' => 'Alice', 'type' => 'human'],
            ['id' => 2, 'name' => 'dev-news', 'type' => 'bot'],
            ['id' => 3, 'name' => 'Legacy'],
            ['id' => 4, 'name' => 'Empty type', 'type' => ''],
        ];

        $options = get_users_for_select();

        $this->assertSame([1, 3, 4], array_column($options, 'value'));
        $this->assertSame(['Alice', 'Legacy', 'Empty type'], array_column($options, 'caption'));
    }
}
