<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — pengumpul data LAPORAN KANDIDAT (read-only).
 *
 * Satu tempat yang merakit seluruh isi laporan: biodata, foto verifikasi,
 * perjalanan tahap, hasil tes, dan jawaban formulir. Dipakai PDF maupun Excel,
 * supaya keduanya mustahil menampilkan angka yang berbeda untuk orang yang
 * sama — kalau masing-masing merakit sendiri, satu perubahan aturan hanya akan
 * terpasang di salah satunya dan tak ada yang menyadarinya sampai ada yang
 * membandingkan dua berkas.
 *
 * TIDAK menyentuh sesi login: dipanggil dari dalam antrean, tempat tidak ada
 * pengguna yang sedang masuk.
 */
class LaporanKandidat
{
    /**
     * FORMULIR MANA YANG BOLEH DICETAK.
     *
     * Satu lamaran bisa punya beberapa pengisian: MT mengumpulkan data dua kali
     * (pendaftaran, lalu kelengkapan data diri di tahap berikutnya). Yang
     * terbaru dikembalikan lebih dulu dan ditandai `utama` — itulah yang paling
     * sering dimaksud "cetak datanya".
     *
     * Hanya yang SUDAH DIKIRIM yang masuk: draf belum tentu benar, dan mencetak
     * setengah jawaban sebagai dokumen resmi lebih buruk daripada tidak mencetak.
     */
    public static function daftarFormulir(int $lamaranId): array
    {
        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->whereNotNull('fp.Waktu_Kirim')
            ->orderByDesc('fp.Waktu_Kirim')
            ->select('fp.Id_Formulir_Pengisian', 'fp.Sumber', 'fp.Komponen_Kode', 'fp.Waktu_Kirim',
                't.Urutan as TahapUrutan', 't.Label as TahapLabel')
            ->get();

        return $rows->values()->map(fn ($r, $i) => [
            'id' => Hashids::encode($r->Id_Formulir_Pengisian),
            'label' => $r->TahapLabel ?: ($r->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
            'sumber' => $r->Sumber,
            'komponen' => $r->Komponen_Kode,
            'tahapUrutan' => (int) ($r->TahapUrutan ?? 0),
            'waktuKirim' => (string) $r->Waktu_Kirim,
            // Terbaru = pilihan bawaan. Ditandai di sini, bukan disimpulkan
            // layar dari urutan array — urutan gampang berubah tanpa sengaja.
            'utama' => $i === 0,
        ])->all();
    }

    /**
     * Rakit seluruh isi laporan.
     *
     * @param  int[]  $pengisianIds  formulir yang diminta; kosong = yang terbaru.
     */
    public static function rakit(int $lamaranId, array $pengisianIds = []): ?array
    {
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $lamaranId)
            ->select(
                'l.*',
                'u.Nama as UserNama', 'u.Email as UserEmail', 'u.No_Hp as UserHp',
                'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori',
                'x.Posisi', 'x.Level', 'x.Departemen', 'x.Lokasi',
            )
            ->first();

        if (! $lamaran) {
            return null;
        }

        $profil = LamaranService::dataKandidatEmail($lamaranId);

        return [
            'kandidat' => [
                'nama' => $lamaran->UserNama ?: $lamaran->Created_By,
                'email' => $lamaran->UserEmail,
                'hp' => $profil['hp'] ?: $lamaran->UserHp,
                'kodeLamaran' => $lamaran->Kode,
                'tglLahir' => $profil['tglLahir'],
                'jkel' => $profil['jkel'],
                'kampus' => $profil['kampus'],
                'tahunLulus' => $profil['tahunLulus'],
                // Rincian pendidikan — dipakai sidebar CV. Formulir MT tidak
                // menanyakan tahun lulus (hanya status & semester berjalan),
                // jadi keduanya ikut supaya kolom pendidikan tidak kosong
                // hanya karena pertanyaannya memang tak pernah diajukan.
                'jurusan' => $profil['jurusan'] ?? null,
                'jenjang' => $profil['jenjang'] ?? null,
                'ipk' => $profil['ipk'] ?? null,
                'statusStudi' => $profil['statusStudi'] ?? null,
                'semester' => $profil['semester'] ?? null,
                // NULL bila memang tidak ada — dan tata letaknya menyesuaikan.
                // Bingkai kosong berlabel "foto" pada dokumen yang dibaca
                // direksi terbaca seperti berkas yang gagal dimuat.
                'foto' => self::fotoDataUri(self::pathFotoKandidat($lamaranId, $profil['fotoPath'] ?? null)),
            ],
            'lamaran' => [
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->ProgramKategori,
                'posisi' => $lamaran->Posisi,
                'level' => $lamaran->Level,
                'departemen' => $lamaran->Departemen,
                'lokasi' => $lamaran->Lokasi,
                'status' => $lamaran->Status,
                'hasilAkhir' => $lamaran->Hasil_Akhir,
                'waktuLamar' => (string) $lamaran->Waktu_Lamar,
                'gugurDi' => $lamaran->Gugur_Di_Tahap,
                'alasanGugur' => $lamaran->Alasan_Gugur,
            ],
            'tahap' => self::tahap($lamaranId),
            'formulir' => self::formulir($lamaranId, $pengisianIds),
            'dicetak' => now()->format('d M Y H:i'),
        ];
    }

