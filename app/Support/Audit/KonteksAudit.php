<?php

namespace App\Support\Audit;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use WeakMap;

/**
 * KONTEKS AUDIT — siapa & dari mana sebuah perubahan data admin berasal.
 *
 * Pemicu audit di database (docs/07-10-2026/admin/01-audit-trail.sql) mencatat
 * nilai lama → baru setiap baris tabel yang diaudit, tetapi database tidak
 * tahu staf mana yang sedang bekerja: semua perintah memakai login aplikasi
 * yang sama. Kelas ini menitipkan identitas itu ke SESSION_CONTEXT('wc.audit')
 * tepat sebelum perintah TULIS pertama, lalu pemicu membacanya.
 *
 *   PANEL    permintaan staf (aktor = akun di sesi career_auth)
 *   TUGAS    job antrean / pemicu Cloud Scheduler
 *   SINKRON  peristiwa dari situs kandidat (konteks = id kotak masuk)
 *   KONSOL   perintah artisan (aktor = operator)
 *
 * Murah: satu perjalanan ke database hanya bila konteksnya berubah, dan
 * hanya sebelum perintah tulis — bacaan dan tulisan tabel teknis (sesi,
 * antrean) tidak memicunya. Tanpa konteks, pemicu tetap mencatat dengan
 * Sumber LANGSUNG dan login database aslinya.
 */
final class KonteksAudit
{
    public const KUNCI = 'wc.audit';

    /** @var array<string, int|string|null> */
    private static array $kini = [];

    /** @var list<array<string, int|string|null>> */
    private static array $tumpukan = [];

    /** @var WeakMap<\PDO, string>|null konteks yang sudah terpasang per sambungan */
    private static ?WeakMap $terpasang = null;

    private static bool $galatDicatat = false;

