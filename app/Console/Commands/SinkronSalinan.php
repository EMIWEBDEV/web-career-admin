<?php

namespace App\Console\Commands;

use App\Support\Sinkron\OperasiSinkron;
use App\Support\Sinkron\PenyapuSalinan;
use Illuminate\Console\Command;

/**
 * Salinan master admin → database publik tanpa batas waktu per putaran —
 * untuk muatan awal (±330 ribu baris Master_Kampus) atau rekonsiliasi penuh
 * sesudah perbaikan data. Tick biasa tetap mencicilnya sendiri.
 */
class SinkronSalinan extends Command
{
    protected $signature = 'sinkron:salinan
        {--penuh : Abaikan gerbang sidik tabel (bandingkan semua)}
        {--tabel=* : Hanya tabel tertentu (nama admin atau publik)}
        {--oleh= : Nama/email operator untuk jejak (bawaan: pengguna@mesin)}';

    protected $description = 'Rekonsiliasi salinan master admin ke database publik';

    public function handle(PenyapuSalinan $penyapu): int
    {
        return OperasiSinkron::jalankan($this, function (\ArrayObject $catatan) use ($penyapu) {
            $r = $penyapu->sapu((bool) $this->option('penuh'), null, $this->option('tabel') ?: null);

            $this->info("Tabel: {$r['tabel']} (dilewati {$r['dilewati']}) — ditulis {$r['tulis']}, dihapus {$r['hapus']}.");
            if ($r['jeda']) {
                $this->warn('Dijeda di registri (N_WEB_CAREERS_Sinkron_Tabel.Aktif = T): '.implode(', ', $r['jeda']));
            }
            foreach ($r['galat'] as $t => $g) {
                $this->error("{$t}: {$g}");
            }
            $catatan['jumlah'] = $r['tulis'] + $r['hapus'];
            $catatan['hasil'] = $r;

            return $r['galat'] ? self::FAILURE : self::SUCCESS;
        });
    }
}
