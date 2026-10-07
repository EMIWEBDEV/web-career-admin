<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\Log;

/**
 * Menarik pesan dari langganan wc-masuk-sinkron ke kotak masuk.
 *
 *   BARU / DUPLIKAT  → ack (sudah aman di Sinkron_Masuk)
 *   DITOLAK          → nack: kembali ke langganan, dicoba ulang dengan jeda,
 *                      sesudah 10× pindah ke DLQ wc-masuk-mati (bukti utuh)
 *   Admin DB galat   → PEMUTUS ARUS: berhenti menarik; pesan yang belum
 *                      tersimpan tidak di-ack dan dikirim ulang Google
 *                      tanpa ada yang hilang.
 */
final class PenarikPesan
{
    public function __construct(private PubSubLangganan $langganan) {}

    /** @return array{ditarik: int, baru: int, duplikat: int, ditolak: int, kunci: list<string>, putus: bool} */
    public function tarik(int $detik = 20): array
    {
        $hasil = ['ditarik' => 0, 'baru' => 0, 'duplikat' => 0, 'ditolak' => 0, 'kunci' => [], 'putus' => false];
        $kunci = [];
        $akhir = microtime(true) + max(1, $detik);
        $per = max(1, (int) config('sinkron.pubsub.per_tarik', 100));

        do {
            // Menunggu pesan selama sisa jendela tarik (paling lama batas HTTP).
            $tunggu = max(3, min((int) ceil($akhir - microtime(true)), (int) config('sinkron.pubsub.timeout', 20)));
            $pesan = $this->langganan->tarik($per, $tunggu);
            if (! $pesan) {
                break;
            }
            $hasil['ditarik'] += count($pesan);

            $ack = [];
            $nack = [];
            foreach ($pesan as $i => $p) {
                try {
                    $r = KotakMasuk::terimaPesan($p['data'], $p['attributes'], $p['messageId'], $p['publishTime'], $p['orderingKey']);
                } catch (\Throwable $e) {
                    // Admin DB tidak menjawab: sisanya dikembalikan utuh.
                    Log::error('[SINKRON] kotak masuk tidak bisa menyimpan — penarikan dihentikan: '.$e->getMessage());
                    $nack = array_merge($nack, array_column(array_slice($pesan, $i), 'ackId'));
                    $hasil['putus'] = true;
                    break;
                }

                if ($r['hasil'] === KotakMasuk::DITOLAK) {
                    $nack[] = $p['ackId'];
                    $hasil['ditolak']++;

                    continue;
                }

                $ack[] = $p['ackId'];
                if ($r['hasil'] === KotakMasuk::BARU) {
                    $hasil['baru']++;
                    $kunci[(string) $r['kunciUrut']] = true;
                } else {
                    $hasil['duplikat']++;
                }
            }

            $this->langganan->ack($ack);
            if ($nack) {
                try {
                    $this->langganan->nack($nack);
                } catch (\Throwable $e) {
                    Log::warning('[SINKRON] nack gagal (pesan tetap dikirim ulang sesudah batas ack): '.$e->getMessage());
                }
            }
        } while (! $hasil['putus'] && microtime(true) < $akhir);

        $hasil['kunci'] = array_keys($kunci);

        return $hasil;
    }
}
