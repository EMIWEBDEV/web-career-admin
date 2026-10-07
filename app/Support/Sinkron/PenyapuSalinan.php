<?php

namespace App\Support\Sinkron;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PENYAPU SALINAN MASTER — tabel master admin → salinan di database publik
 * (lowongan, alur, formulir, kampus/prodi, menu kandidat, divisi HRIS, …).
 *
 * Rekonsiliasi LANGSUNG, bukan peristiwa per baris: sisi publik itulah
 * keadaannya. Tiap baris di kedua sisi diberi sidik yang sama persis —
 * HASHBYTES(SHA2_256) atas JSON kolom-kolomnya (tipe kolom kedua sisi
 * identik, dibuat dari katalog yang sama) — lalu:
 *
 *   gerbang   COUNT + CHECKSUM_AGG(BINARY_CHECKSUM) tabel admin dibanding
 *             tanda air terakhir; sama → tabel dilewati (murah, tiap tick).
 *             Rekonsiliasi PENUH tanpa gerbang tiap SINKRON_SALINAN_PENUH_MENIT.
 *   tambah    baris admin ber-id di atas id terbesar publik disalin
 *             berurutan — muatan awal ratusan ribu baris (Master_Kampus)
 *             bisa dicicil lintas tick, dan dilanjutkan dari id terakhir.
 *   ember     id dibagi per 1.000; ember yang sidik gabungannya beda saja
 *             yang dibandingkan per baris.
 *   tulis     MERGE … USING OPENJSON (500 baris per perintah) + hapus baris
 *             yang sudah tidak ada di admin.
 *
 * Tanpa kolom baru di tabel admin, dan memulihkan sendiri salinan yang
 * disunting/rusak di sisi publik. Hak peran publik: SELECT/INSERT/UPDATE/
 * DELETE salinan saja (wc_publik_worker).
 *
 * Registri N_WEB_CAREERS_Sinkron_Tabel: Aktif = T menjeda satu tabel (tabel
 * lain jalan terus); hasil tiap tabel dicatat di Terakhir_*.
 */
