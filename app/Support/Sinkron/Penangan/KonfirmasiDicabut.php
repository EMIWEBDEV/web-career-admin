<?php

namespace App\Support\Sinkron\Penangan;

use App\Support\Career\KonfirmasiJadwal;
use App\Support\Sinkron\PeristiwaDitolak;

/**
 * Konfirmasi.Dicabut — kandidat membatalkan permintaan jadwal lain.
 * Aturannya di KonfirmasiJadwal::cabutPermintaan.
 */
final class KonfirmasiDicabut implements Penangan
{
    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        [$id, $sub, $pelaku] = KonfirmasiDijawab::sasaran($muatan);

        $r = KonfirmasiJadwal::cabutPermintaan($id, (int) $muatan['versi'], $pelaku);
        if (! ($r['ok'] ?? false)) {
            throw new PeristiwaDitolak((string) ($r['pesan'] ?? 'Permintaan tidak bisa dibatalkan.'));
        }

        return HasilPenanganan::ok('aktivitas:'.$id, (string) ($r['pesan'] ?? 'Permintaan jadwal lain dibatalkan.'))
            ->segarkan((int) $sub->Lamaran_Id);
    }
}
