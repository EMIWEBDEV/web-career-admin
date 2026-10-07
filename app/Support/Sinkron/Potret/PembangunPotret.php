<?php

namespace App\Support\Sinkron\Potret;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Support\Career\BatasIsi;
use App\Support\Career\BiayaAktivitas;
use App\Support\Career\FormulirSchema;
use App\Support\Career\JadwalPrivat;
use App\Support\Career\KonfirmasiJadwal;
use App\Support\Career\LamaranService;
use App\Support\Career\SuratJadwal;
use App\Support\Career\UndanganJadwal;
use App\Support\Sinkron\Keluar;
use App\Support\Sinkron\PetaId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * PEMBANGUN POTRET PORTAL — keadaan satu lamaran sebagaimana dilihat
 * kandidatnya, untuk N_WEB_CAREERS_Pub_Portal_Lamaran (kontrak 1, dibaca
 * App\Support\Portal\Potret di project pengguna).
 *
 * Isinya pindahan portal kandidat yang dulu hidup di panel ini (portalDetail,
 * lamaranSaya, halaman konfirmasi, feedback), dengan aturan kerahasiaan yang
 * sama: hasil tahap baru keluar sesudah boleh diumumkan, nilai & catatan
 * penilai tidak pernah keluar, aktivitas internal disaring.
 *
 * WAKTU. Potret didorong saat datanya berubah, bukan saat jam berganti.
 * Turunan waktu tetap dihitung SEKARANG (berubah hanya di batasnya, dan
 * penyegar bergiliran menangkapnya), sedangkan yang berubah TERUS — hitung
 * mundur, tanggal usulan — dibuang. Di sampingnya ditaruh PENANDA waktu
 * mentah yang dihitung ulang situs kandidat setiap kali dibaca
 * (PenilaiWaktu): _jendela, _lewatSetelah, _tutupSetelah, _konfirmasi.
 *
 * DOKUMEN. Berkas yang dirujuk potret (berkas hasil tahap, surat pengantar)
 * didorong sebagai Dokumen.Tersedia ke bucket publik; yang tidak lagi
 * dirujuk dinonaktifkan.
 */
final class PembangunPotret
{
    public const KONTRAK = 1;

    /** @var array<int, ?object> */
    private array $tipe = [];

    /**
     * Bangun & antrekan potret satu lamaran bila berubah.
     *
     * @return bool true bila versi baru diantrekan
     */
    public static function segarkan(int $lamaranId): bool
    {
        $b = (new self)->bangun($lamaranId);
        if (! $b) {
            return false;
        }

        return DB::transaction(function () use ($b) {
            $lama = Keluar::terakhir(Keluar::PORTAL, $b['kode']);
            $versi = Keluar::antrekan(Keluar::PORTAL, $b['kode'], $b['muatan']);

            foreach ($b['dokumen'] as $kunci => $d) {
                Keluar::antrekan(Keluar::DOKUMEN, $kunci, $d);
            }
            foreach (array_diff((array) ($lama['dokumen'] ?? []), array_keys($b['dokumen'])) as $kunci) {
                Keluar::antrekan(Keluar::DOKUMEN, (string) $kunci, [
                    'kunci' => (string) $kunci,
                    'kode_lamaran' => $b['kode'],
                    'aktif' => false,
                ]);
            }

            return $versi !== null;
        });
    }

    /**
     * @return array{kode: string, muatan: array, dokumen: array<string, array>}|null
     *               null bila lamarannya tidak ada atau akunnya belum punya pasangan di zona luar
     */
    public function bangun(int $lamaranId): ?array
    {
        $l = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'l.Program_Batch_Id')
            ->where('l.Id_Lamaran', $lamaranId)
            ->select('l.*', 'p.Penyelenggara', 'p.Kategori as ProgramKategori', 'b.Nama as BatchNama')
            ->first();
        if (! $l) {
            return null;
        }

        $idPublik = PetaId::publik(PetaId::AKUN, (int) $l->Id_Users);
        if ($idPublik === null) {
            return null;
        }

        $sekarang = now();
        $dokumen = [];