final class PenyapuSalinan
{
    /** Tabel admin => tabel salinan publik. Urutan = urutan sapu. */
    public const TABEL = [
        'N_WEB_CAREERS_Program' => 'N_WEB_CAREERS_Program',
        'N_WEB_CAREERS_Pembukaan' => 'N_WEB_CAREERS_Pembukaan',
        'N_WEB_CAREERS_Program_Batch' => 'N_WEB_CAREERS_Program_Batch',
        'N_WEB_CAREERS_Program_Posisi' => 'N_WEB_CAREERS_Program_Posisi',
        'N_WEB_CAREERS_Points_MPP' => 'N_WEB_CAREERS_Points_MPP',
        'N_WEB_CAREERS_Detail_MPP' => 'N_WEB_CAREERS_Detail_MPP',
        'N_WEB_CAREERS_Detail_Benefit_MPP' => 'N_WEB_CAREERS_Detail_Benefit_MPP',
        'N_WEB_CAREERS_Detail_Skill_MPP' => 'N_WEB_CAREERS_Detail_Skill_MPP',
        'N_WEB_CAREERS_Master_Benefit' => 'N_WEB_CAREERS_Master_Benefit',
        'N_WEB_CAREERS_Master_Employment' => 'N_WEB_CAREERS_Master_Employment',
        'N_WEB_CAREERS_Master_Experience_Level' => 'N_WEB_CAREERS_Master_Experience_Level',
        'N_WEB_CAREERS_Master_Workplace' => 'N_WEB_CAREERS_Master_Workplace',
        'N_WEB_CAREERS_Master_Skill' => 'N_WEB_CAREERS_Master_Skill',
        'N_WEB_CAREERS_Master_Jadwal' => 'N_WEB_CAREERS_Master_Jadwal',
        'N_WEB_CAREERS_Master_Jadwal_Agenda' => 'N_WEB_CAREERS_Master_Jadwal_Agenda',
        'N_WEB_CAREERS_Master_Alur' => 'N_WEB_CAREERS_Master_Alur',
        'N_WEB_CAREERS_Master_Alur_Tahap' => 'N_WEB_CAREERS_Master_Alur_Tahap',
        'N_WEB_CAREERS_Master_Alur_Tahap_Tes' => 'N_WEB_CAREERS_Master_Alur_Tahap_Tes',
        'N_WEB_CAREERS_Master_Tipe_Tahap' => 'N_WEB_CAREERS_Master_Tipe_Tahap',
        'N_WEB_CAREERS_Division_Informations' => 'N_WEB_CAREERS_Division_Informations',
        'N_WEB_CAREERS_Sub_Divisi_Informations' => 'N_WEB_CAREERS_Sub_Divisi_Informations',
        'N_WEB_CAREERS_Master_Hero_Slide' => 'N_WEB_CAREERS_Master_Hero_Slide',
        'N_WEB_CAREERS_Master_Faq' => 'N_WEB_CAREERS_Master_Faq',
        'N_WEB_CAREERS_Master_Faq_Kategori' => 'N_WEB_CAREERS_Master_Faq_Kategori',
        'N_WEB_CAREERS_Master_Formulir' => 'N_WEB_CAREERS_Master_Formulir',
        'N_WEB_CAREERS_Master_Formulir_Versi' => 'N_WEB_CAREERS_Master_Formulir_Versi',
        'N_WEB_CAREERS_Program_Syarat' => 'N_WEB_CAREERS_Program_Syarat',
        'N_WEB_CAREERS_Aturan_Lamaran' => 'N_WEB_CAREERS_Aturan_Lamaran',
        'N_WEB_CAREERS_Master_Jenjang' => 'N_WEB_CAREERS_Master_Jenjang',
        'N_WEB_CAREERS_Master_Jenis_Institusi' => 'N_WEB_CAREERS_Master_Jenis_Institusi',
        'N_WEB_CAREERS_Jenis_Institusi_Jenjang' => 'N_WEB_CAREERS_Jenis_Institusi_Jenjang',
        'N_WEB_CAREERS_Master_Bidang_Ilmu' => 'N_WEB_CAREERS_Master_Bidang_Ilmu',
        'N_WEB_CAREERS_Master_Prodi' => 'N_WEB_CAREERS_Master_Prodi',
        'N_WEB_CAREERS_Prodi_Jenjang' => 'N_WEB_CAREERS_Prodi_Jenjang',
        'N_WEB_CAREERS_Master_Konfirmasi_Jadwal' => 'N_WEB_CAREERS_Master_Konfirmasi_Jadwal',
        'N_WEB_CAREERS_Master_Alasan_Jadwal' => 'N_WEB_CAREERS_Master_Alasan_Jadwal',
        'N_WEB_CAREERS_Master_Mode_Jadwal' => 'N_WEB_CAREERS_Master_Mode_Jadwal',
        'N_WEB_CAREERS_Menu' => 'N_WEB_CAREERS_Menu',
        'N_WEB_CAREERS_Aksi' => 'N_WEB_CAREERS_Aksi',
        'N_WEB_CAREERS_Klasifikasi_Akun' => 'N_WEB_CAREERS_Klasifikasi_Akun',
        'N_WEB_CAREERS_Klasifikasi_Menu' => 'N_WEB_CAREERS_Klasifikasi_Menu',
        'N_WEB_CAREERS_Klasifikasi_Menu_Aksi' => 'N_WEB_CAREERS_Klasifikasi_Menu_Aksi',
        'N_WEB_CAREERS_Klasifikasi_Whitelist' => 'N_WEB_CAREERS_Klasifikasi_Whitelist',
        'HRIS_Divisi' => 'N_WEB_CAREERS_HRIS_Divisi',
        'HRIS_Sub_Divisi' => 'N_WEB_CAREERS_HRIS_Sub_Divisi',
        'HRIS_Divisi_Sub_Divisi' => 'N_WEB_CAREERS_HRIS_Divisi_Sub_Divisi',
        'N_HRIS_Master_Lokasi' => 'N_WEB_CAREERS_HRIS_Master_Lokasi',
        'HRIS_Transaksi_GForm' => 'N_WEB_CAREERS_HRIS_Transaksi_GForm',
        // Paling besar (±330 ribu baris) paling akhir: tabel kecil tidak
        // ikut menunggu muatan awalnya.
        'N_WEB_CAREERS_Master_Kampus' => 'N_WEB_CAREERS_Master_Kampus',
    ];

    private const EMBER = 1000;

    /** Tabel sekecil ini dibandingkan per baris langsung, tanpa ember. */
    private const KECIL = 5000;

    private const PENUH = 'salinan:penuh';

    /** @var array<string, array> metadata per tabel publik */
    private array $meta = [];

