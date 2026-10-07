<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PETA ID — id publik ↔ id admin (N_WEB_CAREERS_Sinkron_Peta).
 *
 * Id admin tidak pernah keluar zona dalam, dan id publik tidak dipakai di
 * tabel admin mana pun. Sambungannya hanya di sini:
 *
 *   AKUN      Id_Users publik            ↔ Id_Users admin
 *   BERKAS    "tes:{Id_Lamaran_Tes_Berkas publik}" ↔ Id_Lamaran_Tes_Berkas admin
 *   FORMULIR  Kode pengisian (FLL-…)      ↔ Id_Formulir_Pengisian admin
 *
 * Lamaran tidak dipetakan: KODE-nya sama di kedua zona.
 */
final class PetaId
{
    public const TABEL = 'N_WEB_CAREERS_Sinkron_Peta';

    public const AKUN = 'AKUN';

    public const BERKAS = 'BERKAS';

    public const FORMULIR = 'FORMULIR';

    public static function admin(string $jenis, string|int $kunciPublik): ?int
    {
        $id = DB::table(self::TABEL)
            ->where('Jenis_Entitas', $jenis)
            ->where('Kunci_Publik', (string) $kunciPublik)
            ->value('Id_Admin');

        return $id !== null ? (int) $id : null;
    }

    public static function publik(string $jenis, int $idAdmin): ?string
    {
        $k = DB::table(self::TABEL)
            ->where('Jenis_Entitas', $jenis)
            ->where('Id_Admin', $idAdmin)
            ->value('Kunci_Publik');

        return $k !== null ? (string) $k : null;
    }

    /**
     * Pasang peta. Idempoten untuk pasangan yang sama; pasangan yang
     * BERTABRAKAN (kunci publik sudah menunjuk id admin lain, atau sebaliknya)
     * adalah galat data — tidak pernah ditimpa diam-diam.
     */
    public static function pasang(string $jenis, string|int $kunciPublik, int $idAdmin): void
    {
        $kunciPublik = (string) $kunciPublik;
        $ada = self::admin($jenis, $kunciPublik);
        if ($ada === $idAdmin) {
            return;
        }
        if ($ada !== null) {
            throw new RuntimeException("Peta {$jenis} {$kunciPublik} sudah menunjuk id admin {$ada}, bukan {$idAdmin}.");
        }
        $lain = self::publik($jenis, $idAdmin);
        if ($lain !== null) {
            throw new RuntimeException("Id admin {$idAdmin} ({$jenis}) sudah dipetakan ke {$lain}, bukan {$kunciPublik}.");
        }

        DB::table(self::TABEL)->insert([
            'Jenis_Entitas' => $jenis,
            'Kunci_Publik' => $kunciPublik,
            'Id_Admin' => $idAdmin,
        ]);
    }

    /**
     * Id akun admin dari id publik — wajib ada. Peristiwa akun selalu datang
     * berurutan (kunci urut = akun), jadi Akun.Terdaftar sudah diproses lebih
     * dulu; bila belum, peristiwanya ditunda (galat biasa → dicoba lagi).
     */
    public static function akunAdmin(int $idPublik): int
    {
        return self::admin(self::AKUN, $idPublik)
            ?? throw new RuntimeException("Akun publik #{$idPublik} belum punya salinan di zona dalam (Akun.Terdaftar belum diproses).");
    }
}
