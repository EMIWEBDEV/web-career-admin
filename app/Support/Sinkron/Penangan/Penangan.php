<?php

namespace App\Support\Sinkron\Penangan;

/**
 * Satu jenis peristiwa masuk. Dijalankan DI DALAM transaksi Admin DB yang
 * sama dengan perubahan status kotak masuknya — efek bisnis, status DIPROSES,
 * dan kabar baliknya tersimpan bersama, atau tidak sama sekali.
 *
 * Lempar PeristiwaDitolak untuk penolakan yang sah (tidak dicoba ulang);
 * galat lain membuat peristiwanya GAGAL dan dicoba lagi dengan jeda.
 */
interface Penangan
{
    /**
     * @param  array  $muatan  muatan peristiwa (sudah lolos Gerbang 2)
     * @param  object  $baris  baris Sinkron_Masuk (Id_Masuk, Event_Id, Jenis, Kunci_Urut, Diterima_At, …)
     */
    public function tangani(array $muatan, object $baris): HasilPenanganan;
}
