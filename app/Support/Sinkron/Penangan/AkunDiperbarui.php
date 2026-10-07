<?php

namespace App\Support\Sinkron\Penangan;

use App\Jobs\Career\WcSyncEmailJob;
use App\Support\Career\RegistrasiKandidatCat;
use App\Support\Sinkron\PetaId;
use Illuminate\Support\Facades\DB;

/**
 * Akun.Diperbarui — profil akun kandidat berubah di situs kandidat.
 *
 *   daftar_ulang          akun lama yang kedaluwarsa / belum terverifikasi
 *                         didaftarkan ulang (data diri baru)
 *   email_terverifikasi   kandidat menekan tautan verifikasi
 *   sandi_diganti         reset sandi berhasil → surel pemberitahuan
 *
 * Salinan akun zona dalam mengikuti; surel pemberitahuan dikirim dari sini
 * (EVO Mail hanya terjangkau zona dalam).
 */
final class AkunDiperbarui implements Penangan
{
    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $a = (array) ($muatan['akun'] ?? []);
        $id = PetaId::akunAdmin((int) ($a['id_publik'] ?? 0));
        $perubahan = array_values(array_filter((array) ($muatan['perubahan'] ?? []), 'is_string'));

        AkunTerdaftar::perbarui($id, $a, $a['email'] ?? null);

        $hasil = HasilPenanganan::ok('akun:'.$id);

        if (in_array('sandi_diganti', $perubahan, true)) {
            $hasil->lalu(fn () => WcSyncEmailJob::dispatch(WcSyncEmailJob::JENIS_RESET_SELESAI, $id));
        }

        if (in_array('daftar_ulang', $perubahan, true)
            && ! DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $id)->value('Kode_Calon')) {
            $hasil->lalu(fn () => RegistrasiKandidatCat::daftarkan(
                $id,
                (string) ($a['nama'] ?? ''),
                (string) ($a['email'] ?? ''),
                $a['no_hp'] ?? null,
                (string) ($a['nik'] ?? ''),
            ));
        }

        return $hasil;
    }
}
