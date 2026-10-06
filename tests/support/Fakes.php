<?php
namespace Engine\Tests\Support;

/**
 * Registry driving the small fake collaborator functions in tests/support/fakes_*.php.
 * Tests configure it in setUp() and call Fakes::reset().
 */
class Fakes
{
    /** @var array<string, array<string, mixed>> db_get_meta($table, $attr) values */
    public static array $meta = [];
    /** @var list<array{0:string,1:string}> set_session_msg() calls */
    public static array $sessionMessages = [];
    /** @var list<array> payloads received by hook callbacks */
    public static array $hookCalls = [];
    /** @var mixed what the fake db_get() returns */
    public static mixed $dbGetResult = [];

    public static function reset(): void
    {
        self::$meta = [];
        self::$sessionMessages = [];
        self::$hookCalls = [];
        self::$dbGetResult = [];
    }
}