    /**
     * @return array{tabel: int, dilewati: int, tulis: int, hapus: int, belum: list<string>, jeda: list<string>, galat: array<string, string>}
     */
    public function sapu(bool $penuh = false, ?int $batasDetik = 25, ?array $hanya = null): array
    {
        $akhir = $batasDetik ? microtime(true) + $batasDetik : null;
        // Jadwal rekonsiliasi penuh hanya untuk sapuan SEMUA tabel.
        $penuh = $penuh || (! $hanya && $this->waktunyaPenuh());
        $hasil = ['tabel' => 0, 'dilewati' => 0, 'tulis' => 0, 'hapus' => 0, 'belum' => [], 'jeda' => [], 'galat' => []];
        $registri = new RegistriSinkron;

        foreach (self::TABEL as $admin => $publik) {
            if ($hanya && ! in_array($publik, $hanya, true) && ! in_array($admin, $hanya, true)) {
                continue;
            }
            if ($registri->dijeda($admin)) {
                $hasil['jeda'][] = $publik;
                $registri->catat($admin, 'dijeda', null, false);

                continue;
            }
            if ($akhir && microtime(true) > $akhir) {
                $hasil['belum'][] = $publik;

                continue;
            }

            try {
                $r = $this->tabel($admin, $publik, $penuh, $akhir);
            } catch (\Throwable $e) {
                $hasil['galat'][$publik] = $e->getMessage();
                Log::warning("[SINKRON] salinan {$publik} gagal: ".$e->getMessage());
                $registri->catat($admin, 'galat: '.$e->getMessage(), null, false);

                continue;
            }

            $hasil['tabel']++;
            $hasil['dilewati'] += $r['dilewati'] ? 1 : 0;
            $hasil['tulis'] += $r['tulis'];
            $hasil['hapus'] += $r['hapus'];
            if (! $r['tuntas']) {
                $hasil['belum'][] = $publik;
                $registri->catat($admin, "berlanjut: {$r['tulis']} ditulis", null, false);
            } elseif ($r['tulis'] || $r['hapus']) {
                $registri->catat($admin, "{$r['tulis']} ditulis, {$r['hapus']} dihapus", $r['jumlah'], true);
            } else {
                $registri->catat($admin, 'cocok', $r['jumlah'], true);
            }
        }
        $registri->simpan();

        // Tabel yang dijeda tidak menghalangi jadwal penuh tabel lainnya.
        if ($penuh && ! $hasil['belum'] && ! $hasil['galat'] && ! $hanya) {
            TandaAir::tulisAngka(self::PENUH, (int) now()->getTimestampMs());
        }

        return $hasil;
    }

    /** @return array{dilewati: bool, tulis: int, hapus: int, tuntas: bool, jumlah: int} */
    public function tabel(string $admin, string $publik, bool $penuh = false, ?float $akhir = null): array
    {
        $m = $this->meta($admin, $publik);
        $gerbang = $this->gerbang($admin, $m);
        $kunciGerbang = 'salinan:'.$publik;
        // 8 heksadesimal pertama gerbang = jumlah baris tabel admin.
        $jumlah = (int) hexdec(substr($gerbang, 0, 8));

        if (! $penuh && TandaAir::hex($kunciGerbang) === $gerbang) {
            return ['dilewati' => true, 'tulis' => 0, 'hapus' => 0, 'tuntas' => true, 'jumlah' => $jumlah];
        }

        $tulis = 0;
        $hapus = 0;
        $tuntas = true;

        // ── 1. tambah: baris di atas id terbesar publik (muatan awal) ─────────
        if ($m['idTunggal']) {
            $pk = $m['pk'][0];
            $maks = $this->publik()->table($publik)->max($pk);
            while (true) {
                if ($akhir && microtime(true) > $akhir) {
                    $tuntas = false;
                    break;
                }
                $json = $this->ambilJson($admin, $m, "[{$pk}] > ?", [$maks ?? PHP_INT_MIN], (int) config('sinkron.salinan.per_tambah', 2000), "[{$pk}]");
                $baris = $json ? (json_decode($json, true) ?: []) : [];
                if (! $baris) {
                    break;
                }
                // Di atas id terbesar publik = pasti belum ada → INSERT polos.
                $this->tulis($publik, $m, $json, true);
                $tulis += count($baris);
                $maks = max(array_map(fn ($r) => (int) $r[$pk], $baris));
            }
        }

        // ── 2. bandingkan sisanya (ember → baris) ────────────────────────────
        if ($tuntas) {
            [$beda, $hilang] = $this->beda($admin, $publik, $m);
            foreach (array_chunk($beda, (int) config('sinkron.salinan.per_tulis', 500)) as $potong) {
                if ($akhir && microtime(true) > $akhir) {
                    $tuntas = false;
                    break;
                }
                [$syarat, $ikat] = $this->syaratKunci($m, $potong);
                $json = $this->ambilJson($admin, $m, $syarat, $ikat, null, null);
                if ($json) {
                    $this->tulis($publik, $m, $json);
                    $tulis += count($potong);
                }
            }
            if ($tuntas) {
                foreach (array_chunk($hilang, 500) as $potong) {
                    [$syarat, $ikat] = $this->syaratKunci($m, $potong);
                    $hapus += $this->publik()->delete("DELETE FROM dbo.[{$publik}] WHERE {$syarat}", $ikat);
                }
            }
        }

        if ($tuntas) {
            TandaAir::tulisHex($kunciGerbang, $gerbang);
        }

        if ($tulis || $hapus) {
            Log::info("[SINKRON] salinan {$publik}: {$tulis} ditulis, {$hapus} dihapus".($tuntas ? '' : ' (berlanjut)').'.');
        }

        return ['dilewati' => false, 'tulis' => $tulis, 'hapus' => $hapus, 'tuntas' => $tuntas, 'jumlah' => $jumlah];
    }

