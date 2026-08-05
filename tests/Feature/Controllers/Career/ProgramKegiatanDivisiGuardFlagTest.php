<?php

namespace Tests\Feature\Controllers\Career;

use App\Http\Controllers\Career\ProgramKegiatan\ProgramKegiatanController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProgramKegiatanDivisiGuardFlagTest extends TestCase
{
    public function test_cek_info_divisi_skips_db_and_returns_lengkap_when_flag_disabled(): void
    {
        config(['career_divisi_guard.enabled' => false]);

        // No DB connection/tables are configured for HRIS_Transaksi_GForm,
        // HRIS_Divisi, N_WEB_CAREERS_Division_Informations, etc. If the
        // controller queried the DB despite the flag being off, this call
        // would throw a query/connection exception instead of returning.
        $request = Request::create('/program-kegiatan/cek-info-divisi', 'POST', [
            'mppRefs' => ['MPP-0001', 'MPP-0002'],
        ]);

        $response = (new ProgramKegiatanController)->cekInfoDivisi($request);

        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame([], $payload['result']['belumLengkap']);
        $this->assertSame('Cek info divisi (nonaktif)', $payload['message']);
    }
}
