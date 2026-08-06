<?php

namespace App\Http\Controllers\Career\MasterWorkplace;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER WORKPLACE (tipe LOKASI kerja: On-site / Hybrid / Remote).
 *
 * Dipakai N_WEB_CAREERS_Detail_MPP.Workplace_Type → tampil di form lowongan MPP,
 * kartu lowongan landing page, dan Monitoring MPP. Karena itu baris yang sudah
 * dipakai lowongan TIDAK boleh dihapus — hanya dinonaktifkan.
 *
 * CATATAN SKEMA — tabel ini lebih tua dari master lain:
 *   - tidak punya Kode / Urutan / Ikon / Warna / Flag_Sistem;
 *   - Created_By & Updated_By bertipe INT (Id_Users), bukan varchar nama +
 *     kolom *_By_Id terpisah seperti master yang lebih baru.
 * Jadi nama pembuat diambil lewat join ke N_WEB_CAREERS_Users, dan ikon/warna
 * kartu diturunkan di sisi Vue dari nama tipe (deterministik).
 */
class MasterWorkplaceController extends Controller
{
    private string $tbl = 'N_WEB_CAREERS_Master_Workplace';

    private string $pk = 'Id_Workplace';

    public function index()
    {
        return Inertia::render('Career/admin/master-workplace/masterWorkplace', CareerShell::props('/master-workplace', 'Master Workplace'));
    }

    /** Jumlah lowongan pemakai per tipe — jadi badge di tabel SEKALIGUS alasan tombol hapus dikunci. */
    private function pemakaian()
    {
        return DB::table('N_WEB_CAREERS_Detail_MPP')
            ->select('Workplace_Type', DB::raw('COUNT(*) as Jumlah'))
            ->whereNotNull('Workplace_Type')
            ->groupBy('Workplace_Type');
    }

    public function list()
    {
        try {
            // Urut menurut Id, bukan abjad: urutan seed sudah berjenjang dari
            // paling "di kantor" ke paling "jauh dari kantor" (WFO → Hybrid → WFH),
            // dan itu urutan yang diharapkan muncul di pilihan MPP.
            $rows = DB::table($this->tbl . ' as w')
                ->leftJoin('N_WEB_CAREERS_Users as uc', 'uc.Id_Users', '=', 'w.Created_By')
                ->leftJoin('N_WEB_CAREERS_Users as uu', 'uu.Id_Users', '=', 'w.Updated_By')
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Workplace_Type', '=', 'w.Id_Workplace')
                ->orderBy('w.' . $this->pk)
                ->select('w.*', 'uc.Nama as Pembuat', 'uu.Nama as Pengubah', DB::raw('ISNULL(p.Jumlah, 0) as Dipakai'))
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Workplace),
                    'nama' => $r->Nama_Workplace,
                    'keterangan' => $r->Keterangan,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'dipakai' => (int) $r->Dipakai,
                    'createdBy' => $r->Pembuat,
                    'createdAt' => $r->Created_At,
                    'updatedBy' => $r->Pengubah,
                    'updatedAt' => $r->Updated_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data tipe lokasi kerja dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat master workplace: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data tipe lokasi kerja', 500);
        }
    }

    /** Batas panjang mengikuti lebar kolom asli (varchar 100) — bukan angka karangan. */
    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:100',
        ];
    }

    /**
     * Nama tipe harus unik. Collation SQL Server di sini case-insensitive, tapi
     * LOWER+TRIM dipakai eksplisit supaya "hybrid " dan "Hybrid" tetap tertangkap
     * seandainya database dipindah ke collation case-sensitive.
     */
    private function namaBentrok(string $nama, ?int $kecualiId = null): bool
    {
        return DB::table($this->tbl)
            ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Workplace))) = ?', [mb_strtolower(trim($nama))])
            ->when($kecualiId, fn ($q) => $q->where($this->pk, '!=', $kecualiId))
            ->exists();
    }

    /** Jumlah lowongan yang memakai tipe ini. */
    private function jumlahPemakai(int $id): int
    {
        return DB::table('N_WEB_CAREERS_Detail_MPP')->where('Workplace_Type', $id)->count();
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());

            if ($this->namaBentrok($data['nama'])) {
                return ResponseHelper::error('Nama tipe lokasi kerja sudah dipakai.', 422);
            }

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            DB::table($this->tbl)->insert([
                'Nama_Workplace' => trim($data['nama']),
                'Keterangan' => $data['keterangan'] ? trim($data['keterangan']) : null,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userId,
            ]);

            Log::channel('web_career')->info("Master workplace dibuat ({$data['nama']}) oleh {$userName}");

            return ResponseHelper::success(null, 'Tipe lokasi kerja berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat master workplace: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table($this->tbl)->where($this->pk, $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules());

            if ($this->namaBentrok($data['nama'], (int) $realId)) {
                return ResponseHelper::error('Nama tipe lokasi kerja sudah dipakai.', 422);
            }

            DB::table($this->tbl)->where($this->pk, $realId)->update([
                'Nama_Workplace' => trim($data['nama']),
                'Keterangan' => $data['keterangan'] ? trim($data['keterangan']) : null,
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master workplace #{$id} diperbarui");

            return ResponseHelper::success(null, 'Tipe lokasi kerja diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update master workplace #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Nonaktifkan / aktifkan. Tipe nonaktif hilang dari pilihan lowongan BARU,
     * tapi lowongan lama yang sudah memakainya tetap utuh — inilah jalan keluar
     * yang benar untuk tipe yang tidak boleh dihapus.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table($this->tbl)->where($this->pk, $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.id'),
            ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master workplace #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle master workplace #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table($this->tbl)->where($this->pk, $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Penjaga terakhir: UI sudah menonaktifkan tombolnya, tapi permintaan
            // bisa datang dari mana saja — hitung ulang di sini, jangan percaya klien.
            $dipakai = $this->jumlahPemakai((int) $realId);
            if ($dipakai > 0) {
                return ResponseHelper::error("Tipe ini dipakai {$dipakai} lowongan — nonaktifkan saja.", 422);
            }

            DB::table($this->tbl)->where($this->pk, $realId)->delete();
            Log::channel('web_career')->info("Master workplace #{$id} dihapus");

            return ResponseHelper::success(null, 'Tipe lokasi kerja dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus master workplace #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
