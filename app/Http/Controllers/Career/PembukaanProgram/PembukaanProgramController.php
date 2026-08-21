<?php

namespace App\Http\Controllers\Career\PembukaanProgram;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PEMBUKAAN PROGRAM (publikasi 1 program ke landing + window pendaftaran).
 * Channel UMUM/KAMPUS SUDAH DIGABUNG: semua pembukaan bersifat publik (kolom Channel
 * dipertahankan sebagai 'UMUM' agar skema lama tetap valid). Pembatasan kampus mitra
 * diatur lewat SYARAT (operator ADA_DI) di Program Kegiatan, bukan whitelist di sini.
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel + Hashids.
 */
class PembukaanProgramController extends Controller
{
    /** Nilai channel tunggal setelah UMUM & KAMPUS digabung. */
    private const CHANNEL = 'UMUM';

    public function index()
    {
        return Inertia::render('Career/admin/pembukaan-program/pembukaanProgram', CareerShell::props('/karir/pembukaan', 'Pembukaan Program'));
    }

    public function list(Request $request)
    {
        try {
            // Pola & perilaku SAMA dengan Program Kegiatan: filter server-side,
            // urut terbaru di atas, dan tab kategori dari master + hak akses.
            $q = trim((string) $request->query('q', ''));
            $kategori = trim((string) $request->query('kategori', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            $izin = AksesService::kategoriDiizinkan('pembukaanPage');

            $dasar = fn () => DB::table('N_WEB_CAREERS_Pembukaan as pb')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'pb.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'pb.Program_Batch_Id')
                ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('pb.Kode', 'like', "%{$q}%")
                        ->orWhere('p.Nama', 'like', "%{$q}%")
                        ->orWhere('p.Kode', 'like', "%{$q}%")
                        ->orWhere('b.Nama', 'like', "%{$q}%");
                }))
                ->when(in_array($status, ['TERBIT', 'DRAFT'], true), fn ($w) => $w->where('pb.Status_Publish', $status))
                ->when($dari, fn ($w) => $w->whereDate('pb.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('pb.Created_At', '<=', $sampai));

            $hitungKategori = (clone $dasar())
                ->select('p.Kategori', DB::raw('COUNT(*) as jml'))
                ->groupBy('p.Kategori')
                ->pluck('jml', 'Kategori');

            $rows = $dasar()
                ->when($kategori !== '', fn ($w) => $w->where('p.Kategori', $kategori))
                // Terbaru → terlama: berdasarkan waktu dibuat, ID sebagai cadangan.
                ->orderByDesc('pb.Created_At')
                ->orderByDesc('pb.Id_Pembukaan')
                ->select('pb.*', 'u.Nama as Pembuat', 'p.Nama as ProgramNama', 'p.Kode as ProgramKode', 'p.Kategori as ProgramKategori', 'b.Nama as BatchNama')
                ->get();

            $data = $rows->map(fn ($r) => [
                'id' => Hashids::encode($r->Id_Pembukaan),
                'kode' => $r->Kode,
                'program' => $r->ProgramKode,
                'programNama' => $r->ProgramNama,
                'kategori' => $r->ProgramKategori,
                'masaBerlaku' => $r->Masa_Berlaku,
                'buka' => $r->Tanggal_Buka,
                'tutup' => $r->Tanggal_Tutup,
                'batchNama' => $r->BatchNama,
                'statusPublish' => $r->Status_Publish,
                'createdBy' => $r->Pembuat ?: $r->Created_By,
                'createdAt' => $r->Created_At,
            ])->values();

            $tabKategori = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
                ->where('Flag_Aktif', 'Y')
                ->when($izin, fn ($w) => $w->whereIn('Kode', $izin))
                ->orderBy('Id_Master_Talent_Acquisition')
                ->get(['Kode', 'Nama'])
                ->map(fn ($k) => [
                    'kode' => $k->Kode,
                    'nama' => $k->Nama,
                    'jumlah' => (int) ($hitungKategori[$k->Kode] ?? 0),
                ])
                ->values();

            return ResponseHelper::success([
                'data' => $data,
                'kategori' => $tabKategori,
                'total' => (int) $hitungKategori->sum(),
            ], 'Data pembukaan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat pembukaan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data pembukaan', 500);
        }
    }

    /** Daftar program (BERJALAN) + detail untuk KARTU picker di modal "Buka Program". */
    public function programs()
    {
        try {
            $izin = AksesService::kategoriDiizinkan('pembukaanPage');

            $rows = DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->leftJoin('N_WEB_CAREERS_Master_Jadwal as j', 'j.Kode', '=', 'p.Jadwal_Kode')
                ->where('p.Status', 'BERJALAN')
                ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
                // Terbaru → terlama, disamakan dengan daftar Program Kegiatan:
                // waktu dibuat yang menentukan, Id hanya pemecah seri.
                ->orderByDesc('p.Created_At')
                ->orderByDesc('p.Id_Program')
                ->select('p.Kode', 'p.Nama', 'p.Kategori', 'p.Penyelenggara', 'p.Jadwal_Kode', 'a.Nama as AlurNama', 'j.Kegiatan as JadwalNama')
                ->get();

            // Jumlah posisi per program.
            $posisiCount = DB::table('N_WEB_CAREERS_Program_Posisi as x')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
                ->whereIn('p.Kode', $rows->pluck('Kode')->all() ?: [''])
                ->select('p.Kode', DB::raw('COUNT(*) as J'))->groupBy('p.Kode')->pluck('J', 'Kode');

            // Rentang tanggal dari agenda jadwal (mulai paling awal – selesai paling akhir).
            $jadwalKodes = $rows->pluck('Jadwal_Kode')->filter()->unique()->all();
            $range = $jadwalKodes
                ? DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda as ag')
                    ->join('N_WEB_CAREERS_Master_Jadwal as j', 'j.Id_Master_Jadwal', '=', 'ag.Master_Jadwal_Id')
                    ->whereIn('j.Kode', $jadwalKodes)
                    ->select('j.Kode', DB::raw('MIN(ag.Tanggal_Mulai) as mulai'), DB::raw('MAX(ag.Tanggal_Selesai) as selesai'))
                    ->groupBy('j.Kode')->get()->keyBy('Kode')
                : collect();

            $data = $rows->map(fn ($r) => [
                'kode' => $r->Kode,
                'nama' => $r->Nama,
                'kategori' => $r->Kategori,
                'penyelenggara' => $r->Penyelenggara ?: 'EVO Group',
                'alur' => $r->AlurNama,
                'jadwal' => $r->JadwalNama,
                'jumlahPosisi' => (int) ($posisiCount[$r->Kode] ?? 0),
                'tglMulai' => $r->Jadwal_Kode ? ($range[$r->Jadwal_Kode]->mulai ?? null) : null,
                'tglSelesai' => $r->Jadwal_Kode ? ($range[$r->Jadwal_Kode]->selesai ?? null) : null,
            ])->values();

            return ResponseHelper::success($data, 'Daftar program');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat program picker: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat program', 500);
        }
    }

    private function rules(): array
    {
        return [
            'program' => 'required|string|max:40',
            'masaBerlaku' => 'required|in:BERBATAS,EVERGREEN',
            'buka' => 'nullable|date',
            'tutup' => 'nullable|date',
            // Status publish tak lagi diisi di form CREATE (otomatis TERBIT).
            // Tetap diterima untuk UPDATE / toggle dari daftar.
            'statusPublish' => 'nullable|in:DRAFT,TERBIT',
        ];
    }

    private function programId(string $kode): ?int
    {
        return DB::table('N_WEB_CAREERS_Program')->where('Kode', $kode)->value('Id_Program');
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

            DB::table('N_WEB_CAREERS_Pembukaan')->insert([
                'Kode' => $kode, 'Program_Id' => $programId, 'Channel' => self::CHANNEL, 'Masa_Berlaku' => $data['masaBerlaku'],
                'Tanggal_Buka' => $data['buka'] ?? null, 'Tanggal_Tutup' => $data['masaBerlaku'] === 'EVERGREEN' ? null : ($data['tutup'] ?? null),
                // Buka Program = langsung TERBIT. Draft diatur belakangan lewat toggle di daftar.
                'Program_Batch_Id' => null, 'Status_Publish' => 'TERBIT',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId, 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

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

            DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->update([
                'Program_Id' => $programId, 'Channel' => self::CHANNEL, 'Masa_Berlaku' => $data['masaBerlaku'],
                'Tanggal_Buka' => $data['buka'] ?? null, 'Tanggal_Tutup' => $data['masaBerlaku'] === 'EVERGREEN' ? null : ($data['tutup'] ?? null),
                // Pertahankan status lama bila tak dikirim (field disembunyikan di form).
                'Status_Publish' => $data['statusPublish'] ?? $row->Status_Publish,
                'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

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
