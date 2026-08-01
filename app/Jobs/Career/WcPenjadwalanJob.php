<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Services\WebCareers\HclClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — PENERBITAN TOKEN UJIAN KE HCLEARN (latar belakang).
 *
 * KENAPA DIANTREKAN
 * Menekan "Generate" untuk 200 kandidat berarti satu permintaan HTTP menunggu
 * HCLearn membuatkan 200 token. Permintaannya habis waktu, admin menekan ulang,
 * dan lahirlah penjadwalan ganda. Sejak sekarang baris penjadwalan disimpan
 * lebih dulu (status DIANTRIKAN), lalu penerbitan tokennya dikerjakan job ini.
 *
 * KONTRAK STATUS — dibaca layar Daftar Penjadwalan:
 *   DIANTRIKAN  baris sudah ada, token belum terbit;
 *   BERJALAN    minimal satu token terbit;
 *   GAGAL       tak satu pun terbit. Barisnya SENGAJA tidak dihapus supaya
 *               admin tahu apa yang terjadi; tautan kandidatnya dilepas agar
 *               mereka kembali muncul di antrean "menunggu jadwal".
 *
 * Idempoten: peserta yang tokennya sudah terbit dilewati, jadi percobaan ulang
 * setelah gagal separuh tidak pernah menerbitkan token dobel.
 *
 * Nama antrean `wc-penjadwalanworker` harus ada di Cloud Tasks:
 *     gcloud tasks queues create wc-penjadwalanworker
 * Di lokal cukup `php artisan queue:work` (lihat AntreanWebCareers).
 */
class WcPenjadwalanJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-penjadwalanworker';

    /** Menerbitkan ratusan token bisa lama; beri ruang sebelum dianggap mati. */
    public $timeout = 900;

    /** HCLearn sesekali tersendat — dicoba ulang, dengan jeda menaik. */
    public $tries = 3;

    public $backoff = [30, 120];

    public function __construct(
        private int $penjadwalanId,
        private int $penjadwalanTahapId,
        // Pengenal paket dari HCLearn: STRING terenkripsi, bukan angka.
        private string $idMasterUjian,
        private string $waktuMulai,
        private string $waktuAkhir,
    ) {
        $this->aturAntrean(self::QUEUE);
    }

    public function handle(HclClient $hcl): void
    {
        $penjadwalan = DB::table('N_WEB_CAREERS_Penjadwalan')
            ->where('Id_Penjadwalan', $this->penjadwalanId)->first();

        // Penjadwalannya sudah dibatalkan/dihapus sementara job menunggu giliran.
        if (! $penjadwalan) {
            Log::channel('web_career')->info("[ANTREAN] Penjadwalan #{$this->penjadwalanId} sudah tidak ada — job dilewati.");

            return;
        }

        // Hanya peserta yang tokennya BELUM terbit. Inilah yang membuat percobaan
        // ulang aman: yang sudah berhasil tidak dikirim ulang ke HCLearn.
        $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNull('Short_Token')
            ->get();

        if ($peserta->isEmpty()) {
            $this->rapikanStatus();

            return;
        }

        $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
            ->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)->first();
        $program = DB::table('N_WEB_CAREERS_Program')
            ->where('Id_Program', $penjadwalan->Program_Id)->first();

        $hasil = $hcl->post('penjadwalan', [
            'Kode_WC_Penjadwalan' => $penjadwalan->Kode,
            'Nama_Penjadwalan' => $penjadwalan->Nama,
            'Id_WC_Penjadwalan' => $this->penjadwalanId,
            'Id_WC_Penjadwalan_Tahap' => $this->penjadwalanTahapId,
            'Id_WC_Program' => $penjadwalan->Program_Id,
            'Nama_Program' => $program->Nama ?? '',
            'Kode_WC_Tahap' => $tahap->Kode ?? '',
            'Label_Tahap' => $tahap->Label ?? '',
            'Urutan_WC_Tahap' => (int) ($tahap->Urutan ?? 0),
            'Total_WC_Tahap' => (int) $penjadwalan->Jumlah_Tahap,
            'Id_Master_Ujian' => $this->idMasterUjian,
            'Waktu_Mulai' => $this->waktuMulai,
            'Waktu_Akhir' => $this->waktuAkhir,
            // KAMERA WAJIB dipaksa dari sisi Web Careers agar CAT tak pernah
            // menjalankan sesi tanpa kamera.
            config('hclearn.kamera_field', 'Flag_Camera') => (bool) config('hclearn.wajib_kamera', true),
            'Url_Callback' => config('hclearn.public_url') . '/' . ltrim(config('hclearn.callback_path'), '/'),
            'peserta' => $peserta->map(fn ($p) => [
                'Id_WC_Penjadwalan_Peserta' => (int) $p->Id_Penjadwalan_Peserta,
                'Kode_Peserta' => $p->Kode_Peserta,
                'Jenis_User' => 'eksternal',
                'Id_WC_Users' => $p->Users_Id,
                'Id_WC_Lamaran' => $p->Lamaran_Id,
                'Nama' => $p->Nama,
                'Email' => $p->Email,
                'No_Hp' => $p->No_Hp,
                'Posisi_Dilamar' => $p->Posisi_Dilamar,
            ])->values()->all(),
        ], [
            'Jenis_Event' => 'PENJADWALAN_UJIAN',
            'Penjadwalan_Id' => $this->penjadwalanId,
            'Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId,
        ]);

        if (! $hasil['sukses']) {
            // Ditolak mentah — biarkan job dicoba ulang oleh antrean.
            DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
                ->update(['Status' => 'GAGAL', 'Pesan_Error' => substr($hasil['message'], 0, 480), 'Updated_At' => now()]);

            throw new \RuntimeException('HCLearn menolak permintaan: ' . $hasil['message']);
        }

        $res = $hasil['result'] ?? [];

        // CAT memproses di antreannya sendiri — token menyusul lewat callback.
        if (($res['mode'] ?? null) === 'ANTRIAN') {
            DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
                ->update(['Status' => 'DIANTRIKAN', 'Waktu_Kirim' => now(), 'Updated_At' => now()]);
            DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
                ->update(['Status' => 'DIANTRIKAN', 'Catatan' => 'Menunggu HCLearn menerbitkan token.', 'Updated_At' => now()]);
            Log::channel('web_career')->info("[ANTREAN] {$penjadwalan->Kode} diteruskan ke antrean HCLearn.");

            return;
        }

        [$sukses, $gagal, $pesanGagal] = app(\App\Http\Controllers\Career\Penjadwalan\PenjadwalanController::class)
            ->serapBalasanHclearn($res, $peserta);

        DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
            ->update([
                'Status' => $gagal === 0 ? 'TERKIRIM' : ($sukses > 0 ? 'SEBAGIAN' : 'GAGAL'),
                'Pesan_Error' => $pesanGagal ? substr($pesanGagal, 0, 480) : null,
                'Waktu_Kirim' => now(), 'Updated_At' => now(),
            ]);

        $this->rapikanStatus($pesanGagal);

        Log::channel('web_career')->info("[ANTREAN] {$penjadwalan->Kode}: {$sukses} token terbit, {$gagal} gagal.");
    }

    /**
     * Selaraskan status penjadwalan dengan kenyataan tokennya.
     *
     * Tak satu pun token terbit → GAGAL, dan tautan kandidat DILEPAS supaya
     * mereka kembali ke antrean "menunggu jadwal". Barisnya tidak dihapus:
     * penjadwalan yang lenyap tanpa jejak membuat admin menebak-nebak.
     */
    private function rapikanStatus(?string $pesanGagal = null): void
    {
        $terbit = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNotNull('Short_Token')->count();

        if ($terbit > 0) {
            DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
                ->update(['Status' => 'BERJALAN', 'Catatan' => $pesanGagal ? substr($pesanGagal, 0, 480) : null, 'Updated_At' => now()]);

            return;
        }

        DB::transaction(function () use ($pesanGagal) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
                ->where('Flag_Selesai', 'N')
                ->update(['Penjadwalan_Tahap_Id' => null, 'Status' => 'BELUM', 'Updated_At' => now()]);

            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
                ->update(['Penjadwalan_Tahap_Id' => null, 'Updated_At' => now()]);

            DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
                ->update([
                    'Status' => 'GAGAL',
                    'Catatan' => substr($pesanGagal ?: 'Tidak ada token yang berhasil diterbitkan HCLearn.', 0, 480),
                    'Updated_At' => now(),
                ]);
        });

        Log::channel('web_career')->warning("[ANTREAN] Penjadwalan #{$this->penjadwalanId} GAGAL total — kandidat dikembalikan ke antrean.");
    }

    /** Percobaan terakhir pun gagal: tandai jelas, jangan biarkan menggantung. */
    public function failed(\Throwable $e): void
    {
        Log::channel('web_career')->error("[ANTREAN] WcPenjadwalanJob #{$this->penjadwalanId} gagal total: " . $e->getMessage());
        $this->rapikanStatus($e->getMessage());
    }
}
