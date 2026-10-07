<?php

namespace App\Support\Sinkron\Penangan;

use App\Jobs\Career\WcSyncEmailJob;
use App\Support\Sinkron\KotakMasuk;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;
use Illuminate\Support\Facades\DB;

/**
 * Akun.KodeDiminta — situs kandidat meminta surel berisi tautan verifikasi
 * (VERIFIKASI) atau kode reset sandi (RESET).
 *
 * Kodenya dibuat, di-hash, dan diperiksa di situs kandidat; zona dalam hanya
 * mengirim surelnya. Rahasianya tetap TERBUNGKUS sampai job surel membukanya,
 * dan begitu job diantrekan, bungkusnya dihapus dari kotak masuk — tidak ada
 * kode hidup yang tertinggal di Admin DB.
 */
final class AkunKodeDiminta implements Penangan
{
    private const JENIS = [
        'VERIFIKASI' => WcSyncEmailJob::JENIS_VERIFIKASI,
        'RESET' => WcSyncEmailJob::JENIS_RESET_OTP,
    ];

    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $jenis = self::JENIS[(string) ($muatan['jenis'] ?? '')] ?? null;
        $rahasia = (string) ($muatan['rahasia'] ?? '');
        if (! $jenis || $rahasia === '') {
            throw new PeristiwaDitolak('Permintaan kode tidak dikenali.');
        }

        $id = PetaId::akunAdmin((int) $muatan['id_publik']);
        $data = [
            'rahasia' => $rahasia,
            'menit' => max(1, (int) ($muatan['berlaku_menit'] ?? 30)),
            'kepada' => (string) ($muatan['email'] ?? ''),
            'nama' => $muatan['nama'] ?? null,
        ];

        // Bungkus rahasia tidak disimpan lebih lama dari perlunya.
        $bersih = $muatan;
        $bersih['rahasia'] = null;
        DB::table(KotakMasuk::TABEL)->where('Id_Masuk', $baris->Id_Masuk)->update([
            'Muatan' => json_encode($bersih, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return HasilPenanganan::ok('akun:'.$id)
            ->lalu(fn () => WcSyncEmailJob::untukKandidat($jenis, $id, $data));
    }
}