    // ═══════════════════════ PEMBANDING ═══════════════════════

    /**
     * Kunci (JSON string per baris) yang perlu ditulis dan yang perlu dihapus.
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function beda(string $admin, string $publik, array $m): array
    {
        $jumlah = (int) DB::table($admin)->count();
        if (! $m['idTunggal'] || $jumlah <= self::KECIL) {
            return $this->bedaBaris($admin, $publik, $m, null);
        }

        $pk = $m['pk'][0];
        $ember = fn (Connection $db, string $t) => collect($db->select(
            "SELECT [{$pk}] / ".self::EMBER." AS e, COUNT_BIG(*) AS n, CHECKSUM_AGG(CHECKSUM(h)) AS s
               FROM (SELECT t.[{$pk}], {$this->sidik('t', $m)} AS h FROM dbo.[{$t}] t) z
              GROUP BY [{$pk}] / ".self::EMBER
        ))->keyBy(fn ($r) => (int) $r->e)->map(fn ($r) => $r->n.':'.$r->s);

        $a = $ember(DB::connection(), $admin);
        $p = $ember($this->publik(), $publik);
        $embers = $a->keys()->merge($p->keys())->unique()->filter(fn ($e) => $a->get($e) !== $p->get($e))->values()->all();
        if (! $embers) {
            return [[], []];
        }

        $tulis = [];
        $hapus = [];
        foreach (array_chunk($embers, 50) as $potong) {
            [$t, $h] = $this->bedaBaris($admin, $publik, $m, $potong);
            array_push($tulis, ...$t);
            array_push($hapus, ...$h);
        }

        return [$tulis, $hapus];
    }

    /**
     * Banding per baris (seluruh tabel, atau ember tertentu).
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function bedaBaris(string $admin, string $publik, array $m, ?array $embers): array
    {
        $saring = '';
        $ikat = [];
        if ($embers !== null) {
            $pk = $m['pk'][0];
            $saring = " WHERE t.[{$pk}] / ".self::EMBER.' IN ('.implode(',', array_fill(0, count($embers), '?')).')';
            $ikat = $embers;
        }

        $kolomPk = implode(', ', array_map(fn ($k) => "t.[{$k}]", $m['pk']));
        $sidik = fn (Connection $db, string $t) => collect($db->select(
            "SELECT {$kolomPk}, CONVERT(VARCHAR(66), {$this->sidik('t', $m)}, 1) AS h FROM dbo.[{$t}] t{$saring}",
            $ikat
        ))->mapWithKeys(fn ($r) => [$this->kunciBaris($m, $r) => $r->h]);

        $a = $sidik(DB::connection(), $admin);
        $p = $sidik($this->publik(), $publik);

        $tulis = $a->filter(fn ($h, $k) => $p->get($k) !== $h)->keys()->map(fn ($k) => json_decode($k, true))->values()->all();
        $hapus = $p->keys()->diff($a->keys())->map(fn ($k) => json_decode($k, true))->values()->all();

        return [$tulis, $hapus];
    }

    private function kunciBaris(array $m, object $r): string
    {
        $k = [];
        foreach ($m['pk'] as $kol) {
            $v = $r->{$kol};
            $k[] = is_numeric($v) && $m['tipe'][$kol]['angka'] ? (int) $v : (string) $v;
        }

        return json_encode($k);
    }

    /** Ekspresi sidik satu baris — sama persis di kedua sisi. */
    private function sidik(string $alias, array $m): string
    {
        $kolom = implode(', ', array_map(fn ($k) => "{$alias}.[{$k}] AS [{$k}]", $m['kolom']));

        return "HASHBYTES('SHA2_256', (SELECT {$kolom} FOR JSON PATH, WITHOUT_ARRAY_WRAPPER, INCLUDE_NULL_VALUES))";
    }

