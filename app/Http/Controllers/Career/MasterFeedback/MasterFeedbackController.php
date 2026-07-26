<?php

namespace App\Http\Controllers\Career\MasterFeedback;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MasterFeedbackController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/master-feedback/Index',
            CareerShell::props('/karir/master-feedback', 'Master Feedback Form')
        );
    }

    public function list(Request $request)
    {
        $query = DB::table('N_WEB_CAREERS_Master_Feedback_Form')
            ->where('Flag_Cancellation', 'T')
            ->select('Id_Master_Feedback_Form', 'Nama', 'Deskripsi', 'Mode_Tampilan',
                     'Durasi_Hari', 'Flag_Aktif', 'Created_At', 'Created_By');

        if ($request->search) {
            $query->where('Nama', 'LIKE', "%{$request->search}%");
        }

        $items = $query->orderBy('Created_At', 'DESC')->get();

        // Hitung jumlah pertanyaan per form (1 query, bukan N+1)
        $counts = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Flag_Cancellation', 'T')
            ->whereIn('Master_Feedback_Form_Id', $items->pluck('Id_Master_Feedback_Form'))
            ->selectRaw('Master_Feedback_Form_Id, COUNT(*) as jumlah')
            ->groupBy('Master_Feedback_Form_Id')
            ->pluck('jumlah', 'Master_Feedback_Form_Id');

        $items->transform(function ($f) use ($counts) {
            $f->Jumlah_Pertanyaan = $counts[$f->Id_Master_Feedback_Form] ?? 0;
            return $f;
        });

        return ResponseHelper::success($items);
    }

    public function show($id)
    {
        $startedAt = microtime(true);
        $cacheKey = $this->detailCacheKey($id);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return ResponseHelper::success($cached);
        }

        $queryFormStartedAt = microtime(true);
        $form = DB::table($this->masterFeedbackFormTableForRead())
            ->where('Id_Master_Feedback_Form', $id)
            ->where('Flag_Cancellation', 'T')
            ->select('Id_Master_Feedback_Form', 'Nama', 'Deskripsi', 'Mode_Tampilan',
                     'Durasi_Hari', 'Flag_Aktif')
            ->first();
        $queryFormMs = (microtime(true) - $queryFormStartedAt) * 1000;

        if (! $form) return ResponseHelper::error('Form tidak ditemukan', 404);

        $queryPertanyaanStartedAt = microtime(true);
        $pertanyaan = DB::table($this->masterFeedbackPertanyaanTableForRead())
            ->where('Master_Feedback_Form_Id', $id)
            ->where('Flag_Cancellation', 'T')
            ->orderBy('Urutan')
            ->select('Id_Master_Feedback_Pertanyaan', 'Urutan', 'Tipe', 'Label', 'Opsi',
                     'Skala_Min', 'Skala_Max', 'Label_Min', 'Label_Max')
            ->get();
        $queryPertanyaanMs = (microtime(true) - $queryPertanyaanStartedAt) * 1000;

        // Konversi ke array murni — hindari stdClass + Collection mix
        $transformStartedAt = microtime(true);
        $items = [];
        foreach ($pertanyaan as $p) {
            $items[] = [
                'Id_Master_Feedback_Pertanyaan' => $p->Id_Master_Feedback_Pertanyaan,
                'Urutan' => (int) $p->Urutan,
                'Tipe' => $p->Tipe,
                'Label' => $p->Label,
                'Opsi' => $p->Opsi ? json_decode($p->Opsi, true) : [],
                'Skala_Min' => $p->Skala_Min !== null ? (int) $p->Skala_Min : null,
                'Skala_Max' => $p->Skala_Max !== null ? (int) $p->Skala_Max : null,
                'Label_Min' => $p->Label_Min ?? null,
                'Label_Max' => $p->Label_Max ?? null,
            ];
        }
        $transformMs = (microtime(true) - $transformStartedAt) * 1000;

        $result = [
            'Id_Master_Feedback_Form' => $form->Id_Master_Feedback_Form,
            'Nama' => $form->Nama,
            'Deskripsi' => $form->Deskripsi,
            'Mode_Tampilan' => $form->Mode_Tampilan,
            'Durasi_Hari' => $form->Durasi_Hari !== null ? (int) $form->Durasi_Hari : null,
            'Flag_Aktif' => $form->Flag_Aktif,
            'pertanyaan' => $items,
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(10));

        $totalMs = (microtime(true) - $startedAt) * 1000;
        if ($totalMs >= 1000) {
            Log::channel('feedback')->warning('Master feedback detail endpoint lambat', [
                'form_id' => (int) $id,
                'durasi_total_ms' => (int) round($totalMs),
                'durasi_query_form_ms' => (int) round($queryFormMs),
                'durasi_query_pertanyaan_ms' => (int) round($queryPertanyaanMs),
                'durasi_transform_ms' => (int) round($transformMs),
                'jumlah_pertanyaan' => count($items),
            ]);
        }

        return ResponseHelper::success($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:500',
            'mode_tampilan' => 'required|in:WIZARD,SCROLL',
            'durasi_hari' => 'nullable|integer|min:1',
            'flag_aktif' => 'required|in:Y,T',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        $id = DB::table('N_WEB_CAREERS_Master_Feedback_Form')->insertGetId([
            'Nama' => $validated['nama'],
            'Deskripsi' => $validated['deskripsi'] ?? null,
            'Mode_Tampilan' => $validated['mode_tampilan'],
            'Durasi_Hari' => $validated['durasi_hari'] ?? null,
            'Flag_Aktif' => $validated['flag_aktif'],
            'Created_At' => $now,
            'Created_By' => $user['name'] ?? 'SISTEM',
            'Created_By_Id' => $user['id'] ?? null,
            'Updated_At' => $now,
            'Updated_By' => $user['name'] ?? 'SISTEM',
            'Updated_By_Id' => $user['id'] ?? null,
        ], 'Id_Master_Feedback_Form');

        Log::channel('feedback')->info('Form feedback dibuat', ['form_id' => $id]);
        $this->clearDetailCache($id);
        return ResponseHelper::success(['id' => $id], 'Form berhasil dibuat', 201);
    }

    public function update($id, Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:500',
            'mode_tampilan' => 'required|in:WIZARD,SCROLL',
            'durasi_hari' => 'nullable|integer|min:1',
            'flag_aktif' => 'required|in:Y,T',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        DB::table('N_WEB_CAREERS_Master_Feedback_Form')
            ->where('Id_Master_Feedback_Form', $id)
            ->update([
                'Nama' => $validated['nama'],
                'Deskripsi' => $validated['deskripsi'] ?? null,
                'Mode_Tampilan' => $validated['mode_tampilan'],
                'Durasi_Hari' => $validated['durasi_hari'] ?? null,
                'Flag_Aktif' => $validated['flag_aktif'],
                'Updated_At' => $now,
                'Updated_By' => $user['name'] ?? 'SISTEM',
                'Updated_By_Id' => $user['id'] ?? null,
            ]);

        $this->clearDetailCache($id);
        return ResponseHelper::success(null, 'Form berhasil diupdate');
    }

    public function destroy($id)
    {
        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        DB::table('N_WEB_CAREERS_Master_Feedback_Form')
            ->where('Id_Master_Feedback_Form', $id)
            ->update([
                'Flag_Cancellation' => 'Y',
                'Cancelled_At' => $now,
                'Cancelled_By' => $user['name'] ?? 'SISTEM',
            ]);

        $this->clearDetailCache($id);
        return ResponseHelper::success(null, 'Form berhasil dihapus');
    }

    public function storePertanyaan($formId, Request $request)
    {
        $validated = $request->validate([
            'pertanyaan' => 'required|array|min:1',
            'pertanyaan.*.urutan' => 'required|integer|min:1',
            'pertanyaan.*.tipe' => 'required|string|max:20',
            'pertanyaan.*.label' => 'required|string|max:500',
            'pertanyaan.*.opsi' => 'nullable|array',
            'pertanyaan.*.skala_min' => 'nullable|integer',
            'pertanyaan.*.skala_max' => 'nullable|integer',
            'pertanyaan.*.label_min' => 'nullable|string|max:100',
            'pertanyaan.*.label_max' => 'nullable|string|max:100',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        DB::transaction(function () use ($formId, $validated, $now, $user) {
            DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
                ->where('Master_Feedback_Form_Id', $formId)
                ->where('Flag_Cancellation', 'T')
                ->update([
                    'Flag_Cancellation' => 'Y',
                    'Cancelled_At' => $now,
                    'Cancelled_By' => $user['name'] ?? 'SISTEM',
                ]);

            $rows = [];
            foreach ($validated['pertanyaan'] as $p) {
                $rows[] = [
                    'Master_Feedback_Form_Id' => $formId,
                    'Urutan' => $p['urutan'],
                    'Tipe' => $p['tipe'],
                    'Label' => $p['label'],
                    'Opsi' => isset($p['opsi']) ? json_encode($p['opsi']) : null,
                    'Skala_Min' => $p['skala_min'] ?? null,
                    'Skala_Max' => $p['skala_max'] ?? null,
                    'Label_Min' => $p['label_min'] ?? null,
                    'Label_Max' => $p['label_max'] ?? null,
                    'Created_At' => $now,
                    'Created_By' => $user['name'] ?? 'SISTEM',
                    'Created_By_Id' => $user['id'] ?? null,
                ];
            }
            DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')->insert($rows);
        });

        $this->clearDetailCache($formId);
        return ResponseHelper::success(null, 'Pertanyaan berhasil disimpan');
    }

    public function updatePertanyaan($id, Request $request)
    {
        $validated = $request->validate([
            'tipe' => 'required|string|max:20',
            'label' => 'required|string|max:500',
            'opsi' => 'nullable|array',
            'skala_min' => 'nullable|integer',
            'skala_max' => 'nullable|integer',
            'urutan' => 'required|integer',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');
        $formId = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Id_Master_Feedback_Pertanyaan', $id)
            ->value('Master_Feedback_Form_Id');

        DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Id_Master_Feedback_Pertanyaan', $id)
            ->update([
                'Tipe' => $validated['tipe'],
                'Label' => $validated['label'],
                'Opsi' => isset($validated['opsi']) ? json_encode($validated['opsi']) : null,
                'Skala_Min' => $validated['skala_min'] ?? null,
                'Skala_Max' => $validated['skala_max'] ?? null,
                'Urutan' => $validated['urutan'],
                'Updated_At' => $now,
                'Updated_By' => $user['name'] ?? 'SISTEM',
                'Updated_By_Id' => $user['id'] ?? null,
            ]);

        if ($formId !== null) {
            $this->clearDetailCache($formId);
        }

        return ResponseHelper::success(null, 'Pertanyaan diupdate');
    }

    public function destroyPertanyaan($id)
    {
        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');
        $formId = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Id_Master_Feedback_Pertanyaan', $id)
            ->value('Master_Feedback_Form_Id');

        DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Id_Master_Feedback_Pertanyaan', $id)
            ->update([
                'Flag_Cancellation' => 'Y',
                'Cancelled_At' => $now,
                'Cancelled_By' => $user['name'] ?? 'SISTEM',
            ]);

        if ($formId !== null) {
            $this->clearDetailCache($formId);
        }

        return ResponseHelper::success(null, 'Pertanyaan dihapus');
    }

    private function detailCacheKey($id): string
    {
        return 'career:master-feedback:detail:' . (string) $id;
    }

    private function clearDetailCache($id): void
    {
        Cache::forget($this->detailCacheKey($id));
    }

    private function masterFeedbackFormTableForRead()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            return DB::raw('N_WEB_CAREERS_Master_Feedback_Form WITH (NOLOCK)');
        }

        return 'N_WEB_CAREERS_Master_Feedback_Form';
    }

    private function masterFeedbackPertanyaanTableForRead()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            return DB::raw('N_WEB_CAREERS_Master_Feedback_Pertanyaan WITH (NOLOCK)');
        }

        return 'N_WEB_CAREERS_Master_Feedback_Pertanyaan';
    }
}
