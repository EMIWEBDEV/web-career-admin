<?php

namespace App\Console\Commands;

use App\Support\Sinkron\OperasiSinkron;
use App\Support\Sinkron\PendorongKeluar;
use App\Support\Sinkron\Potret\PembangunPotret;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bangun ulang potret portal kandidat — satu lamaran (kode) atau semuanya —
 * lalu dorong ke database publik. Potret yang tidak berubah tidak diantrekan.
 */
class SinkronPotret extends Command
{
    protected $signature = 'sinkron:potret
        {kode?* : Kode lamaran (LMR-…)}
        {--semua : Seluruh lamaran}
        {--tanpa-dorong : Hanya antrekan, jangan dorong sekarang}
        {--oleh= : Nama/email operator untuk jejak (bawaan: pengguna@mesin)}';

    protected $description = 'Bangun ulang potret portal kandidat & dorong ke database publik';

    public function handle(PendorongKeluar $pendorong): int
    {
        if (! $this->argument('kode') && ! $this->option('semua')) {
            $this->error('Sebutkan kode lamaran, atau --semua.');

            return self::INVALID;
        }

        return OperasiSinkron::jalankan($this, fn (\ArrayObject $catatan) => $this->bangun($pendorong, $catatan));
    }

    private function bangun(PendorongKeluar $pendorong, \ArrayObject $catatan): int
    {
        $q = DB::table('N_WEB_CAREERS_Lamaran')->orderBy('Id_Lamaran');
        if ($kode = $this->argument('kode')) {
            $q->whereIn('Kode', $kode);
        }

        $diantre = 0;
        $dilewati = 0;
        $galat = 0;
        $bar = $this->output->createProgressBar((clone $q)->count());
        foreach ($q->pluck('Id_Lamaran') as $id) {
            try {
                PembangunPotret::segarkan((int) $id) ? $diantre++ : $dilewati++;
            } catch (\Throwable $e) {
                $galat++;
                $this->newLine();
                $this->error("Lamaran #{$id}: ".$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Diantrekan {$diantre}, tidak berubah/tanpa akun publik {$dilewati}, galat {$galat}.");
        $catatan['jumlah'] = $diantre;
        $catatan['dilewati'] = $dilewati;
        $catatan['galat'] = $galat;

        if (! $this->option('tanpa-dorong')) {
            $dorong = $pendorong->dorongHabis(600);
            $this->line('dorong: '.json_encode($dorong));
            $catatan['dorong'] = $dorong;
        }

        return $galat ? self::FAILURE : self::SUCCESS;
    }
}
