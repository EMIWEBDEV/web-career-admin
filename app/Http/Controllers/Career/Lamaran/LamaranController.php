<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\LamaranService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — Lamaran (sisi kandidat + worklist admin).
 *
 * Sisi kandidat  : lihat loker, melamar, isi formulir tiap tahap, pantau status.
 * Sisi admin     : worklist tahap yang menunggu keputusan + ketuk palu.
 *
 * Seluruh mutasi berat (snapshot tahap, mesin syarat, gerak lamaran) ada di
 * LamaranService supaya bisa diuji terpisah dari HTTP.
 */
class LamaranController extends Controller
{
    public function __construct(private LamaranService $svc)
    {
    }

    // ═══════════════════════ KANDIDAT ═══════════════════════

    /** /kandidat/loker — daftar posisi yang sedang dibuka. */
    public function loker()
    {
        return Inertia::render('Career/portal/Loker', CareerShell::props('/kandidat/loker', 'Cari Lowongan', [
            'loker' => $this->daftarLoker(),
        ]));
    }

    /** JSON daftar loker — posisi BUKA pada program BERJALAN. */
    public function lokerList()
    {
        return ResponseHelper::success($this->daftarLoker(), 'Daftar loker');
    }

    private function daftarLoker(): array
    {
        return DB::table('N_WEB_CAREERS_Program_Posisi as x')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
            ->where('p.Status', 'BERJALAN')
            ->where('x.Status', 'BUKA')
            ->orderBy('p.Nama')
            ->select('x.*', 'p.Nama as ProgramNama', 'p.Kategori', 'p.Warna', 'p.Kode as ProgramKode', 'p.Id_Program')
            ->get()
            ->map(fn ($x) => [
                'id' => Hashids::encode($x->Id_Program_Posisi),
                'programId' => Hashids::encode($x->Id_Program),
                'program' => $x->ProgramNama,
                'kategori' => $x->Kategori,
                'warna' => $x->Warna,
                'posisi' => $x->Posisi,
                'departemen' => $x->Departemen,
                'lokasi' => $x->Lokasi,
                'level' => $x->Level ?? null,
                'kuota' => (int) $x->Kuota,
                'mppRef' => $x->Mpp_Ref ?? null,
            ])
            ->values()
            ->all();
    }

    /** POST /api/v1/lamaran — kandidat melamar sebuah posisi. */
    public function lamar(Request $request)
    {
        $userId = (int) session('career_auth.id');
        if (! $userId) {
            return ResponseHelper::error('Sesi tidak sah.', 401);
        }

        $data = $request->validate([
            'programId' => 'required|string',
            'posisiId' => 'required|string',
        ]);

        $programId = Hashids::decode($data['programId'])[0] ?? null;
        $posisiId = Hashids::decode($data['posisiId'])[0] ?? null;
        if (! $programId || ! $posisiId) {
            return ResponseHelper::error('Data lamaran tidak valid.', 422);
        }

        try {
            $hasil = $this->svc->buatLamaran($userId, (int) $programId, (int) $posisiId);
            if (! $hasil['ok']) {
                return ResponseHelper::error($hasil['pesan'], 422);
            }

            return ResponseHelper::success(['lamaran' => Hashids::encode($hasil['lamaranId'])], $hasil['pesan'], 201);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat lamaran: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses lamaran.', 500);
        }
    }

    /** /kandidat/portal — lamaran milik kandidat yang sedang login. */
    public function portalIndex()
    {
        return Inertia::render('Career/portal/LamaranSaya', CareerShell::props('/kandidat/portal', 'Lamaran Saya', [
            'lamaran' => $this->lamaranSaya(),
        ]));
    }

    private function lamaranSaya(): array
    {
        $userId = (int) session('career_auth.id');

        return DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Users', $userId)
            ->orderByDesc('l.Id_Lamaran')
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Warna', 'x.Posisi', 'x.Lokasi')
            ->get()
            ->map(fn ($l) => [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'program' => $l->ProgramNama,
                'posisi' => $l->Posisi,
                'lokasi' => $l->Lokasi,
                'warna' => $l->Warna,
                'kategori' => $l->Kategori,
                'status' => $l->Status,
                'hasilAkhir' => $l->Hasil_Akhir,
                'urutanTahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'gugurDi' => $l->Gugur_Di_Tahap,
                'waktuLamar' => $l->Waktu_Lamar,
            ])
            ->values()
            ->all();
    }

    /** /kandidat/lamaran/{id} — detail satu lamaran + tahap aktif + formulirnya. */
    public function portalDetail(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $realId)
            ->where('l.Id_Users', $userId)
            ->select('l.*', 'p.Nama as ProgramNama', 'x.Posisi', 'x.Lokasi')
            ->first();

