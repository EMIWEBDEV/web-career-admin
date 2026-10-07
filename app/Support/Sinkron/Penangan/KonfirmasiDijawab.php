<?php

namespace App\Support\Sinkron\Penangan;

use App\Support\Career\KonfirmasiJadwal;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;

/**
 * Konfirmasi.Dijawab — kandidat menjawab konfirmasi kehadiran (hadir / minta
 * jadwal lain / tidak melanjutkan) dari portal atau tautan surel.
 *
 * Seluruh aturan (versi, batas, jatah, komitmen ubah jawaban, final) tetap
 * di KonfirmasiJadwal::jawab — situs kandidat hanya memeriksa lebih dulu
 * supaya umpan baliknya instan; keputusan sahnya di sini.
 */
final class KonfirmasiDijawab implements Penangan
{
    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        [$id, $sub, $pelaku] = self::sasaran($muatan);

        $j = (array) $muatan['jawaban'];
        $r = KonfirmasiJadwal::jawab($id, (int) $muatan['versi'], (string) ($j['kode'] ?? ''), [
            'alasan' => $j['alasan'] ?? null,
            'catatan' => $j['catatan'] ?? null,
            'usulan' => is_array($j['usulan'] ?? null) ? $j['usulan'] : [],
            'statusDilihat' => $muatan['status_dilihat'] ?? null,
            'paham' => (bool) ($j['paham'] ?? false),
        ], $pelaku);

        if (! ($r['ok'] ?? false)) {
            throw new PeristiwaDitolak((string) ($r['pesan'] ?? 'Jawaban konfirmasi tidak dapat diproses.'));
        }

        return HasilPenanganan::ok('aktivitas:'.$id, (string) ($r['pesan'] ?? 'Jawabanmu sudah kami terima.'))
            ->segarkan((int) $sub->Lamaran_Id);
    }

    /**
     * Aktivitas milik akun & lamaran di muatan, beserta pelaku jejaknya.
     *
     * @return array{0: int, 1: object, 2: array}
     */
    public static function sasaran(array $muatan): array
    {
        $userId = PetaId::akunAdmin((int) $muatan['akun']['id_publik']);
        $id = (int) $muatan['aktivitas_id'];
        $sub = KonfirmasiJadwal::konteks($id);
        if (! $sub || (int) $sub->Id_Users !== $userId || (string) $sub->LamaranKode !== (string) $muatan['kode']) {
            throw new PeristiwaDitolak('Jadwal tidak ditemukan.');
        }

        $p = (array) ($muatan['pelaku'] ?? []);
        $kanal = ($p['kanal'] ?? '') === KonfirmasiJadwal::K_PORTAL ? KonfirmasiJadwal::K_PORTAL : KonfirmasiJadwal::K_TAUTAN;

        return [$id, $sub, [
            'jenis' => KonfirmasiJadwal::KANDIDAT,
            'kanal' => $kanal,
            'nama' => (string) $sub->KandidatNama,
            'id' => $userId,
            'ip' => isset($p['ip']) ? mb_substr((string) $p['ip'], 0, 45) : null,
            'perangkat' => isset($p['perangkat']) ? mb_substr((string) $p['perangkat'], 0, 60) : null,
        ]];
    }
}
