<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * KOTAK MASUK — gerbang peristiwa dari zona luar ke Admin DB.
 *
 *   Gerbang 2 (di sini)  amplop dibongkar & diperiksa: jenis daftar putih,
 *                        Event_Id sah, kunci idempoten berawalan jenisnya,
 *                        kunci urut = akun, akun di muatan = akun kunci urut,
 *                        field wajib per jenis, ukuran wajar.
 *   Gerbang 3 (database) usp_WC_Sinkron_Terima — menyimpan atau menjawab
 *                        DUPLIKAT (Event_Id / Idempotency_Key sudah ada).
 *
 * Yang lolos tersimpan berstatus DITERIMA; pemrosesannya urusan
 * PemrosesMasuk. Pesan Pub/Sub boleh di-ack begitu jawabannya BARU/DUPLIKAT.
 */
final class KotakMasuk
{
    public const TABEL = 'N_WEB_CAREERS_Sinkron_Masuk';

    public const BARU = 'BARU';

    public const DUPLIKAT = 'DUPLIKAT';

    public const DITOLAK = 'DITOLAK';

    /** Jenis yang boleh datang dari luar + field muatan yang WAJIB ada. */
    public const JENIS = [
        'Akun.Terdaftar' => ['akun'],
        'Akun.Diperbarui' => ['akun', 'perubahan'],
        'Akun.KodeDiminta' => ['id_publik', 'email', 'jenis', 'rahasia'],
        'Lamaran.Dikirim' => ['kode', 'akun', 'pembukaan_id', 'posisi_id'],
        'Lamaran.Dibatalkan' => ['kode', 'akun'],
        'Formulir.Dikirim' => ['kode', 'akun', 'tahap_id', 'jawaban'],
        'Berkas.Diunggah' => ['kode', 'akun', 'aktivitas_id', 'berkas'],
        'Konfirmasi.Dijawab' => ['kode', 'akun', 'aktivitas_id', 'versi', 'jawaban'],
        'Konfirmasi.Dicabut' => ['kode', 'akun', 'aktivitas_id', 'versi'],
        'Feedback.Dikirim' => ['kode', 'akun', 'feedback_id', 'jawaban'],
    ];

    /** Batas CHECK kolom Muatan: 524.288 byte NVARCHAR = 262.144 karakter. */
    private const MAKS_KARAKTER = 262000;

    /**
     * Terima satu pesan Pub/Sub (bentuk REST: data base64 + attributes).
     *
     * @return array{hasil: string, alasan: ?string, kunciUrut: ?string, eventId: ?string}
     */
    public static function terimaPesan(string $dataBase64, array $atribut, ?string $messageId, ?string $publishTime, ?string $orderingKey): array
    {
        $json = base64_decode($dataBase64, true);
        $amplop = $json !== false ? json_decode($json, true) : null;
        if (! is_array($amplop)) {
            return self::tolak('badan pesan bukan JSON base64', null, $messageId);
        }

        return self::terima($amplop, $atribut, $messageId, $publishTime, $orderingKey);
    }

