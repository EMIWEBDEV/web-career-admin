<?php

namespace App\Support\Sinkron;

use RuntimeException;

/**
 * Peristiwa yang SAH bentuknya tetapi tidak boleh diterapkan (lowongan sudah
 * tutup, jadwal sudah diganti, feedback sudah terisi, …).
 *
 * Bukan galat: tidak dicoba ulang. Kotak masuk menandainya DITOLAK dan pesan
 * ini sampai ke kandidat lewat Outbox publik (Hasil_Keterangan).
 */
final class PeristiwaDitolak extends RuntimeException {}
