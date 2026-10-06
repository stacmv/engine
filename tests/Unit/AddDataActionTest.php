<?php
namespace Engine\Tests\Unit;

use Engine\Tests\Support\Fakes;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

function _test_rejecting_onbeforeadd(array $p): array
{
    return ['res' => false, 'reason' => 'rejected_by_hook', 'errors' => ['x' => 'bad']];
}
function _test_recording_onafteradd(array $p): void
{
    \Engine\Tests\Support\Fakes::$hookCalls[] = $p;
}

class AddDataActionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/Fakes.php';
        require_once __DIR__ . '/../support/fakes_db.php';
        engine_require('functions/engine_actions.php');
        Fakes::reset();
    }

    #[RunInSeparateProcess]
    public function testOnAfterAddGetsNullFormdataAndAddedIdWhenOnBeforeAddRejects(): void
    {
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', false);
        }
        global $_PARAMS, $_DATA, $_PAGE, $IS_AJAX, $CFG;
        $_PARAMS = ['object' => 'post', 'to' => 'somewhere'];
        $_DATA = [];
        $_PAGE = ['uri' => 'add/post'];
        $IS_AJAX = true;
        $CFG = [];

        Fakes::$meta['posts'] = [
            'onbeforeadd' => __NAMESPACE__ . '\_test_rejecting_onbeforeadd',
            'onafteradd' => __NAMESPACE__ . '\_test_recording_onafteradd',
        ];

        // null redirect_on_fail: no redirect() on the failure path.
        [$res, $reason] = add_data_action('posts', '', null);

        $this->assertFalse($res);
        $this->assertSame('rejected_by_hook', $reason);
        $this->assertCount(1, Fakes::$hookCalls);
        $this->assertArrayHasKey('formdata', Fakes::$hookCalls[0]);
        $this->assertNull(Fakes::$hookCalls[0]['formdata']);
        $this->assertArrayHasKey('added_id', Fakes::$hookCalls[0]);
        $this->assertNull(Fakes::$hookCalls[0]['added_id']);
        $this->assertFalse(Fakes::$hookCalls[0]['res']);
    }
}
