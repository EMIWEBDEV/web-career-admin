<?php

namespace App\Support\Career;

use App\Services\WebCareers\HclClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daftarkan kandidat ke HCLearn LEWAT API (POST api/v1/web-careers/kandidat)
 * — TIDAK insert langsung ke HRIS_Rekrutmen_Karyawan. Kode_Calon dibuat oleh
 * CAT memakai aturan internalnya (prefix 'CK', sama dengan insert data calon),
 * lalu disimpan ke Users.Kode_Calon.
 *
 * Dulu dipanggil langsung saat kandidat mendaftar di panel ini. Pendaftaran kini
 * terjadi di situs kandidat (project web-careers-pengguna); zona dalam memanggil
 * ini sewaktu memproses peristiwa Akun.Terdaftar.
 *
 * Idempoten di sisi CAT (kunci Id_WC_Users): registrasi ulang mengembalikan
 * Kode_Calon lama. Best-effort — kegagalan API tidak menggagalkan pemrosesan;
 * Kode_Calon menyusul (kandidat tanpa kode tertahan saat penjadwalan tes,
 * dengan pesan yang sudah ada di modul Penjadwalan).
 */
final class RegistrasiKandidatCat
{
    public static function daftarkan(int $idUsers, string $nama, string $email, ?string $hp, string $nik): void
    {
        try {
            $hasil = app(HclClient::class)->post(
                'kandidat',
                [
                    'Id_WC_Users' => $idUsers,
                    'Nama' => $nama,
                    'Email' => $email,
                    'HP' => $hp,
                    'NIK' => $nik,
                ],
                [
                    'Jenis_Event' => 'REGISTRASI_KANDIDAT',
                ],
            );

            $kodeCalon = $hasil['result']['Kode_Calon'] ?? null;
            if (! $hasil['sukses'] || ! $kodeCalon) {
                Log::channel('web_career')->error(
                    "Registrasi kandidat #{$idUsers} ke HCLearn gagal: " . ($hasil['message'] ?? 'tanpa pesan'),
                );

                return;
            }

            DB::table('N_WEB_CAREERS_Users')
                ->where('Id_Users', $idUsers)
                ->update(['Kode_Calon' => $kodeCalon]);
            Log::channel('web_career')->info("Kandidat #{$idUsers} terdaftar di HCLearn: {$kodeCalon}");
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal daftarkan kandidat #{$idUsers} ke HCLearn: " . $e->getMessage());
        }
    }
}
