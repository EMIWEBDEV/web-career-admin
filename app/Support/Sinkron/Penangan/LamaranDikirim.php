<?php

namespace App\Support\Sinkron\Penangan;

use App\Jobs\Career\WcBiodataHrisJob;
use App\Support\Career\LamaranService;
use App\Support\Career\TerimaLamaran;
use App\Support\Sinkron\Keluar;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;
use App\Support\Sinkron\SalinBerkas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lamaran.Dikirim — kandidat melamar di situs kandidat.
 *
 * Sama dengan jalur WcApplyFormJob, dengan KODE lamaran yang sudah dipakai
 * situs kandidat (kunci bersama kedua zona):
 *   berkas karantina → bucket admin (path sama)
 *   LamaranService::buatLamaran (snapshot alur, syarat auto-gugur, tahap 1)
 *   Formulir_Berkas pendaftaran (TerimaLamaran — aturan yang sama)
 *   Apply_Payload SELESAI — panel "lamaran gagal masuk" & operasional
 *   dashboard tetap membaca tabel yang sama
 * Sesudah commit: surel hasil (wc-applymail) & biodata HRIS (wc-biodata-hris).
 *
 * Ditolak (lowongan tutup, tidak layak, sudah melamar) → lamaran publiknya
 * ditutup (Status DITOLAK) supaya kandidat tidak tertahan aturan "satu lamaran
 * aktif", dan alasannya tampil di layar lamar.
 */
final class LamaranDikirim implements Penangan, PenanganDitolak, PenanganMati
{
    public function __construct(private LamaranService $svc) {}

    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $kode = (string) $muatan['kode'];
        $idPublik = (int) $muatan['akun']['id_publik'];
        $userId = PetaId::akunAdmin($idPublik);
        $nama = (string) ($muatan['akun']['nama'] ?? '') ?: (string) DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->value('Nama');

