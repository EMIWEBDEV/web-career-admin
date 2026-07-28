<?php

namespace App\Http\Controllers\Career\TalentPool;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — TALENT POOL.
 *
 * Kolam kandidat "bagus tapi belum terpakai": diisi otomatis saat admin menekan
 * "Masuk Talent Pool" di Worklist (lihat LamaranService::simpanKeTalentPool).
 * Halaman ini hanya MEMBACA + mengelola status kartu (aktif/ditarik/arsip) —
 * tidak menyentuh alur seleksi. Semua data dari N_WEB_CAREERS_Talent_Pool.
 */
class TalentPoolController extends Controller
{
    /** Halaman Inertia (data di-fetch sendiri ke list()). */
    public function index()
    {
        return Inertia::render('Career/admin/talent-pool/talentPool', CareerShell::props('/karir/talent-pool', 'Talent Pool'));
    }

    /** Data kartu Talent Pool + ringkasan angka. */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));

            $rows = DB::table('N_WEB_CAREERS_Talent_Pool as tp')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'tp.Id_Users')
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('u.Nama', 'like', "%{$q}%")
                        ->orWhere('tp.Posisi', 'like', "%{$q}%")
                        ->orWhere('tp.Program_Nama', 'like', "%{$q}%")
                        ->orWhere('tp.Tag', 'like', "%{$q}%");
                }))
                ->when(in_array($status, ['AKTIF', 'DITARIK', 'ARSIP'], true), fn ($w) => $w->where('tp.Status', $status))
                ->orderByDesc('tp.Id_Talent_Pool')
                ->select('tp.*', 'u.Nama as Kandidat', 'u.Email as KandidatEmail')
                ->get();

            $data = $rows->map(fn ($r) => [
                'id' => Hashids::encode($r->Id_Talent_Pool),
                'kandidat' => $r->Kandidat ?: '—',
                'email' => $r->KandidatEmail,
                'posisi' => $r->Posisi ?: '—',
                'program' => $r->Program_Nama ?: '—',
                'tahapAsal' => $r->Tahap_Asal,
                'skor' => $r->Skor !== null ? (float) $r->Skor : null,
                'tag' => $r->Tag,
                'catatan' => $r->Catatan,
                'status' => $r->Status,
                'createdBy' => $r->Created_By ?: 'Sistem',
                'createdAt' => $r->Created_At,
            ])->values();

            $ringkas = [
                'total' => $data->count(),
                'aktif' => $data->where('status', 'AKTIF')->count(),
                'ditarik' => $data->where('status', 'DITARIK')->count(),
                'arsip' => $data->where('status', 'ARSIP')->count(),
            ];

            return ResponseHelper::success(['data' => $data, 'ringkas' => $ringkas], 'Data talent pool dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data talent pool', 500);
        }
    }

    /** Ubah kartu: status (AKTIF/DITARIK/ARSIP), tag, catatan. */
    public function ubah(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak valid.', 422);
            }

            $data = $request->validate([
                'status' => 'required|in:AKTIF,DITARIK,ARSIP',
                'tag' => 'nullable|string|max:150',
                'catatan' => 'nullable|string|max:1000',
            ]);

            $ubah = [
                'Status' => $data['status'],
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ];
            if ($request->has('tag')) {
                $ubah['Tag'] = $data['tag'] ?? null;
            }
            if ($request->has('catatan')) {
                $ubah['Catatan'] = $data['catatan'] ?? null;
            }

            $terpengaruh = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->update($ubah);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Talent Pool #{$realId} diperbarui → {$data['status']}");

            return ResponseHelper::success(null, 'Kartu talent pool diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal ubah talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Hapus kartu dari kolam (permanen). */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->delete();
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Talent Pool #{$realId} dihapus");

            return ResponseHelper::success(null, 'Kartu dihapus dari Talent Pool');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