    /** Sidik gerbang tabel admin (16 heksadesimal: 8 jumlah + 8 checksum). */
    private function gerbang(string $admin, array $m): string
    {
        $kolom = implode(', ', array_map(fn ($k) => "[{$k}]", $m['kolomGerbang']));
        $r = DB::selectOne("SELECT COUNT_BIG(*) AS n, ISNULL(CHECKSUM_AGG(BINARY_CHECKSUM({$kolom})), 0) AS s FROM dbo.[{$admin}]");

        return strtoupper(sprintf('%08x%08x', ((int) $r->n) & 0xFFFFFFFF, ((int) $r->s) & 0xFFFFFFFF));
    }

    // ═══════════════════════ BACA & TULIS ═══════════════════════

    /** Baris admin sebagai JSON array (kolom salinan saja), atau null bila kosong. */
    private function ambilJson(string $admin, array $m, string $syarat, array $ikat, ?int $top, ?string $urut): ?string
    {
        $kolom = implode(', ', array_map(fn ($k) => "[{$k}]", $m['kolom']));
        $sql = 'SELECT (SELECT '.($top ? "TOP ({$top}) " : '')."{$kolom} FROM dbo.[{$admin}] WHERE {$syarat}"
            .($urut ? " ORDER BY {$urut}" : '').' FOR JSON PATH, INCLUDE_NULL_VALUES) AS j';
        $j = DB::selectOne($sql, $ikat)->j ?? null;

        return $j ? (string) $j : null;
    }

    private function tulis(string $publik, array $m, string $json, bool $sisipSaja = false): void
    {
        $with = implode(', ', array_map(fn ($k) => "[{$k}] {$m['tipe'][$k]['sql']} '\$.\"{$k}\"'", $m['kolom']));
        if ($sisipSaja) {
            $kolom = implode(', ', array_map(fn ($k) => "[{$k}]", $m['kolom']));
            $versi = (int) now()->getTimestampMs();
            $this->publik()->statement(
                "INSERT INTO dbo.[{$publik}] ({$kolom}, [Sinkron_Versi], [Sinkron_At])
                 SELECT {$kolom}, {$versi}, SYSUTCDATETIME() FROM OPENJSON(?) WITH ({$with});",
                [$json],
            );

            return;
        }
        $on = implode(' AND ', array_map(fn ($k) => "t.[{$k}] = s.[{$k}]", $m['pk']));
        $set = implode(', ', array_map(fn ($k) => "t.[{$k}] = s.[{$k}]", array_diff($m['kolom'], $m['pk'])));
        $kolom = implode(', ', array_map(fn ($k) => "[{$k}]", $m['kolom']));
        $nilai = implode(', ', array_map(fn ($k) => "s.[{$k}]", $m['kolom']));
        $versi = (int) now()->getTimestampMs();

        $this->publik()->statement(
            "MERGE dbo.[{$publik}] WITH (HOLDLOCK) AS t
             USING (SELECT * FROM OPENJSON(?) WITH ({$with})) AS s
                ON {$on}
             WHEN MATCHED THEN UPDATE SET ".($set !== '' ? $set.', ' : '')."t.[Sinkron_Versi] = {$versi}, t.[Sinkron_At] = SYSUTCDATETIME()
             WHEN NOT MATCHED THEN INSERT ({$kolom}, [Sinkron_Versi], [Sinkron_At]) VALUES ({$nilai}, {$versi}, SYSUTCDATETIME());",
            [$json],
        );
    }

    /** @return array{0: string, 1: array} syarat WHERE untuk sekumpulan kunci baris */
    private function syaratKunci(array $m, array $kunci): array
    {
        if (count($m['pk']) === 1) {
            return ['['.$m['pk'][0].'] IN ('.implode(',', array_fill(0, count($kunci), '?')).')', array_map(fn ($k) => $k[0], $kunci)];
        }

        $bagian = [];
        $ikat = [];
        foreach ($kunci as $k) {
            $bagian[] = '('.implode(' AND ', array_map(fn ($kol) => "[{$kol}] = ?", $m['pk'])).')';
            array_push($ikat, ...$k);
        }

        return [implode(' OR ', $bagian), $ikat];
    }

