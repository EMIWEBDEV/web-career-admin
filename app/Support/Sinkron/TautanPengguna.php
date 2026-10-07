<?php

namespace App\Support\Sinkron;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Vinkla\Hashids\Facades\Hashids;

/**
 * TAUTAN KE SITUS KANDIDAT (project web-careers-pengguna).
 *
 * Panel admin tidak lagi melayani satu pun halaman kandidat. Setiap tautan
 * untuk kandidat — undangan & konfirmasi kehadiran, surat jadwal, feedback,
 * verifikasi surel, portal — menunjuk ke situs pengguna (config sinkron.pengguna_url).
 *
 * Dibangun dengan UrlGenerator Laravel di atas DAFTAR RUTE PENGGUNA di bawah,
 * bukan rute aplikasi ini, sehingga:
 *   - bentuk tanda tangannya sama persis dengan yang diperiksa pengguna
 *     (Laravel yang sama, kunci TAUTAN_KUNCI yang sama);
 *   - tidak ikut terpanggang route:cache — alamatnya dibaca dari config saat jalan;
 *   - panel admin tidak pernah ikut mencocokkan jalur-jalur itu.
 *
 * URI di RUTE WAJIB sama dengan routes/pengguna/*.php di project pengguna.
 */
final class TautanPengguna
{
    /** nama rute => URI di project pengguna */
    private const RUTE = [
        'career.konfirmasi.halaman' => 'karir/konfirmasi/{id}/{versi}',
        'career.surat.jadwal' => 'karir/surat-jadwal/{id}/{urutan?}',
        'career.portal.index' => 'kandidat/portal',
        'career.portal.kode' => 'kandidat/lamaran/kode/{kode}',
        'career.lowongan.detail' => 'karir/landing-page/lowongan/{id}',
        'career.mt.detail' => 'karir/landing-page/mt/{id}',
        'career.auth.verifikasi-email' => 'verifikasi-email',
        'career.feedback.form' => 'feedback/{kode}/{hashids}/{signature}',
    ];

    private static ?UrlGenerator $url = null;

    private static ?string $kunciBasis = null;

    /** Generator URL untuk situs pengguna. Gagal keras bila alamat/kuncinya belum diatur. */
    public static function url(): UrlGenerator
    {
        $basis = (string) config('sinkron.pengguna_url');
        $kunci = (string) config('sinkron.kunci_tautan');
        if ($basis === '' || $kunci === '') {
            throw new RuntimeException('PENGGUNA_URL / TAUTAN_KUNCI belum diatur — tautan untuk kandidat tidak bisa dibuat.');
        }
        if (self::$url && self::$kunciBasis === $basis.'|'.$kunci) {
            return self::$url;
        }

        $rute = new RouteCollection();
        foreach (self::RUTE as $nama => $uri) {
            $rute->add((new Route(['GET', 'HEAD'], $uri, fn () => null))->name($nama));
        }

        $url = new UrlGenerator($rute, Request::create($basis));
        $url->forceRootUrl($basis);
        $url->setKeyResolver(fn () => (string) config('sinkron.kunci_tautan'));
        self::$kunciBasis = $basis.'|'.$kunci;

        return self::$url = $url;
    }

    /**
     * Halaman konfirmasi kehadiran (bertanda tangan). `l` = kode lamaran — ikut
     * ditandatangani, jadi tautannya tidak bisa ditukar ke lamaran lain.
     */
    public static function konfirmasi(int $subTesId, int $versi, DateTimeInterface $sampai, ?string $kode = null): string
    {
        return self::url()->temporarySignedRoute('career.konfirmasi.halaman', $sampai, [
            'id' => Hashids::encode($subTesId),
            'versi' => $versi,
            'l' => $kode ?? self::kodeAktivitas($subTesId),
        ]);
    }

    /** Surat jadwal urutan ke-N (bertanda tangan, `l` = kode lamaran). */
    public static function suratJadwal(int $subTesId, int $urutan, DateTimeInterface $sampai, ?string $kode = null): string
    {
        return self::url()->temporarySignedRoute('career.surat.jadwal', $sampai, [
            'id' => Hashids::encode($subTesId),
            'urutan' => $urutan,
            'l' => $kode ?? self::kodeAktivitas($subTesId),
        ]);
    }

    /** Detail lamaran di portal — lewat KODE (zona dalam tidak tahu id publiknya). */
    public static function lamaran(string $kode, array $query = []): string
    {
        return self::url()->route('career.portal.kode', ['kode' => $kode] + $query);
    }

    public static function portal(): string
    {
        return self::url()->route('career.portal.index');
    }

    /** Halaman publik lowongan rekrutmen — `PB-{kode pembukaan}-{hash posisi}`. */
    public static function lowongan(string $id): string
    {
        return self::url()->route('career.lowongan.detail', ['id' => $id]);
    }

    /** Halaman publik program MT — `PB-{kode pembukaan}`. */
    public static function mt(string $id): string
    {
        return self::url()->route('career.mt.detail', ['id' => $id]);
    }

    public static function verifikasiEmail(string $email, string $token): string
    {
        return self::url()->route('career.auth.verifikasi-email', ['email' => $email, 'token' => $token]);
    }

    /**
     * Isian feedback: /feedback/{kode}/{hash id feedback}/{tanda} — tanda =
     * 32 karakter pertama HMAC-SHA256("{id feedback}|{kode}", TAUTAN_KUNCI).
     * Sama dengan App\Support\Portal\TautanFeedback di project pengguna.
     */
    public static function feedback(int $feedbackId, string $kode): string
    {
        $url = self::url();
        $tanda = substr(hash_hmac('sha256', $feedbackId.'|'.$kode, (string) config('sinkron.kunci_tautan')), 0, 32);

        return $url->route('career.feedback.form', [
            'kode' => $kode,
            'hashids' => Hashids::encode($feedbackId),
            'signature' => $tanda,
        ]);
    }

    /** Kode lamaran pemilik satu aktivitas (Lamaran_Tahap_Tes). */
    public static function kodeAktivitas(int $subTesId): string
    {
        $kode = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
            ->value('l.Kode');

        if (! $kode) {
            throw new RuntimeException("Kode lamaran untuk aktivitas #{$subTesId} tidak ditemukan.");
        }

        return (string) $kode;
    }
}
