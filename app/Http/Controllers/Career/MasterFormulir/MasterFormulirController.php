<?php

namespace App\Http\Controllers\Career\MasterFormulir;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\KodeUnik;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER FORMULIR (katalog).
 *
 * Tabel ini SENGAJA tidak menyimpan pertanyaan. Skema formulir hidup di kode
 * (resources/js/Components/career/formulir/Formulir1.vue, Formulir2.vue),
 * karena bentuk formulir rekrutmen tidak bisa ditebak dari awal dan
 * menyusunnya lewat UI berisiko menghasilkan formulir rusak.
 *
 * Yang disimpan di sini hanya: nama, kategori, dan KOMPONEN mana yang dipakai.
 * Menambah formulir baru = buat komponen Vue + daftarkan di registry.js +
 * satu baris di tabel ini. Tidak ada ALTER TABLE, karena jawaban kandidat
 * tersimpan sebagai JSON di N_WEB_CAREERS_Formulir_Pengisian.
 */
class MasterFormulirController extends Controller
{
    /**
     * Komponen yang tersedia. HARUS sama persis dengan kunci pada
     * resources/js/Components/career/formulir/registry.js — daftar di sini
     * mencegah kode asing tersimpan dan merender halaman kosong di sisi kandidat.
     */
    private const KOMPONEN = ['FORMULIR_1', 'FORMULIR_2'];

    public function index()
    {
        return Inertia::render(
            'Career/admin/master-formulir/masterFormulir',
            CareerShell::props('/master-formulir', 'Master Formulir')
        );
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Formulir as f')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'f.Created_By_Id')
                ->orderBy('f.Id_Master_Formulir')
                ->select('f.*', 'u.Nama as Pembuat')
                ->get()
                ->map(fn ($f) => [
                    'id' => Hashids::encode($f->Id_Master_Formulir),
                    'kode' => $f->Kode,
                    'nama' => $f->Nama,
                    'kategori' => $f->Kategori,
                    'deskripsi' => $f->Deskripsi,
                    'komponen' => $f->Komponen_Kode ?? null,
                    'petunjuk' => $f->Petunjuk ?? null,
                    'status' => $f->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $f->Pembuat ?: $f->Created_By,
                    'createdAt' => $f->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data formulir dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat formulir: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data formulir', 500);
        }
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:120',
            'kategori' => 'nullable|string|max:20',
            'deskripsi' => 'nullable|string|max:500',
            'komponen' => 'required|in:' . implode(',', self::KOMPONEN),
            'petunjuk' => 'nullable|string|max:500',
        ];
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            $kode = $this->kodeUnik($data['nama']);

            DB::table('N_WEB_CAREERS_Master_Formulir')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'] ?? null,
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Komponen_Kode' => $data['komponen'],
                'Petunjuk' => $data['petunjuk'] ?? null,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master formulir dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Formulir berhasil didaftarkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat formulir: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());

            DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->update([
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'] ?? null,
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Komponen_Kode' => $data['komponen'],
                'Petunjuk' => $data['petunjuk'] ?? null,
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master formulir #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Formulir diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'T',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Jangan hapus formulir yang jawabannya sudah ada — riwayat lamaran
            // kandidat akan kehilangan acuan pertanyaannya.
            $dipakai = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                ->where('Master_Formulir_Id', $realId)->count();
            if ($dipakai) {
                return ResponseHelper::error("Formulir sudah dipakai {$dipakai} pengisian — nonaktifkan saja, jangan dihapus.", 422);
            }

            // Tahan juga bila masih dirujuk tahap alur, supaya alur tidak menunjuk kosong.
            $dipakaiAlur = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Formulir_Kode', $row->Kode)->count();
            if ($dipakaiAlur) {
                return ResponseHelper::error("Formulir masih dipakai {$dipakaiAlur} tahap alur seleksi.", 422);
            }

            DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->delete();

            Log::channel('web_career')->info("Master formulir #{$realId} dihapus");

            return ResponseHelper::success(null, 'Formulir dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    private function kodeUnik(string $nama): string
    {
        // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
        $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Formulir', 'Kode', $nama, 30, 'FORMULIR');
        return $kode;
    }
}
