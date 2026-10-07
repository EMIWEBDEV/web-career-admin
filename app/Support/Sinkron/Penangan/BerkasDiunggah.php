<?php

namespace App\Support\Sinkron\Penangan;

use App\Support\Career\UndanganJadwal;
use App\Support\Sinkron\Keluar;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;
use App\Support\Sinkron\SalinBerkas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Berkas.Diunggah — kandidat menyatakan berkas sebuah aktivitas (jawaban tes
 * offline, hasil MCU mandiri, …) LENGKAP di situs kandidat.
 *
 * Berkasnya disalin dari karantina ke bucket admin, dicatat sebagai
 * Lamaran_Tes_Berkas milik aktivitas itu (stempel Terkirim_At = saat kandidat
 * mengirim), dan aktivitasnya ditandai sudah dikirim — sama dengan "Kirim
 * berkas" yang lama. Salinan bersihnya juga didorong ke bucket publik supaya
 * kandidat tetap bisa membukanya sesudah karantina dibersihkan.
 */
final class BerkasDiunggah implements Penangan
{
    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $userId = PetaId::akunAdmin((int) $muatan['akun']['id_publik']);
        $kode = (string) $muatan['kode'];

        $tes = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', (int) $muatan['aktivitas_id'])
            ->where('l.Kode', $kode)
            ->where('l.Id_Users', $userId)
            ->select('t.*', 'h.Lamaran_Id', 'h.Status as StatusTahap', 'l.Status as StatusLamaran')
            ->first();
        if (! $tes) {
            throw new PeristiwaDitolak('Aktivitas tidak ditemukan.');
        }
        if (! UndanganJadwal::aturanUnggah($tes)) {
            throw new PeristiwaDitolak('Aktivitas ini tidak meminta unggahan berkas.');
        }
        if ($tes->Flag_Selesai === 'Y' || $tes->StatusTahap !== 'BERJALAN' || $tes->StatusLamaran !== 'BERJALAN') {
            throw new PeristiwaDitolak('Aktivitas ini sudah selesai — berkas tidak bisa diubah lagi.');
        }

        $dikirim = isset($muatan['dikirim_at']) ? Carbon::parse($muatan['dikirim_at'])->setTimezone(config('app.timezone')) : now();
        $nama = (string) DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->value('Nama');
        $putaran = max(1, (int) ($muatan['putaran'] ?? 1));
        $jumlah = 0;

        foreach ((array) $muatan['berkas'] as $b) {
            $idPublik = (int) ($b['id_publik'] ?? 0);
            if ($idPublik < 1 || empty($b['path'])) {
                continue;
            }
            $kunci = 'tes:'.$idPublik;
            if ($idAdmin = PetaId::admin(PetaId::BERKAS, $kunci)) {
                continue; // sudah tercatat (peristiwa ulang)
            }

            $path = SalinBerkas::dariKarantina((string) $b['path']);
            $ext = strtolower((string) ($b['ext'] ?? pathinfo($path, PATHINFO_EXTENSION)));
            $idAdmin = (int) DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->insertGetId([
                'Lamaran_Tahap_Tes_Id' => (int) $tes->Id_Lamaran_Tahap_Tes,
                'Lamaran_Id' => (int) $tes->Lamaran_Id,
                'Id_Users' => $userId,
                'Nama_File' => Str::limit((string) ($b['nama'] ?? basename($path)), 290, ''),
                'Path_File' => $path,
                'Mime' => $b['mime'] ?? null,
                'Ext' => mb_substr($ext, 0, 15) ?: null,
                'Ukuran' => isset($b['ukuran']) ? (int) $b['ukuran'] : null,
                'Putaran' => $putaran,
                'Terkirim_At' => $dikirim,
                'Created_At' => $dikirim,
                'Created_By' => $nama,
                'Created_By_Id' => $userId,
            ], 'Id_Lamaran_Tes_Berkas');
            PetaId::pasang(PetaId::BERKAS, $kunci, $idAdmin);
            $jumlah++;

            Keluar::antrekan(Keluar::DOKUMEN, 'tes-berkas:'.$idPublik, [
                'kunci' => 'tes-berkas:'.$idPublik,
                'kode_lamaran' => $kode,
                'jenis' => 'BERKAS_KANDIDAT',
                'nama' => (string) ($b['nama'] ?? basename($path)),
                'path' => $path,
                'mime' => $b['mime'] ?? null,
                'ukuran' => isset($b['ukuran']) ? (int) $b['ukuran'] : null,
                'sha256' => null,
                'aktif' => true,
            ]);
        }

        if (empty($tes->Unggah_Kirim_At)) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $tes->Id_Lamaran_Tahap_Tes)->update([
                'Unggah_Kirim_At' => $dikirim,
                'Unggah_Kirim_By' => mb_substr($nama, 0, 150) ?: 'KANDIDAT',
                'Unggah_Kirim_Ip' => isset($muatan['ip']) ? Str::limit((string) $muatan['ip'], 60, '') : null,
                'Updated_At' => now(),
                'Updated_By' => mb_substr($nama, 0, 150) ?: 'KANDIDAT',
            ]);
        }

        return HasilPenanganan::ok('aktivitas:'.$tes->Id_Lamaran_Tahap_Tes, "Berkas diterima ({$jumlah} berkas).")
            ->segarkan((int) $tes->Lamaran_Id);
    }
}
