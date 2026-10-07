<?php

namespace App\Support\Sinkron;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pelanggan Pub/Sub lewat REST — langganan wc-masuk-sinkron (mode TARIK).
 *
 * Zona dalam yang menjangkau keluar: menarik pesan, lalu meng-ack HANYA
 * sesudah kotak masuk menyimpannya. Pesan yang belum di-ack disimpan Google
 * (sampai 7 hari) dan dikirim ulang; yang gagal 10 kali pindah ke DLQ
 * wc-masuk-mati.
 *
 * Akun layanannya cukup roles/pubsub.subscriber pada langganan itu.
 */
final class PubSubLangganan
{
    /**
     * Tarik pesan. Bila langganan kosong, Pub/Sub MENAHAN permintaannya sampai
     * ada pesan (long-poll) — pesan yang terbit selama $tungguDetik langsung
     * diterima. Habisnya waktu tunggu berarti "tidak ada pesan", bukan galat.
     *
     * @return list<array{ackId: string, messageId: string, publishTime: ?string, data: string, attributes: array, orderingKey: ?string, deliveryAttempt: ?int}>
     */
    public function tarik(int $maks, ?int $tungguDetik = null): array
    {
        try {
            $res = $this->panggil(':pull', ['maxMessages' => max(1, min(1000, $maks))], $tungguDetik);
        } catch (ConnectionException $e) {
            if (str_contains($e->getMessage(), 'cURL error 28')) {
                return [];
            }
            throw $e;
        }

        return array_values(array_map(fn (array $m) => [
            'ackId' => (string) ($m['ackId'] ?? ''),
            'messageId' => (string) ($m['message']['messageId'] ?? ''),
            'publishTime' => $m['message']['publishTime'] ?? null,
            'data' => (string) ($m['message']['data'] ?? ''),
            'attributes' => (array) ($m['message']['attributes'] ?? []),
            'orderingKey' => $m['message']['orderingKey'] ?? null,
            'deliveryAttempt' => isset($m['deliveryAttempt']) ? (int) $m['deliveryAttempt'] : null,
        ], (array) ($res->json('receivedMessages') ?? [])));
    }

    /** @param  list<string>  $ackIds */
    public function ack(array $ackIds): void
    {
        foreach (array_chunk(array_values(array_filter($ackIds)), 500) as $potong) {
            $this->panggil(':acknowledge', ['ackIds' => $potong]);
        }
    }

    /**
     * Kembalikan pesan ke langganan SEKARANG (nack). Jeda kirim ulangnya
     * mengikuti kebijakan coba-ulang langganan (10–600 detik).
     *
     * @param  list<string>  $ackIds
     */
    public function nack(array $ackIds): void
    {
        foreach (array_chunk(array_values(array_filter($ackIds)), 500) as $potong) {
            $this->panggil(':modifyAckDeadline', ['ackIds' => $potong, 'ackDeadlineSeconds' => 0]);
        }
    }

    private function panggil(string $aksi, array $badan, ?int $timeout = null): Response
    {
        $cfg = config('sinkron.pubsub');
        if (empty($cfg['project']) || empty($cfg['langganan'])) {
            throw new RuntimeException('Pub/Sub belum diatur (PUBSUB_PROJECT / PUBSUB_LANGGANAN).');
        }

        $url = rtrim((string) $cfg['endpoint'], '/')."/v1/projects/{$cfg['project']}/subscriptions/{$cfg['langganan']}{$aksi}";
        $kirim = fn () => Http::withToken(TokenGoogle::pubsub())
            ->acceptJson()
            ->timeout(max(3, $timeout ?? (int) $cfg['timeout']))
            ->post($url, $badan);

        $res = $kirim();
        if ($res->status() === 401) {
            TokenGoogle::lupakan();
            $res = $kirim();
        }

        if (! $res->successful()) {
            throw new RuntimeException("Pub/Sub {$aksi} menjawab HTTP ".$res->status().': '.Str::limit((string) $res->body(), 300));
        }

        return $res;
    }
}
