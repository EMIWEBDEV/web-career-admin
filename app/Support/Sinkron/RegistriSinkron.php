<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * REGISTRI TABEL SINKRON (N_WEB_CAREERS_Sinkron_Tabel) — tanda tabel admin
 * mana yang ikut sinkron dua zona, arahnya, caranya, dan keadaan terakhir
 * salinannya. Daftar CARA menyalin tetap di kode (PenyapuSalinan::TABEL);
 * registri menjeda tabel tertentu (Aktif = T) dan mencatat pantauannya.
 *
 * Tabel registri belum ada (SQL 07-10 belum dijalankan) → semua tabel
 * dianggap aktif dan pantauan dilewati, sinkron tetap berjalan.
 */
final class RegistriSinkron
{
    public const TABEL = 'N_WEB_CAREERS_Sinkron_Tabel';

    /** Pantauan "cocok" yang tidak berubah cukup diperbarui sesering ini. */
    private const SEGARKAN_MENIT = 10;

    /** @var array<string, object>|null */
    private ?array $baris = null;

    /** @var array<string, array{h: string, n: ?int, ok: int}> */
    private array $catatan = [];

    /** Baris SALINAN per tabel admin; [] bila registri belum ada. */
    private function baris(): array
    {
        if ($this->baris !== null) {
            return $this->baris;
        }

        try {
            $this->baris = DB::table(self::TABEL)
                ->where('Cara', 'SALINAN')
                ->get(['Tabel_Admin', 'Aktif', 'Terakhir_Sinkron_Utc', 'Terakhir_Hasil'])
                ->keyBy('Tabel_Admin')
                ->all();
        } catch (\Throwable) {
            $this->baris = [];
        }

        return $this->baris;
    }

    public function dijeda(string $tabelAdmin): bool
    {
        $b = $this->baris()[$tabelAdmin] ?? null;

        return $b !== null && $b->Aktif === 'T';
    }

    /**
     * Catat hasil satu tabel. $ok = salinan sekarang dipastikan sama (waktu
     * Terakhir_Sinkron ikut maju); galat / belum tuntas hanya mengubah hasilnya.
     */
    public function catat(string $tabelAdmin, string $hasil, ?int $jumlah, bool $ok): void
    {
        $b = $this->baris()[$tabelAdmin] ?? null;
        if ($b === null) {
            return;
        }

        // Hasil sama dengan yang tersimpan: tidak ditulis lagi, kecuali waktu
        // "terakhir cocok"-nya sudah basi.
        $hasil = mb_substr($hasil, 0, 400);
        $segar = $b->Terakhir_Sinkron_Utc !== null
            && Carbon::parse($b->Terakhir_Sinkron_Utc, 'UTC')->gt(Carbon::now('UTC')->subMinutes(self::SEGARKAN_MENIT));
        if ($hasil === $b->Terakhir_Hasil && (! $ok || $segar)) {
            return;
        }

        $this->catatan[$tabelAdmin] = ['h' => $hasil, 'n' => $jumlah, 'ok' => $ok ? 1 : 0];
    }

    /** Satu perintah untuk semua tabel yang dicatat di putaran ini. */
    public function simpan(): void
    {
        if (! $this->catatan) {
            return;
        }

        $data = [];
        foreach ($this->catatan as $t => $c) {
            $data[] = ['t' => $t] + $c;
        }
        $this->catatan = [];
        $this->baris = null;

        try {
            DB::statement(
                'UPDATE r SET
                        [Terakhir_Sinkron_Utc] = CASE WHEN s.ok = 1 THEN SYSUTCDATETIME() ELSE r.[Terakhir_Sinkron_Utc] END,
                        [Terakhir_Hasil] = s.h,
                        [Jumlah_Baris] = COALESCE(s.n, r.[Jumlah_Baris])
                   FROM dbo.['.self::TABEL.'] AS r
                   JOIN OPENJSON(?) WITH (t NVARCHAR(128) \'$.t\', h NVARCHAR(400) \'$.h\', n INT \'$.n\', ok BIT \'$.ok\') AS s
                     ON s.t = r.[Tabel_Admin]',
                [json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            );
        } catch (\Throwable) {
            // Pantauan saja — salinan tetap berjalan.
        }
    }
}
