<?php

namespace App\Http\Controllers\Career\MasterFeedback;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
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
            ->select('*');

        if ($request->search) {
            $query->where('Nama', 'LIKE', "%{$request->search}%");
        }

        $items = $query->orderBy('Created_At', 'DESC')->get();
        return ResponseHelper::success($items);
    }

    public function show($id)
    {
        $form = DB::table('N_WEB_CAREERS_Master_Feedback_Form')
            ->where('Id_Master_Feedback_Form', $id)
            ->where('Flag_Cancellation', 'T')
            ->first();

        if (! $form) return ResponseHelper::error('Form tidak ditemukan', 404);

        $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Master_Feedback_Form_Id', $id)
            ->where('Flag_Cancellation', 'T')
            ->orderBy('Urutan')
            ->get();

        $form->pertanyaan = $pertanyaan;
        return ResponseHelper::success($form);
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
                    'Created_At' => $now,
                    'Created_By' => $user['name'] ?? 'SISTEM',
                    'Created_By_Id' => $user['id'] ?? null,
                ];
            }
            DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')->insert($rows);
        });

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

        return ResponseHelper::success(null, 'Pertanyaan diupdate');
    }

    public function destroyPertanyaan($id)
    {
        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Id_Master_Feedback_Pertanyaan', $id)
            ->update([
                'Flag_Cancellation' => 'Y',
                'Cancelled_At' => $now,
                'Cancelled_By' => $user['name'] ?? 'SISTEM',
            ]);

        return ResponseHelper::success(null, 'Pertanyaan dihapus');
    }
}
