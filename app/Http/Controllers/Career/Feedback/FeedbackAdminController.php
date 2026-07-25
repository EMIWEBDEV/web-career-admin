<?php

namespace App\Http\Controllers\Career\Feedback;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\FeedbackExportJob;
use App\Support\Career\FeedbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FeedbackAdminController extends Controller
{
    public function __construct(private FeedbackService $service) {}

    public function dashboard()
    {
        return Inertia::render('Career/admin/feedback-dashboard/Index');
    }

    public function chartData(Request $request)
    {
        $formId = $request->input('form_id');
        $programId = $request->input('program_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $overview = $this->service->aggregate($formId ? (int) $formId : null,
            $programId ? (int) $programId : null, $dateFrom, $dateTo);

        $programComparison = $this->getProgramComparison(
            $formId ? (int) $formId : null, $dateFrom, $dateTo);
        $npsTrend = $this->getNpsTrend(
            $formId ? (int) $formId : null, $programId ? (int) $programId : null);
        $perPertanyaan = $formId ? $this->getPerPertanyaan((int) $formId, $dateFrom, $dateTo) : null;
        $lolosVsGagal = $formId ? $this->getLolosVsGagal((int) $formId, $dateFrom, $dateTo) : null;

        return ResponseHelper::success([
            'overview' => $overview,
            'program_comparison' => $programComparison,
            'nps_trend' => $npsTrend,
            'per_pertanyaan' => $perPertanyaan,
            'lolos_vs_gagal' => $lolosVsGagal,
        ]);
    }

    public function detail($feedbackId)
    {
        $feedback = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'l.Program_Id', '=', 'p.Id_Program')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'l.Id_Users', '=', 'u.Id_Users')
            ->where('fj.Id_Feedback_Jawaban', $feedbackId)
            ->select('fj.*', 'l.Kode as Kode_Lamaran', 'l.Hasil_Akhir', 'p.Nama as Program_Nama',
                'u.Nama as Nama_Kandidat', 'u.Email')
            ->first();

        if (! $feedback) return ResponseHelper::error('Feedback tidak ditemukan', 404);

        $jawaban = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Master_Feedback_Pertanyaan as p',
                'd.Master_Feedback_Pertanyaan_Id', '=', 'p.Id_Master_Feedback_Pertanyaan')
            ->where('d.Feedback_Jawaban_Id', $feedbackId)
            ->select('p.Label', 'p.Tipe', 'p.Opsi', 'd.Jawaban')
            ->orderBy('p.Urutan')
            ->get();

        $feedback->jawaban = $jawaban;
        return ResponseHelper::success($feedback);
    }

    public function requestExport(Request $request)
    {
        $validated = $request->validate([
            'form_id' => 'required|integer',
            'program_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $userId = session('career_auth.id');

        $exportId = DB::table('N_WEB_CAREERS_Export_Log')->insertGetId([
            'Export_Type' => 'FEEDBACK',
            'Id_Users' => $userId,
            'Keterangan' => 'Export Feedback ' . FormatTanggalHelper::format($now, true),
            'Filters_Json' => json_encode($validated),
            'Status_Export' => 'DIPROSES',
            'Created_At' => $now,
        ], 'Id_Export');

        FeedbackExportJob::dispatch($exportId, (int) $validated['form_id'], $validated)
            ->onQueue('async_export');

        return ResponseHelper::success(['export_id' => $exportId], 'Export dimulai');
    }

    public function pollExport()
    {
        $userId = session('career_auth.id');

        $items = DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Users', $userId)
            ->where('Flag_Cancellation', 'T')
            ->where(function ($q) {
                $q->where('Status_Export', 'DIPROSES')
                  ->orWhere(function ($q2) {
                      $q2->where('Status_Export', 'SELESAI')
                         ->where('Completed_At', '>=', \Carbon\Carbon::now()->subDays(1));
                  });
            })
            ->orderBy('Created_At', 'DESC')
            ->get()
            ->map(function ($e) {
                $e->Created_At = FormatTanggalHelper::format($e->Created_At, true);
                $e->Completed_At = $e->Completed_At ? FormatTanggalHelper::format($e->Completed_At, true) : null;
                if ($e->File_Path) {
                    $e->File_Url = Storage::disk('gcs')->temporaryUrl($e->File_Path, now()->addMinutes(15));
                }
                return $e;
            });

        return ResponseHelper::success($items);
    }

    public function downloadExport($id)
    {
        $export = DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $id)
            ->where('Flag_Cancellation', 'T')
            ->first();

        if (! $export || ! $export->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $url = Storage::disk('gcs')->temporaryUrl($export->File_Path, now()->addMinutes(15));
        return redirect()->away($url);
    }

    public function dismissExport($id)
    {
        $export = DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $id)
            ->where('Flag_Cancellation', 'T')
            ->first();

        if (! $export) return ResponseHelper::error('Export tidak ditemukan', 404);

        if (! empty($export->File_Path)) {
            try {
                Storage::disk('gcs')->delete($export->File_Path);
            } catch (\Throwable $e) {
                Log::channel('feedback')->warning('Gagal hapus file export GCS', [
                    'export_id' => $id, 'path' => $export->File_Path,
                ]);
            }
        }

        $now = FormatTanggalHelper::getCurrentTime();
        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $id)
            ->update([
                'Flag_Cancellation' => 'Y',
                'Cancelled_At' => $now,
                'Cancelled_By' => session('career_auth.name'),
            ]);

        return ResponseHelper::success(null, 'Export dihapus');
    }

    // ═══════════════ PRIVATE HELPERS ═══════════════

    private function getProgramComparison(?int $formId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->join('N_WEB_CAREERS_Program as p', 'l.Program_Id', '=', 'p.Id_Program')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI');

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);
        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        $items = $query->selectRaw("
                p.Id_Program,
                p.Nama as Program_Nama,
                COUNT(*) as total_respon,
                AVG(TRY_CAST(
                    (SELECT AVG(TRY_CAST(d2.Jawaban AS FLOAT))
                     FROM N_WEB_CAREERS_Feedback_Jawaban_Detail d2
                     JOIN N_WEB_CAREERS_Master_Feedback_Pertanyaan p2
                       ON d2.Master_Feedback_Pertanyaan_Id = p2.Id_Master_Feedback_Pertanyaan
                     WHERE d2.Feedback_Jawaban_Id = fj.Id_Feedback_Jawaban
                       AND p2.Tipe IN ('RATING','LIKERT'))
                AS FLOAT)) as avg_rating
            ")
            ->groupBy('p.Id_Program', 'p.Nama')
            ->orderBy('total_respon', 'DESC')
            ->get()
            ->toArray();

        return $items;
    }

    private function getNpsTrend(?int $formId, ?int $programId): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->join('N_WEB_CAREERS_Master_Feedback_Pertanyaan as p', 'd.Master_Feedback_Pertanyaan_Id', '=', 'p.Id_Master_Feedback_Pertanyaan')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('p.Tipe', 'NPS');

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);
        if ($programId) $query->where('l.Program_Id', $programId);

        // Group by month, calculate NPS per month
        $scores = $query->selectRaw("
                FORMAT(fj.Submitted_At, 'yyyy-MM') as bulan,
                d.Jawaban as skor
            ")
            ->get()
            ->groupBy('bulan');

        $trend = [];
        foreach ($scores as $bulan => $items) {
            $skorArray = $items->pluck('skor')->map(fn($s) => (int) $s)->toArray();
            $trend[] = [
                'bulan' => $bulan,
                'nps' => $this->service->calculateNPS($skorArray),
                'total' => count($skorArray),
            ];
        }

        return $trend;
    }

    private function getPerPertanyaan(int $formId, ?string $dateFrom, ?string $dateTo): array
    {
        $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Master_Feedback_Form_Id', $formId)
            ->where('Flag_Cancellation', 'T')
            ->orderBy('Urutan')
            ->get();

        $result = [];
        foreach ($pertanyaan as $p) {
            $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
                ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
                ->where('d.Master_Feedback_Pertanyaan_Id', $p->Id_Master_Feedback_Pertanyaan)
                ->where('fj.Status_Pengisian', 'TERISI')
                ->where('fj.Flag_Cancellation', 'T');

            if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
            if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

            $details = $query->select('d.Jawaban')->get();

            $item = ['id' => $p->Id_Master_Feedback_Pertanyaan, 'label' => $p->Label, 'tipe' => $p->Tipe];

            if (in_array($p->Tipe, ['RATING', 'LIKERT'])) {
                $scores = $details->map(fn($d) => (float) $d->Jawaban)->toArray();
                $item['avg'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
                $item['total'] = count($scores);
                $dist = array_fill(1, 5, 0);
                foreach ($scores as $s) {
                    $bucket = max(1, min(5, (int) $s));
                    if (isset($dist[$bucket])) $dist[$bucket]++;
                }
                $item['distribusi'] = $dist;
            } elseif ($p->Tipe === 'NPS') {
                $scores = $details->map(fn($d) => (int) $d->Jawaban)->toArray();
                $item['nps'] = $this->service->calculateNPS($scores);
                $item['total'] = count($scores);
            } elseif (in_array($p->Tipe, ['RADIO', 'CHECKBOX', 'DROPDOWN'])) {
                $counts = $details->groupBy('Jawaban')->map->count()->toArray();
                $item['counts'] = $counts;
                $item['total'] = $details->count();
            } elseif ($p->Tipe === 'TEXTAREA') {
                $recent = $details->take(5)->pluck('Jawaban')->toArray();
                $item['recent'] = $recent;
                $item['total'] = $details->count();
            }

            $result[] = $item;
        }

        return $result;
    }

    private function getLolosVsGagal(int $formId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Master_Feedback_Form_Id', $formId)
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('fj.Flag_Cancellation', 'T')
            ->whereIn('l.Hasil_Akhir', ['DITERIMA', 'DITOLAK']);

        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        $groups = $query->selectRaw("
                l.Hasil_Akhir,
                COUNT(*) as total,
                AVG(DATEDIFF(SECOND, fj.Created_At, fj.Submitted_At)) as avg_waktu_detik
            ")
            ->groupBy('l.Hasil_Akhir')
            ->get()
            ->keyBy('Hasil_Akhir');

        $lolos = $groups['DITERIMA'] ?? null;
        $gagal = $groups['DITOLAK'] ?? null;

        return [
            'lolos' => $lolos ? ['total' => $lolos->total, 'avg_waktu_detik' => $lolos->avg_waktu_detik] : null,
            'gagal' => $gagal ? ['total' => $gagal->total, 'avg_waktu_detik' => $gagal->avg_waktu_detik] : null,
        ];
    }
}