        $tahapRows = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $lamaranId)->orderBy('Urutan')->get();
        $subTes = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahapRows->pluck('Id_Lamaran_Tahap')->all() ?: [0])
            ->orderBy('Urutan')->orderBy('Id_Lamaran_Tahap_Tes')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        $ujian = $this->ujian($l, $tahapRows, $subTes);
        $berkasTahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
            ->where('Lamaran_Id', $lamaranId)->whereNull('Ulang_Id')
            ->orderBy('Id_Lamaran_Tahap_Berkas')->get()->groupBy('Lamaran_Tahap_Id');
        $lokasi = DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->whereIn('Id_Master_Lokasi', $subTes->flatten(1)->pluck('Jadwal_Lokasi_Id')->filter()->unique()->values()->all() ?: [0])
            ->get()->keyBy('Id_Master_Lokasi');

        $tipeTahap = LamaranController::masterTipeTahap();
        $modePengumuman = LamaranController::masterModePengumuman();
        $aktivitas = [];
        $konfirmasi = [];

        $tahap = $tahapRows->map(function ($t) use ($l, $subTes, $ujian, $berkasTahap, $lokasi, $tipeTahap, $modePengumuman, $sekarang, &$dokumen, &$aktivitas, &$konfirmasi) {
            $info = $tipeTahap->get($t->Tipe_Tahap_Kode);
            $semua = collect($subTes->get($t->Id_Lamaran_Tahap, []));
            // Aktivitas internal (background check, cek referensi) disaring DI
            // SINI — apa pun yang masuk potret bisa dibaca kandidat.
            $terlihat = $semua->filter(fn ($s) => JadwalPrivat::terlihat($s, $s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode))->values();

            // Giliran pada tahap BERURUTAN dihitung dari SELURUH aktivitas.
            $penghalang = LamaranController::modeUrutanMengunci($t->Urutan_Aktivitas ?? null)
                ? $semua->sortBy('Urutan')->first(fn ($s) => ($s->Flag_Selesai ?? 'N') !== 'Y')
                : null;

            $tes = $terlihat->map(function ($s) use ($l, $t, $ujian, $lokasi, $tipeTahap, $penghalang, &$dokumen, &$aktivitas, &$konfirmasi) {
                $ti = $tipeTahap->get($s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode);
                $online = ($ti->Perilaku_Kode ?? 'MANUAL') === 'CAT';
                $su = $s->Penjadwalan_Tahap_Id ? $ujian->get($s->Penjadwalan_Tahap_Id) : null;
                $hash = Hashids::encode($s->Id_Lamaran_Tahap_Tes);
                $unggah = self::unggah($s);
                $jadwal = $s->Jadwal_Mulai ? $this->jadwal($s, $ti, $lokasi, $hash) : null;
                $kartuKonf = self::konfirmasiKartu($s, $hash);

                $aktivitas[(string) $s->Id_Lamaran_Tahap_Tes] = $this->aktivitas($l, $t, $s, $unggah, $dokumen);
                if (! empty($s->Konfirmasi_Status) && ($h = self::halamanKonfirmasi((int) $s->Id_Lamaran_Tahap_Tes))) {
                    $konfirmasi[(string) $s->Id_Lamaran_Tahap_Tes] = $h;
                }

                return [
                    'id' => $hash,
                    'urutan' => (int) $s->Urutan,
                    'label' => $s->Label,
                    'tipe' => $s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode,
                    'tipeNama' => $ti->Nama ?? null,
                    'tipeIkon' => $ti->Ikon ?? null,
                    'pesan' => $ti->Pesan_Kandidat ?? null,
                    'eksternal' => $online,
                    'peran' => $s->Peran,
                    'status' => $s->Status,
                    'hasil' => $s->Hasil,
                    // Nilai & catatan penilai SENGAJA tidak dikirim (rapor internal).
                    'unggah' => $unggah,
                    'mcu' => $s->Mcu_Status ? [
                        'status' => $s->Mcu_Status,
                        'label' => LamaranController::masterMcuStatus()->get($s->Mcu_Status)->Nama ?? $s->Mcu_Status,
                        'penyedia' => $s->Mcu_Penyedia,
                        'tanggal' => (string) ($s->Mcu_Tanggal ?: ''),
                        'catatan' => $s->Mcu_Catatan,
                    ] : null,
                    'perluJadwal' => ($ti->Flag_Jadwal ?? 'T') === 'Y',
                    'berformulir' => ($ti->Flag_Formulir ?? 'T') === 'Y',
                    'jadwal' => $jadwal,
                    'konfirmasi' => $kartuKonf,
                    'biaya' => UndanganJadwal::tampilBiaya($s) ? BiayaAktivitas::status($s, $ti) : null,
                    'selesai' => $s->Flag_Selesai === 'Y',
                    'butuhJadwal' => $online && $s->Flag_Selesai !== 'Y' && ! $su,
                    'terkunci' => $penghalang
                        && $s->Flag_Selesai !== 'Y'
                        && $penghalang->Id_Lamaran_Tahap_Tes !== $s->Id_Lamaran_Tahap_Tes,
                    // Nama penghalang tidak disebut bila aktivitas itu internal.
                    'menunggu' => $penghalang
                        && $s->Flag_Selesai !== 'Y'
                        && $penghalang->Id_Lamaran_Tahap_Tes !== $s->Id_Lamaran_Tahap_Tes
                        && JadwalPrivat::terlihat($penghalang, $penghalang->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode)
                            ? $penghalang->Label
                            : null,
                    'ujian' => self::bentukUjian($su),
                ];
            })->values()->all();

            $u = $t->Penjadwalan_Tahap_Id ? $ujian->get($t->Penjadwalan_Tahap_Id) : null;
            $mp = $modePengumuman->get(strtoupper((string) ($t->Mode_Pengumuman ?: 'OTOMATIS')));
            $diumumkan = $t->Waktu_Diumumkan ? Carbon::parse($t->Waktu_Diumumkan) : null;
            $terbit = LamaranController::terbitKeKandidat($t, $sekarang);
            $hasilTampil = (bool) $t->Hasil && $terbit;

            $berkas = [];
            if ($hasilTampil) {
                foreach ($berkasTahap->get($t->Id_Lamaran_Tahap, []) as $b) {
                    $ext = strtolower((string) ($b->Ext ?: pathinfo((string) $b->Nama_File, PATHINFO_EXTENSION)));
                    $kunci = 'tahap-berkas:'.$b->Id_Lamaran_Tahap_Berkas;
                    $dokumen[$kunci] = [
                        'kunci' => $kunci,
                        'kode_lamaran' => $l->Kode,
                        'jenis' => 'HASIL_TAHAP',
                        'nama' => (string) $b->Nama_File,
                        'path' => (string) $b->Path_File,
                        'mime' => $b->Mime,
                        'ukuran' => (int) $b->Ukuran,
                        'sha256' => null,
                        'aktif' => true,
                    ];
                    $berkas[] = [
                        'nama' => $b->Nama_File,
                        'ext' => $ext,
                        'ukuran' => (int) $b->Ukuran,
                        'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                        'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                        'url' => '/kandidat/lamaran/tahap/berkas/'.rawurlencode($kunci),
                    ];
                }
            }

            return [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'tipeNama' => $info->Nama ?? null,
                'tipeIkon' => $info->Ikon ?? null,
                'perilaku' => $info->Perilaku_Kode ?? 'MANUAL',
                'pesan' => $info->Pesan_Kandidat ?? null,
                'provider' => $t->Provider,
                'formulir' => $t->Formulir_Kode,
                'status' => $t->Status,
                // Hasil yang belum boleh diumumkan tidak dikirim sama sekali.
                'hasil' => $hasilTampil ? $t->Hasil : null,
                'skor' => $hasilTampil ? $t->Skor : null,
                // Catatan EKSTERNAL saja, dan baru sesudah hasilnya terbit.
                'catatanEksternal' => $hasilTampil ? ($t->Catatan_Eksternal_Html ?? null) : null,
                'catatanAt' => $hasilTampil && ($t->Catatan_Eksternal_Html ?? null) ? (string) $t->Diputus_At : null,
                'batas' => self::batasIsi(BatasIsi::status($t, $sekarang)),
                'waktuMulai' => $t->Waktu_Mulai ? (string) $t->Waktu_Mulai : null,
                'waktuSelesai' => $t->Waktu_Selesai ? (string) $t->Waktu_Selesai : null,
                'sudahIsi' => (bool) $t->Formulir_Pengisian_Id,
                'butuhJadwal' => ! self::bentukUjian($u) && collect($tes)->contains(fn ($x) => $x['butuhJadwal']),
                'otomatis' => strtoupper((string) ($t->Keputusan_Mode ?? 'MANUAL')) === 'SYSTEM',
                'siapDiputus' => ($t->Siap_Diputus ?? 'N') === 'Y',
                'penawaran' => ($info->Flag_Penawaran ?? 'T') === 'Y',
                'hasilTampil' => $hasilTampil,
                'berkas' => $berkas,
                'diputusAt' => $t->Diputus_At ? (string) $t->Diputus_At : null,
                'menungguPengumuman' => (bool) $t->Hasil && ! $terbit,
                'pengumuman' => $mp ? [
                    'kode' => $mp->Kode,
                    'nama' => $mp->Nama,
                    'label' => $mp->Label,
                    'ikon' => $mp->Ikon,
                    'warna' => $mp->Warna,
                    'tanggal' => $diumumkan?->toIso8601String(),
                ] : null,
                'ujian' => self::bentukUjian($u),
                'tes' => $tes,
                // Boolean telanjang: ada yang ditangani tim, tanpa nama/jadwal.
                'adaJadwalInternal' => $semua->contains(fn ($s) => JadwalPrivat::untuk($s->Tipe_Tahap_Kode ?? null)),
            ];
        })->values()->all();

        $status = self::statusKandidat((string) $l->Status);
        $prefill = array_intersect_key(
            LamaranService::dataKandidatEmail($lamaranId),
            array_flip(['kampus', 'tahunLulus', 'tglLahir', 'jkel', 'jurusan', 'jenjang', 'ipk', 'statusStudi', 'semester']),
        );

        $potret = [
            'kontrak' => self::KONTRAK,
            'kode' => $l->Kode,
            'lamaran' => [
                'status' => $l->Status,
                'statusLabel' => $status['label'],
                'statusNada' => $status['nada'],
                'urutanTahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'hasilAkhir' => $l->Hasil_Akhir,
                'gugurDi' => $l->Gugur_Di_Tahap,
                'alasanGugur' => $l->Alasan_Gugur,
                'batch' => $l->BatchNama,
                'penyelenggara' => $l->Penyelenggara,
            ],
            // Stepper "PROGRES SELEKSI" — hasil yang belum terbit ikut ditahan.
            'tahapan' => $tahapRows->map(fn ($t) => [
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'status' => $t->Status,
                'hasil' => $t->Hasil && LamaranController::terbitKeKandidat($t, $sekarang) ? $t->Hasil : null,
            ])->values()->all(),
            'detail' => [
                'tahap' => $tahap,
                'tugas' => self::tugas($lamaranId),
                'konteks' => (object) [],
            ],
            'prefill' => $prefill ?: (object) [],
            'tahapFormulir' => self::tahapFormulir($l, $tahapRows, $sekarang) ?: (object) [],
            'aktivitas' => $aktivitas ?: (object) [],
            'konfirmasi' => $konfirmasi ?: (object) [],
            'feedback' => self::feedback($lamaranId),
        ];

        ksort($dokumen);

        return [
            'kode' => (string) $l->Kode,
            'muatan' => [
                'kode' => (string) $l->Kode,
                'id_users' => (int) $idPublik,
                'lamaran' => [
                    'Urutan_Tahap' => (int) $l->Urutan_Tahap,
                    'Total_Tahap' => $l->Total_Tahap !== null ? (int) $l->Total_Tahap : null,
                    'Status' => (string) $l->Status,
                    'Hasil_Akhir' => $l->Hasil_Akhir,
                    'Waktu_Selesai' => $l->Waktu_Selesai ? Carbon::parse($l->Waktu_Selesai)->format('Y-m-d H:i:s') : null,
                ],
                'potret' => $potret,
                // Lamaran yang LAHIR di zona dalam (ditarik tim dari Talent Pool)
                // belum punya baris di publik — pendorong membuatkannya sekali.
                'buat' => $l->Asal_Talent_Pool_Id ? [
                    'Kategori' => $l->Kategori,
                    'Program_Id' => $l->Program_Id !== null ? (int) $l->Program_Id : null,
                    'Program_Batch_Id' => $l->Program_Batch_Id !== null ? (int) $l->Program_Batch_Id : null,
                    'Program_Posisi_Id' => $l->Program_Posisi_Id !== null ? (int) $l->Program_Posisi_Id : null,
                    'Mpp_Ref' => $l->Mpp_Ref,
                    'Pembukaan_Id' => $l->Pembukaan_Id !== null ? (int) $l->Pembukaan_Id : null,
                    'Master_Alur_Id' => $l->Master_Alur_Id !== null ? (int) $l->Master_Alur_Id : null,
                    'Asal_Talent_Pool_Id' => (int) $l->Asal_Talent_Pool_Id,
                    'Mulai_Dari_Urutan' => $l->Mulai_Dari_Urutan !== null ? (int) $l->Mulai_Dari_Urutan : null,
                    'Waktu_Lamar' => $l->Waktu_Lamar ? Carbon::parse($l->Waktu_Lamar)->format('Y-m-d H:i:s') : null,
                ] : null,
                // Kunci dokumen yang dirujuk — pembanding untuk versi berikutnya.
                'dokumen' => array_keys($dokumen),
            ],
            'dokumen' => $dokumen,
        ];
    }

    // ═══════════════════════ BAGIAN-BAGIAN ═══════════════════════

    /** Sesi ujian pihak ke-3 milik pelamar ini, dikunci per Penjadwalan_Tahap. */
    private function ujian(object $l, Collection $tahapRows, Collection $subTes): Collection
    {
        $ids = $tahapRows->pluck('Penjadwalan_Tahap_Id')
            ->merge($subTes->flatten(1)->pluck('Penjadwalan_Tahap_Id'))
            ->filter()->unique()->values()->all();
        if (! $ids) {
            return collect();
        }

        return DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as ps')
            ->join('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'ps.Penjadwalan_Tahap_Id')
            ->whereIn('ps.Penjadwalan_Tahap_Id', $ids)
            ->where(fn ($w) => $w->where('ps.Lamaran_Id', $l->Id_Lamaran)->orWhere('ps.Users_Id', $l->Id_Users))
            // Jendela PESERTA menang atas jendela tahap.
            ->select('ps.*', 'pt.Nama_Ujian')
            ->selectRaw('COALESCE(ps.Waktu_Mulai, pt.Waktu_Mulai) as Jendela_Mulai')
            ->selectRaw('COALESCE(ps.Waktu_Akhir, pt.Waktu_Akhir) as Jendela_Akhir')
            ->orderBy('ps.Id_Penjadwalan_Peserta')
            ->get()
            ->keyBy('Penjadwalan_Tahap_Id');
    }

    private static function bentukUjian(?object $u): ?array
    {
        if (! $u) {
            return null;
        }
        $mulai = $u->Jendela_Mulai ? Carbon::parse($u->Jendela_Mulai) : null;
        $akhir = $u->Jendela_Akhir ? Carbon::parse($u->Jendela_Akhir) : null;
        $sekarang = now();

        return [
            'terjadwal' => (bool) $u->Link_Ujian || (bool) $u->Short_Token,
            'token' => $u->Short_Token,
            'otp' => $u->Akses_OTP,
            'link' => $u->Link_Ujian,
            'namaUjian' => $u->Nama_Ujian,
            'waktuMulai' => $mulai?->toIso8601String(),
            'waktuSelesai' => $akhir?->toIso8601String(),
            'statusKirim' => $u->Status_Kirim,
            'statusPengerjaan' => $u->Status_Pengerjaan,
            'nilai' => $u->Total_Nilai,
            'kelulusan' => $u->Status_Kelulusan,
            'belumMulai' => $mulai ? $sekarang->lt($mulai) : false,
            'sudahLewat' => $akhir ? $sekarang->gt($akhir) : false,
            'bisaAkses' => $mulai && $akhir && $sekarang->betweenIncluded($mulai, $akhir)
                && (bool) $u->Link_Ujian && $u->Status_Pengerjaan !== 'selesai',
            '_jendela' => ['mulai' => $mulai?->toIso8601String(), 'akhir' => $akhir?->toIso8601String()],
        ];
    }

    /** Aturan unggah kandidat + penanda tutupnya. */
    private static function unggah(object $s): ?array
    {
        $a = UndanganJadwal::aturanUnggah($s);
        if ($a && ! empty($a['batas']) && empty($a['terkirim'])) {
            $a['_tutupSetelah'] = Carbon::parse($a['batas'])->toIso8601String();
        }

        return $a;
    }

    private function jadwal(object $s, ?object $ti, Collection $lokasi, string $hash): array
    {
        $j = [
            'mode' => $s->Jadwal_Mode,
            'daring' => strtoupper((string) $s->Jadwal_Mode) === 'DARING',
            'mulai' => (string) $s->Jadwal_Mulai,
            'selesai' => (string) ($s->Jadwal_Selesai ?: ''),
            'link' => $s->Jadwal_Link,
            'kontak' => $s->Jadwal_Kontak ?? null,
            'lokasi' => $s->Jadwal_Lokasi,
            'tempat' => LamaranController::tempatJadwal($s, $s->Jadwal_Lokasi_Id ? $lokasi->get($s->Jadwal_Lokasi_Id) : null),
            'catatan' => $s->Jadwal_Catatan,
        ] + LamaranController::jadwalTambahan($s, $ti, '/kandidat/lamaran/tes/'.$hash.'/surat');

        $terbuka = ($s->Flag_Selesai ?? 'N') !== 'Y' && empty($s->Jadwal_Hadir);
        $batas = $j['batasUnggah'] ?? (! empty($j['batasWaktu']) ? ($j['batas'] ?? null) : null);
        if ($terbuka && $batas) {
            $j['_lewatSetelah'] = Carbon::parse($batas)->toIso8601String();
        }

        return $j;
    }

    /** Gerbang aktivitas untuk unggah berkas & surat pengantar (potret.aktivitas). */
    private function aktivitas(object $l, object $t, object $s, ?array $unggah, array &$dokumen): array
    {
        $terbuka = ($s->Flag_Selesai ?? 'N') !== 'Y' && $t->Status === 'BERJALAN' && $l->Status === 'BERJALAN';

        $surat = [];
        foreach (SuratJadwal::daftar($s) as $urutan => $i) {
            $kunci = 'surat:'.$s->Id_Lamaran_Tahap_Tes.':'.$urutan;
            $dokumen[$kunci] = [
                'kunci' => $kunci,
                'kode_lamaran' => $l->Kode,
                'jenis' => 'SURAT_PENGANTAR',
                'nama' => (string) $i['nama'],
                'path' => (string) $i['path'],
                'mime' => 'application/pdf',
                'ukuran' => (int) $i['ukuran'],
                'sha256' => null,
                'aktif' => true,
            ];
            $surat[] = ['urutan' => (int) $urutan, 'nama' => $i['nama'], 'ukuran' => (int) $i['ukuran'], 'dokumen' => $kunci];
        }

        return [
            'label' => $s->Label,
            'terbuka' => $terbuka,
            'alasanTutup' => $terbuka ? null : 'Aktivitas ini sudah selesai — berkas tidak bisa diubah lagi.',
            'unggah' => $unggah ? [
                'format' => array_values($unggah['format'] ?? ['pdf']),
                'maksMb' => (int) ($unggah['maksMb'] ?? 5),
                'batas' => $unggah['batas'] ?? null,
            ] : null,
            'surat' => $surat,
            'putaran' => max(1, (int) ($l->Putaran ?? 1)),
        ];
    }

    /** Batas pengisian formulir tahap: tanpa hitung mundur, plus penanda lewat. */
    private static function batasIsi(?array $b): ?array
    {
        if (! $b) {
            return null;
        }
        unset($b['sisaDetik']);
        if (! empty($b['batas']) && empty($b['terkirim'])) {
            $b['_lewatSetelah'] = Carbon::parse($b['batas'])->toIso8601String();
        }

        return $b;
    }

    /** Tahap aktif yang menuntut formulir & belum diisi → tugas kandidat. */
    private static function tugas(int $lamaranId): ?array
    {
        $aktif = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
            ->where('t.Lamaran_Id', $lamaranId)
            ->where('t.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Kode')
            ->whereNull('t.Formulir_Pengisian_Id')
            ->orderBy('t.Urutan')
            ->select('t.Id_Lamaran_Tahap', 't.Label', 't.Formulir_Kode', 't.Formulir_Komponen', 't.Formulir_Versi',
                'f.Nama as FormulirNama', 'f.Komponen_Kode')
            ->first();
        if (! $aktif) {
            return null;
        }

        // Skema dikunci ke versi yang dibekukan saat tahap dibuat.
        $schema = FormulirSchema::byKodeDanVersi($aktif->Formulir_Kode, $aktif->Formulir_Versi !== null ? (int) $aktif->Formulir_Versi : null);

        return [
            'tahapId' => Hashids::encode($aktif->Id_Lamaran_Tahap),
            'label' => $aktif->Label,
            'formulir' => $aktif->Formulir_Kode,
            'formulirNama' => $aktif->FormulirNama,
            'komponen' => $aktif->Formulir_Komponen ?: ($schema['komponen'] ?? $aktif->Komponen_Kode),
            'schema' => $schema['schema'] ?? null,
            'versiId' => $schema['versiId'] ?? null,
            'versi' => $schema['versi'] ?? null,
            'batas' => self::batasIsi(BatasIsi::statusTahap((int) $aktif->Id_Lamaran_Tahap)),
        ];
    }

    /** Gerbang draf & kirim formulir per tahap (potret.tahapFormulir). */
    private static function tahapFormulir(object $l, Collection $tahapRows, Carbon $sekarang): array
    {
        $hasil = [];
        foreach ($tahapRows as $t) {
            if (! $t->Formulir_Kode && (int) $t->Urutan !== 1) {
                continue;
            }

            $batas = BatasIsi::status($t, $sekarang);
            $alasan = match (true) {
                $l->Status !== 'BERJALAN' => 'Proses seleksi untuk lamaran ini sudah selesai.',
                ! empty($t->Formulir_Pengisian_Id) => 'Formulir tahap ini sudah terkirim.',
                $t->Status !== 'BERJALAN' => 'Formulir tahap ini tidak sedang dalam pengisian.',
                (bool) ($batas['belumDiatur'] ?? false) => 'Formulir tahap ini belum dibuka — jadwal pengisiannya belum diatur tim rekrutmen.',
                (bool) ($batas['belumBuka'] ?? false) => 'Formulir tahap ini baru dibuka '.($batas['bukaTeks'] ?? '').'.',
                default => null,
            };

            $hasil[(string) $t->Id_Lamaran_Tahap] = [
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'formulirKode' => $t->Formulir_Kode,
                'formulirVersi' => $t->Formulir_Versi !== null ? (int) $t->Formulir_Versi : null,
                'terbuka' => $alasan === null,
                'alasanTutup' => $alasan,
                'batasIsi' => $batas['batas'] ?? null,
            ];
        }

        return $hasil;
    }

    /**
     * Kartu konfirmasi di portal kandidat (status, kalimat, batas, bahan jawab),
     * dengan pintu jawab situs kandidat dan penanda batasnya.
     */
    private static function konfirmasiKartu(object $s, string $hash): ?array
    {
        if (! KonfirmasiJadwal::siap() || empty($s->Konfirmasi_Status)) {
            return null;
        }

        $id = (int) $s->Id_Lamaran_Tahap_Tes;
        if ($s->Konfirmasi_Status === KonfirmasiJadwal::DITUNDA) {
            if (! empty($s->Jadwal_Mulai)) {
                return null;
            }
            $def = KonfirmasiJadwal::master()->get(KonfirmasiJadwal::DITUNDA);

            return [
                'status' => KonfirmasiJadwal::DITUNDA,
                'kalimat' => $def->Kalimat_Kandidat ?? 'Jadwal ini ditunda. Jadwal pengganti akan kami kabarkan.',
                'warna' => $def->Warna ?? '#7c3aed',
                'ikon' => $def->Ikon ?? 'bi-pause-circle-fill',
                'bolehJawab' => false,
                'tunda' => KonfirmasiJadwal::tundaUntukKandidat(KonfirmasiJadwal::infoTunda($id)),
            ];
        }
        if (empty($s->Jadwal_Mulai)) {
            return null;
        }

        $sub = KonfirmasiJadwal::konteks($id);
        if (! $sub) {
            return null;
        }
        $versi = (int) ($sub->Jadwal_Versi ?? 0);
        $bahan = self::bahanJawab($sub, $versi, [
            'jawab' => "/kandidat/konfirmasi/{$hash}/{$versi}/jawab",
            'cabut' => "/kandidat/konfirmasi/{$hash}/{$versi}/cabut",
        ]);
        $k = $bahan['konfirmasi'];

        return [
            'status' => $k['status'],
            'kalimat' => $k['kalimat'],
            'warna' => $k['warna'],
            'ikon' => $k['ikon'],
            'batas' => $k['batas'],
            'batasTeks' => $k['batasTeks'],
            'bolehJawab' => $k['bolehJawab'],
            'jawab' => $bahan,
        ];
    }

    /**
     * Halaman konfirmasi (tautan surel) untuk versi jadwal YANG BERLAKU —
     * potret.konfirmasi.{id}. Keadaan "acara sudah lewat" dan tautan jawabnya
     * dibuat situs kandidat sendiri.
     */
    private static function halamanKonfirmasi(int $id): ?array
    {
        if (! KonfirmasiJadwal::siap()) {
            return null;
        }
        $sub = KonfirmasiJadwal::konteks($id);
        if (! $sub || empty($sub->Konfirmasi_Status)) {
            return null;
        }

        $versi = (int) ($sub->Jadwal_Versi ?? 0);
        $judul = [
            'aktivitas' => $sub->Label,
            'posisi' => $sub->Posisi ?: $sub->ProgramNama,
            'program' => $sub->ProgramNama,
            'tahap' => $sub->TahapLabel,
            'kode' => $sub->LamaranKode,
            'nama' => $sub->KandidatNama,
        ];
        $keadaan = match (true) {
            ($sub->StatusLamaran ?? '') !== 'BERJALAN' => 'LAMARAN_SELESAI',
            ($sub->Flag_Selesai ?? 'T') === 'Y' || ! empty($sub->Jadwal_Hadir) => 'SELESAI',
            empty($sub->Jadwal_Mulai) => 'DITUNDA',
            default => 'TERBUKA',
        };

        $h = ['keadaan' => $keadaan, 'judul' => $judul];
        if ($keadaan === 'DITUNDA' && $sub->Konfirmasi_Status === KonfirmasiJadwal::DITUNDA) {
            $h['tunda'] = KonfirmasiJadwal::tundaUntukKandidat(KonfirmasiJadwal::infoTunda($id));
        }

        if ($keadaan === 'TERBUKA') {
            $mode = UndanganJadwal::mode($sub->Jadwal_Mode);
            $daring = strtoupper((string) $sub->Jadwal_Mode) === 'DARING';
            $tempat = $daring ? null : LamaranController::tempatJadwal($sub);
            $h['jadwal'] = [
                'waktuTeks' => UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai),
                'mulai' => (string) $sub->Jadwal_Mulai,
                'modeNama' => $mode->Nama ?? $sub->Jadwal_Mode,
                'daring' => $daring,
                'link' => $daring ? $sub->Jadwal_Link : null,
                'tempat' => $tempat ? [
                    'nama' => $tempat['nama'] ?? null,
                    'alamat' => $tempat['alamatLengkap'] ?? null,
                    'mapsUrl' => $tempat['mapsUrl'] ?? null,
                ] : null,
                'detailLokasi' => $daring ? null : ($sub->Jadwal_Lokasi ?: null),
                'kontak' => $sub->Jadwal_Kontak ?? null,
                'instruksi' => $sub->Jadwal_Catatan ?: null,
            ];
            $h += self::bahanJawab($sub, $versi, []);
        }

        return [
            'versi' => $versi,
            'mulai' => $sub->Jadwal_Mulai ? (string) $sub->Jadwal_Mulai : null,
            'halaman' => $h,
        ];
    }

    /**
     * KonfirmasiJadwal::bahanJawab tanpa nilai yang berubah terus (tanggal
     * usulan dihitung ulang situs kandidat) + penanda batas & keterbukaannya.
     */
    private static function bahanJawab(object $sub, int $versi, array $url): array
    {
        $b = KonfirmasiJadwal::bahanJawab($sub, $versi, $url);
        $b['aturanUsulan']['tanggalMin'] = null;
        $b['aturanUsulan']['tanggalMaks'] = null;
        $b['_konfirmasi'] = [
            'batas' => $b['konfirmasi']['batas'] ?? null,
            'mulai' => $sub->Jadwal_Mulai ? (string) $sub->Jadwal_Mulai : null,
            // KonfirmasiJadwal::galatTerbuka() TANPA syarat waktu — "sudah
            // lewat" dinilai situs kandidat dari batasnya, saat dibaca.
            'terbuka' => ($sub->StatusLamaran ?? '') === 'BERJALAN'
                && ($sub->Flag_Selesai ?? 'T') !== 'Y'
                && empty($sub->Jadwal_Hadir)
                && ! empty($sub->Jadwal_Mulai)
                && (int) ($sub->Jadwal_Versi ?? 0) === $versi
                && ! empty($sub->Konfirmasi_Status),
        ];

        return $b;
    }

    /** Survei feedback lamaran ini (potret.feedback). */
    private static function feedback(int $lamaranId): array
    {
        $rows = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Master_Feedback_Form as ff', 'ff.Id_Master_Feedback_Form', '=', 'fj.Master_Feedback_Form_Id')
            ->where('fj.Lamaran_Id', $lamaranId)
            ->where('fj.Flag_Cancellation', 'T')
            ->orderBy('fj.Id_Feedback_Jawaban')
            ->select('fj.Id_Feedback_Jawaban', 'fj.Status_Pengisian', 'fj.Created_At', 'fj.Master_Feedback_Form_Id',
                'ff.Nama as Form_Nama', 'ff.Durasi_Hari', 'ff.Mode_Tampilan')
            ->get();

        return $rows->map(function ($f) {
            $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
                ->where('Master_Feedback_Form_Id', $f->Master_Feedback_Form_Id)
                ->where('Flag_Cancellation', 'T')
                ->orderBy('Urutan')->orderBy('Id_Master_Feedback_Pertanyaan')
                ->get(['Id_Master_Feedback_Pertanyaan', 'Master_Feedback_Form_Id', 'Urutan', 'Tipe', 'Label', 'Opsi',
                    'Skala_Min', 'Skala_Max', 'Label_Min', 'Label_Max'])
                ->map(fn ($p) => [
                    'Id_Master_Feedback_Pertanyaan' => (int) $p->Id_Master_Feedback_Pertanyaan,
                    'Master_Feedback_Form_Id' => (int) $p->Master_Feedback_Form_Id,
                    'Urutan' => (int) $p->Urutan,
                    'Tipe' => $p->Tipe,
                    'Label' => $p->Label,
                    'Opsi' => $p->Opsi ? json_decode($p->Opsi, true) : null,
                    'Skala_Min' => $p->Skala_Min !== null ? (int) $p->Skala_Min : null,
                    'Skala_Max' => $p->Skala_Max !== null ? (int) $p->Skala_Max : null,
                    'Label_Min' => $p->Label_Min,
                    'Label_Max' => $p->Label_Max,
                ])->values()->all();

            return [
                'id' => (int) $f->Id_Feedback_Jawaban,
                'status' => $f->Status_Pengisian,
                'dibuatAt' => $f->Created_At ? Carbon::parse($f->Created_At)->toIso8601String() : null,
                'durasiHari' => min(30, max(1, (int) ($f->Durasi_Hari ?? 30))),
                'formNama' => $f->Form_Nama,
                'pertanyaan' => $pertanyaan,
                'modeTampilan' => $f->Mode_Tampilan ?: 'SCROLL',
            ];
        })->values()->all();
    }

    /** Kata & nada status lamaran — dari master hasil keputusan. */
    private static function statusKandidat(string $status): array
    {
        $status = strtoupper($status);
        if ($status === 'BERJALAN') {
            return ['label' => 'Berjalan', 'nada' => 'berjalan'];
        }

        $def = LamaranService::masterHasilKeputusan()->get($status);
        if (! $def) {
            return ['label' => $status ?: '—', 'nada' => 'berjalan'];
        }
        if (($def->Flag_Lolos ?? 'T') === 'Y') {
            return ['label' => 'Diterima', 'nada' => 'lolos'];
        }
        // Keputusan yang datang DARI KANDIDAT tidak pernah diwarnai sebagai kegagalan.
        if (($def->Flag_Oleh_Kandidat ?? 'T') === 'Y') {
            return ['label' => $def->Nama, 'nada' => 'netral'];
        }

        return ['label' => $def->Nama, 'nada' => ($def->Flag_Talent_Pool ?? 'T') === 'Y' ? 'menunggu' : 'gugur'];
    }
}
