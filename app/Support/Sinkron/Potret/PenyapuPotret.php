<?php

namespace App\Support\Sinkron\Potret;

use App\Support\Sinkron\TandaAir;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PENYAPU POTRET — menangkap SETIAP perubahan lamaran di panel admin tanpa
 * menyisipkan kode ke ratusan tempat yang mengubah data lamaran.
 *
 *   1. Penanda waktu: lamaran yang dirinya / tahapnya / aktivitasnya / sesi
 *      ujiannya / berkas hasilnya / feedback-nya / undangan & permintaan
 *      jadwalnya berubah (Updated_At) sejak sapuan lalu (mundur 5 menit
 *      sebagai pengaman jam & transaksi panjang).
 *   2. Giliran: N lamaran BERJALAN (+ yang baru selesai) disegarkan bergiliran
 *      — menangkap perubahan yang tak meninggalkan Updated_At (skrip SQL,
 *      batas waktu yang terlewati).
 *
 * Membangun potret yang tidak berubah tidak mengantrekan apa pun (hash sama),
 * jadi sapuan berulang aman dan murah di sisi publik.
 */
final class PenyapuPotret
{
    private const JAM = 'potret:jam';

    private const GILIRAN = 'potret:giliran';

    private const MUNDUR_DETIK = 300;

    /** @return array{berubah: int, diantrekan: int, giliran: int} */
    public function sapu(int $batasDetik = 25): array
    {
        $akhir = microtime(true) + $batasDetik;
        $mulai = now();
        $hasil = ['berubah' => 0, 'diantrekan' => 0, 'giliran' => 0];

        // ── 1. Penanda waktu ─────────────────────────────────────────────
        $jam = TandaAir::angka(self::JAM);
        $dari = $jam > 0
            ? Carbon::createFromTimestampMs($jam, config('app.timezone'))->subSeconds(self::MUNDUR_DETIK)
            : $mulai->copy()->subDay();
        // Sapuan sebelumnya terpotong waktu → lanjut dari id terakhir yang
        // sudah disegarkan (jendela waktunya tetap), tidak mengulang dari awal.
        $kursor = TandaAir::angka(self::JAM.':kursor');

        $selesai = true;
        foreach ($this->berubahSejak($dari) as $id) {
            if ($id <= $kursor) {
                continue;
            }
            if (microtime(true) > $akhir) {
                $selesai = false;
                break;
            }
            $hasil['berubah']++;
            $hasil['diantrekan'] += $this->segarkan($id) ? 1 : 0;
            $kursor = $id;
        }
        if ($selesai) {
            TandaAir::tulisAngka(self::JAM, (int) $mulai->getTimestampMs());
            TandaAir::tulisAngka(self::JAM.':kursor', 0);
        } else {
            // Jam TIDAK dimajukan (perubahan selama sapuan ini ikut tertangkap
            // nanti); kursor menyimpan sampai mana jendela ini sudah dikerjakan.
            TandaAir::tulisAngka(self::JAM.':kursor', $kursor);
        }

        // ── 2. Giliran ───────────────────────────────────────────────────
        $n = max(0, (int) config('sinkron.potret.giliran', 40));
        if ($n > 0 && microtime(true) < $akhir) {
            $kursor = TandaAir::angka(self::GILIRAN);
            $giliran = $this->giliran($kursor, $n);
            if (count($giliran) < $n) {
                $giliran = array_merge($giliran, $this->giliran(0, $n - count($giliran)));
            }
            $terakhir = $kursor;
            foreach (array_unique($giliran) as $id) {
                if (microtime(true) > $akhir) {
                    break;
                }
                $hasil['giliran']++;
                $hasil['diantrekan'] += $this->segarkan($id) ? 1 : 0;
                $terakhir = $id;
            }
            TandaAir::tulisAngka(self::GILIRAN, $terakhir);
        }

        return $hasil;
    }

    private function segarkan(int $id): bool
    {
        try {
            return PembangunPotret::segarkan($id);
        } catch (\Throwable $e) {
            Log::warning("[SINKRON] potret lamaran #{$id} gagal dibangun: ".$e->getMessage());

            return false;
        }
    }

    /** @return list<int> */
    private function berubahSejak(Carbon $dari): array
    {
        $t = $dari->format('Y-m-d H:i:s');
        $sql = "
            SELECT Id_Lamaran AS id FROM N_WEB_CAREERS_Lamaran WHERE Updated_At >= ?
            UNION SELECT Lamaran_Id FROM N_WEB_CAREERS_Lamaran_Tahap WHERE Updated_At >= ?
            UNION SELECT h.Lamaran_Id FROM N_WEB_CAREERS_Lamaran_Tahap_Tes x
                  JOIN N_WEB_CAREERS_Lamaran_Tahap h ON h.Id_Lamaran_Tahap = x.Lamaran_Tahap_Id
                  WHERE x.Updated_At >= ?
            UNION SELECT Lamaran_Id FROM N_WEB_CAREERS_Penjadwalan_Peserta WHERE Lamaran_Id IS NOT NULL AND Updated_At >= ?
            UNION SELECT Lamaran_Id FROM N_WEB_CAREERS_Lamaran_Tahap_Berkas WHERE ISNULL(Updated_At, Created_At) >= ?
            UNION SELECT Lamaran_Id FROM N_WEB_CAREERS_Feedback_Jawaban WHERE ISNULL(Updated_At, Created_At) >= ?
            UNION SELECT h.Lamaran_Id FROM N_WEB_CAREERS_CRM_Konfirmasi_Email c
                  JOIN N_WEB_CAREERS_Lamaran_Tahap_Tes x ON x.Id_Lamaran_Tahap_Tes = c.Lamaran_Tahap_Tes_Id
                  JOIN N_WEB_CAREERS_Lamaran_Tahap h ON h.Id_Lamaran_Tahap = x.Lamaran_Tahap_Id
                  WHERE c.Updated_At >= ?
            UNION SELECT h.Lamaran_Id FROM N_WEB_CAREERS_Lamaran_Tahap_Tes_Jadwal_Permintaan p
                  JOIN N_WEB_CAREERS_Lamaran_Tahap_Tes x ON x.Id_Lamaran_Tahap_Tes = p.Lamaran_Tahap_Tes_Id
                  JOIN N_WEB_CAREERS_Lamaran_Tahap h ON h.Id_Lamaran_Tahap = x.Lamaran_Tahap_Id
                  WHERE p.Updated_At >= ?";

        return array_map(fn ($r) => (int) $r->id, DB::select("SELECT DISTINCT id FROM ({$sql}) z WHERE id IS NOT NULL ORDER BY id", array_fill(0, 8, $t)));
    }

    /** @return list<int> */
    private function giliran(int $sesudah, int $n): array
    {
        return DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Lamaran', '>', $sesudah)
            ->where(fn ($q) => $q->where('Status', 'BERJALAN')->orWhere('Waktu_Selesai', '>=', now()->subDays(7)))
            ->orderBy('Id_Lamaran')
            ->limit($n)
            ->pluck('Id_Lamaran')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
