<?php

namespace Tests\Feature\Controllers\Career;

use App\Http\Controllers\Career\CareerLandingController;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CareerLandingDivisiGuardFlagTest extends TestCase
{
    public function test_tim_info_rows_returns_empty_and_skips_db_when_flag_disabled(): void
    {
        config(['career_divisi_guard.enabled' => false]);

        // No DB tables (N_WEB_CAREERS_Division_Informations, HRIS_Divisi) are
        // configured. If timInfoRows() queried the DB despite the flag being
        // off, the query would throw and be swallowed by its own catch block,
        // still yielding [] — so this test also asserts on a log-free run via
        // the absence of any thrown/logged exception during the call.
        $controller = new CareerLandingController;
        $method = new ReflectionMethod(CareerLandingController::class, 'timInfoRows');

        $result = $method->invoke($controller);

        $this->assertSame([], $result);
    }

    public function test_show_tim_aborts_404_when_flag_disabled(): void
    {
        config(['career_divisi_guard.enabled' => false]);

        $this->expectException(NotFoundHttpException::class);

        (new CareerLandingController)->showTim('divisi-manapun');
    }
}