    /** Perjalanan tahap + hasil tiap aktivitasnya. */
    private static function tahap(int $lamaranId): array
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Urutan')
            ->get();

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahap->pluck('Id_Lamaran_Tahap')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        return $tahap->map(fn ($t) => [
            'urutan' => (int) $t->Urutan,
            'label' => $t->Label,
            'status' => $t->Status,
            'hasil' => $t->Hasil,
            'catatan' => $t->Catatan,
            'diputusAt' => (string) ($t->Diputus_At ?: ''),
            'diputusOleh' => $t->Diputus_By,
            'ditahan' => ($t->Hold_Flag ?? 'T') === 'Y',
            // Aktivitas INTERNAL tetap masuk: laporan ini dibaca TIM, bukan
            // kandidat. Justru background check & cek referensi yang paling
            // sering ditanyakan saat keputusan ditinjau ulang.
            'aktivitas' => collect($sub->get($t->Id_Lamaran_Tahap, []))->map(fn ($s) => [
                'label' => $s->Label,
                'peran' => $s->Peran,
                'status' => $s->Status,
                'hasil' => $s->Hasil,
                'nilai' => $s->Nilai !== null ? (float) $s->Nilai : null,
                'internal' => ($s->Tampil_Kandidat ?? 'Y') !== 'Y',
                'catatan' => $s->Catatan,
                'mcuStatus' => $s->Mcu_Status,
            ])->values()->all(),
        ])->values()->all();
    }

    /** Jawaban formulir terpilih (atau yang terbaru bila tak ditentukan). */
    private static function formulir(int $lamaranId, array $pengisianIds): array
    {
        $q = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->whereNotNull('fp.Waktu_Kirim')
            ->select('fp.*', 't.Urutan as TahapUrutan', 't.Label as TahapLabel');

        if ($pengisianIds) {
            // Dipilih admin → dicetak menurut KRONOLOGI, supaya jawaban lama
            // dan pembaruannya terbaca berurutan.
            $rows = $q->whereIn('fp.Id_Formulir_Pengisian', $pengisianIds)
                ->orderBy('fp.Waktu_Kirim')
                ->get();
        } else {
            // Tanpa pilihan: ambil YANG TERBARU saja. Mencetak semua secara
            // diam-diam membuat laporan satu kandidat membengkak jadi belasan
            // halaman berisi jawaban yang sudah ia perbarui sendiri.
            //
            // Hanya SATU orderBy di sini: SQL Server menolak kolom yang sama
            // muncul dua kali di ORDER BY, dan menambahkan urutan naik di bawah
            // (untuk kronologi) membuat kueri ini gagal total.
            $rows = $q->orderByDesc('fp.Waktu_Kirim')->limit(1)->get();
        }

        $berkas = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $rows->pluck('Id_Formulir_Pengisian')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Formulir_Pengisian_Id');

        return $rows->map(function ($fp) use ($berkas, $lamaranId) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];
            $berkasIni = collect($berkas->get($fp->Id_Formulir_Pengisian, []));
            // LABEL ASLI dari skema yang dibekukan saat formulir dikirim.
            $label = self::labelSkema($fp->Schema_Snapshot_Json ?? null);
            $urutan = array_flip(array_keys($label));

            return [
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'komponen' => $fp->Komponen_Kode,
                'waktuKirim' => (string) $fp->Waktu_Kirim,
                'isian' => collect($jawaban)
                    // URUTAN MENGIKUTI FORMULIR, bukan urutan kunci di JSON.
                    // Yang diisi kandidat berurut logis (identitas → alamat →
                    // kontak darurat); JSON menyimpannya sesuai urutan tulis,
                    // dan dokumen yang melompat-lompat memaksa pembaca mencari.
                    // Kunci yang tak ada di skema didorong ke belakang.
                    ->sortBy(fn ($v, $k) => $urutan[$k] ?? 9999)
                    ->map(function ($v, $k) use ($berkasIni, $label, $lamaranId) {
                    $b = $berkasIni->firstWhere('Field_Key', $k);

                    return [
                        'key' => $k,
                        // Label ASLI dari skema formulir; hanya bila kuncinya
                        // tidak ada di sana barulah namanya dirapikan sendiri.
                        // Tanpa ini dokumen resmi berbunyi "V Nama", "Nik",
                        // "Alamat Ktp" — pertanyaan yang di layar berbunyi utuh
                        // dan benar tiba-tiba jadi singkatan di atas kertas.
                        'label' => $label[$k] ?? ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => self::nilaiTeks($v),
                        // Isian berupa berkas dicetak sebagai ADA/TIDAK, bukan
                        // nama file — nama berkas tidak berarti apa pun di
                        // atas kertas, dan berkasnya sendiri tidak ikut tercetak.
                        'berkas' => $b ? [
                            'nama' => $b->Nama_Asli,
                            'status' => $b->Status_Verifikasi,
                            'tautan' => self::tautanBerkas($lamaranId, (int) $b->Id_Formulir_Berkas),
                        ] : null,
                    ];
                })->values()->all(),
                'dokumen' => $berkasIni->map(fn ($b) => [
                    'field' => $b->Field_Key,
                    'nama' => $b->Nama_Asli,
                    'status' => $b->Status_Verifikasi,
                    // Bisa diklik langsung dari dalam PDF — lihat tautanBerkas().
                    'tautan' => self::tautanBerkas($lamaranId, (int) $b->Id_Formulir_Berkas),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Tautan dokumen yang bisa diklik DARI DALAM PDF.
     *
     * Bertanda tangan (HMAC dari APP_KEY) dan berumur, bukan rute admin biasa:
     * pembaca PDF tidak membawa cookie sesi, jadi tautan ke rute admin akan
     * selalu mendarat di halaman login — tautan yang pasti gagal lebih buruk
     * daripada tidak ada tautan sama sekali.
     *
     * UMURNYA TERBATAS, dan itu disengaja. Laporan ini memuat data pribadi dan
     * kerap diteruskan lewat surel; tautan yang berlaku selamanya berarti
     * salinan PDF lama tetap membuka dokumen kandidat bertahun-tahun kemudian.
     * 30 hari cukup untuk satu putaran seleksi, sesudahnya laporan tinggal
     * dicetak ulang.
     *
     * Dibangun di ANTREAN, tempat tidak ada permintaan HTTP — jadi alamat
     * dasarnya diambil dari APP_URL. Bila APP_URL salah, tautannya menunjuk ke
     * host yang keliru; itu satu-satunya setelan yang harus benar di produksi.
     */
    private static function tautanBerkas(int $lamaranId, int $berkasId): ?string
    {
        if (! $berkasId) {
            return null;
        }

        try {
            return URL::temporarySignedRoute(
                'career.laporan.berkas',
                now()->addDays(30),
                ['lamaran' => Hashids::encode($lamaranId), 'berkas' => Hashids::encode($berkasId)],
            );
        } catch (\Throwable $e) {
            // Laporan tanpa tautan masih berguna; laporan yang gagal terbit tidak.
            Log::channel('web_career')->warning('[LAPORAN] tautan berkas gagal dibuat: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Peta `key => label` dari skema formulir yang dibekukan saat dikirim.
     *
     * Snapshot, BUKAN skema yang berlaku sekarang: pertanyaan bisa diganti
     * namanya setelah kandidat menjawab, dan mencetak jawaban lama di bawah
     * pertanyaan baru adalah cara paling halus menyampaikan hal yang keliru.
     *
     * Urutan kemunculannya ikut terjaga — dipakai mengurutkan isian di dokumen
     * sesuai urutan formulirnya.
     */
    private static function labelSkema(?string $json): array
    {
        $skema = json_decode($json ?: '', true);
        if (! is_array($skema)) {
            return [];
        }

        $peta = [];
        foreach ($skema['langkah'] ?? [] as $langkah) {
            foreach ($langkah['bagian'] ?? [] as $bagian) {
                foreach ($bagian['field'] ?? [] as $f) {
                    if (! empty($f['key']) && ! empty($f['label'])) {
                        $peta[$f['key']] = $f['label'];
                    }
                }
            }
        }

        return $peta;
    }

    /**
     * Jawaban formulir → satu untai teks yang layak dicetak.
     *
     * ISIAN BERULANG BERISI ARRAY OF OBJECT. Pengalaman kerja, riwayat
     * pendidikan, dan daftar keahlian tersimpan sebagai
     * `[{posisi: …, perusahaan: …}, …]`. `implode()` di atasnya melempar
     * "Array to string conversion" — peringatan belaka di PHP, sehingga yang
     * tercetak bukan galat melainkan kata "Array" di tengah dokumen resmi yang
     * dibaca direksi. Persis kelas kekeliruan yang paling lama tak ketahuan.
     *
     * Bentuk cetaknya: tiap baris jadi satu kalimat "label: isi", dipisah titik
     * koma. Kedalamannya dibatasi — struktur yang lebih dalam dari itu hampir
     * pasti data rusak, dan menelusurinya terus hanya menghasilkan paragraf
     * yang tak seorang pun baca.
     */
    private static function nilaiTeks(mixed $v, int $dalam = 0): string
    {
        if (is_bool($v)) {
            return $v ? 'Ya' : 'Tidak';
        }

        if (! is_array($v)) {
            return trim((string) $v);
        }

        if ($dalam >= 3) {
            return '(data bersarang)';
        }

        $bagian = [];
        foreach ($v as $k => $isi) {
            $teks = self::nilaiTeks($isi, $dalam + 1);
            if ($teks === '') {
                continue;
            }
            // Kunci numerik tidak disebut: "1: Jakarta, 2: Bandung" hanya
            // menambah angka yang tidak berarti apa-apa bagi pembaca.
            $bagian[] = is_int($k) ? $teks : ucwords(str_replace(['_', '-'], ' ', (string) $k)) . ': ' . $teks;
        }

        return implode($dalam === 0 ? '; ' : ', ', $bagian);
    }

    /**
     * FOTO MANA YANG DIPAKAI DOKUMEN — menurut urutan di master.
     *
     *   1. PAS FOTO dari formulir. Foto resmi, memang disiapkan untuk dokumen.
     *   2. FOTO VERIFIKASI. Selalu diminta sejak awal apply, jadi hampir pasti
     *      terisi — tapi tujuannya memastikan orangnya, bukan dipandang, dan
     *      kebanyakan diambil seadanya dengan kamera depan.
     *   3. Tidak ada. Dikembalikan null, dan tata letaknya menyesuaikan.
     *
     * Daftar kuncinya DARI MASTER (Kode='FOTO'), bukan ditulis di sini:
     * formulir dirancang lewat layar, jadi kunci baru bisa lahir kapan saja —
     * dan kode yang lupa disunting akan diam-diam kembali memakai foto
     * verifikasi tanpa satu pun galat.
     *
     * @param  ?string  $cadangan  path foto verifikasi yang sudah ditemukan pemanggil
     */
    private static function pathFotoKandidat(int $lamaranId, ?string $cadangan): ?string
    {
        $kunci = DB::table('N_WEB_CAREERS_Master_Kunci_Identitas')
            ->where('Kode', 'FOTO')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->pluck('Field_Key')->all();

        if (! $kunci) {
            return $cadangan;
        }

        // Seluruh berkas foto milik lamaran ini, sekali kueri. Pengisian
        // TERBARU didahulukan: kandidat yang memperbarui pas fotonya di tahap
        // lanjutan berarti yang lama sudah tidak ia akui.
        $berkas = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->whereIn('fb.Field_Key', $kunci)
            ->whereNotNull('fb.Path_File')
            ->orderByDesc('fp.Waktu_Kirim')
            ->select('fb.Field_Key', 'fb.Path_File')
            ->get();

        foreach ($kunci as $k) {
            $path = $berkas->firstWhere('Field_Key', $k)->Path_File ?? null;
            if ($path) {
                return $path;
            }
        }

        return $cadangan;
    }

    /**
     * Foto verifikasi → data URI, supaya IKUT TERCETAK.
     *
     * dompdf mengambil gambar jarak jauh lewat permintaan HTTP-nya sendiri, dan
     * berkas kita ada di bucket berwenang — permintaan itu akan ditolak, lalu
     * fotonya hilang tanpa galat apa pun. Ditanam sebagai data URI supaya
     * dokumennya berdiri sendiri.
     *
     * Kegagalan mengembalikan null, bukan melempar: laporan tanpa foto masih
     * berguna, laporan yang gagal terbit tidak.
     */
    private static function fotoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if (! $disk->exists($path)) {
                return null;
            }

            $isi = $disk->get($path);
            // Batas aman: foto verifikasi seharusnya beberapa ratus KB. Yang
            // jauh lebih besar hampir pasti salah unggah, dan menanamnya
            // sebagai base64 membuat PDF-nya membengkak & gagal dirender.
            if (strlen($isi) > 3 * 1024 * 1024) {
                return null;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');

            return 'data:' . $mime . ';base64,' . base64_encode($isi);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] foto verifikasi gagal dimuat: ' . $e->getMessage());

            return null;
        }
    }

    /** Logo perusahaan sebagai data URI — alasan sama dengan foto di atas. */
    public static function logoDataUri(): ?string
    {
        $file = public_path('logo/EVOGROUP.png');

        return is_file($file)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($file))
            : null;
    }
}
