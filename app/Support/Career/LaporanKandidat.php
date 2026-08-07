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
                // NAMA RESMI dari formulir lebih dulu, nama akun cadangan —
                // aturan yang sama dengan kartu worklist. Nama akun diketik
                // saat mendaftar dan kerap seadanya; dokumen resmi yang menyebut
                // kandidat dengan nama itu tidak cocok dengan KTP-nya sendiri.
                'nama' => self::namaResmi($lamaranId) ?: ($lamaran->UserNama ?: $lamaran->Created_By),
                'email' => $lamaran->UserEmail,
                'hp' => $profil['hp'] ?: $lamaran->UserHp,
                'kodeLamaran' => $lamaran->Kode,
                'nik' => $profil['nik'] ?? null,
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
                'foto' => self::fotoKandidat($lamaranId, $profil['fotoPath'] ?? null),
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

    /**
     * Nama resmi kandidat menurut FORMULIR.
     *
     * Pengisian dibaca urut dari yang PALING AWAL: formulir pendaftaran diisi
     * lebih dulu dan itulah yang memuat identitas; formulir tahap lanjutan
     * umumnya tidak menanyakan nama lagi.
     */
    private static function namaResmi(int $lamaranId): ?string
    {
        $adaSnapshot = FormulirSchema::punyaKolomPengisianSnapshot();

        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Id_Formulir_Pengisian')
            ->get(array_merge(
                ['Jawaban_Json'],
                $adaSnapshot ? ['Schema_Snapshot_Json'] : [],
            ));

        foreach ($rows as $fp) {
            $nama = IdentitasKandidat::nama(
                json_decode($fp->Jawaban_Json ?: '{}', true) ?: [],
                $fp->Schema_Snapshot_Json ?? null,
            );
            if ($nama) {
                return $nama;
            }
        }

        return null;
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
            // LABEL, TIPE, dan PEMBABAKAN asli dari skema yang dibekukan saat
            // formulir dikirim.
            $peta = self::petaSkema($fp->Schema_Snapshot_Json ?? null);
            $label = $peta['label'];
            $urutan = array_flip(array_keys($label));

            $isian = collect($jawaban)
                // URUTAN MENGIKUTI FORMULIR, bukan urutan kunci di JSON.
                // Yang diisi kandidat berurut logis (identitas → alamat →
                // kontak darurat); JSON menyimpannya sesuai urutan tulis,
                // dan dokumen yang melompat-lompat memaksa pembaca mencari.
                // Kunci yang tak ada di skema didorong ke belakang.
                ->sortBy(fn ($v, $k) => $urutan[$k] ?? 9999)
                ->map(function ($v, $k) use ($berkasIni, $label, $peta, $lamaranId) {
                    $b = $berkasIni->firstWhere('Field_Key', $k);

                    return [
                        'key' => $k,
                        // Label ASLI dari skema formulir; hanya bila kuncinya
                        // tidak ada di sana barulah namanya dirapikan sendiri.
                        // Tanpa ini dokumen resmi berbunyi "V Nama", "Nik",
                        // "Alamat Ktp" — pertanyaan yang di layar berbunyi utuh
                        // dan benar tiba-tiba jadi singkatan di atas kertas.
                        'label' => $label[$k] ?? ucwords(str_replace(['_', '-'], ' ', $k)),
                        // Tipe field menentukan BENTUK CETAKNYA: persetujuan
                        // jadi baris bercentang, isian panjang jadi baris penuh,
                        // sisanya masuk kisi dua kolom. Tanpa ini semuanya
                        // tercetak sebagai satu daftar label-nilai yang rata.
                        'tipe' => $peta['tipe'][$k] ?? null,
                        'nilai' => self::nilaiTeks($v),
                        // Isian BERULANG (pengalaman kerja, riwayat sertifikasi)
                        // disimpan sebagai array of objek. Barisnya dipertahankan
                        // supaya bisa dicetak sebagai kartu bernomor, bukan satu
                        // paragraf panjang bertitik koma.
                        'baris' => self::baris($v, $label),
                        // Isian berupa berkas dicetak sebagai ADA/TIDAK, bukan
                        // nama file — nama berkas tidak berarti apa pun di
                        // atas kertas, dan berkasnya sendiri tidak ikut tercetak.
                        'berkas' => $b ? [
                            'nama' => $b->Nama_Asli,
                            'status' => $b->Status_Verifikasi,
                            'tautan' => self::tautanBerkas($lamaranId, (int) $b->Id_Formulir_Berkas),
                        ] : null,
                    ];
                })->values()->all();

            return [
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'komponen' => $fp->Komponen_Kode,
                'waktuKirim' => (string) $fp->Waktu_Kirim,
                // Datar — dipakai ekspor Excel, yang memang ingin satu baris
                // satu fakta tanpa pembabakan.
                'isian' => $isian,
                // Berbabak — dipakai PDF. Judulnya diambil dari BAGIAN formulir
                // itu sendiri, jadi dokumen ikut berubah begitu perancang
                // menambah atau menamai ulang sebuah bagian.
                'bagian' => self::bagi($isian, $peta['bagian']),
                'dokumen' => $berkasIni->map(fn ($b) => [
                    'field' => $b->Field_Key,
                    // LABEL ASLI pertanyaannya, bukan kunci yang dirapikan.
                    // Tanpa ini tabel dokumen berbunyi "Dok Kk", "Dok Ktp" —
                    // singkatan internal yang tak pernah dilihat kandidat,
                    // sementara pertanyaannya di layar berbunyi "Kartu Keluarga".
                    'label' => $label[$b->Field_Key] ?? ucwords(str_replace(['_', '-'], ' ', (string) $b->Field_Key)),
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
     * Label, tipe, dan PEMBABAKAN dari skema yang dibekukan saat dikirim.
     *
     * Snapshot, BUKAN skema yang berlaku sekarang: pertanyaan bisa diganti
     * namanya setelah kandidat menjawab, dan mencetak jawaban lama di bawah
     * pertanyaan baru adalah cara paling halus menyampaikan hal yang keliru.
     *
     * Urutan kemunculannya ikut terjaga — dipakai mengurutkan isian di dokumen
     * sesuai urutan formulirnya.
     *
     * BAGIAN ikut dibaca karena judulnya ("Data Pribadi & Kontak", "Alamat",
     * "Kontak Darurat") adalah satu-satunya sumber pembabakan yang benar. Kalau
     * dokumen ini memakai daftar judul sendiri, bagian yang baru ditambahkan
     * lewat perancang formulir akan menumpuk di bagian "lain-lain" selamanya.
     *
     * @return array{label: array<string,string>, tipe: array<string,string>, bagian: list<array{judul:string,berulang:bool,keys:list<string>}>}
     */
    private static function petaSkema(?string $json): array
    {
        $peta = ['label' => [], 'tipe' => [], 'bagian' => []];

        $skema = json_decode($json ?: '', true);
        if (! is_array($skema)) {
            return $peta;
        }

        foreach ($skema['langkah'] ?? [] as $langkah) {
            foreach ($langkah['bagian'] ?? [] as $bagian) {
                $keys = [];

                if (! empty($bagian['berulang'])) {
                    // Seluruh isi bagian berulang tersimpan di bawah SATU kunci
                    // penampung berisi array baris — sama seperti yang dipakai
                    // formulir di layar (lihat kunciBagian() di aturan.js).
                    $kunci = (string) ($bagian['key'] ?? '');
                    if ($kunci === '') {
                        $kunci = trim(preg_replace('/[^a-z0-9]+/', '_',
                            mb_strtolower((string) ($bagian['judul'] ?? 'bagian'))) ?: '', '_') ?: 'bagian';
                    }
                    $keys[] = $kunci;
                    $peta['label'][$kunci] ??= trim((string) ($bagian['judul'] ?? $kunci));

                    // Label kolom di dalam baris — dipakai menamai isi kartu.
                    // `??=` supaya tidak menimpa field tingkat atas bernama sama.
                    foreach ($bagian['field'] ?? [] as $f) {
                        if (! empty($f['key']) && ! empty($f['label'])) {
                            $peta['label'][$f['key']] ??= $f['label'];
                        }
                    }
                } else {
                    foreach ($bagian['field'] ?? [] as $f) {
                        if (empty($f['key'])) {
                            continue;
                        }
                        $keys[] = $f['key'];
                        if (! empty($f['label'])) {
                            $peta['label'][$f['key']] = $f['label'];
                        }
                        if (! empty($f['tipe'])) {
                            $peta['tipe'][$f['key']] = mb_strtolower((string) $f['tipe']);
                        }
                    }
                }

                if ($keys) {
                    $peta['bagian'][] = [
                        'judul' => trim((string) ($bagian['judul'] ?? '')),
                        'berulang' => ! empty($bagian['berulang']),
                        'keys' => $keys,
                    ];
                }
            }
        }

        return $peta;
    }

    /**
     * Isian → dikelompokkan mengikuti bagian formulirnya.
     *
     * Kunci yang tak dikenali skema TIDAK dibuang, melainkan jatuh ke satu
     * bagian tak berjudul di paling belakang. Jawaban yang hilang diam-diam
     * dari dokumen resmi jauh lebih berbahaya daripada jawaban yang tampil
     * tanpa judul bagian — yang pertama tak seorang pun sadari.
     *
     * @param  list<array>  $isian
     * @param  list<array{judul:string,berulang:bool,keys:list<string>}>  $bagian
     */
    private static function bagi(array $isian, array $bagian): array
    {
        $sisa = collect($isian)->keyBy('key');
        $hasil = [];

        foreach ($bagian as $b) {
            $ambil = [];
            foreach ($b['keys'] as $k) {
                if ($sisa->has($k)) {
                    $ambil[] = $sisa->get($k);
                    $sisa->forget($k);
                }
            }
            // Bagian yang seluruh pertanyaannya tidak terjawab (mis. tertutup
            // syarat "tampil_jika") tidak dicetak sebagai judul kosong.
            if ($ambil) {
                $hasil[] = ['judul' => $b['judul'], 'berulang' => $b['berulang'], 'isian' => $ambil];
            }
        }

        if ($sisa->isNotEmpty()) {
            $hasil[] = ['judul' => '', 'berulang' => false, 'isian' => $sisa->values()->all()];
        }

        return $hasil;
    }

    /**
     * Jawaban bagian berulang → daftar baris siap cetak.
     *
     * Hanya array-of-objek yang diakui; daftar biasa (jawaban checkbox) bukan
     * baris dan tetap dicetak sebagai satu untai teks.
     *
     * @return ?list<list<array{label:string,nilai:string}>>
     */
    private static function baris(mixed $v, array $label): ?array
    {
        if (! is_array($v) || ! $v || ! array_is_list($v)) {
            return null;
        }

        $baris = [];
        foreach ($v as $r) {
            if (! is_array($r) || array_is_list($r)) {
                return null;
            }

            $isi = [];
            foreach ($r as $k => $nilai) {
                $teks = self::nilaiTeks($nilai, 1);
                if ($teks === '') {
                    continue;
                }
                $isi[] = [
                    'label' => $label[$k] ?? ucwords(str_replace(['_', '-'], ' ', (string) $k)),
                    'nilai' => $teks,
                ];
            }

            if ($isi) {
                $baris[] = $isi;
            }
        }

        return $baris ?: null;
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
     * FOTO MANA YANG DIPAKAI DOKUMEN.
     *
     * Formulirnya DIRANCANG LEWAT LAYAR: kunci baru bisa lahir kapan saja
     * tanpa seorang pun menyunting kode ini. Karena itu tidak satu pun nama
     * kolom ditulis mati di sini — semuanya ditemukan dengan menelusuri apa
     * yang benar-benar tersimpan. Urutannya, dari yang paling layak dicetak:
     *
     *   1. MASTER (Kode='FOTO') — daftar kunci yang memang diakui admin.
     *   2. SKEMA yang dibekukan saat formulir dikirim — field bertipe `foto`
     *      lebih dulu, lalu field `file` yang pertanyaannya memang meminta
     *      pas foto. Ini yang membuat field pas foto BARU langsung terpakai.
     *   3. Berkas GAMBAR mana pun yang kuncinya berbunyi foto (bukan verifikasi).
     *   4. FOTO VERIFIKASI. Hampir pasti ada karena diminta sejak awal apply —
     *      tapi tujuannya memastikan orangnya, bukan dipandang, dan kebanyakan
     *      diambil seadanya dengan kamera depan. Karena itu paling belakang.
     *   5. Tidak ada → null, dan tata letaknya menyesuaikan (tanpa bingkai kosong).
     *
     * Di tiap langkah, TABEL BERKAS dicari lebih dulu lalu JAWABAN_JSON:
     * sebagian formulir menyimpan path — atau bahkan data URI hasil jepretan
     * kamera — di dalam jawaban, tidak pernah singgah di tabel berkas.
     *
     * @param  ?string  $cadangan  path foto verifikasi yang sudah ditemukan pemanggil
     */
    private static function fotoKandidat(int $lamaranId, ?string $cadangan): ?string
    {
        // DICOBA SATU PER SATU SAMPAI ADA YANG BENAR-BENAR TERBACA.
        //
        // Baris di tabel berkas bukan jaminan berkasnya masih ada: di data
        // sekarang pun ada pas foto yang barisnya lengkap tapi objeknya sudah
        // tidak ada di bucket. Kalau yang seperti itu langsung dianggap "foto
        // kandidat", dokumennya terbit tanpa foto padahal foto verifikasinya
        // masih utuh — dan tak ada galat yang memberi tahu siapa pun.
        foreach (self::calonFoto($lamaranId, $cadangan) as $path) {
            if ($uri = self::fotoDataUri($path)) {
                return $uri;
            }
        }

        return null;
    }

    /**
     * Seluruh calon foto, terurut dari yang paling layak dicetak.
     *
     * @return list<string>
     */
    private static function calonFoto(int $lamaranId, ?string $cadangan): array
    {
        // Pengisian TERBARU didahulukan di seluruh langkah: kandidat yang
        // memperbarui pas fotonya di tahap lanjutan berarti yang lama sudah
        // tidak ia akui. Draf tidak disaring di sini — foto yang sudah
        // terunggah tetap fotonya, sekalipun formulirnya belum dikirim ulang.
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderByDesc('Waktu_Kirim')
            ->orderByDesc('Id_Formulir_Pengisian')
            ->get(['Id_Formulir_Pengisian', 'Jawaban_Json', 'Schema_Snapshot_Json']);

        if ($pengisian->isEmpty()) {
            return array_values(array_filter([$cadangan]));
        }

        $ids = $pengisian->pluck('Id_Formulir_Pengisian')->all();
        $urut = array_flip($ids);

        // HANYA BERKAS GAMBAR. Tanpa saringan ini, formulir yang menamai kunci
        // ijazahnya "foto_ijazah" akan menaruh PDF di dalam bingkai pas foto —
        // dompdf memuatnya sebagai gambar rusak, bukan sebagai galat, jadi yang
        // sampai ke pembaca adalah kotak kosong tanpa ada yang tahu kenapa.
        $berkas = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $ids)
            ->whereNotNull('Path_File')
            ->get(['Formulir_Pengisian_Id', 'Field_Key', 'Path_File', 'Mime', 'Ekstensi'])
            ->filter(fn ($b) => self::berkasGambar($b))
            ->sortBy(fn ($b) => $urut[$b->Formulir_Pengisian_Id] ?? 9999)
            ->values();

        $calon = [];
        $kumpulkan = function (array $kunci) use ($berkas, $pengisian, &$calon) {
            foreach ($kunci as $k) {
                if ($path = $berkas->firstWhere('Field_Key', $k)->Path_File ?? null) {
                    $calon[] = $path;
                }
                foreach ($pengisian as $p) {
                    $jawaban = json_decode($p->Jawaban_Json ?: '{}', true) ?: [];
                    if ($nilai = self::nilaiGambar($jawaban[$k] ?? null)) {
                        $calon[] = $nilai;
                    }
                }
            }
        };

        $kumpulkan(DB::table('N_WEB_CAREERS_Master_Kunci_Identitas')
            ->where('Kode', 'FOTO')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->pluck('Field_Key')->all());

        $kumpulkan(self::kunciFotoSkema($pengisian));

        foreach ($berkas as $b) {
            if (self::berbunyiPasFoto((string) $b->Field_Key)) {
                $calon[] = $b->Path_File;
            }
        }

        if ($cadangan) {
            $calon[] = $cadangan;
        }

        return array_values(array_unique($calon));
    }

    /**
     * Kunci pas foto MENURUT SKEMA yang dibekukan saat formulir dikirim.
     *
     * Tiga lapis, dan URUTANNYA YANG PENTING:
     *
     *   1. field `foto` yang BUKAN foto verifikasi — pas foto sungguhan.
     *   2. field `file` yang pertanyaannya berbunyi pas foto, sebab sebagian
     *      formulir memakai unggahan berkas biasa, bukan tipe khusus.
     *   3. field `foto` yang justru foto verifikasi.
     *
     * Lapis ketiga terpisah karena foto verifikasi di sistem ini DIAMBIL LEWAT
     * KAMERA, jadi ia juga bertipe `foto`. Bila ketiganya disatukan, formulir
     * yang menanyakan foto verifikasi lebih dulu (dan itu urutan yang wajar,
     * ia diminta sejak awal apply) akan membuat swafoto seadanya menang atas
     * pas foto resmi — tanpa satu pun galat, dan hanya ketahuan kalau ada yang
     * membandingkan dokumennya dengan formulir aslinya.
     *
     * Dibaca dari SNAPSHOT, bukan skema yang berlaku sekarang: field foto bisa
     * saja sudah dihapus dari formulir, sementara fotonya masih tersimpan.
     */
    private static function kunciFotoSkema(\Illuminate\Support\Collection $pengisian): array
    {
        $pas = [];
        $file = [];
        $verifikasi = [];

        foreach ($pengisian as $p) {
            $skema = json_decode($p->Schema_Snapshot_Json ?: '', true);
            if (! is_array($skema)) {
                continue;
            }

            foreach ($skema['langkah'] ?? [] as $langkah) {
                foreach ($langkah['bagian'] ?? [] as $bagian) {
                    foreach ($bagian['field'] ?? [] as $f) {
                        $key = (string) ($f['key'] ?? '');
                        if ($key === '') {
                            continue;
                        }

                        $tipe = strtolower((string) ($f['tipe'] ?? ''));
                        $sebutan = $key . ' ' . ($f['label'] ?? '');

                        if ($tipe === 'foto') {
                            self::berbunyiPasFoto($sebutan) ? $pas[] = $key : $verifikasi[] = $key;
                        } elseif ($tipe === 'file' && self::berbunyiPasFoto($sebutan)) {
                            $file[] = $key;
                        }
                    }
                }
            }
        }

        return array_values(array_unique([...$pas, ...$file, ...$verifikasi]));
    }

    /**
     * Berkas ini gambar atau bukan.
     *
     * `Mime` yang menentukan bila terisi — nama berkas datang dari kandidat dan
     * bisa berbunyi apa saja. Ekstensi hanya dipakai untuk baris lama yang
     * ditulis sebelum kolom Mime ada.
     */
    private static function berkasGambar(object $b): bool
    {
        $mime = strtolower(trim((string) ($b->Mime ?? '')));
        if ($mime !== '') {
            return str_starts_with($mime, 'image/');
        }

        $ext = strtolower((string) ($b->Ekstensi ?: pathinfo((string) $b->Path_File, PATHINFO_EXTENSION)));

        return in_array(ltrim($ext, '.'), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /** Pertanyaan/kunci ini meminta PAS FOTO — dan bukan foto verifikasi. */
    private static function berbunyiPasFoto(string $teks): bool
    {
        $t = mb_strtolower($teks);

        // Foto verifikasi punya jalurnya sendiri di paling belakang; kalau ia
        // ikut tertangkap di sini, ia akan menang atas pas foto yang sebenarnya.
        if (str_contains($t, 'verif') || str_contains($t, 'selfie') || str_contains($t, 'swafoto')) {
            return false;
        }

        return str_contains($t, 'foto') || str_contains($t, 'photo');
    }

    /** Nilai jawaban yang berupa gambar: data URI tertanam, atau path berkas. */
    private static function nilaiGambar(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }

        $v = trim($v);
        if ($v === '') {
            return null;
        }

        return str_starts_with($v, 'data:image/') || preg_match('/\.(jpe?g|png|webp)$/i', $v)
            ? $v
            : null;
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

        // Sudah tertanam sejak dari jawaban formulir (jepretan kamera). Tidak
        // ada yang perlu diambil dari GCS — batas ukurannya tetap ditegakkan,
        // sebab base64 raksasa membuat PDF-nya gagal dirender. Tetap dipotong
        // bulat seperti yang dari GCS: dua sumber yang sama-sama foto kandidat
        // tidak boleh tampil dengan bentuk yang berbeda.
        if (str_starts_with($path, 'data:image/')) {
            if (strlen($path) > 4 * 1024 * 1024) {
                return null;
            }

            $bytes = base64_decode(explode(',', $path, 2)[1] ?? '', true);

            return ($bytes !== false && $bytes !== '' ? self::bulatkan($bytes) : null) ?: $path;
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

            if ($bulat = self::bulatkan($isi)) {
                return $bulat;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');

            return 'data:' . $mime . ';base64,' . base64_encode($isi);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] foto verifikasi gagal dimuat: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Foto → PNG BULAT dengan latar tembus pandang, dipotong tengah.
     *
     * Rancangannya memakai foto lingkaran ber-`fit: cover`. dompdf tidak
     * memotong gambar mengikuti border-radius — yang keluar tetap persegi —
     * jadi pemotongannya dikerjakan di sini, bukan diserahkan ke CSS.
     * Sekaligus menyelesaikan `cover`: potret dan lanskap sama-sama dipotong
     * dari TENGAH, bukan dipenyet jadi persegi.
     *
     * Ukurannya dipatok 236 px — dua kali ukuran cetak (118 px) supaya tetap
     * tajam saat dokumen dicetak, tanpa menanam foto 1,4 MB apa adanya.
     *
     * Mengembalikan null bila GD tidak ada atau gambarnya tak terbaca; pemanggil
     * lalu memakai gambar aslinya. Foto persegi masih jauh lebih baik daripada
     * dokumen yang gagal terbit.
     */
    private static function bulatkan(string $isi): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $asli = @imagecreatefromstring($isi);
            if (! $asli) {
                return null;
            }

            $lw = imagesx($asli);
            $lt = imagesy($asli);
            $sisi = min($lw, $lt);
            // Potong dari tengah secara mendatar, tapi agak ke ATAS secara
            // tegak: pada pas foto, wajah ada di sepertiga atas — memotong
            // tepat di tengah kerap memenggal dahi.
            $x = (int) (($lw - $sisi) / 2);
            $y = (int) min(max(0, ($lt - $sisi) / 4), $lt - $sisi);

            $n = 236;
            $keluar = imagecreatetruecolor($n, $n);
            imagealphablending($keluar, false);
            imagesavealpha($keluar, true);
            imagefilledrectangle($keluar, 0, 0, $n, $n, imagecolorallocatealpha($keluar, 0, 0, 0, 127));
            imagealphablending($keluar, true);
            imagecopyresampled($keluar, $asli, 0, 0, $x, $y, $n, $n, $sisi, $sisi);
            imagedestroy($asli);

            // Sudut di luar lingkaran dikembalikan jadi tembus pandang. Digambar
            // per baris memakai persamaan lingkaran — imageellipse tidak bisa
            // "menghapus" ke alfa, dan imageantialias tidak berlaku pada alfa.
            imagealphablending($keluar, false);
            $bening = imagecolorallocatealpha($keluar, 0, 0, 0, 127);
            $r = $n / 2;
            for ($by = 0; $by < $n; $by++) {
                $dy = $by + 0.5 - $r;
                $lebar = sqrt(max(0, $r * $r - $dy * $dy));
                $kiri = (int) floor($r - $lebar);
                $kanan = (int) ceil($r + $lebar);
                if ($kiri > 0) {
                    imagefilledrectangle($keluar, 0, $by, $kiri - 1, $by, $bening);
                }
                if ($kanan < $n) {
                    imagefilledrectangle($keluar, $kanan, $by, $n - 1, $by, $bening);
                }
            }
            imagesavealpha($keluar, true);

            ob_start();
            imagepng($keluar, null, 8);
            $png = (string) ob_get_clean();
            imagedestroy($keluar);

            return $png !== '' ? 'data:image/png;base64,' . base64_encode($png) : null;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] foto gagal dibulatkan: ' . $e->getMessage());

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