    // ═══════════════════════ METADATA ═══════════════════════

    /**
     * Kolom yang disalin = kolom publik (tanpa Sinkron_*) yang juga ada di
     * admin, urut kolom publik; PK dari tabel publik.
     */
    private function meta(string $admin, string $publik): array
    {
        if (isset($this->meta[$publik])) {
            return $this->meta[$publik];
        }

        $kolomPublik = $this->publik()->select(
            'SELECT c.name, ty.name AS tipe, c.max_length, c.precision, c.scale
               FROM sys.columns c JOIN sys.types ty ON ty.user_type_id = c.user_type_id
              WHERE c.object_id = OBJECT_ID(?) ORDER BY c.column_id',
            ['dbo.'.$publik],
        );
        if (! $kolomPublik) {
            throw new RuntimeException("Tabel salinan {$publik} tidak ada / tidak terbaca di database publik.");
        }
        $kolomAdmin = collect(DB::select('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)', ['dbo.'.$admin]))->pluck('name')->all();
        if (! $kolomAdmin) {
            throw new RuntimeException("Tabel sumber {$admin} tidak ada di database admin.");
        }

        $kolom = [];
        $tipe = [];
        foreach ($kolomPublik as $c) {
            if (in_array($c->name, ['Sinkron_Versi', 'Sinkron_At'], true) || ! in_array($c->name, $kolomAdmin, true)) {
                continue;
            }
            $kolom[] = $c->name;
            $tipe[$c->name] = ['sql' => self::tipeSql($c), 'angka' => in_array($c->tipe, ['int', 'bigint', 'smallint', 'tinyint'], true)];
        }

        $pk = collect($this->publik()->select(
            'SELECT c.name FROM sys.indexes i
               JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
               JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
              WHERE i.object_id = OBJECT_ID(?) AND i.is_primary_key = 1 ORDER BY ic.key_ordinal',
            ['dbo.'.$publik],
        ))->pluck('name')->all();
        if (! $pk || array_diff($pk, $kolom)) {
            throw new RuntimeException("Kunci utama {$publik} tidak ditemukan di kolom salinan.");
        }

        // BINARY_CHECKSUM mengabaikan tipe tak-terbanding (text/ntext/image/xml) —
        // aman dilewatkan; kolom (MAX) tetap ikut.
        $kolomGerbang = array_values(array_filter($kolom, fn ($k) => ! in_array(strtolower($this->tipeMentah($kolomPublik, $k)), ['text', 'ntext', 'image', 'xml'], true)));

        return $this->meta[$publik] = [
            'kolom' => $kolom,
            'tipe' => $tipe,
            'pk' => $pk,
            'idTunggal' => count($pk) === 1 && $tipe[$pk[0]]['angka'],
            'kolomGerbang' => $kolomGerbang ?: $pk,
        ];
    }

    private function tipeMentah(array $kolomPublik, string $nama): string
    {
        foreach ($kolomPublik as $c) {
            if ($c->name === $nama) {
                return (string) $c->tipe;
            }
        }

        return '';
    }

    private static function tipeSql(object $c): string
    {
        $t = strtolower((string) $c->tipe);
        $len = (int) $c->max_length;

        return match ($t) {
            'varchar', 'char', 'varbinary', 'binary' => strtoupper($t).'('.($len === -1 ? 'MAX' : $len).')',
            'nvarchar', 'nchar' => strtoupper($t).'('.($len === -1 ? 'MAX' : intdiv($len, 2)).')',
            'decimal', 'numeric' => strtoupper($t).'('.(int) $c->precision.','.(int) $c->scale.')',
            'datetime2', 'time', 'datetimeoffset' => strtoupper($t).'('.(int) $c->scale.')',
            'text' => 'VARCHAR(MAX)',
            'ntext' => 'NVARCHAR(MAX)',
            default => strtoupper($t),
        };
    }

    private function waktunyaPenuh(): bool
    {
        $menit = max(10, (int) config('sinkron.salinan.penuh_menit', 360));

        return now()->getTimestampMs() - TandaAir::angka(self::PENUH) >= $menit * 60000;
    }

    private function publik(): Connection
    {
        return PendorongKeluar::publik();
    }
}
