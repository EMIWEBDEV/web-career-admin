<?php

namespace App\Support\Sinkron;

/**
 * Akun kandidat hidup di situs kandidat (database publik): profil, sandi, dan
 * verifikasinya diubah kandidat sendiri di sana. Dari panel ini yang boleh
 * menyeberang hanya STATUS-nya (diblokir / dibuka tim) — lewat Akun.Status.
 */
final class StatusAkun
{
    public const PESAN_KANDIDAT = 'Data akun kandidat diubah kandidat sendiri di situs kandidat. Dari panel ini yang bisa diubah hanya status aktif dan kode karyawannya.';

    /** Kabarkan status akun ke situs kandidat — bila akunnya memang ada di sana. */
    public static function kabarkan(int $idAdmin, string $status): void
    {
        $idPublik = PetaId::publik(PetaId::AKUN, $idAdmin);
        if ($idPublik === null) {
            return;
        }

        Keluar::antrekan(Keluar::AKUN, 'akun:'.$idPublik, ['id_publik' => (int) $idPublik, 'status' => $status]);
    }
}
