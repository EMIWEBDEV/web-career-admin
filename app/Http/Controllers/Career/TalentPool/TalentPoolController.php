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

    /** Data kartu Talent Pool + ringkasan + paginasi (server-side). */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $tag = trim((string) $request->query('tag', ''));
            $sort = (string) $request->query('sort', 'terbaru');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(60, max(6, (int) $request->query('perPage', 12)));
            $now = now();

            // Query dasar (search + tag), dipakai ulang untuk ringkas & data.
            $base = fn () => DB::table('N_WEB_CAREERS_Talent_Pool as tp')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'tp.Id_Users')
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('u.Nama', 'like', "%{$q}%")
                        ->orWhere('tp.Posisi', 'like', "%{$q}%")
                        ->orWhere('tp.Program_Nama', 'like', "%{$q}%")
                        ->orWhere('tp.Tag', 'like', "%{$q}%");
                }))
                ->when($tag !== '', fn ($w) => $w->where('tp.Tag', 'like', "%{$tag}%"));

            // Ringkasan atas set ter-search (TANPA filter status) — status efektif.
            $ringkas = [
                'total' => $base()->count(),
                'aktif' => $base()->where('tp.Status', 'AKTIF')->where(fn ($w) => $w->whereNull('tp.Tanggal_Kedaluwarsa')->orWhere('tp.Tanggal_Kedaluwarsa', '>=', $now))->count(),
                'ditarik' => $base()->where('tp.Status', 'DITARIK')->count(),
                'arsip' => $base()->where('tp.Status', 'ARSIP')->count(),
                'kedaluwarsa' => $base()->where(fn ($w) => $w->where('tp.Status', 'KEDALUWARSA')->orWhere(fn ($z) => $z->where('tp.Status', 'AKTIF')->whereNotNull('tp.Tanggal_Kedaluwarsa')->where('tp.Tanggal_Kedaluwarsa', '<', $now)))->count(),
            ];

            // Query data + filter status efektif.
            $data = $base();
            $this->filterStatus($data, $status, $now);

            $total = (clone $data)->count();

            // Urutan.
            match ($sort) {
                'lama' => $data->orderBy('tp.Id_Talent_Pool'),
                'skor' => $data->orderByDesc('tp.Skor'),
                'kedaluwarsa' => $data->orderBy('tp.Tanggal_Kedaluwarsa'),
                'nama' => $data->orderBy('u.Nama'),
                default => $data->orderByDesc('tp.Id_Talent_Pool'),
            };

            $rows = $data->offset(($page - 1) * $perPage)->limit($perPage)
                ->select('tp.*', 'u.Nama as Kandidat', 'u.Email as KandidatEmail')->get();

            return ResponseHelper::success([
                'data' => $rows->map(fn ($r) => $this->bentukKartu($r, $now))->values(),
                'ringkas' => $ringkas,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPage' => (int) ceil($total / $perPage),
            ], 'Data talent pool dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data talent pool', 500);
        }
    }

    /** Terapkan filter STATUS EFEKTIF (KEDALUWARSA dihitung dari tanggal). */
    private function filterStatus($query, string $status, $now): void
    {
        match ($status) {
            'AKTIF' => $query->where('tp.Status', 'AKTIF')->where(fn ($w) => $w->whereNull('tp.Tanggal_Kedaluwarsa')->orWhere('tp.Tanggal_Kedaluwarsa', '>=', $now)),
            'KEDALUWARSA' => $query->where(fn ($w) => $w->where('tp.Status', 'KEDALUWARSA')->orWhere(fn ($z) => $z->where('tp.Status', 'AKTIF')->whereNotNull('tp.Tanggal_Kedaluwarsa')->where('tp.Tanggal_Kedaluwarsa', '<', $now))),
            'DITARIK' => $query->where('tp.Status', 'DITARIK'),
            'ARSIP' => $query->where('tp.Status', 'ARSIP'),
            default => null,
        };
    }

    /** Bentuk satu baris DB → kartu untuk frontend. */
    private function bentukKartu($r, $now): array
    {
        $exp = $r->Tanggal_Kedaluwarsa ? \Illuminate\Support\Carbon::parse($r->Tanggal_Kedaluwarsa) : null;
        $habis = $exp ? $exp->isPast() : false;
        $status = ($r->Status === 'AKTIF' && $habis) ? 'KEDALUWARSA' : $r->Status;

        return [
            'id' => Hashids::encode($r->Id_Talent_Pool),
            'kandidat' => $r->Kandidat ?: '—',
            'email' => $r->KandidatEmail,
            'posisi' => $r->Posisi ?: '—',
            'program' => $r->Program_Nama ?: '—',
            'tahapAsal' => $r->Tahap_Asal,
            'skor' => $r->Skor !== null ? (float) $r->Skor : null,
            'tag' => $r->Tag,
            'catatan' => $r->Catatan,
            'status' => $status,
            'tanggalKedaluwarsa' => $exp ? $exp->format('d M Y') : null,
            'sisaHari' => $exp ? (int) $now->diffInDays($exp, false) : null,
            'kedaluwarsa' => $habis,
            'createdBy' => $r->Created_By ?: 'Sistem',
            'createdAt' => $r->Created_At,
        ];
    }

    /** Aksi massal: ubah status banyak kartu sekaligus (arsip/aktif/ditarik). */
    public function bulk(Request $request)
    {
        try {
            $data = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'string',
                'aksi' => 'required|in:ARSIP,AKTIF,DITARIK,HAPUS,PERPANJANG',
            ]);

            $realIds = collect($data['ids'])->map(fn ($h) => Hashids::decode($h)[0] ?? null)->filter()->values()->all();
            if (! $realIds) {
                return ResponseHelper::error('Tidak ada kartu valid.', 422);
            }

            $now = now();
            $nama = session('career_auth.nama', 'ADMIN');
            $uid = session('career_auth.id');
            $tabel = DB::table('N_WEB_CAREERS_Talent_Pool')->whereIn('Id_Talent_Pool', $realIds);

            if ($data['aksi'] === 'HAPUS') {
                $n = $tabel->delete();
            } elseif ($data['aksi'] === 'PERPANJANG') {
                $n = $tabel->update(['Status' => 'AKTIF', 'Tanggal_Kedaluwarsa' => \App\Support\Career\LamaranService::hitungKedaluwarsa($now), 'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $uid]);
            } else {
                $n = $tabel->update(['Status' => $data['aksi'], 'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $uid]);
            }

            Log::channel('web_career')->info("Talent Pool bulk {$data['aksi']}: {$n} kartu oleh {$nama}");

            return ResponseHelper::success(['jumlah' => $n], "{$n} kartu diproses.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal bulk talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses aksi massal', 500);
        }
    }

    /** Ekspor CSV daftar Talent Pool (mengikuti filter search/tag/status). */
    public function export(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));
        $tag = trim((string) $request->query('tag', ''));
        $now = now();

        $query = DB::table('N_WEB_CAREERS_Talent_Pool as tp')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'tp.Id_Users')
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                $x->where('u.Nama', 'like', "%{$q}%")->orWhere('tp.Posisi', 'like', "%{$q}%")->orWhere('tp.Program_Nama', 'like', "%{$q}%")->orWhere('tp.Tag', 'like', "%{$q}%");
            }))
            ->when($tag !== '', fn ($w) => $w->where('tp.Tag', 'like', "%{$tag}%"));
        $this->filterStatus($query, $status, $now);

        $rows = $query->orderByDesc('tp.Id_Talent_Pool')->select('tp.*', 'u.Nama as Kandidat', 'u.Email as KandidatEmail')->get();

        $nama = 'talent-pool-' . $now->format('Ymd-His') . '.csv';
        $header = ['Kandidat', 'Email', 'Posisi', 'Program', 'Tahap Asal', 'Skor', 'Tag', 'Status', 'Masuk', 'Kedaluwarsa'];

        return response()->streamDownload(function () use ($rows, $now, $header) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $r) {
                $exp = $r->Tanggal_Kedaluwarsa ? \Illuminate\Support\Carbon::parse($r->Tanggal_Kedaluwarsa) : null;
                $st = ($r->Status === 'AKTIF' && $exp && $exp->isPast()) ? 'KEDALUWARSA' : $r->Status;
                fputcsv($out, [
                    $r->Kandidat, $r->KandidatEmail, $r->Posisi, $r->Program_Nama, $r->Tahap_Asal,
                    $r->Skor, $r->Tag, $st,
                    $r->Tanggal_Masuk ? \Illuminate\Support\Carbon::parse($r->Tanggal_Masuk)->format('Y-m-d') : '',
                    $exp ? $exp->format('Y-m-d') : '',
                ]);
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv']);
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

    /** Perpanjang masa berlaku: reset kedaluwarsa = sekarang + durasi master aktif. */
    public function perpanjang($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak valid.', 422);
            }
            $now = now();
            $terpengaruh = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->update([
                'Status' => 'AKTIF',
                'Tanggal_Kedaluwarsa' => \App\Support\Career\LamaranService::hitungKedaluwarsa($now),
                'Updated_At' => $now,
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Talent Pool #{$realId} diperpanjang");

            return ResponseHelper::success(null, 'Masa berlaku diperpanjang');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal perpanjang talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperpanjang', 500);
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