    /**
     * @param  array  $amplop  {event_id, jenis, versi_skema, idempotency_key, kunci_urut, dibuat_at, muatan}
     * @return array{hasil: string, alasan: ?string, kunciUrut: ?string, eventId: ?string}
     */
    public static function terima(array $amplop, array $atribut = [], ?string $messageId = null, ?string $publishTime = null, ?string $orderingKey = null): array
    {
        if ($alasan = self::periksa($amplop, $atribut, $orderingKey)) {
            return self::tolak($alasan, $amplop, $messageId);
        }

        $muatan = json_encode($amplop['muatan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($muatan === false || mb_strlen($muatan) > self::MAKS_KARAKTER) {
            return self::tolak('muatan terlalu besar atau tak bisa dikodekan', $amplop, $messageId);
        }

        $baris = DB::selectOne(
            'SET NOCOUNT ON;
             DECLARE @hasil VARCHAR(10);
             EXEC dbo.usp_WC_Sinkron_Terima
                  @Event_Id = ?, @Idempotency_Key = ?, @Jenis = ?, @Versi_Skema = ?, @Kunci_Urut = ?,
                  @Muatan = ?, @Pubsub_Message_Id = ?, @Terbit_At = ?, @Hasil = @hasil OUTPUT;
             SELECT @hasil AS Hasil;',
            [
                strtolower((string) $amplop['event_id']),
                (string) $amplop['idempotency_key'],
                (string) $amplop['jenis'],
                (int) $amplop['versi_skema'],
                (string) $amplop['kunci_urut'],
                $muatan,
                $messageId !== null ? mb_substr($messageId, 0, 64) : null,
                self::waktu($publishTime),
            ],
        );

        $hasil = (string) ($baris->Hasil ?? '');
        if ($hasil === self::DITOLAK) {
            return self::tolak('ditolak usp_WC_Sinkron_Terima', $amplop, $messageId);
        }
        if (! in_array($hasil, [self::BARU, self::DUPLIKAT], true)) {
            throw new \RuntimeException('usp_WC_Sinkron_Terima tidak menjawab (hasil kosong).');
        }

        return ['hasil' => $hasil, 'alasan' => null, 'kunciUrut' => (string) $amplop['kunci_urut'], 'eventId' => strtolower((string) $amplop['event_id'])];
    }

    /** Alasan penolakan, atau null bila amplop sah. */
    public static function periksa(array $a, array $atribut = [], ?string $orderingKey = null): ?string
    {
        $jenis = $a['jenis'] ?? null;
        if (! is_string($jenis) || ! array_key_exists($jenis, self::JENIS)) {
            return 'jenis tidak dikenal';
        }

        $eventId = $a['event_id'] ?? null;
        if (! is_string($eventId) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $eventId)) {
            return 'event_id bukan UUID';
        }
        if (isset($atribut['event_id']) && strcasecmp((string) $atribut['event_id'], $eventId) !== 0) {
            return 'event_id atribut ≠ badan';
        }
        if (isset($atribut['jenis']) && (string) $atribut['jenis'] !== $jenis) {
            return 'jenis atribut ≠ badan';
        }

        $versi = $a['versi_skema'] ?? null;
        $bulat = is_int($versi) || (is_string($versi) && ctype_digit($versi));
        if (! $bulat || (int) $versi < 1 || (int) $versi > 20) {
            return 'versi_skema tidak sah';
        }

        $idem = $a['idempotency_key'] ?? null;
        if (! is_string($idem) || strlen($idem) > 160 || ! str_starts_with($idem, $jenis.':')) {
            return 'idempotency_key tidak sah';
        }

        $urut = $a['kunci_urut'] ?? null;
        if (! is_string($urut) || ! preg_match('/^akun:(\d{1,10})$/', $urut, $m)) {
            return 'kunci_urut tidak sah';
        }
        if ($orderingKey !== null && $orderingKey !== '' && $orderingKey !== $urut) {
            return 'orderingKey ≠ kunci_urut';
        }

        $muatan = $a['muatan'] ?? null;
        if (! is_array($muatan)) {
            return 'muatan bukan objek';
        }
        foreach (self::JENIS[$jenis] as $wajib) {
            if (! array_key_exists($wajib, $muatan)) {
                return "muatan tanpa field {$wajib}";
            }
        }

        // Peristiwa hanya boleh menyangkut akun pemilik kunci urutnya.
        $akun = $jenis === 'Akun.KodeDiminta' ? ($muatan['id_publik'] ?? null) : ($muatan['akun']['id_publik'] ?? null);
        if ((string) $akun !== $m[1]) {
            return 'akun di muatan ≠ kunci_urut';
        }

        return null;
    }

    private static function tolak(string $alasan, ?array $amplop, ?string $messageId): array
    {
        Log::warning('[SINKRON] pesan DITOLAK di gerbang masuk: '.$alasan, [
            'message_id' => $messageId,
            'event_id' => $amplop['event_id'] ?? null,
            'jenis' => $amplop['jenis'] ?? null,
            'kunci_urut' => $amplop['kunci_urut'] ?? null,
        ]);

        return ['hasil' => self::DITOLAK, 'alasan' => $alasan, 'kunciUrut' => null, 'eventId' => null];
    }

    /** publishTime RFC 3339 (UTC, nanodetik) → DATETIME2(3). */
    private static function waktu(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }

        try {
            return Carbon::parse($iso)->utc()->format('Y-m-d H:i:s.v');
        } catch (\Throwable) {
            return null;
        }
    }
}
