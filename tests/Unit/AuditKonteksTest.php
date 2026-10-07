<?php

namespace Tests\Unit;

use App\Support\Audit\KonteksAudit;
use App\Support\Sinkron\OperasiSinkron;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Konteks audit (siapa & dari mana) yang dititipkan ke SESSION_CONTEXT untuk
 * pemicu audit di database admin: hanya sebelum perintah tulis, hanya bila
 * berubah, dan lapisannya pulih sesudah job / peristiwa sinkron selesai.
 */
class AuditKonteksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        KonteksAudit::reset();
    }

    protected function tearDown(): void
    {
        KonteksAudit::reset();
        parent::tearDown();
    }

    public function test_hanya_perintah_tulis_yang_butuh_konteks(): void
    {
        foreach ([
            'insert into [N_WEB_CAREERS_Lamaran] ([Kode]) values (?)',
            'update [N_WEB_CAREERS_Lamaran_Tahap] set [Status] = ? where [Id_Lamaran_Tahap] = ?',
            'delete from [N_WEB_CAREERS_Master_Faq] where [Id_Master_Faq] = ?',
            'merge [N_WEB_CAREERS_Program] as t using (select 1) as s on 1 = 0 when not matched then insert default values;',
            'SET NOCOUNT ON; EXEC dbo.usp_WC_Sinkron_Terima ?',
            'DECLARE @hasil VARCHAR(10); EXEC dbo.usp_WC_Sinkron_Terima @Hasil = @hasil OUTPUT',
            "  UPDATE dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes SET Catatan = N'x'",
        ] as $sql) {
            $this->assertTrue(KonteksAudit::menulis($sql), $sql);
        }

        foreach ([
            'select * from [N_WEB_CAREERS_Lamaran] where [Kode] = ?',
            'update [N_WEB_CAREERS_Sessions] set [payload] = ? where [id] = ?',
            'insert into [N_WEB_CAREERS_Sessions] ([id]) values (?)',
            'delete from [N_WEB_CAREERS_Jobs] where [id] = ?',
            'delete from [cache_locks] where [key] = ?',
        ] as $sql) {
            $this->assertFalse(KonteksAudit::menulis($sql), $sql);
        }
    }

    public function test_lapisan_sinkron_pulih_ke_konteks_panel(): void
    {
        KonteksAudit::atur(['sumber' => 'PANEL', 'aktor_id' => 5, 'aktor' => 'staf@evo', 'ip' => '10.1.1.1', 'permintaan' => 'req-1']);

        $di = KonteksAudit::dengan(['sumber' => 'SINKRON', 'aktor_id' => null, 'aktor' => 'akun:12', 'ip' => null], fn () => KonteksAudit::kini());

        $this->assertSame(['sumber' => 'SINKRON', 'aktor' => 'akun:12', 'permintaan' => 'req-1'], $di);
        $this->assertSame(5, KonteksAudit::kini()['aktor_id']);
        $this->assertSame('PANEL', KonteksAudit::kini()['sumber']);

        try {
            KonteksAudit::dengan(['sumber' => 'SINKRON'], fn () => throw new \RuntimeException('gagal'));
        } catch (\RuntimeException) {
        }
        $this->assertSame('PANEL', KonteksAudit::kini()['sumber'], 'konteks pulih walau penangan melempar galat');
    }

    public function test_job_sinkron_di_dalam_permintaan_mewarisi_aktor_dan_permintaan(): void
    {
        KonteksAudit::atur(['sumber' => 'PANEL', 'aktor_id' => 7, 'aktor' => 'a@evo', 'permintaan' => 'jejak-1']);
        KonteksAudit::masuk(['sumber' => 'TUGAS', 'konteks' => 'job App\\Jobs\\X']);

        $this->assertSame(['sumber' => 'TUGAS', 'aktor_id' => 7, 'aktor' => 'a@evo', 'konteks' => 'job App\\Jobs\\X', 'permintaan' => 'jejak-1'], KonteksAudit::kini());

        KonteksAudit::keluar();
        KonteksAudit::keluar(); // keluar berlebih tidak merusak lapisan dasar
        $this->assertSame('PANEL', KonteksAudit::kini()['sumber']);
    }

    public function test_nilai_dipotong_sesuai_kolom_audit(): void
    {
        KonteksAudit::atur(['sumber' => 'PANEL-TERLALU-PANJANG', 'konteks' => str_repeat('k', 400), 'aktor' => '', 'ip' => null]);

        $k = KonteksAudit::kini();
        $this->assertSame('PANEL-TERL', $k['sumber']);
        $this->assertSame(300, mb_strlen($k['konteks']));
        $this->assertArrayNotHasKey('aktor', $k);

        KonteksAudit::reset();
        $this->assertSame('', KonteksAudit::tanda(), 'tanpa konteks = tidak ada yang dititipkan');
    }

    public function test_konteks_dipasang_sekali_per_perubahan_dan_dikosongkan_dengan_literal_null(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $st = $this->createMock(\PDOStatement::class);
        $db = $this->createMock(Connection::class);
        $db->method('getPdo')->willReturn($pdo);

        $dikirim = [];
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($st) {
            $this->assertStringContainsString("sp_set_session_context @key = N'wc.audit', @value = ?", $sql);

            return $st;
        });
        $st->method('execute')->willReturnCallback(function (array $p) use (&$dikirim) {
            $dikirim[] = $p[0];

            return true;
        });
        $dikosongkan = 0;
        $pdo->method('exec')->willReturnCallback(function (string $sql) use (&$dikosongkan) {
            $this->assertStringContainsString('@value = NULL', $sql);
            $dikosongkan++;

            return 0;
        });

        KonteksAudit::atur(['sumber' => 'PANEL', 'aktor_id' => 3]);
        KonteksAudit::terapkan($db, 'select 1');                                   // baca: tidak
        KonteksAudit::terapkan($db, 'update [N_WEB_CAREERS_Lamaran] set x = 1');    // pasang
        KonteksAudit::terapkan($db, 'insert into [N_WEB_CAREERS_Lamaran] (x) values (1)'); // sama: tidak
        KonteksAudit::aktor(4, 'b@evo');
        KonteksAudit::terapkan($db, 'delete from [N_WEB_CAREERS_Talent_Pool] where 1 = 0'); // berubah: pasang
        KonteksAudit::atur([]);
        KonteksAudit::terapkan($db, 'update [N_WEB_CAREERS_Lamaran] set x = 2');    // kosong: literal NULL

        $this->assertSame(['{"sumber":"PANEL","aktor_id":3}', '{"sumber":"PANEL","aktor_id":4,"aktor":"b@evo"}'], $dikirim);
        $this->assertSame(1, $dikosongkan);
    }

    public function test_galat_memasang_konteks_tidak_menggagalkan_penulisan(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('getPdo')->willThrowException(new \RuntimeException('sambungan putus'));

        KonteksAudit::atur(['sumber' => 'PANEL']);
        KonteksAudit::terapkan($db, 'update [N_WEB_CAREERS_Lamaran] set x = 1');

        $this->assertTrue(true);
    }

    public function test_rute_panel_berawalan_api_v1_tetap_panel_dengan_aktor_dari_sesi(): void
    {
        Route::middleware('web')->get('/api/v1/uji-konteks-audit', fn () => response()->json(KonteksAudit::kini()));

        $this->withSession(['career_auth' => ['id' => 9, 'email' => 'staf@evo.test']])
            ->withHeader('X-Cloud-Trace-Context', '105445aa7843bc8bf206b12000100000/1;o=1')
            ->getJson('/api/v1/uji-konteks-audit')
            ->assertOk()
            ->assertJson(['sumber' => 'PANEL', 'aktor_id' => 9, 'aktor' => 'staf@evo.test', 'permintaan' => '105445aa7843bc8bf206b12000100000'])
            ->assertJsonPath('konteks', 'GET /api/v1/uji-konteks-audit');
    }

    public function test_grup_api_tercatat_tugas_tanpa_aktor(): void
    {
        Route::middleware('api')->get('/api/uji-konteks-audit-mesin', fn () => response()->json(KonteksAudit::kini()));

        $r = $this->getJson('/api/uji-konteks-audit-mesin')->assertOk()->assertJsonPath('sumber', 'TUGAS');
        $this->assertArrayNotHasKey('aktor_id', $r->json());
    }

    public function test_ringkasan_operator_memotong_daftar_panjang(): void
    {
        $this->assertSame([1, 2, 3], OperasiSinkron::daftar([1, 2, 3]));

        $d = OperasiSinkron::daftar(range(1, 1200));
        $this->assertSame(1200, $d['jumlah']);
        $this->assertCount(500, $d['awal']);
    }
}
