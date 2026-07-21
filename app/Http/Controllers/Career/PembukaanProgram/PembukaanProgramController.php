<?php

namespace App\Http\Controllers\Career\PembukaanProgram;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PEMBUKAAN PROGRAM (publikasi 1 program ke channel + window; detail: kampus target).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel + Hashids.
 */
class PembukaanProgramController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/pembukaan-program/pembukaanProgram', CareerShell::props('/karir/pembukaan', 'Pembukaan Program'));
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Pembukaan as pb')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'pb.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'pb.Program_Batch_Id')
                ->orderByDesc('pb.Id_Pembukaan')
                ->select('pb.*', 'u.Nama as Pembuat', 'p.Nama as ProgramNama', 'p.Kode as ProgramKode', 'p.Kategori as ProgramKategori', 'b.Nama as BatchNama')
                ->get();

            $kampus = DB::table('N_WEB_CAREERS_Pembukaan_Kampus as pk')
                ->leftJoin('N_WEB_CAREERS_Master_Kampus as k', 'k.Id_Master_Kampus', '=', 'pk.Master_Kampus_Id')
                ->select('pk.Pembukaan_Id', 'pk.Master_Kampus_Id', 'k.Nama as KampusNama')
                ->get()->groupBy('Pembukaan_Id');

            $data = $rows->map(fn ($r) => [
                'id' => Hashids::encode($r->Id_Pembukaan),
                'kode' => $r->Kode,
                'program' => $r->ProgramKode,
                'programNama' => $r->ProgramNama,
                'kategori' => $r->ProgramKategori,
                'channel' => $r->Channel,
                'masaBerlaku' => $r->Masa_Berlaku,
                'buka' => $r->Tanggal_Buka,
                'tutup' => $r->Tanggal_Tutup,
                'batchNama' => $r->BatchNama,
                'statusPublish' => $r->Status_Publish,
                'kampusIds' => collect($kampus->get($r->Id_Pembukaan, []))->map(fn ($x) => $x->Master_Kampus_Id)->values(),
                'kampus' => collect($kampus->get($r->Id_Pembukaan, []))->map(fn ($x) => $x->KampusNama)->values(),
                'createdBy' => $r->Pembuat ?: $r->Created_By,
                'createdAt' => $r->Created_At,
            ])->values();

            return ResponseHelper::success($data, 'Data pembukaan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat pembukaan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data pembukaan', 500);
        }
    }

    private function rules(): array
    {
        return [
            'program' => 'required|string|max:40',
            'channel' => 'required|in:UMUM,KAMPUS',
            'masaBerlaku' => 'required|in:BERBATAS,EVERGREEN',
            'buka' => 'nullable|date',
            'tutup' => 'nullable|date',
            'statusPublish' => 'required|in:DRAFT,TERBIT',
            'kampusIds' => 'nullable|array',
            'kampusIds.*' => 'integer',
        ];
    }

    private function programId(string $kode): ?int
    {
        return DB::table('N_WEB_CAREERS_Program')->where('Kode', $kode)->value('Id_Program');
    }

    private function simpanKampus(int $pembukaanId, array $kampusIds, ?int $userId): void
    {
        foreach (array_unique($kampusIds) as $kid) {
            DB::table('N_WEB_CAREERS_Pembukaan_Kampus')->insert(['Pembukaan_Id' => $pembukaanId, 'Master_Kampus_Id' => (int) $kid, 'Created_By_Id' => $userId, 'Updated_By_Id' => $userId]);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $programId = $this->programId($data['program']);
            if (! $programId) {
                return ResponseHelper::error('Program tidak ditemukan.', 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            $kode = 'PUB-' . str_pad((string) (DB::table('N_WEB_CAREERS_Pembukaan')->max('Id_Pembukaan') + 1), 3, '0', STR_PAD_LEFT);
            $kampus = $data['channel'] === 'KAMPUS' ? ($data['kampusIds'] ?? []) : [];

            DB::transaction(function () use ($data, $kode, $programId, $kampus, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Pembukaan')->insertGetId([
                    'Kode' => $kode, 'Program_Id' => $programId, 'Channel' => $data['channel'], 'Masa_Berlaku' => $data['masaBerlaku'],
                    'Tanggal_Buka' => $data['buka'] ?? null, 'Tanggal_Tutup' => $data['masaBerlaku'] === 'EVERGREEN' ? null : ($data['tutup'] ?? null),
                    'Program_Batch_Id' => null, 'Status_Publish' => $data['statusPublish'],
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId, 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Pembukaan');
                $this->simpanKampus($id, $kampus, $userId);
            });

            Log::channel('web_career')->info("Pembukaan dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Pembukaan berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat pembukaan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());
            $programId = $this->programId($data['program']);
            if (! $programId) {
                return ResponseHelper::error('Program tidak ditemukan.', 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $kampus = $data['channel'] === 'KAMPUS' ? ($data['kampusIds'] ?? []) : [];

            DB::transaction(function () use ($data, $realId, $programId, $kampus, $userId, $userName) {
                DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->update([
                    'Program_Id' => $programId, 'Channel' => $data['channel'], 'Masa_Berlaku' => $data['masaBerlaku'],
                    'Tanggal_Buka' => $data['buka'] ?? null, 'Tanggal_Tutup' => $data['masaBerlaku'] === 'EVERGREEN' ? null : ($data['tutup'] ?? null),
                    'Status_Publish' => $data['statusPublish'],
                    'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
                DB::table('N_WEB_CAREERS_Pembukaan_Kampus')->where('Pembukaan_Id', $realId)->delete();
                $this->simpanKampus($realId, $kampus, $userId);
            });

            Log::channel('web_career')->info("Pembukaan #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Pembukaan diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->update([
                'Status_Publish' => $aktif ? 'TERBIT' : 'DRAFT',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Pembukaan #{$realId} status " . ($aktif ? 'TERBIT' : 'DRAFT'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                DB::table('N_WEB_CAREERS_Pembukaan_Kampus')->where('Pembukaan_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->delete();
            });
            Log::channel('web_career')->info("Pembukaan #{$realId} dihapus");

            return ResponseHelper::success(null, 'Pembukaan dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