        $sudah = DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $kode)->first(['Id_Lamaran', 'Id_Users']);
        if ($sudah) {
            if ((int) $sudah->Id_Users !== $userId) {
                throw new PeristiwaDitolak('Kode lamaran bentrok dengan lamaran lain.');
            }

            return HasilPenanganan::ok('lamaran:'.$sudah->Id_Lamaran)->segarkan((int) $sudah->Id_Lamaran);
        }

        // Berkas menyeberang lebih dulu: lamaran tanpa CV-nya lebih buruk
        // daripada lamaran yang tertunda (galat salin → dicoba lagi).
        $berkas = [];
        foreach ((array) ($muatan['berkas'] ?? []) as $b) {
            if (empty($b['path']) || empty($b['field'])) {
                continue;
            }
            $berkas[] = [
                'field' => (string) $b['field'],
                'bagian' => $b['bagian'] ?? null,
                'baris' => isset($b['baris']) ? (int) $b['baris'] : null,
                'nama' => (string) ($b['nama'] ?? basename((string) $b['path'])),
                'path' => SalinBerkas::dariKarantina((string) $b['path']),
                'ext' => $b['ext'] ?? null,
                'mime' => $b['mime'] ?? null,
                'ukuran' => isset($b['ukuran']) ? (int) $b['ukuran'] : null,
                'hash' => $b['hash'] ?? null,
            ];
        }

        $jawaban = is_array($muatan['jawaban'] ?? null) ? $muatan['jawaban'] : null;
        $hasil = $this->svc->buatLamaran(
            $userId,
            (int) $muatan['pembukaan_id'],
            (int) $muatan['posisi_id'],
            null,
            $muatan['gugur_alasan'] ?? null,
            $jawaban,
            $kode,
            $nama,
        );

        if (! ($hasil['ok'] ?? false)) {
            throw new PeristiwaDitolak((string) ($hasil['pesan'] ?? 'Lamaran tidak dapat diproses.'));
        }

        $lamaranId = (int) $hasil['lamaranId'];
        // buatLamaran mengembalikan lamaran LAMA bila posisi ini sudah pernah
        // dilamar (mis. lamaran sebelum dua zona) — bukan lamaran ini.
        if (DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $lamaranId)->value('Kode') !== $kode) {
            throw new PeristiwaDitolak('Kamu sudah melamar posisi ini sebelumnya.');
        }

        TerimaLamaran::simpanBerkasPendaftaran($lamaranId, $userId, $berkas, $nama);

        $pengisianKode = $muatan['formulir']['pengisian_kode'] ?? null;
        $pengisianId = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)->where('Sumber', 'PENDAFTARAN')
            ->orderBy('Id_Formulir_Pengisian')->value('Id_Formulir_Pengisian');
        if ($pengisianKode && $pengisianId) {
            PetaId::pasang(PetaId::FORMULIR, (string) $pengisianKode, (int) $pengisianId);
        }

        self::catatPayload($muatan, $userId, $nama, [
            'Status' => 'SELESAI',
            'Lamaran_Id' => $lamaranId,
            'Pesan_Error' => null,
        ]);

        Log::channel('web_career')->info("[APPLY] {$kode} diproses dari situs kandidat — lamaran #{$lamaranId}, ".count($berkas).' berkas.');

        return HasilPenanganan::ok('lamaran:'.$lamaranId, (string) ($hasil['pesan'] ?? 'Lamaran diterima.'))
            ->segarkan($lamaranId)
            ->lalu(fn () => TerimaLamaran::kirimEmailHasil($lamaranId, $userId))
            ->lalu(fn () => WcBiodataHrisJob::dispatch($userId, 'APPLY'));
    }

    public function ditolak(array $muatan, object $baris, string $alasan): void
    {
        $idPublik = (int) ($muatan['akun']['id_publik'] ?? 0);
        $userId = PetaId::admin(PetaId::AKUN, $idPublik);

        if ($userId) {
            self::catatPayload($muatan, $userId, (string) ($muatan['akun']['nama'] ?? ''), [
                'Status' => 'GAGAL',
                'Pesan_Error' => mb_substr('Ditolak: '.$alasan, 0, 480),
            ]);
        }

        // Lamaran publiknya ditutup — tanpa potret (tidak ada lamaran di dalam).
        Keluar::antrekan(Keluar::PORTAL, (string) $muatan['kode'], [
            'kode' => (string) $muatan['kode'],
            'id_users' => $idPublik,
            'lamaran' => [
                'Urutan_Tahap' => 1,
                'Total_Tahap' => null,
                'Status' => 'DITOLAK',
                'Hasil_Akhir' => null,
                'Waktu_Selesai' => now()->format('Y-m-d H:i:s'),
            ],
            'potret' => null,
        ]);
    }

    public function mati(array $muatan, object $baris, string $galat): void
    {
        $userId = PetaId::admin(PetaId::AKUN, (int) ($muatan['akun']['id_publik'] ?? 0));
        if ($userId) {
            self::catatPayload($muatan, $userId, (string) ($muatan['akun']['nama'] ?? ''), [
                'Status' => 'GAGAL',
                'Percobaan' => (int) $baris->Percobaan,
                'Pesan_Error' => mb_substr($galat, 0, 480),
            ]);
        }
    }

    /** Baris Apply_Payload (Process_Id = kode lamaran) — dibuat atau diperbarui. */
    private static function catatPayload(array $m, int $userId, string $nama, array $nilai): void
    {
        $kode = (string) $m['kode'];
        $waktu = isset($m['waktu_lamar']) ? Carbon::parse($m['waktu_lamar'])->setTimezone(config('app.timezone')) : now();

        $dasar = [
            'Users_Id' => $userId,
            'Pembukaan_Id' => (int) ($m['pembukaan_id'] ?? 0) ?: null,
            'Program_Posisi_Id' => (int) ($m['posisi_id'] ?? 0) ?: null,
            'Nama_Kandidat' => mb_substr($nama, 0, 150) ?: null,
            'Payload_Json' => json_encode([
                'sumber' => 'SITUS_KANDIDAT',
                'userId' => $userId,
                'pembukaanId' => $m['pembukaan_id'] ?? null,
                'posisiId' => $m['posisi_id'] ?? null,
                'gugurAlasan' => $m['gugur_alasan'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'Waktu_Proses' => now(),
            'Updated_At' => now(),
            'Updated_By' => 'SINKRON',
        ];

        $ada = DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $kode)->exists();
        $ada
            ? DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $kode)->update($nilai + $dasar)
            : DB::table('N_WEB_CAREERS_Apply_Payload')->insert($nilai + $dasar + [
                'Process_Id' => $kode,
                'Tahun' => $waktu->format('Y'),
                'Bulan' => $waktu->format('m'),
                'Tanggal' => $waktu->format('d'),
                'Created_At' => $waktu,
                'Created_By' => mb_substr($nama, 0, 200) ?: 'KANDIDAT',
                'Created_By_Id' => $userId,
            ]);
    }
}
