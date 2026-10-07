<?php

namespace Tests\Unit;

use App\Support\Sinkron\KotakMasuk;
use App\Support\Sinkron\PendorongKeluar;
use App\Support\Sinkron\PenjagaDorong;
use App\Support\Sinkron\RahasiaSinkron;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gerbang dua zona yang tidak butuh database:
 *   - KotakMasuk::periksa (Gerbang 2) — hanya peristiwa yang dikenal, berbentuk
 *     benar, dan menyangkut akun pemilik kunci urutnya yang boleh masuk;
 *   - PenjagaDorong — rute dorong mati tanpa setelan, token wajib ada;
 *   - RahasiaSinkron — pasangan bungkus situs kandidat;
 *   - path objek dokumen publik ditentukan isinya.
 */
class SinkronGerbangTest extends TestCase
{
    private function amplop(array $timpa = []): array
    {
        return array_replace([
            'event_id' => (string) Str::uuid(),
            'jenis' => 'Lamaran.Dibatalkan',
            'versi_skema' => 1,
            'idempotency_key' => 'Lamaran.Dibatalkan:LMR-ABC',
            'kunci_urut' => 'akun:12',
            'muatan' => ['kode' => 'LMR-ABC', 'akun' => ['id_publik' => 12]],
        ], $timpa);
    }

    public function test_amplop_sah_lolos(): void
    {
        $a = $this->amplop();

        $this->assertNull(KotakMasuk::periksa($a, ['event_id' => strtoupper($a['event_id']), 'jenis' => $a['jenis']], 'akun:12'));
    }

    public static function amplopTakSah(): array
    {
        return [
            'jenis palsu' => [['jenis' => 'Kandidat.Diluluskan', 'idempotency_key' => 'Kandidat.Diluluskan:1'], 'jenis tidak dikenal'],
            'event_id bukan uuid' => [['event_id' => '123'], 'event_id bukan UUID'],
            'versi di luar batas' => [['versi_skema' => 99], 'versi_skema tidak sah'],
            'kunci idempoten bukan milik jenisnya' => [['idempotency_key' => 'Akun.Terdaftar:12'], 'idempotency_key tidak sah'],
            'kunci urut bukan akun' => [['kunci_urut' => 'lamaran:7'], 'kunci_urut tidak sah'],
            'muatan bukan objek' => [['muatan' => 'x'], 'muatan bukan objek'],
            'field wajib hilang' => [['muatan' => ['akun' => ['id_publik' => 12]]], 'muatan tanpa field kode'],
            'akun lain' => [['muatan' => ['kode' => 'LMR-ABC', 'akun' => ['id_publik' => 13]]], 'akun di muatan ≠ kunci_urut'],
        ];
    }

    /** @dataProvider amplopTakSah */
    public function test_amplop_tak_sah_ditolak(array $timpa, string $alasan): void
    {
        $this->assertSame($alasan, KotakMasuk::periksa($this->amplop($timpa)));
    }

    public function test_atribut_dan_kunci_urut_pubsub_harus_sama_dengan_badan(): void
    {
        $a = $this->amplop();

        $this->assertSame('event_id atribut ≠ badan', KotakMasuk::periksa($a, ['event_id' => (string) Str::uuid()]));
        $this->assertSame('jenis atribut ≠ badan', KotakMasuk::periksa($a, ['jenis' => 'Lamaran.Dikirim']));
        $this->assertSame('orderingKey ≠ kunci_urut', KotakMasuk::periksa($a, [], 'akun:99'));
    }

    public function test_kode_diminta_memakai_id_publik_di_akar_muatan(): void
    {
        $a = $this->amplop([
            'jenis' => 'Akun.KodeDiminta',
            'idempotency_key' => 'Akun.KodeDiminta:'.Str::uuid(),
            'muatan' => ['id_publik' => 12, 'email' => 'a@b.c', 'jenis' => 'RESET', 'rahasia' => 'x'],
        ]);

        $this->assertNull(KotakMasuk::periksa($a));
        $a['muatan']['id_publik'] = 5;
        $this->assertSame('akun di muatan ≠ kunci_urut', KotakMasuk::periksa($a));
    }

    public function test_badan_pesan_bukan_base64_json_ditolak_tanpa_database(): void
    {
        $r = KotakMasuk::terimaPesan('@@bukan-base64@@', [], 'm1', null, null);

        $this->assertSame(KotakMasuk::DITOLAK, $r['hasil']);
    }

    public function test_rute_dorong_mati_tanpa_setelan_dan_menuntut_token(): void
    {
        config(['sinkron.dorong.audience' => null, 'sinkron.dorong.akun' => null]);
        $this->assertFalse(PenjagaDorong::aktif());

        config(['sinkron.dorong.audience' => 'https://contoh/api/pubsub/wc-masuk', 'sinkron.dorong.akun' => 'push@proyek.iam.gserviceaccount.com']);
        $this->assertTrue(PenjagaDorong::aktif());
        $this->assertSame('tanpa token', PenjagaDorong::periksa(null));
        $this->assertSame('tanpa token', PenjagaDorong::periksa('Basic abc'));
    }

    public function test_rahasia_sinkron_membuka_bungkus_situs_kandidat(): void
    {
        $kunci = random_bytes(32);
        config(['sinkron.kunci_rahasia' => 'base64:'.base64_encode($kunci)]);
        $bungkus = (new Encrypter($kunci, 'AES-256-CBC'))->encryptString(json_encode(['otp' => '123456']));

        $this->assertSame(['otp' => '123456'], RahasiaSinkron::buka($bungkus));
    }

    public function test_rahasia_sinkron_tanpa_kunci_menolak(): void
    {
        config(['sinkron.kunci_rahasia' => null]);

        $this->expectException(\RuntimeException::class);
        RahasiaSinkron::buka('apa saja');
    }

    public function test_path_dokumen_publik_ditentukan_isinya(): void
    {
        $a = PendorongKeluar::objekPublik(['kunci' => 'surat:1:0', 'kode_lamaran' => 'LMR-ABC', 'nama' => 'Surat.PDF', 'path' => 'surat/a.pdf', 'ukuran' => 10]);
        $b = PendorongKeluar::objekPublik(['kunci' => 'surat:1:0', 'kode_lamaran' => 'LMR-ABC', 'nama' => 'Surat.PDF', 'path' => 'surat/b.pdf', 'ukuran' => 10]);
        $c = PendorongKeluar::objekPublik(['kunci' => 'x', 'kode_lamaran' => 'LMR-ABC', 'nama' => 'cv.pdf', 'sha256' => str_repeat('ab', 32)]);

        $this->assertStringStartsWith('dokumen/LMR-ABC/', $a);
        $this->assertStringEndsWith('.pdf', $a);
        $this->assertNotSame($a, $b, 'berkas pengganti mendapat objek baru');
        $this->assertSame('dokumen/LMR-ABC/'.str_repeat('ab', 16).'.pdf', $c);
    }
}
