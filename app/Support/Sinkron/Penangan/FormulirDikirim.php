<?php

namespace App\Support\Sinkron\Penangan;

use App\Jobs\Career\WcBiodataHrisJob;
use App\Support\Career\LamaranService;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;
use App\Support\Sinkron\SalinBerkas;
use Illuminate\Support\Facades\DB;

/**
 * Formulir.Dikirim — kandidat mengirim formulir sebuah tahap (bukan
 * pendaftaran) di situs kandidat.
 *
 * Sama dengan kirim formulir yang lama: LamaranService::simpanPengisian
 * (skema yang dibekukan, syarat auto-gugur, rekomendasi mesin) lalu berkas
 * draf menjadi Formulir_Berkas — berkasnya disalin dari karantina dulu.
 * Sesudah commit: biodata HRIS (data diri kandidat masuk di formulir ini).
 */
final class FormulirDikirim implements Penangan
{
    public function __construct(private LamaranService $svc) {}

    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $userId = PetaId::akunAdmin((int) $muatan['akun']['id_publik']);
        $tahapId = (int) $muatan['tahap_id'];

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap', $tahapId)
            ->where('l.Kode', (string) $muatan['kode'])
            ->where('l.Id_Users', $userId)
            ->first(['t.Id_Lamaran_Tahap', 't.Lamaran_Id', 't.Formulir_Pengisian_Id', 'l.Id_Lamaran']);
        if (! $tahap) {
            throw new PeristiwaDitolak('Tahap formulir ini tidak ditemukan.');
        }
        if ($tahap->Formulir_Pengisian_Id) {
            throw new PeristiwaDitolak('Formulir tahap ini sudah terkirim sebelumnya.');
        }

        $berkas = [];
        foreach ((array) ($muatan['berkas'] ?? []) as $b) {
            if (! empty($b['path']) && ! empty($b['field'])) {
                $berkas[] = $b + ['path_admin' => SalinBerkas::dariKarantina((string) $b['path'])];
            }
        }

        $nama = (string) DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->value('Nama');
        $jawaban = is_array($muatan['jawaban']) ? $muatan['jawaban'] : [];
        $r = $this->svc->simpanPengisian($tahapId, $userId, $jawaban, isset($muatan['ip']) ? mb_substr((string) $muatan['ip'], 0, 45) : null, $nama);
        if (! ($r['ok'] ?? false)) {
            throw new PeristiwaDitolak((string) ($r['pesan'] ?? 'Formulir tidak dapat diproses.'));
        }

        $pengisianId = (int) ($r['pengisianId'] ?? DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $tahapId)->value('Formulir_Pengisian_Id'));
        $now = now();
        foreach (array_values($berkas) as $i => $b) {
            $ext = strtolower((string) ($b['ext'] ?? pathinfo((string) $b['path'], PATHINFO_EXTENSION)) ?: 'pdf');
            DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                'Formulir_Pengisian_Id' => $pengisianId,
                'Id_Users' => $userId,
                'Bagian_Key' => $b['bagian'] ?? null,
                'Baris_Index' => isset($b['baris']) ? (int) $b['baris'] : null,
                'Field_Key' => (string) $b['field'],
                'Urutan' => $i + 1,
                'Nama_Asli' => mb_substr((string) ($b['nama'] ?? 'berkas.'.$ext), 0, 250),
                'Path_File' => $b['path_admin'],
                'Ukuran_Byte' => (int) ($b['ukuran'] ?? 0),
                'Mime' => $b['mime'] ?? null,
                'Ekstensi' => mb_substr($ext, 0, 10),
                'Status_Verifikasi' => 'BELUM',
                'Waktu_Unggah' => $now,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
            ]);
        }

        if (! empty($muatan['formulir']['pengisian_kode'])) {
            PetaId::pasang(PetaId::FORMULIR, (string) $muatan['formulir']['pengisian_kode'], $pengisianId);
        }

        return HasilPenanganan::ok('pengisian:'.$pengisianId, (string) ($r['pesan'] ?? 'Formulir terkirim.'))
            ->segarkan((int) $tahap->Id_Lamaran)
            ->lalu(fn () => WcBiodataHrisJob::dispatch($userId, 'KIRIM_FORMULIR'));
    }
}
