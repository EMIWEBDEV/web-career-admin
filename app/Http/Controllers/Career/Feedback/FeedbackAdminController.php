<?php

namespace App\Http\Controllers\Career\Feedback;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\FeedbackExportJob;
use App\Support\Career\FeedbackService;
use App\Support\CareerShell;
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
        return Inertia::render('Career/admin/feedback-dashboard/Index',
            CareerShell::props('/karir/feedback-dashboard', 'Feedback Dashboard')
        );
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
        // Baca dari snapshot — data historis akurat meskipun pertanyaan master berubah
        $baseQuery = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->where('fj.Master_Feedback_Form_Id', $formId)
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('fj.Flag_Cancellation', 'T');

        if ($dateFrom) $baseQuery->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $baseQuery->where('fj.Submitted_At', '<=', $dateTo);

        $all = $baseQuery
            ->select('d.Master_Feedback_Pertanyaan_Id', 'd.Label_Snapshot', 'd.Tipe_Snapshot',
                     'd.Opsi_Snapshot', 'd.Skala_Min_Snapshot', 'd.Skala_Max_Snapshot', 'd.Jawaban')
            ->get()
            ->groupBy('Master_Feedback_Pertanyaan_Id');

        $result = [];
        foreach ($all as $pertanyaanId => $rows) {
            $first = $rows->first();
            $label = $first->Label_Snapshot ?? 'Pertanyaan #' . $pertanyaanId;
            $tipe = $first->Tipe_Snapshot ?? 'TEXTAREA';

            $item = ['id' => $pertanyaanId, 'label' => $label, 'tipe' => $tipe];
            $jawabanValues = $rows->pluck('Jawaban');

            if (in_array($tipe, ['RATING', 'LIKERT'])) {
                $scores = $jawabanValues->map(fn($v) => (float) $v)->toArray();
                $item['avg'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
                $item['total'] = count($scores);
                $dist = array_fill(1, 5, 0);
                foreach ($scores as $s) {
                    $bucket = max(1, min(5, (int) $s));
                    if (isset($dist[$bucket])) $dist[$bucket]++;
                }
                $item['distribusi'] = $dist;
            } elseif ($tipe === 'NPS') {
                $scores = $jawabanValues->map(fn($v) => (int) $v)->toArray();
                $item['nps'] = $this->service->calculateNPS($scores);
                $item['total'] = count($scores);
            } elseif (in_array($tipe, ['RADIO', 'CHECKBOX', 'DROPDOWN'])) {
                $counts = $rows->groupBy('Jawaban')->map->count()->toArray();
                $item['counts'] = $counts;
                $item['total'] = $rows->count();
            } elseif ($tipe === 'TEXTAREA') {
                $item['recent'] = $jawabanValues->take(5)->toArray();
                $item['total'] = $rows->count();
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

    // ═══════════════ MONITORING ═══════════════

    public function monitoringKpi(Request $request) {
        $fId = $request->input('form_id'); $pId = $request->input('program_id');
        $q = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')->join('N_WEB_CAREERS_Lamaran as l','fj.Lamaran_Id','=','l.Id_Lamaran')->leftJoin('N_WEB_CAREERS_Master_Feedback_Form as ff','fj.Master_Feedback_Form_Id','=','ff.Id_Master_Feedback_Form')->where('fj.Flag_Cancellation','T');
        if($fId) $q->where('fj.Master_Feedback_Form_Id',$fId); if($pId) $q->where('l.Program_Id',$pId);
        $all=(clone$q)->count(); $ok=(clone$q)->where('fj.Status_Pengisian','TERISI')->count();
        $pend=(clone$q)->where('fj.Status_Pengisian','MENUNGGU')->whereRaw('DATEDIFF(DAY,fj.Created_At,GETDATE())<=COALESCE(ff.Durasi_Hari,30)')->count();
        $exp=(clone$q)->where('fj.Status_Pengisian','MENUNGGU')->whereRaw('DATEDIFF(DAY,fj.Created_At,GETDATE())>COALESCE(ff.Durasi_Hari,30)')->count();
        return ResponseHelper::success(['total'=>$all,'terisi'=>$ok,'pending'=>$pend,'expired'=>$exp,'response_rate'=>$all>0?round($ok/$all*100):0]);
    }

    public function monitoringData(Request $request) {
        $fId=$request->input('form_id');$pId=$request->input('program_id');$st=$request->input('status');$s=$request->input('search');$df=$request->input('date_from');$dt=$request->input('date_to');$pg=(int)$request->input('page',1);$lm=min((int)$request->input('limit',20),100);
        $q=DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')->join('N_WEB_CAREERS_Lamaran as l','fj.Lamaran_Id','=','l.Id_Lamaran')->leftJoin('N_WEB_CAREERS_Users as u','l.Id_Users','=','u.Id_Users')->leftJoin('N_WEB_CAREERS_Program as p','l.Program_Id','=','p.Id_Program')->leftJoin('N_WEB_CAREERS_Master_Feedback_Form as ff','fj.Master_Feedback_Form_Id','=','ff.Id_Master_Feedback_Form')->where('fj.Flag_Cancellation','T');
        if($fId)$q->where('fj.Master_Feedback_Form_Id',$fId);if($pId)$q->where('l.Program_Id',$pId);if($s)$q->where(fn($x)=>$x->where('u.Nama','LIKE',"%{$s}%")->orWhere('l.Kode','LIKE',"%{$s}%"));if($df)$q->where('l.Waktu_Lamar','>=',$df);if($dt)$q->where('l.Waktu_Lamar','<=',$dt);
        if($st==='EXPIRED')$q->where('fj.Status_Pengisian','MENUNGGU')->whereRaw('DATEDIFF(DAY,fj.Created_At,GETDATE())>COALESCE(ff.Durasi_Hari,30)');elseif($st)$q->where('fj.Status_Pengisian',$st);
        $ttl=$q->count();$items=$q->select('fj.Id_Feedback_Jawaban','fj.Status_Pengisian','fj.Created_At','fj.Submitted_At','fj.Master_Feedback_Form_Id','ff.Nama as Form_Nama','l.Id_Lamaran','l.Kode as Kode_Lamaran','l.Hasil_Akhir','l.Waktu_Lamar','u.Nama as Nama_Kandidat','u.Email','p.Nama as Program_Nama')->orderBy('fj.Created_At','DESC')->offset(($pg-1)*$lm)->limit($lm)->get();
        return ResponseHelper::successWithPagination($items,$pg,$lm,$ttl);
    }

    public function reassignForm(Request $request) {
        $v=$request->validate(['feedback_ids'=>'required|array|min:1','feedback_ids.*'=>'integer','new_form_id'=>'required|integer','resend_email'=>'boolean']);
        $now=FormatTanggalHelper::getCurrentTime();$uid=session('career_auth.id');$un=(string)($uid??'SISTEM');$c=0;
        foreach($v['feedback_ids'] as $fid){$old=DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$fid)->where('Flag_Cancellation','T')->first();if(!$old)continue;
            DB::transaction(function()use($fid,$v,$now,$un,$uid,$old,&$c){DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$fid)->update(['Flag_Cancellation'=>'Y','Cancelled_At'=>$now,'Cancelled_By'=>$un]);DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail')->where('Feedback_Jawaban_Id',$fid)->delete();
                $nid=DB::table('N_WEB_CAREERS_Feedback_Jawaban')->insertGetId(['Lamaran_Id'=>$old->Lamaran_Id,'Master_Feedback_Form_Id'=>$v['new_form_id'],'Email_Token'=>$old->Email_Token,'Status_Pengisian'=>'MENUNGGU','Created_At'=>$now],'Id_Feedback_Jawaban');
                $fs=app(FeedbackService::class);$t=$fs->generateTokenPair($nid,$old->Email_Token);DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$nid)->update(['Token_Hash'=>$t['hashids'].'.'.$t['signature']]);$c++;
                if(!empty($v['resend_email'])){$l=DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran',$old->Lamaran_Id)->first();if($l){$st=$l->Hasil_Akhir==='DITERIMA'?'LOLOS':'GUGUR';$url=rtrim(config('app.url'),'/').'/feedback/'.$t['hashids'].'/'.$t['signature'];\App\Jobs\Career\WcApplyEmailJob::dispatch((int)$l->Id_Users,$st,['kode'=>$l->Kode,'feedbackUrl'=>$url]);}}
            });}
        Log::channel('feedback')->info('Reassign',['count'=>$c,'form'=>$v['new_form_id']]);return ResponseHelper::success(['reassigned'=>$c],"$c feedback di-reassign.");
    }

    public function resendEmail(Request $request) {
        $v=$request->validate(['feedback_ids'=>'required|array|min:1','feedback_ids.*'=>'integer']);$s=0;
        foreach($v['feedback_ids'] as $fid){$fb=DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$fid)->where('Flag_Cancellation','T')->first();if(!$fb||!$fb->Email_Token)continue;$l=DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran',$fb->Lamaran_Id)->first();if(!$l)continue;$st=$l->Hasil_Akhir==='DITERIMA'?'LOLOS':'GUGUR';$pt=explode('.',$fb->Token_Hash,2);$url=rtrim(config('app.url'),'/').'/feedback/'.($pt[0]??'').'/'.($pt[1]??'');\App\Jobs\Career\WcApplyEmailJob::dispatch((int)$l->Id_Users,$st,['kode'=>$l->Kode,'feedbackUrl'=>$url]);$s++;}
        return ResponseHelper::success(['resent'=>$s],"$s email terkirim.");
    }
}
