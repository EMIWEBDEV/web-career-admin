<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * RETENSI — masa simpan jejak & tabel sinkron (N_WEB_CAREERS_Retensi),
 * dijalankan dbo.usp_WC_Retensi_Jalankan. Prosedur itu sendiri yang menjaga
 * jeda ±20 jam per kebijakan dan lantai masa simpannya; aplikasi cukup
 * memanggilnya dari tick, paling sering sekali per jam per instance.
 */
final class Retensi
{
    /** @return array<string, int|string> Kode => jumlah baris, atau ['dilewati' => alasan] */
    public static function jalankan(bool $paksa = false): array
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return ['dilewati' => 'bukan SQL Server'];
        }
        if (! $paksa && ! Cache::add('wc-sinkron:retensi', 1, 3600)) {
            return [];
        }

        try {
            $baris = DB::select('SET NOCOUNT ON; EXEC dbo.usp_WC_Retensi_Jalankan @Paksa = ?', [$paksa ? 1 : 0]);
        } catch (\Throwable $e) {
            $pesan = $e->getMessage();
            if (str_contains($pesan, 'Could not find stored procedure')) {
                return ['dilewati' => 'prosedur retensi belum dipasang (SQL 07-10-2026)'];
            }
            if (str_contains($pesan, 'permission was denied')) {
                return ['dilewati' => 'akun aplikasi belum diberi EXECUTE usp_WC_Retensi_Jalankan'];
            }
            throw $e;
        }

        $hasil = [];
        foreach ($baris as $r) {
            $hasil[(string) $r->Kode] = (int) $r->Jumlah;
        }

        return $hasil;
    }
}
