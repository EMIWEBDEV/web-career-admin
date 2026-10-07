<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\DB;

/**
 * OUTBOX KELUAR — antrean dorongan dari zona dalam ke database publik
 * (N_WEB_CAREERS_Sinkron_Keluar). Pendorongnya PendorongKeluar.
 *
 *   Portal.Lamaran    potret portal satu lamaran + kolom status lamaran publik
 *   Peristiwa.Hasil   jawaban atas satu peristiwa masuk (DIPROSES / DITOLAK)
 *   Dokumen.Tersedia  dokumen untuk kandidat disalin ke bucket publik
 *   Akun.Status       akun kandidat diblokir / dibuka
 *
 * Keadaan dikirim UTUH per kunci (state transfer), dengan VERSI yang naik per
 * (Jenis, Kunci): sisi publik menerapkan hanya yang lebih baru, jadi dorongan
 * ganda atau terbalik tidak menimpa apa pun. Muatan yang sama persis dengan
 * versi terakhir tidak diantrekan lagi.
 */
final class Keluar
{
    public const TABEL = 'N_WEB_CAREERS_Sinkron_Keluar';

    public const PORTAL = 'Portal.Lamaran';

    public const HASIL = 'Peristiwa.Hasil';

    public const DOKUMEN = 'Dokumen.Tersedia';

    public const AKUN = 'Akun.Status';

    /**
     * Antrekan satu keadaan. Dipanggil di dalam transaksi perubahannya bila
     * ada; kalau tidak, membuka transaksinya sendiri.
     *
     * @return int|null Versi yang diantrekan; null bila muatannya sama dengan versi terakhir
     */
    public static function antrekan(string $jenis, string $kunci, array $muatan, bool $paksa = false): ?int
    {
        $json = json_encode($muatan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = strtoupper(hash('sha256', $json));
        $kunci = mb_substr($kunci, 0, 160);

        return DB::transaction(function () use ($jenis, $kunci, $json, $hash, $paksa) {
            // UPDLOCK+HOLDLOCK pada rentang (Jenis, Kunci): dua pengantre kunci
            // yang sama saling menunggu — versi tidak pernah kembar.
            $akhir = DB::table(self::TABEL)
                ->lock('WITH (UPDLOCK, HOLDLOCK)')
                ->where('Jenis', $jenis)
                ->where('Kunci', $kunci)
                ->orderByDesc('Versi')
                ->selectRaw('[Versi], CONVERT(VARCHAR(66), [Hash_Muatan], 1) AS Hash_Hex')
                ->first();

            if (! $paksa && $akhir && $akhir->Hash_Hex !== null && strtoupper(substr((string) $akhir->Hash_Hex, 2)) === $hash) {
                return null;
            }

            $versi = (int) ($akhir->Versi ?? 0) + 1;
            DB::table(self::TABEL)->insert([
                'Jenis' => $jenis,
                'Kunci' => $kunci,
                'Versi' => $versi,
                'Muatan' => $json,
                'Hash_Muatan' => DB::raw('0x'.$hash),
            ]);

            return $versi;
        });
    }

    /** Muatan versi terakhir yang diantrekan untuk sebuah kunci. */
    public static function terakhir(string $jenis, string $kunci): ?array
    {
        $m = DB::table(self::TABEL)
            ->where('Jenis', $jenis)
            ->where('Kunci', $kunci)
            ->orderByDesc('Versi')
            ->value('Muatan');

        return $m !== null ? json_decode((string) $m, true) : null;
    }
}