    /** Pasang pengait ke koneksi admin + peristiwa antrean & konsol. Dipanggil sekali dari AppServiceProvider. */
    public static function daftarkan(): void
    {
        if (! config('audit.konteks', true)) {
            return;
        }

        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $e) => self::kaitkan($e->connection));
        foreach (DB::getConnections() as $koneksi) {
            self::kaitkan($koneksi);
        }

        // Job yang dijalankan di dalam permintaan (antrean sync) mewarisi aktor
        // & id permintaannya; job dari Cloud Tasks mulai tanpa aktor.
        Event::listen(JobProcessing::class, function (JobProcessing $e) {
            $k = ['sumber' => 'TUGAS', 'konteks' => 'job '.$e->job->resolveName()];
            if ($id = (string) $e->job->getJobId()) {
                $k['permintaan'] = $id;
            }
            self::masuk($k);
        });
        Event::listen(JobProcessed::class, fn () => self::keluar());
        Event::listen(JobExceptionOccurred::class, fn () => self::keluar());

        Event::listen(CommandStarting::class, function (CommandStarting $e) {
            if ($e->command === null || in_array($e->command, ['queue:work', 'queue:listen', 'schedule:run', 'schedule:work'], true)) {
                return;
            }
            self::atur([
                'sumber' => 'KONSOL',
                'aktor' => self::operatorOs(),
                'konteks' => 'artisan '.$e->command,
            ]);
        });
    }

    /** Hanya koneksi ADMIN bawaan ber-SQL Server; koneksi publik punya auditnya sendiri. */
    public static function kaitkan(Connection $koneksi): void
    {
        if ($koneksi->getName() !== config('database.default') || $koneksi->getDriverName() !== 'sqlsrv') {
            return;
        }
        $koneksi->beforeExecuting(function (string $query, array $bindings, Connection $c) {
            self::terapkan($c, $query);
        });
    }

    /** Ganti konteks lapisan teratas. */
    public static function atur(array $konteks): void
    {
        self::$kini = self::rapikan($konteks);
    }

    /** Tambahkan / timpa sebagian konteks lapisan teratas. */
    public static function tambah(array $konteks): void
    {
        self::$kini = self::rapikan(array_merge(self::$kini, $konteks));
    }

    /** Aktor berubah di tengah permintaan (masuk / keluar). */
    public static function aktor(?int $id, ?string $label): void
    {
        self::tambah(['aktor_id' => $id, 'aktor' => $label]);
    }

    /** Lapisan baru di atas konteks sekarang (job sinkron di dalam permintaan tetap membawa aktornya). */
    public static function masuk(array $konteks): void
    {
        self::$tumpukan[] = self::$kini;
        self::$kini = self::rapikan(array_merge(self::$kini, $konteks));
    }

    public static function keluar(): void
    {
        if (self::$tumpukan) {
            self::$kini = array_pop(self::$tumpukan);
        }
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $kerja
     * @return T
     */
    public static function dengan(array $konteks, \Closure $kerja): mixed
    {
        self::masuk($konteks);
        try {
            return $kerja();
        } finally {
            self::keluar();
        }
    }

    /** @return array<string, int|string|null> */
    public static function kini(): array
    {
        return self::$kini;
    }

    /** Untuk uji: kembali ke keadaan awal. */
    public static function reset(): void
    {
        self::$kini = [];
        self::$tumpukan = [];
        self::$terpasang = null;
    }

    /** JSON yang dititipkan ke SESSION_CONTEXT, '' bila tanpa konteks. */
    public static function tanda(): string
    {
        return self::$kini ? json_encode(self::$kini, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }

    /**
     * Perintah yang bisa mengubah tabel yang diaudit. Bacaan polos dan tulisan
     * ke tabel teknis (config audit.lewati) tidak perlu konteks.
     */
    public static function menulis(string $sql): bool
    {
        if (! preg_match('/^\s*(insert|update|delete|merge|exec|execute|declare|set|with|begin)\b/i', $sql, $m)) {
            return false;
        }
        if (in_array(strtolower($m[1]), ['insert', 'update', 'delete'], true)
            && preg_match('/^\s*(?:insert\s+into|update|delete\s+from|delete)\s+(?:\[?dbo\]?\.)?\[?([A-Za-z0-9_]+)\]?/i', $sql, $t)) {
            return ! in_array(strtolower($t[1]), array_map('strtolower', (array) config('audit.lewati', [])), true);
        }

        return true;
    }

    /** Pasang konteks ke sambungan bila perintahnya menulis dan konteksnya berubah. */
    public static function terapkan(Connection $koneksi, string $sql): void
    {
        if (! self::menulis($sql)) {
            return;
        }

        try {
            $pdo = $koneksi->getPdo();
            if (! $pdo instanceof \PDO) {
                return;
            }
            self::$terpasang ??= new WeakMap;
            $tanda = self::tanda();
            if ((self::$terpasang[$pdo] ?? '') === $tanda) {
                return;
            }

            // PDO langsung (bukan lewat Laravel) supaya pengait ini tidak terpanggil ulang.
            // NULL hanya diterima sebagai literal — parameter terikat bernilai NULL ditolak.
            if ($tanda === '') {
                $pdo->exec("EXEC sys.sp_set_session_context @key = N'".self::KUNCI."', @value = NULL");
            } else {
                $pdo->prepare("EXEC sys.sp_set_session_context @key = N'".self::KUNCI."', @value = ?")->execute([$tanda]);
            }
            self::$terpasang[$pdo] = $tanda;
        } catch (\Throwable $e) {
            // Audit tidak boleh menggagalkan pekerjaan: pemicu tetap mencatat (Sumber LANGSUNG).
            if (! self::$galatDicatat) {
                self::$galatDicatat = true;
                Log::warning('[AUDIT] konteks tidak terpasang ke sesi database: '.$e->getMessage());
            }
        }
    }

    public static function operatorOs(): string
    {
        $pengguna = function_exists('get_current_user') ? (string) get_current_user() : '';

        return mb_substr(($pengguna !== '' ? $pengguna : '?').'@'.(gethostname() ?: '?'), 0, 150);
    }

    /** @return array<string, int|string|null> */
    private static function rapikan(array $k): array
    {
        $batas = ['sumber' => 10, 'aktor' => 150, 'konteks' => 300, 'ip' => 45, 'permintaan' => 64];
        $hasil = [];
        foreach (['sumber', 'aktor_id', 'aktor', 'konteks', 'ip', 'permintaan'] as $kunci) {
            $v = $k[$kunci] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $hasil[$kunci] = $kunci === 'aktor_id' ? (int) $v : mb_substr((string) $v, 0, $batas[$kunci]);
        }

        return $hasil;
    }
}
