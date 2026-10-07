<?php

namespace App\Support\Sinkron\Penangan;

use App\Support\Career\KonfirmasiJadwal;
use App\Support\Sinkron\Keluar;
use App\Support\Sinkron\PetaId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lamaran.Dibatalkan — kandidat membatalkan lamarannya di situs kandidat
 * (baris publiknya sudah dihapus di sana).
 *
 *   Lamaran yang belum ke mana-mana (belum ada jadwal, undangan, berkas
 *   aktivitas, keputusan) dihapus — perilaku pembatalan yang lama.
 *   Lamaran yang sudah berjalan TIDAK dihapus: jejak jadwal, undangan, dan
 *   hasilnya tetap milik tim. Lamarannya ditutup MUNDUR, undangan & permintaan
 *   jadwal yang masih terbuka ditutup.
 */
final class LamaranDibatalkan implements Penangan
{
    public const ALASAN = 'Kandidat membatalkan lamaran dari situs kandidat.';

    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $kode = (string) $muatan['kode'];
        $userId = PetaId::akunAdmin((int) $muatan['akun']['id_publik']);

        $l = DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $kode)->where('Id_Users', $userId)->first();
        if (! $l) {
            // Lamarannya tidak pernah masuk (Lamaran.Dikirim ditolak) — beres.
            return HasilPenanganan::ok(null, 'Lamaran dibatalkan.');
        }
        if ($l->Status !== 'BERJALAN') {
            return HasilPenanganan::ok('lamaran:'.$l->Id_Lamaran, 'Lamaran sudah selesai diproses.');
        }

        $tahapIds = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $l->Id_Lamaran)->pluck('Id_Lamaran_Tahap')->all();
        $tesIds = $tahapIds
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Lamaran_Tahap_Id', $tahapIds)->pluck('Id_Lamaran_Tahap_Tes')->all()
            : [];

        if (self::belumBerjalan((int) $l->Id_Lamaran, $tahapIds, $tesIds)) {
            $pengisianIds = DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $l->Id_Lamaran)->pluck('Id_Formulir_Pengisian')->all();
            if ($pengisianIds) {
                DB::table('N_WEB_CAREERS_Formulir_Berkas')->whereIn('Formulir_Pengisian_Id', $pengisianIds)->delete();
            }
            DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->where('Lamaran_Id', $l->Id_Lamaran)->delete();
            DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $l->Id_Lamaran)->delete();
            if ($tesIds) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Id_Lamaran_Tahap_Tes', $tesIds)->delete();
            }
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $l->Id_Lamaran)->delete();
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $l->Id_Lamaran)->delete();
            DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $kode)->update([
                'Status' => 'DIBATALKAN',
                'Updated_At' => now(),
                'Updated_By' => 'SINKRON',
            ]);
            // Lamarannya sudah tidak ada di kedua zona — potret publiknya ikut dibuang.
            Keluar::antrekan(Keluar::PORTAL, $kode, [
                'kode' => $kode,
                'id_users' => (int) $muatan['akun']['id_publik'],
                'lamaran' => null,
                'potret' => null,
            ]);

            Log::channel('web_career')->info("Lamaran {$kode} (#{$l->Id_Lamaran}) dibatalkan kandidat — dihapus (belum berjalan).");

            return HasilPenanganan::ok(null, 'Lamaran dibatalkan.');
        }

        $tahapKini = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $l->Id_Lamaran)->where('Status', 'BERJALAN')
            ->orderBy('Urutan')->first(['Id_Lamaran_Tahap', 'Label']);

        DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $l->Id_Lamaran)->update([
            'Status' => 'MUNDUR',
            'Waktu_Selesai' => now(),
            'Gugur_Di_Tahap' => $tahapKini->Label ?? $l->Gugur_Di_Tahap,
            'Alasan_Gugur' => self::ALASAN,
            'Updated_At' => now(),
            'Updated_By' => 'KANDIDAT (situs kandidat)',
        ]);
        foreach ($tahapIds as $tahapId) {
            KonfirmasiJadwal::tutupTahap((int) $tahapId, 'MUNDUR');
        }

        Log::channel('web_career')->info("Lamaran {$kode} (#{$l->Id_Lamaran}) dibatalkan kandidat — ditutup MUNDUR (sudah berjalan).");

        return HasilPenanganan::ok('lamaran:'.$l->Id_Lamaran, 'Lamaran dibatalkan.')->segarkan((int) $l->Id_Lamaran);
    }

    /** Belum ada satu pun jejak proses selain pendaftaran itu sendiri. */
    private static function belumBerjalan(int $lamaranId, array $tahapIds, array $tesIds): bool
    {
        $diputus = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $lamaranId)
            ->where(fn ($q) => $q->whereNotNull('Hasil')->orWhereNotIn('Status', ['BERJALAN', 'MENUNGGU']))
            ->exists();
        if ($diputus) {
            return false;
        }

        $ada = fn (string $tabel, string $kolom, array $ids) => $ids && DB::table($tabel)->whereIn($kolom, $ids)->exists();

        return ! $ada('N_WEB_CAREERS_CRM_Konfirmasi_Email', 'Lamaran_Tahap_Tes_Id', $tesIds)
            && ! $ada('N_WEB_CAREERS_Lamaran_Tahap_Tes_Jadwal_Permintaan', 'Lamaran_Tahap_Tes_Id', $tesIds)
            && ! $ada('N_WEB_CAREERS_Lamaran_Tes_Berkas', 'Lamaran_Tahap_Tes_Id', $tesIds)
            && ! $ada('N_WEB_CAREERS_Lamaran_Tahap_Berkas', 'Lamaran_Tahap_Id', $tahapIds)
            && ! DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Lamaran_Id', $lamaranId)->exists()
            && ! DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Lamaran_Id', $lamaranId)->exists()
            && ! ($tesIds && DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Id_Lamaran_Tahap_Tes', $tesIds)
                ->where(fn ($q) => $q->whereNotNull('Jadwal_Mulai')->orWhere('Flag_Selesai', 'Y'))->exists());
    }
}
