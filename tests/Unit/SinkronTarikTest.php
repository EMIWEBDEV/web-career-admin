<?php

namespace Tests\Unit;

use App\Support\Sinkron\PubSubLangganan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tarik Pub/Sub: langganan kosong ditahan Google sampai waktu tunggu habis —
 * itu berarti "tidak ada pesan", bukan galat (dulu tercatat ERROR tiap menit).
 * Galat jaringan lain tetap dilempar supaya pemutus arus bekerja.
 */
class SinkronTarikTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['sinkron.pubsub.project' => 'proyek-uji', 'sinkron.pubsub.langganan' => 'wc-masuk-sinkron']);
        Cache::put('sinkron:google:token-pubsub', 'token-uji', 600);
    }

    public function test_waktu_tunggu_habis_berarti_tidak_ada_pesan(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 20002 milliseconds with 0 bytes received'));

        $this->assertSame([], (new PubSubLangganan)->tarik(100, 5));
    }

    public function test_galat_jaringan_lain_tetap_dilempar(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

        $this->expectException(ConnectionException::class);
        (new PubSubLangganan)->tarik(100, 5);
    }

    public function test_pesan_dibentuk_dari_jawaban_pull(): void
    {
        Http::fake(['*' => Http::response(['receivedMessages' => [[
            'ackId' => 'ack-1',
            'deliveryAttempt' => 2,
            'message' => ['messageId' => 'm-1', 'data' => base64_encode('{}'), 'orderingKey' => 'akun:12', 'publishTime' => '2026-10-07T05:00:00Z'],
        ]]])]);

        $p = (new PubSubLangganan)->tarik(100, 5);

        $this->assertCount(1, $p);
        $this->assertSame(['ack-1', 'm-1', 'akun:12', 2], [$p[0]['ackId'], $p[0]['messageId'], $p[0]['orderingKey'], $p[0]['deliveryAttempt']]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/subscriptions/wc-masuk-sinkron:pull') && $r['maxMessages'] === 100);
    }
}