        if (! $lamaran) {
            abort(404);
        }

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $realId)
            ->orderBy('Urutan')
            ->get()
            ->map(fn ($t) => [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'provider' => $t->Provider,
                'formulir' => $t->Formulir_Kode,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
                'sudahIsi' => (bool) $t->Formulir_Pengisian_Id,
            ])
            ->values();

        // Tahap aktif yang menuntut formulir & belum diisi -> kandidat kerjakan.
        $aktif = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
            ->where('t.Lamaran_Id', $realId)
            ->where('t.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Kode')
            ->whereNull('t.Formulir_Pengisian_Id')
            ->orderBy('t.Urutan')
            ->select('t.Id_Lamaran_Tahap', 't.Label', 't.Formulir_Kode', 'f.Nama as FormulirNama', 'f.Komponen_Kode')
            ->first();

        $tugas = $aktif ? [
            'tahapId' => Hashids::encode($aktif->Id_Lamaran_Tahap),
            'label' => $aktif->Label,
            'formulir' => $aktif->Formulir_Kode,
            'formulirNama' => $aktif->FormulirNama,
            'komponen' => $aktif->Komponen_Kode,
        ] : null;

        return Inertia::render('Career/portal/LamaranDetail', CareerShell::props('/kandidat/portal', 'Detail Lamaran', [
            'lamaran' => [
                'id' => $id,
                'kode' => $lamaran->Kode,
                'program' => $lamaran->ProgramNama,
                'posisi' => $lamaran->Posisi,
                'lokasi' => $lamaran->Lokasi,
                'status' => $lamaran->Status,
                'hasilAkhir' => $lamaran->Hasil_Akhir,
                'gugurDi' => $lamaran->Gugur_Di_Tahap,
                'alasanGugur' => $lamaran->Alasan_Gugur,
            ],
            'tahap' => $tahap,
            'tugas' => $tugas,
            // Profil untuk prefill field bertipe "terisi otomatis".
            'profil' => [
                'nama' => session('career_auth.nama'),
                'email' => session('career_auth.email'),
                'hp' => session('career_auth.hp'),
            ],
        ]));
    }

    /** POST /api/v1/lamaran/tahap/{id}/kirim — kandidat mengirim formulir tahap. */
    public function kirimFormulir(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'jawaban' => 'required|array',
        ]);

        try {
            $hasil = $this->svc->simpanPengisian((int) $realId, $userId, $data['jawaban'], $request->ip());
            if (! $hasil['ok']) {
                return ResponseHelper::error($hasil['pesan'], 422);
            }

            return ResponseHelper::success(['rekomendasi' => $hasil['rekomendasi'] ?? null], $hasil['pesan']);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal simpan pengisian: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan formulir.', 500);
        }
    }

    // ═══════════════════════ ADMIN — WORKLIST ═══════════════════════

    /** /karir/pelamar — worklist tahap yang menunggu keputusan admin. */
    public function worklist()
    {
        return Inertia::render('Career/admin/Pelamar', CareerShell::props('/karir/pelamar', 'Worklist Pelamar', [
            'worklist' => $this->daftarWorklist(),
            'lastUpdate' => ['waktu' => now()->format('d M Y H:i')],
        ]));
    }

    public function worklistList()
    {
        return ResponseHelper::success($this->daftarWorklist(), 'Worklist');
    }

    /**
     * Tahap yang: sedang BERJALAN, keputusannya di tangan admin (Internal),
     * dan formulirnya sudah diisi (jadi ada yang dinilai).
     */
    private function daftarWorklist(): array
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('t.Status', 'BERJALAN')
            ->where('l.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Pengisian_Id')
            ->orderByDesc('t.Rekomendasi_At')
            ->select('t.*', 'l.Kode as LamaranKode', 'l.Kategori', 'p.Nama as ProgramNama', 'u.Nama as Pelamar', 'x.Posisi')
            ->get()
            ->map(fn ($t) => [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'lamaranKode' => $t->LamaranKode,
                'pelamar' => $t->Pelamar,
                'program' => $t->ProgramNama,
                'posisi' => $t->Posisi,
                'kategori' => $t->Kategori,
                'tahap' => $t->Label,
                'urutan' => (int) $t->Urutan,
                'rekomendasi' => $t->Rekomendasi,
                'alasan' => $t->Rekomendasi_Alasan,
                'jejak' => json_decode($t->Jejak_Json ?: '[]', true),
                'pengisianId' => $t->Formulir_Pengisian_Id ? Hashids::encode($t->Formulir_Pengisian_Id) : null,
            ])
            ->values()
            ->all();
    }

    /** PATCH /api/v1/lamaran/tahap/{id}/putus — admin ketuk palu. */
    public function putus(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'hasil' => 'required|in:LULUS,GUGUR',
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $hasil = $this->svc->ketukPalu((int) $realId, $data['hasil'], $data['catatan'] ?? null, (int) session('career_auth.id'));
            if (! $hasil['ok']) {
                return ResponseHelper::error($hasil['pesan'], 422);
            }

            return ResponseHelper::success(null, $hasil['pesan']);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ketuk palu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses keputusan.', 500);
        }
    }

    /** GET /api/v1/lamaran/pengisian/{id} — admin melihat jawaban kandidat. */
    public function lihatPengisian(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Id_Formulir_Pengisian', $realId)->first();
        if (! $row) {
            return ResponseHelper::error('Pengisian tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'komponen' => $row->Komponen_Kode,
            'jawaban' => json_decode($row->Jawaban_Json ?: '{}', true),
            'waktuKirim' => $row->Waktu_Kirim,
        ], 'Jawaban kandidat');
    }
}
