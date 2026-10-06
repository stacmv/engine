<?php
namespace Engine\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PasswordsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        engine_require('functions/passwords.php');
    }

    public function testVerifiesBcryptCost12Hash(): void
    {
        // PHP 8.4+ PASSWORD_DEFAULT produces cost 12.
        $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->assertTrue(passwords_verify('secret', $hash));
        $this->assertFalse(passwords_verify('wrong', $hash));
    }

    public function testVerifiesBcryptCost10Hash(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 10]);
        $this->assertTrue(passwords_verify('secret', $hash));
        $this->assertFalse(passwords_verify('wrong', $hash));
    }

    public function testRoundTripWithPasswordsHash(): void
    {
        $hash = passwords_hash('secret');
        $this->assertTrue(passwords_verify('secret', $hash));
        $this->assertFalse(passwords_verify('wrong', $hash));
    }

    public function testStillVerifiesLegacySaltedMd5Hash(): void
    {
        $salt = 'ab12';
        $hash = $salt . md5($salt . md5('secret'));
        $this->assertTrue(passwords_verify('secret', $hash));
        $this->assertFalse(passwords_verify('wrong', $hash));
    }
}
