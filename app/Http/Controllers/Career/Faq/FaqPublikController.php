<?php

namespace App\Http\Controllers\Career\Faq;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Career\CareerLandingController;
use App\Http\Controllers\Controller;
use App\Support\Career\FaqPublik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — HALAMAN FAQ PUBLIK (/karir/faq).
 *
 * Controller sendiri, BUKAN method tambahan di CareerLandingController: berkas
 * itu sudah 2200+ baris dan jadi titik bentrok merge. Payload layout (navbar +
 * footer) tetap diambil dari sana lewat layoutShared() supaya semua halaman
 * publik memakai sumber yang sama.
 *
 * Route:
 *   GET  /karir/faq                       → index
 *   POST /api/v1/karir/faq/{id}/dilihat   → catatDilihat
 *   POST /api/v1/karir/faq/{id}/membantu  → catatMembantu
 */
class FaqPublikController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Faq';

    /** Kunci sesi penanda apa yang sudah dihitung untuk pengunjung ini. */
    private const SESI_DILIHAT = 'faq_dilihat';

    private const SESI_VOTE = 'faq_vote';

    public function index()
    {
        $faq = new FaqPublik();

        return Inertia::render(
            'Career/Faq',
            array_merge(
                (new CareerLandingController())->layoutShared(),
                $faq->semua()
            )
        );
    }

    /**
     * Tambah penghitung "dilihat".
     *
     * Dedup per SESI: satu pengunjung yang membuka-tutup accordion berkali-kali
     * tidak boleh menggelembungkan angka — kalau bisa, statistiknya jadi tidak
     * berguna untuk memutuskan FAQ mana yang perlu diperjelas.
     */
    public function catatDilihat(Request $request, string $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $sudah = (array) $request->session()->get(self::SESI_DILIHAT, []);
            if (in_array($realId, $sudah, true)) {
                return ResponseHelper::success(['dicatat' => false], 'Sudah dicatat sebelumnya');
            }

            $terpengaruh = DB::table(self::TABEL)
                ->where('Id_Master_Faq', $realId)
                ->where('Flag_Cancellation', 'T')
                ->increment('Jumlah_Dilihat');

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $sudah[] = $realId;
            $request->session()->put(self::SESI_DILIHAT, $sudah);

            return ResponseHelper::success(['dicatat' => true], 'Dicatat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("Gagal catat FAQ dilihat #{$id}: " . $e->getMessage());

            // Penghitung gagal bukan alasan menampilkan error ke pembaca.
            return ResponseHelper::success(['dicatat' => false], 'Dilewati');
        }
    }

    /** Vote "membantu / tidak membantu" — satu kali per FAQ per sesi. */
    public function catatMembantu(Request $request, string $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $vote = (array) $request->session()->get(self::SESI_VOTE, []);
            if (array_key_exists((string) $realId, $vote)) {
                return ResponseHelper::error('Anda sudah memberi penilaian untuk pertanyaan ini', 409);
            }

            $membantu = $request->boolean('membantu');
            $kolom = $membantu ? 'Jumlah_Membantu' : 'Jumlah_Tidak_Membantu';

            $terpengaruh = DB::table(self::TABEL)
                ->where('Id_Master_Faq', $realId)
                ->where('Flag_Cancellation', 'T')
                ->increment($kolom);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $vote[(string) $realId] = $membantu;
            $request->session()->put(self::SESI_VOTE, $vote);

            $row = DB::table(self::TABEL)
                ->where('Id_Master_Faq', $realId)
                ->first(['Jumlah_Membantu', 'Jumlah_Tidak_Membantu']);

            return ResponseHelper::success([
                'membantu' => (int) ($row->Jumlah_Membantu ?? 0),
                'tidakMembantu' => (int) ($row->Jumlah_Tidak_Membantu ?? 0),
            ], 'Terima kasih atas penilaian Anda');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal catat FAQ membantu #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan penilaian', 500);
        }
    }
}
