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
use Vinkla\Hashids\Facades\Hashids;

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

    /**
     * Galat dari CAT → kalimat yang berguna bagi admin.
     *
     * Yang tampil di layar penjadwalan sebelumnya adalah lemparan mentah SQL
     * Server milik sistem lain — lengkap dengan nama constraint acak dan
     * potongan perintah INSERT. Bagi orang yang menjadwalkan tes, itu bukan
     * keterangan apa pun: ia tidak tahu apa yang salah, apalagi apa yang harus
     * dilakukan. Yang teknis tetap disimpan utuh di log.
     *
     * Sengaja mengenali POLA, bukan mencocokkan nama constraint: nama seperti
     * `UQ__N_HRIS_K__CA0EF675D95DB9EF` dibuat otomatis SQL Server dan berbeda
     * di tiap lingkungan.
     */
    private static function pesanRamah(string $asli): string
    {
        $l = strtolower($asli);

        if (str_contains($l, 'duplicate key') && str_contains($l, 'id_wc_penjadwalan_peserta')) {
            return 'CAT menolak karena pengenal peserta sudah terpakai di sana. '
                . 'Coba jadwalkan ulang — sistem akan memakai pengenal baru. '
                . 'Bila tetap gagal, laporkan ke tim CAT (rincian teknis ada di log).';
        }

        if (str_contains($l, 'duplicate key')) {
            return 'CAT menolak karena datanya dianggap ganda. Coba jadwalkan ulang; bila tetap gagal, laporkan ke tim CAT (rincian teknis ada di log).';
        }

        if (str_contains($l, 'request expired')) {
            // Sudah pernah memakan waktu berjam-jam untuk didiagnosis.
            return 'CAT menolak karena selisih jam server. Samakan waktu mesin ini dengan CAT (maksimal 60 detik), lalu coba lagi.';
        }

        if (str_contains($l, 'master ujian tidak ditemukan')) {
            return 'Paket ujian tidak dikenali CAT. Pilih ulang paket tesnya di layar penjadwalan.';
        }

        // Galat yang belum dikenali dikirim apa adanya — menyembunyikannya di
        // balik "terjadi kesalahan" justru menghapus satu-satunya petunjuk.
        return $asli;
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

        // PENGENAL UNTUK CAT DISIAPKAN DI SINI, tepat sebelum dikirim.
        //
        // CAT memakai `Id_WC_Penjadwalan_Peserta` sebagai KUNCI UNIK di
        // tabelnya. Dulu kita mengirim kolom IDENTITY kita sendiri — dan
        // IDENTITY bisa TERULANG setelah tabel peserta di-reset, sehingga
        // peserta baru bertabrakan dengan peserta angkatan lama yang masih
        // tersimpan di CAT. Galatnya muncul sebagai "Violation of UNIQUE KEY"
        // mentah, dan sudah dua kali terjadi.
        //
        // Nomornya kini dari SEQUENCE, yang tidak tersentuh TRUNCATE maupun
        // reseed — jadi tidak pernah mundur, apa pun yang terjadi pada tabel.
        //
        // Diberikan hanya kepada yang BELUM punya: percobaan ulang atas kiriman
        // yang gagal memakai nomor yang sama, karena barisnya memang tidak
        // pernah berhasil masuk ke CAT.
        $peserta = $peserta->map(function ($p) {
            if ($p->Ref_Cat_Peserta === null) {
                $p->Ref_Cat_Peserta = DB::selectOne(
                    'SELECT NEXT VALUE FOR SEQ_WC_Penjadwalan_Peserta_Cat AS n'
                )->n;

                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->where('Id_Penjadwalan_Peserta', $p->Id_Penjadwalan_Peserta)
                    ->update(['Ref_Cat_Peserta' => $p->Ref_Cat_Peserta, 'Updated_At' => now()]);
            }

            return $p;
        });

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
                // Nomor SEQUENCE, bukan IDENTITY kita — lihat penjelasan di atas.
                'Id_WC_Penjadwalan_Peserta' => (int) $p->Ref_Cat_Peserta,
                'Kode_Peserta' => $p->Kode_Peserta,
                'Jenis_User' => 'eksternal',
                'Id_WC_Users' => $p->Users_Id,
                'Id_WC_Lamaran' => $p->Lamaran_Id,
                'Nama' => $p->Nama,
                'Email' => $p->Email,
                'No_Hp' => $p->No_Hp,
                'Posisi_Dilamar' => $p->Posisi_Dilamar,
                // KE MANA KANDIDAT PULANG setelah ujiannya selesai.
                //
                // Dipakai CAT untuk DUA jalan keluar sekaligus: tombol "Selesai
                // & Keluar" di Berita Acara, dan hitung mundur 60 detik yang
                // habis. Tanpa ini keduanya bermuara ke halaman akses HCLearn —
                // halaman yang bukan milik kandidat kita, tanpa jalan balik.
                //
                // Dikirim PER PESERTA, bukan per penjadwalan: alamatnya memuat
                // id lamaran, dan satu penjadwalan berisi banyak lamaran.
                //
                // Basisnya `hclearn.public_url`, BUKAN app.url: itulah alamat
                // yang memang sudah ditetapkan sebagai "cara dunia luar
                // menjangkau kami" untuk integrasi ini — sama dengan yang
                // dipakai Url_Callback, jadi keduanya tak bisa lagi menyimpang.
                //
                // `?dari=tes` adalah penanda ASAL, bukan hiasan: halaman lamaran
                // memakainya untuk menyambut kandidat yang baru pulang dari tes
                // dan menunggu hasilnya masuk — lihat LamaranDetail.vue.
                'Url_Kembali' => $p->Lamaran_Id
                    ? rtrim(config('hclearn.public_url'), '/')
                        . '/kandidat/lamaran/' . Hashids::encode($p->Lamaran_Id) . '?dari=tes'
                    : null,
            ])->values()->all(),
        ], [
            'Jenis_Event' => 'PENJADWALAN_UJIAN',
            'Penjadwalan_Id' => $this->penjadwalanId,
            'Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId,
        ]);

        if (! $hasil['sukses']) {
            // Ditolak mentah — biarkan job dicoba ulang oleh antrean.
            DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
                ->update([
                    'Status' => 'GAGAL',
                    'Pesan_Error' => substr(self::pesanRamah($hasil['message']), 0, 480),
                    'Updated_At' => now(),
                ]);

            // Log menyimpan pesan ASLI — yang dirapikan hanya yang dibaca admin.
            Log::channel('web_career')->error('[PENJADWALAN] HCLearn menolak: ' . $hasil['message']);

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
     * Pasang (atau pasang ulang) tautan tahap lamaran kandidat → penjadwalan ini.
     *
     * Inilah yang membuat portal kandidat menampilkan token, OTP, dan jendela
     * waktu tesnya. Aman dipanggil berulang: hanya menyentuh peserta yang
     * tokennya BENAR-BENAR terbit, dan hanya aktivitas yang belum selesai.
     *
     * Aktivitasnya ditentukan `Penjadwalan_Tahap.Tes_Urutan` — bukan ditebak.
     * Satu tahap bisa memuat Psikotes 1, Psikotes 2, dan DISC yang dijadwalkan
     * sendiri-sendiri; menebak berarti kandidat menerima token untuk aktivitas
     * yang salah, dan itu jauh lebih sulit disadari daripada tidak ada token.
     */
    private function pasangTautanKandidat(): void
    {
        $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
            ->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
            ->first(['Urutan', 'Tes_Urutan']);

        if (! $tahap) {
            return;
        }

        // Hanya kandidat yang tokennya terbit. Peserta yang gagal tetap tidak
        // tertaut — portalnya jujur berbunyi "menunggu jadwal", karena memang
        // belum ada yang bisa ia kerjakan.
        $lamaranIds = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNotNull('Short_Token')
            ->pluck('Lamaran_Id')
            ->filter()
            ->unique()
            ->all();

        if (! $lamaranIds) {
            return;
        }

        DB::transaction(function () use ($tahap, $lamaranIds) {
            $now = now();

            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->whereIn('Lamaran_Id', $lamaranIds)
                ->where('Urutan', $tahap->Urutan)
                ->whereNull('Penjadwalan_Tahap_Id')
                ->update(['Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId, 'Updated_At' => $now]);

            $tahapIds = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->whereIn('Lamaran_Id', $lamaranIds)
                ->where('Urutan', $tahap->Urutan)
                ->pluck('Id_Lamaran_Tahap')
                ->all();

            if (! $tahapIds) {
                return;
            }

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->whereIn('Lamaran_Tahap_Id', $tahapIds)
                ->when($tahap->Tes_Urutan, fn ($q, $u) => $q->where('Urutan', $u))
                ->where('Flag_Selesai', 'N')
                ->whereNull('Penjadwalan_Tahap_Id')
                ->update([
                    'Status' => 'DIJADWALKAN',
                    'Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId,
                    'Updated_At' => $now,
                ]);
        });

        Log::channel('web_career')->info(
            '[ANTREAN] tautan kandidat dipasang untuk penjadwalan tahap #' . $this->penjadwalanTahapId
            . ' (' . count($lamaranIds) . ' lamaran).'
        );
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
            // TAUTAN KANDIDAT DIPASANG ULANG.
            //
            // Saat penerbitan token gagal, blok di bawah sengaja MELEPAS tautan
            // supaya kandidat kembali ke antrean "menunggu jadwal". Tapi tak ada
            // satu pun kode yang memasangnya kembali ketika penjadwalan yang
            // sama dicoba ulang dan kali ini berhasil — akibatnya token terbit,
            // admin melihat TERKIRIM, dan kandidat melihat "Menunggu
            // dijadwalkan" selamanya tanpa galat di mana pun.
            //
            // Dikerjakan di sini, bukan di tombol coba-ulang: antrean juga
            // mencoba sendiri saat job gagal, dan perbaikan yang hanya ada di
            // tombol tidak akan pernah berjalan pada jalur itu.
            $this->pasangTautanKandidat();

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
