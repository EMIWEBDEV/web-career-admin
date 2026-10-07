<?php

namespace App\Console\Commands;

use App\Support\Sinkron\KotakMasuk;
use App\Support\Sinkron\OperasiSinkron;
use App\Support\Sinkron\PendorongKeluar;
use App\Support\Sinkron\RegistriSinkron;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kesehatan sinkron dua zona: antrean masuk & keluar per status, peristiwa
 * yang MATI (perlu ditangani), Outbox publik yang belum berbalas, registri
 * tabel sinkron, jejak operator, retensi, dan pemicu audit.
 */
class SinkronStatus extends Command
{
    protected $signature = 'sinkron:status {--mati=10 : Banyak peristiwa MATI/GAGAL terbaru yang ditampilkan}';

    protected $description = 'Ringkasan kesehatan sinkron dua zona (kotak masuk, Sinkron_Keluar, Outbox publik)';

    public function handle(): int
    {
        $rekap = DB::select('SET NOCOUNT ON; EXEC dbo.usp_WC_Sinkron_Rekap');
        $this->table(['Arah', 'Status', 'Jumlah', 'Tertua (UTC)'], array_map(fn ($r) => [$r->Arah, $r->Status, $r->Jumlah, $r->Tertua], $rekap));

        $bermasalah = DB::table(KotakMasuk::TABEL)
            ->whereIn('Status', ['MATI', 'GAGAL'])
            ->orderByDesc('Id_Masuk')
            ->limit((int) $this->option('mati'))
            ->get(['Id_Masuk', 'Status', 'Jenis', 'Kunci_Urut', 'Percobaan', 'Coba_Lagi_At', 'Galat_Terakhir']);
        if ($bermasalah->isNotEmpty()) {
            $this->warn('Peristiwa masuk bermasalah (putar ulang: php artisan sinkron:ulang --id=…):');
            $this->table(['Id', 'Status', 'Jenis', 'Akun', 'Coba', 'Coba lagi', 'Galat'], $bermasalah->map(fn ($r) => [
                $r->Id_Masuk, $r->Status, $r->Jenis, $r->Kunci_Urut, $r->Percobaan, $r->Coba_Lagi_At, mb_strimwidth((string) $r->Galat_Terakhir, 0, 90, '…'),
            ])->all());
        }

        try {
            $outbox = PendorongKeluar::publik()->table('N_WEB_CAREERS_Sinkron_Outbox')
                ->selectRaw('Status, COUNT(*) AS n, MIN(Created_At) AS tertua')->groupBy('Status')->get();
            $this->info('Outbox publik:');
            $this->table(['Status', 'Jumlah', 'Tertua (UTC)'], $outbox->map(fn ($r) => [$r->Status, $r->n, $r->tertua])->all());
        } catch (\Throwable $e) {
            $this->warn('Outbox publik tidak terbaca: '.$e->getMessage());
        }

        $this->registri();
        $this->jejak();

        return self::SUCCESS;
    }

    /** Registri tabel sinkron: berapa tabel per cara, mana yang dijeda / galat. */
    private function registri(): void
    {
        try {
            $baris = DB::table(RegistriSinkron::TABEL)->get(['Tabel_Admin', 'Cara', 'Aktif', 'Terakhir_Sinkron_Utc', 'Terakhir_Hasil']);
        } catch (\Throwable) {
            $this->warn('Registri N_WEB_CAREERS_Sinkron_Tabel belum ada (SQL docs/07-10-2026/admin/01).');

            return;
        }

        $this->info('Registri tabel sinkron: '.$baris->countBy('Cara')->map(fn ($n, $c) => "{$c} {$n}")->implode(', '));
        $perhatian = $baris->filter(fn ($r) => $r->Aktif === 'T' || str_starts_with((string) $r->Terakhir_Hasil, 'galat'));
        if ($perhatian->isNotEmpty()) {
            $this->table(['Tabel', 'Aktif', 'Terakhir cocok (UTC)', 'Hasil'], $perhatian->map(fn ($r) => [
                $r->Tabel_Admin, $r->Aktif, $r->Terakhir_Sinkron_Utc, mb_strimwidth((string) $r->Terakhir_Hasil, 0, 90, '…'),
            ])->all());
        }
    }

    /** Jejak: operator terakhir, retensi, pemicu audit terpasang. */
    private function jejak(): void
    {
        try {
            $op = DB::table(OperasiSinkron::TABEL)->orderByDesc('Id_Operasi')->limit(5)
                ->get(['Id_Operasi', 'Mulai_Utc', 'Perintah', 'Operator', 'Status', 'Jumlah']);
            if ($op->isNotEmpty()) {
                $this->info('Operasi operator terakhir:');
                $this->table(['Id', 'Mulai (UTC)', 'Perintah', 'Operator', 'Status', 'Jumlah'], $op->map(fn ($r) => (array) $r)->all());
            }

            $ret = DB::table('N_WEB_CAREERS_Retensi')->orderBy('Kode')->get(['Kode', 'Aktif', 'Hari_Simpan', 'Terakhir_Jalan_Utc', 'Terakhir_Jumlah']);
            $this->info('Retensi:');
            $this->table(['Kode', 'Aktif', 'Hari', 'Terakhir (UTC)', 'Dipangkas'], $ret->map(fn ($r) => (array) $r)->all());

            $aktif = (int) DB::table('N_WEB_CAREERS_Audit_Tabel')->where('Aktif', 'Y')->count();
            $terpasang = (int) (DB::selectOne("SELECT COUNT(*) AS n FROM sys.triggers WHERE parent_class = 1 AND name LIKE 'trg[_]WC[_]Audit[_]%' AND is_disabled = 0")->n ?? 0);
            $pesan = "Pemicu audit: {$terpasang} terpasang dari {$aktif} tabel aktif di registri.";
            $terpasang < $aktif ? $this->warn($pesan.' Jalankan EXEC dbo.usp_WC_Audit_Pasang (DBA).') : $this->info($pesan);
        } catch (\Throwable $e) {
            $this->warn('Jejak audit belum terbaca (SQL docs/07-10-2026/admin/01 belum dijalankan?): '.mb_strimwidth($e->getMessage(), 0, 160, '…'));
        }
    }
}
