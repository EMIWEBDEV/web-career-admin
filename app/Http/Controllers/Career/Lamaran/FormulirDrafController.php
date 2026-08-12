<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Http\Controllers\Controller;
use App\Support\Career\BerkasBaris;
use App\Support\Career\GcsBerkas;
use App\Support\Career\FormulirSchema;
use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — SIMPAN SEMENTARA (DRAF) FORMULIR TAHAP.
 *
 * Kandidat mengisi formulir bertahap yang panjang (Kelengkapan Data Diri: 4
 * langkah + 4 unggahan). Tanpa draf, satu kali refresh atau sesi putus
 * menghapus seluruh ketikan — dan itu terjadi justru pada formulir terpanjang.
 *
 * KEPUTUSAN PENTING
 *   - Draf disimpan di SERVER (N_WEB_CAREERS_Formulir_Draf), bukan localStorage.
 *     Draf yang hidup di browser hilang saat kandidat pindah perangkat, dan tim
 *     rekrutmen tidak punya cara melihat pengisian yang tertahan.
 *   - Satu baris per (tahap, kandidat); disimpan dengan cara ditimpa. Yang
 *     berguna hanya keadaan terakhir, bukan riwayat ketikan.
 *   - Berkas draf naik ke GCS lewat endpoint ini, dan HANYA bisa dibuka lewat
 *     endpoint pratinjau di bawah yang menerbitkan signed URL berumur pendek.
 *     Front-end tidak pernah menyentuh API storage secara langsung, sehingga
 *     kredensial bucket tidak pernah meninggalkan server.
 *
 * KEPEMILIKAN diperiksa di SETIAP endpoint lewat join ke Lamaran.Id_Users —
 * mengetahui id tahap milik orang lain tidak cukup untuk membaca drafnya.
 */
