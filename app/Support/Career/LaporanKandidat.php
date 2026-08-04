<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
                'foto' => self::fotoDataUri($profil['fotoPath'] ?? null),
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

        return $rows->map(function ($fp) use ($berkas) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];
            $berkasIni = collect($berkas->get($fp->Id_Formulir_Pengisian, []));

            return [
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'komponen' => $fp->Komponen_Kode,
                'waktuKirim' => (string) $fp->Waktu_Kirim,
                'isian' => collect($jawaban)->map(function ($v, $k) use ($berkasIni) {
                    $b = $berkasIni->firstWhere('Field_Key', $k);

                    return [
                        'key' => $k,
                        // Label mentah; pemanggil boleh menimpanya dengan label
                        // asli dari skema (lihat KamusLabelFormulir).
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (string) $v),
                        // Isian berupa berkas dicetak sebagai ADA/TIDAK, bukan
                        // nama file — nama berkas tidak berarti apa pun di
                        // atas kertas, dan berkasnya sendiri tidak ikut tercetak.
                        'berkas' => $b ? ['nama' => $b->Nama_Asli, 'status' => $b->Status_Verifikasi] : null,
                    ];
                })->values()->all(),
                'dokumen' => $berkasIni->map(fn ($b) => [
                    'field' => $b->Field_Key,
                    'nama' => $b->Nama_Asli,
                    'status' => $b->Status_Verifikasi,
                ])->values()->all(),
            ];
        })->values()->all();
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
