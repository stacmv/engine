<?php
namespace Engine\Tests\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class AuthCookieTest extends TestCase
{
    protected function setUp(): void
    {
        engine_require('classes/EModel.class.php');
        engine_require('classes/Model.class.php');
        engine_require('classes/EUser.class.php');
        unset($_SESSION['authenticated']);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['auth_mobile_token'], $_SESSION['authenticated']);
    }

    private function user(): \EUser
    {
        return (new \ReflectionClass(\EUser::class))->newInstanceWithoutConstructor();
    }

    #[RunInSeparateProcess]
    public function testMalformedAuthMobileTokenCookieIsNotAuthenticated(): void
    {
        $_COOKIE['auth_mobile_token'] = 'garbage';

        $this->assertFalse($this->user()->is_authenticated());
    }

    #[RunInSeparateProcess]
    public function testCookieWithTooManyPartsIsNotAuthenticated(): void
    {
        $_COOKIE['auth_mobile_token'] = '1||2||3||4';

        $this->assertFalse($this->user()->is_authenticated());
    }
}
