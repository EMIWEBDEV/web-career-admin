<?php

namespace App\Console\Commands;

use App\Support\Sinkron\Keluar;
use App\Support\Sinkron\KotakMasuk;
use App\Support\Sinkron\OperasiSinkron;
use App\Support\Sinkron\PemrosesMasuk;
use App\Support\Sinkron\PendorongKeluar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Putar ulang yang MATI sesudah penyebabnya diperbaiki:
 *
 *   peristiwa MASUK (kotak masuk) — percobaan dari nol, urutan per akun tetap
 *   dorongan KELUAR (Sinkron_Keluar) — mis. dokumen yang gagal disalin karena
 *   bucket publik belum dibuat
 *
 * Setiap putaran ulang tercatat di N_WEB_CAREERS_Sinkron_Operasi: operator,
 * Id yang diputar ulang beserta status sebelumnya, dan hasilnya.
 */
class SinkronUlang extends Command
{
    protected $signature = 'sinkron:ulang
        {--id=* : Id_Masuk yang diputar ulang}
        {--kunci= : Semua peristiwa MATI/GAGAL satu akun (mis. akun:12)}
        {--semua-mati : Seluruh peristiwa masuk yang MATI}
        {--keluar : Seluruh dorongan keluar yang MATI}
        {--oleh= : Nama/email operator untuk jejak (bawaan: pengguna@mesin)}';

    protected $description = 'Putar ulang peristiwa kotak masuk / dorongan keluar yang MATI';

    public function handle(PemrosesMasuk $pemroses, PendorongKeluar $pendorong): int
    {
        if (! $this->option('keluar') && ! $this->option('id') && ! $this->option('kunci') && ! $this->option('semua-mati')) {
            $this->error('Sebutkan --id, --kunci, --semua-mati, atau --keluar.');

            return self::INVALID;
        }

        return OperasiSinkron::jalankan($this, fn (\ArrayObject $catatan) => $this->ulang($pemroses, $pendorong, $catatan));
    }

    private function ulang(PemrosesMasuk $pemroses, PendorongKeluar $pendorong, \ArrayObject $catatan): int
    {
        if ($this->option('keluar')) {
            $ids = DB::table(Keluar::TABEL)->where('Status', 'MATI')->orderBy('Id_Keluar')->pluck('Id_Keluar')->map(fn ($v) => (int) $v)->all();
            $n = 0;
            foreach (array_chunk($ids, 1000) as $potong) {
                $n += DB::table(Keluar::TABEL)->whereIn('Id_Keluar', $potong)->where('Status', 'MATI')->update([
                    'Status' => 'GAGAL',
                    'Percobaan' => 0,
                    'Coba_Lagi_At' => Carbon::now('UTC'),
                    'Lease_Sampai' => null,
                ]);
            }
            $this->info("{$n} dorongan keluar diputar ulang.");
            $dorong = $pendorong->dorongHabis(120);
            $this->line('dorong: '.json_encode($dorong));
            $catatan['jumlah'] = $n;
            $catatan['keluar_mati'] = OperasiSinkron::daftar($ids);
            $catatan['dorong'] = $dorong;

            return self::SUCCESS;
        }

        $q = DB::table(KotakMasuk::TABEL)->whereIn('Status', ['MATI', 'GAGAL']);
        if ($ids = array_filter(array_map('intval', (array) $this->option('id')))) {
            $q->whereIn('Id_Masuk', $ids);
        } elseif ($kunci = $this->option('kunci')) {
            $q->where('Kunci_Urut', $kunci);
        } else {
            $q->where('Status', 'MATI');
        }

        $baris = $q->orderBy('Id_Masuk')->get(['Id_Masuk', 'Kunci_Urut', 'Status', 'Jenis', 'Percobaan']);
        $catatan['jumlah'] = $baris->count();
        if ($baris->isEmpty()) {
            $this->info('Tidak ada peristiwa yang cocok.');

            return self::SUCCESS;
        }
        // Keadaan SEBELUM diputar ulang — tabel kotak masuk tidak diaudit pemicu.
        $catatan['masuk'] = OperasiSinkron::daftar($baris->map(fn ($b) => [
            'id' => (int) $b->Id_Masuk, 'jenis' => $b->Jenis, 'kunci' => $b->Kunci_Urut, 'status' => $b->Status, 'percobaan' => (int) $b->Percobaan,
        ])->all());

        DB::table(KotakMasuk::TABEL)->whereIn('Id_Masuk', $baris->pluck('Id_Masuk')->all())->update([
            'Status' => 'GAGAL',
            'Percobaan' => 0,
            'Coba_Lagi_At' => null,
            'Updated_At' => Carbon::now('UTC'),
        ]);

        $hasil = [];
        foreach ($baris->pluck('Kunci_Urut')->unique() as $k) {
            $r = $pemroses->prosesKunci((string) $k);
            $hasil[(string) $k] = $r;
            $this->line("{$k}: ".json_encode($r));
        }
        $dorong = $pendorong->dorongHabis(60);
        $this->line('dorong: '.json_encode($dorong));
        $catatan['hasil'] = $hasil;
        $catatan['dorong'] = $dorong;

        return self::SUCCESS;
    }
}
