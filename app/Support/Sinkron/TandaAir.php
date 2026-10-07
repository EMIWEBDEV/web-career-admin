<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\DB;

/**
 * TANDA AIR — 8 byte per nama (N_WEB_CAREERS_Sinkron_Tanda_Air): sampai mana
 * sebuah penyapu sudah bekerja (jam, kursor giliran, sidik tabel salinan).
 *
 * Dibaca/ditulis sebagai heksadesimal supaya tidak bergantung pada cara
 * driver mengembalikan kolom BINARY.
 */
final class TandaAir
{
    public const TABEL = 'N_WEB_CAREERS_Sinkron_Tanda_Air';

    /** 16 digit heksadesimal (tanpa 0x), atau null bila belum pernah ditulis. */
    public static function hex(string $nama): ?string
    {
        $h = DB::table(self::TABEL)->where('Nama', $nama)->selectRaw('CONVERT(VARCHAR(18), [Nilai], 1) AS h')->value('h');

        return $h !== null ? strtoupper(substr((string) $h, 2)) : null;
    }

    public static function angka(string $nama): int
    {
        $h = self::hex($nama);

        return $h !== null ? (int) hexdec(ltrim($h, '0') ?: '0') : 0;
    }

    public static function tulisHex(string $nama, string $hex16): void
    {
        $hex16 = str_pad(strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $hex16)), 16, '0', STR_PAD_LEFT);
        $nilai = DB::raw('0x'.substr($hex16, -16));

        $n = DB::table(self::TABEL)->where('Nama', $nama)->update(['Nilai' => $nilai, 'Updated_At' => DB::raw('SYSUTCDATETIME()')]);
        if ($n === 0) {
            DB::table(self::TABEL)->insert(['Nama' => mb_substr($nama, 0, 100), 'Nilai' => $nilai]);
        }
    }

    public static function tulisAngka(string $nama, int $nilai): void
    {
        self::tulisHex($nama, str_pad(dechex(max(0, $nilai)), 16, '0', STR_PAD_LEFT));
    }
}