class FormulirDrafController extends Controller
{
    /**
     * Tahap milik kandidat yang sedang login, atau null.
     *
     * Dikembalikan berikut Lamaran_Id supaya pemanggil tidak perlu query lagi.
     */
    private function tahapMilikSaya(string $id): ?object
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId || ! $userId) {
            return null;
        }

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap', $realId)
            ->where('l.Id_Users', $userId)
            ->select('t.Id_Lamaran_Tahap', 't.Lamaran_Id', 't.Formulir_Kode', 't.Status', 't.Formulir_Pengisian_Id')
            ->first();
    }

    /**
     * GET /api/v1/lamaran/tahap/{id}/draf — pulihkan isian yang tertunda.
     *
     * Selalu 200. Belum pernah menyimpan draf bukan kesalahan — front-end cukup
     * menerima `draf: null` lalu memulai dari isian kosong seperti biasa.
     */
    public function ambil(string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $d = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap->Id_Lamaran_Tahap)
            ->where('Id_Users', (int) session('career_auth.id'))
            ->first();

        if (! $d) {
            return ResponseHelper::success(['draf' => null], 'Belum ada simpanan sementara.');
        }

        // Berkas dikembalikan sebagai metadata + URL endpoint kita sendiri.
        // Path GCS-nya sengaja TIDAK ikut keluar: browser tidak butuh, dan
        // membocorkannya memberi petunjuk susunan bucket tanpa guna.
        $berkas = collect(BerkasBaris::daftar($d->Berkas_Json ?? null))
            ->map(fn ($b) => [
                'bagian' => $b['bagian'] ?? null,
                'baris' => $b['baris'] ?? null,
                'field' => $b['field'] ?? null,
                'nama' => $b['nama'] ?? null,
                'ukuran' => (int) ($b['ukuran'] ?? 0),
                'mime' => $b['mime'] ?? null,
                // bagian/baris ikut sebagai query string, BUKAN segmen rute:
                // tautan pratinjau yang sudah beredar di draf lama tidak
                // membawa keduanya, dan harus tetap sah.
                'url' => route('career.portal.draf.berkas', array_filter([
                    'id' => $id,
                    'field' => $b['field'] ?? null,
                    'bagian' => $b['bagian'] ?? null,
                    'baris' => $b['baris'] ?? null,
                ], fn ($v) => $v !== null)),
            ])
            ->values();

        return ResponseHelper::success([
            'draf' => [
                'jawaban' => json_decode($d->Jawaban_Json ?: '{}', true) ?: (object) [],
                'langkah' => (int) $d->Langkah_Terakhir,
                'berkas' => $berkas,
                // Nilai mentah dari SQL Server sudah berupa string; membungkusnya
                // dengan optional()->__toString() justru menghasilkan null.
                'disimpanAt' => (string) ($d->Updated_At ?: $d->Created_At),
            ],
        ], 'Simpanan sementara ditemukan.');
    }

    /**
     * POST /api/v1/lamaran/tahap/{id}/draf — simpan setiap kali "Lanjut".
     *
     * Sengaja SINKRON, tidak lewat antrean: kandidat berhak tahu detik itu juga
     * bahwa isiannya aman. Menaruhnya di antrean berarti menampilkan "tersimpan"
     * sebelum benar-benar tersimpan — janji yang belum tentu ditepati.
     */
    public function simpan(Request $request, string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Tahap yang sudah diputus tidak boleh menerima draf baru — isinya sudah
        // jadi bagian dari keputusan seleksi.
        if ($tahap->Status !== 'BERJALAN') {
            return ResponseHelper::error('Tahap ini sudah tidak dalam pengisian.', 409);
        }

        // `present`, BUKAN `required`.
        //
        // Laravel menganggap array kosong sebagai "kosong", sehingga `required`
        // menolak draf yang jawabannya memang belum ada isinya — padahal itu
        // keadaan paling wajar: kandidat menekan Lanjut di langkah yang seluruh
        // isiannya opsional, atau langkah pertama belum sempat diketik. Yang
        // benar-benar wajib adalah kuncinya HADIR, bukan berisi.
        $data = $request->validate([
            'jawaban' => 'present|array',
            'langkah' => 'nullable|integer|min:0|max:50',
            'komponen' => 'nullable|string|max:60',
            'schema' => 'nullable|array',
        ]);

        $userId = (int) session('career_auth.id');
        $nama = session('career_auth.nama');
        $now = now();

        $isi = [
            'Lamaran_Id' => $tahap->Lamaran_Id,
            'Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap,
            'Id_Users' => $userId,
            'Formulir_Kode' => $tahap->Formulir_Kode,
            'Komponen_Kode' => $data['komponen'] ?? null,
            'Langkah_Terakhir' => (int) ($data['langkah'] ?? 0),
            'Jawaban_Json' => json_encode($data['jawaban'], JSON_UNESCAPED_UNICODE),
            'Updated_At' => $now,
            'Updated_By' => $nama,
            'Updated_By_Id' => $userId,
        ];

        if (! empty($data['schema']) && FormulirSchema::punyaKolomDrafSnapshot()) {
            $isi['Schema_Snapshot_Json'] = json_encode($data['schema'], JSON_UNESCAPED_UNICODE);
        }

        // updateOrInsert dijaga indeks unik (Lamaran_Tahap_Id, Id_Users), jadi
        // dua tab yang menyimpan bersamaan tidak bisa melahirkan draf kembar.
        DB::table('N_WEB_CAREERS_Formulir_Draf')->updateOrInsert(
            ['Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap, 'Id_Users' => $userId],
            $isi + ['Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId],
        );

        return ResponseHelper::success(['disimpanAt' => $now->toIso8601String()], 'Tersimpan sementara.');
    }

    /**
     * POST /api/v1/lamaran/tahap/{id}/draf/berkas — unggah berkas draf ke GCS.
     *
     * Berkas naik lewat SERVER, tidak lewat signed upload URL langsung dari
     * browser: dengan begini ukuran, tipe, dan kepemilikan diperiksa sebelum
     * satu byte pun mendarat di bucket.
     */
    public function unggahBerkas(Request $request, string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Batas 5 MB DAN batas PHP dua-duanya berlaku. Bila post_max_size lebih
        // kecil, berkas besar tidak pernah sampai ke validator — $_FILES kosong
        // dan pesannya jadi membingungkan. Karena itu diperiksa lebih dulu.
        if ($request->file('berkas') === null && $request->server('CONTENT_LENGTH') > 0) {
            return ResponseHelper::error(
                'Berkas terlalu besar untuk diterima server. Perkecil ukurannya lalu coba lagi.',
                413,
            );
        }

        $data = $request->validate([
            'field' => 'required|string|max:60',
            'berkas' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ]);

        $userId = (int) session('career_auth.id');
        $file = $request->file('berkas');

        // Struktur folder MENGIKUTI apply-form: tahun/bulan/tanggal/nama —
        // lihat GcsBerkas::folderTahap(). Menelusuri berkas seorang kandidat di
        // bucket jadi tidak menuntut hafal dua pola yang berbeda.
        $gcs = app(GcsBerkas::class);
        $now = now();
        $folder = $gcs->folderTahap(
            $now->format('Y'),
            $now->format('m'),
            $now->format('d'),
            (string) (session('career_auth.nama') ?: 'kandidat'),
        );

        // Ekstensi: pola SAMA dengan apply — jatuh ke extension() bila nama
        // berkas tidak membawa ekstensi.
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            // Unggah lewat GcsBerkas — jalur yang SAMA dengan formulir apply dan
            // sudah terbukti jalan di server. Dua jalur lain (putFileAs dan
            // writeStream) sempat dicoba dan dua-duanya melempar
            // "Path cannot be empty" pada adapter GCS proyek ini; jangan diulang
            // tanpa mengganti adapternya lebih dulu.
            //
            // Memori aman: validasi di atas sudah memotong berkas >5 MB sebelum
            // isinya dibaca, jadi yang masuk RAM paling besar 5 MB.
            // getContent(), BUKAN file_get_contents(getRealPath()).
            //
            // INI PENYEBAB "Path cannot be empty" selama ini. Di bawah Apache,
            // getRealPath() bisa mengembalikan string KOSONG untuk berkas unggahan
            // sementara, sehingga isinya gagal dibaca dan path yang terbentuk ikut
            // kosong. Di CLI getRealPath() selalu benar — itulah kenapa setiap uji
            // baris perintah lolos sementara browser selalu gagal.
            //
            // Jebakan yang sama sudah didokumentasikan di jalur apply
            // (LamaranController::apply): "andal (getPathname), bukan getRealPath
            // yang bisa kosong di Apache". Sekarang keduanya memakai cara yang sama.
            $konten = $file->getContent();

            // CATATAN: GcsBerkas::validasi() TIDAK dipanggil di sini. Batasnya
            // 2 MB dan hanya pdf/jpg/jpeg — itu aturan formulir apply. Formulir
            // tahap punya aturan sendiri (Sertifikat sampai 5 MB, PNG diterima),
            // dan sudah diperiksa oleh validate() di atas. Memanggilnya di sini
            // justru akan menolak berkas yang jelas-jelas dijanjikan boleh.
            // Berkas berulang WAJIB lewat unggahBaris(): path deterministik
            // membuat baris kedua menimpa yang pertama di bucket. Berkas biasa
            // tetap deterministik supaya unggah ulang menimpa versi lamanya
            // alih-alih menumpuk sampah.
            $path = ($data['bagian'] ?? null) !== null && ($data['baris'] ?? null) !== null
                ? $gcs->unggahBaris($folder, $data['field'], $ext, $konten)
                : $gcs->unggah($folder, $data['field'], $ext, $konten);
            unset($konten);
        } catch (\Throwable $e) {
            // Jejak LENGKAP. Pesan "Path cannot be empty" saja tidak menyebut
            // baris mana yang melemparnya, dan tiga percobaan perbaikan sempat
            // salah sasaran karena itu. Nilai folder/berkas ikut dicatat supaya
            // ketahuan bagian mana yang kosong tanpa perlu menebak.
            Log::channel('web_career')->error('[DRAF] unggah GCS gagal: ' . $e->getMessage(), [
                'kelas' => get_class($e),
                'di' => $e->getFile() . ':' . $e->getLine(),
                'folder' => $folder,
                'field' => $data['field'],
                'ext' => $ext,
                'nama_asli' => $file->getClientOriginalName(),
                'ukuran' => $file->getSize(),
                'nama_sesi' => session('career_auth.nama'),
                'disk' => GcsBerkas::DISK,
                'bucket' => config('filesystems.disks.' . GcsBerkas::DISK . '.bucket'),
                'jejak' => collect($e->getTrace())->take(5)
                    ->map(fn ($t) => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '')
                        . ' @ ' . basename($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))
                    ->all(),
            ]);

            return ResponseHelper::error(
                'Berkas gagal diunggah: ' . Str::limit($e->getMessage(), 160),
                500,
            );
        }

        $meta = [
            'bagian' => null,
            'baris' => null,
            'field' => $data['field'],
            'nama' => $file->getClientOriginalName(),
            'path' => $path,
            'ukuran' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];

        // Baris draf mungkin belum ada bila kandidat mengunggah sebelum menekan
        // "Lanjut" sekali pun — jadi dibuat di sini bila perlu.
        $kunci = ['Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap, 'Id_Users' => $userId];
        $baris = DB::table('N_WEB_CAREERS_Formulir_Draf')->where($kunci)->first();

        // DAFTAR, bukan peta berkunci field. Peta hanya sanggup memuat satu entri
        // per field, dan itulah yang meruntuhkan tiga sertifikat jadi satu.
        $semua = BerkasBaris::daftar($baris->Berkas_Json ?? null);

        // Yang diganti HANYA entri dengan triplet yang sama persis. Versi
        // sebelumnya membuang semua entri sefield — termasuk milik baris lain.
        $lama = null;
        $sisa = [];
        foreach ($semua as $e) {
            if (BerkasBaris::cocok($e, $meta['bagian'], $meta['baris'], $meta['field'])) {
                $lama = $e['path'] ?? null;
                continue;
            }
            $sisa[] = $e;
        }
        $sisa[] = $meta;

        DB::table('N_WEB_CAREERS_Formulir_Draf')->updateOrInsert($kunci, [
            'Lamaran_Id' => $tahap->Lamaran_Id,
            'Formulir_Kode' => $tahap->Formulir_Kode,
            'Berkas_Json' => json_encode(array_values($sisa), JSON_UNESCAPED_UNICODE),
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama'),
            'Updated_By_Id' => $userId,
            'Created_At' => $baris->Created_At ?? now(),
            'Created_By' => $baris->Created_By ?? session('career_auth.nama'),
            'Created_By_Id' => $baris->Created_By_Id ?? $userId,
        ]);

        // Berkas lama untuk TRIPLET yang sama dibuang — draf hanya menyimpan
        // versi terakhir tiap baris, dan menyisakannya berarti bucket menumpuk
        // sampah diam-diam. Baris lain di bagian yang sama tidak tersentuh.
        if ($lama && $lama !== $path) {
            try {
                Storage::disk(GcsBerkas::DISK)->delete($lama);
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('[DRAF] berkas lama gagal dihapus: ' . $e->getMessage());
            }
        }

        return ResponseHelper::success([
            'field' => $data['field'],
            'nama' => $meta['nama'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'],
            'url' => route('career.portal.draf.berkas', ['id' => $id, 'field' => $data['field']]),
        ], 'Berkas tersimpan sementara.');
    }

    /**
     * GET /api/v1/lamaran/tahap/{id}/draf/berkas/{field} — pratinjau berkas draf.
     *
     * Menerbitkan signed URL GCS berumur 15 menit lalu mengalihkan ke sana.
     * Tautannya tidak pernah disimpan di mana pun dan kedaluwarsa sendiri, jadi
     * kalaupun tersalin ke luar, umurnya pendek.
     */
    public function berkas(Request $request, string $id, string $field)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            abort(404);
        }

        $d = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap->Id_Lamaran_Tahap)
            ->where('Id_Users', (int) session('career_auth.id'))
            ->value('Berkas_Json');

        // Tautan lama tidak membawa bagian/baris dan itu SAH — artinya berkas
        // biasa di luar bagian berulang. Rute yang sudah beredar di draf lama
        // karena itu tidak patah.
        $bagian = $request->query('bagian');
        $barisQ = $request->query('baris');
        $baris = ($barisQ === null || $barisQ === '') ? null : (int) $barisQ;

        $path = null;
        foreach (BerkasBaris::daftar($d) as $b) {
            if (BerkasBaris::cocok($b, $bagian, $baris, $field)) {
                $path = $b['path'] ?? null;
                break;
            }
        }

        if (! $path) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($path)) {
                return redirect()->away($gcs->temporaryUrl($path, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[DRAF] signed URL gagal: ' . $e->getMessage());
        }

        abort(404);
    }

    /**
     * PINDAHKAN berkas draf jadi berkas PERMANEN milik pengisian.
     *
     * Ini yang selama ini hilang. Alurnya dulu: berkas naik ke GCS sebagai draf,
     * formulir dikirim, lalu bersihkan() MENGHAPUS draf berikut berkasnya —
     * sehingga yang tersisa di pengisian hanya NAMA berkas sebagai teks jawaban,
     * sementara isinya lenyap dari bucket. Kandidat melihat nama berkas yang
     * tidak bisa dibuka, dan tim rekrutmen kehilangan dokumennya.
     *
     * Berkasnya TIDAK disalin ulang di GCS — objek yang sama dipakai, hanya
     * kepemilikannya yang berpindah ke Formulir_Berkas. Karena itu pemanggil
     * WAJIB memakai bersihkan(..., hapusBerkas: false) sesudah ini, kalau tidak
     * berkas yang baru saja diadopsi ikut terhapus.
     *
     * @return int jumlah berkas yang berhasil dicatat
     */
    public static function jadikanPermanen(int $tahapId, int $userId, int $pengisianId, array $jawaban = []): int
    {
        $draf = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->first();

        if (! $draf) {
            return 0;
        }

        // Menerima dua bentuk: daftar baru, dan peta berkunci field milik draf
        // yang dibuat sebelum berkas baris berulang ada.
        $semua = BerkasBaris::daftar($draf->Berkas_Json ?? null);
        if (! $semua) {
            return 0;
        }

        $nama = session('career_auth.nama');
        $now = now();
        $urutan = 0;
        $jumlah = 0;

        foreach ($semua as $b) {
            if (empty($b['path']) || empty($b['field'])) {
                continue;
            }

            $bagian = $b['bagian'] ?? null;
            $idx = $b['baris'] ?? null;

            // BARIS YATIM: kandidat mengunggah lalu menghapus barisnya sebelum
            // mengirim. Mengesahkannya membuat laporan memuat sertifikat yang
            // barisnya sudah tidak ada di jawaban mana pun.
            if ($bagian !== null && $idx !== null) {
                $barisJawaban = $jawaban[$bagian] ?? null;
                if (! is_array($barisJawaban) || ! array_key_exists($idx, $barisJawaban)) {
                    continue;
                }
            }

            $urutan++;

            // Idempoten: kirim ulang untuk pengisian yang sama tidak menggandakan.
            //
            // Penjaganya memakai TRIPLET. Versi sebelumnya hanya (pengisian,
            // field) — dan karena tiap baris berulang memakai field yang sama
            // persis, dua sertifikat berikutnya dianggap duplikat lalu dibuang.
            // Itulah yang membuat pengisian 61 mencatat tiga nama berkas tapi
            // hanya menyimpan satu.
            $sudah = DB::table('N_WEB_CAREERS_Formulir_Berkas')
                ->where('Formulir_Pengisian_Id', $pengisianId)
                ->where('Field_Key', $b['field'])
                ->when($bagian === null, fn ($q) => $q->whereNull('Bagian_Key'))
                ->when($bagian !== null, fn ($q) => $q->where('Bagian_Key', $bagian))
                ->when($idx === null, fn ($q) => $q->whereNull('Baris_Index'))
                ->when($idx !== null, fn ($q) => $q->where('Baris_Index', $idx))
                ->exists();

            if ($sudah) {
                continue;
            }

            $ext = strtolower(pathinfo($b['path'], PATHINFO_EXTENSION) ?: 'pdf');

            DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                'Formulir_Pengisian_Id' => $pengisianId,
                'Id_Users' => $userId,
                'Bagian_Key' => $bagian,
                'Baris_Index' => $idx,
                'Field_Key' => $b['field'],
                'Urutan' => $urutan,
                'Nama_Asli' => $b['nama'] ?? ('berkas.' . $ext),
                'Path_File' => $b['path'],
                'Ukuran_Byte' => (int) ($b['ukuran'] ?? 0),
                'Mime' => $b['mime'] ?? null,
                'Ekstensi' => $ext,
                'Status_Verifikasi' => 'BELUM',
                'Waktu_Unggah' => $now,
                'Created_At' => $now,
                'Created_By' => $nama,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $nama,
                'Updated_By_Id' => $userId,
            ]);

            $jumlah++;
        }

        return $jumlah;
    }

    /**
     * Buang draf sebuah tahap berikut berkasnya di GCS.
     *
     * Dipanggil setelah formulir BERHASIL dikirim: menyisakan draf membuat
     * kandidat yang membuka halaman lagi melihat isian lama seolah belum
     * terkirim. Aman dipanggil walau draf tidak ada.
     */
    public static function bersihkan(int $tahapId, int $userId, bool $hapusBerkas = true): void
    {
        $baris = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->first();

        if (! $baris) {
            return;
        }

        foreach (($hapusBerkas ? (json_decode($baris->Berkas_Json ?: '{}', true) ?: []) : []) as $b) {
            if (! empty($b['path'])) {
                try {
                    Storage::disk(GcsBerkas::DISK)->delete($b['path']);
                } catch (\Throwable $e) {
                    Log::channel('web_career')->warning('[DRAF] sisa berkas gagal dihapus: ' . $e->getMessage());
                }
            }
        }

        DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Id_Formulir_Draf', $baris->Id_Formulir_Draf)
            ->delete();
    }
}
