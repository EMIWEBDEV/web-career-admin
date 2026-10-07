<?php

namespace App\Http\Controllers\Career\Tugas;

use App\Http\Controllers\Controller;
use App\Jobs\Career\WcSinkronMasukJob;
use App\Support\Sinkron\KotakMasuk;
use App\Support\Sinkron\PenjagaDorong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/pubsub/wc-masuk — langganan DORONG wc-masuk-sinkron.
 *
 * Pintu masuk yang sama dengan penarikan (KotakMasuk), hanya pemicunya
 * Google: pesan tersimpan → 204 (Pub/Sub menganggapnya ack), lalu peristiwa
 * akun itu langsung diproses lewat antrean 'wc-sinkron'.
 *
 *   204  tersimpan (BARU) atau sudah pernah (DUPLIKAT)
 *   422  pesan tidak sah — Pub/Sub mengulang lalu memindahkannya ke DLQ
 *   503  Admin DB tidak menjawab — Pub/Sub mengulang dengan jeda; tidak ada
 *        yang hilang
 *   404  langganan dorong belum diatur / token bukan dari Pub/Sub kita
 *
 * Tanpa sesi, CSRF, dan pembatas laju (grup api): Google bisa mengirim
 * ratusan pesan per menit saat antrean menumpuk.
 */
class DorongPubSubController extends Controller
{
    public function terima(Request $request)
    {
        if (! PenjagaDorong::aktif()) {
            abort(404);
        }

        if ($alasan = PenjagaDorong::periksa($request->header('Authorization'))) {
            Log::warning('[SINKRON] dorongan Pub/Sub ditolak: '.$alasan, ['ip' => $request->ip()]);
            abort(404);
        }

        $langganan = 'projects/'.config('sinkron.pubsub.project').'/subscriptions/'.config('sinkron.pubsub.langganan');
        if ($request->input('subscription') !== $langganan) {
            Log::warning('[SINKRON] dorongan dari langganan lain ditolak: '.$request->input('subscription'));
            abort(404);
        }

        $pesan = (array) $request->input('message', []);

        try {
            $r = KotakMasuk::terimaPesan(
                (string) ($pesan['data'] ?? ''),
                (array) ($pesan['attributes'] ?? []),
                isset($pesan['messageId']) ? (string) $pesan['messageId'] : (isset($pesan['message_id']) ? (string) $pesan['message_id'] : null),
                $pesan['publishTime'] ?? ($pesan['publish_time'] ?? null),
                $pesan['orderingKey'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('[SINKRON] kotak masuk tidak bisa menyimpan dorongan: '.$e->getMessage());

            return response()->noContent(503);
        }

        if ($r['hasil'] === KotakMasuk::DITOLAK) {
            return response()->json(['ditolak' => $r['alasan']], 422);
        }

        if ($r['hasil'] === KotakMasuk::BARU) {
            try {
                WcSinkronMasukJob::dispatch((string) $r['kunciUrut']);
            } catch (\Throwable $e) {
                // Tersimpan DITERIMA — tick berikutnya yang memprosesnya.
                Log::warning('[SINKRON] jalur cepat gagal diantrekan: '.$e->getMessage());
            }
        }

        return response()->noContent();
    }
}
